<?php

namespace App\Services;

use App\Constants\Constants;
use App\Jobs\NotifyTaskAssignedJob;
use App\Models\CalendarEvent;
use App\Models\Closing;
use App\Models\ContactNote;
use App\Models\Enquiry;
use App\Models\Offer;
use App\Models\OutlookConnection;
use App\Models\Property;
use App\Models\User;
use App\Repositories\TaskRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TaskService
{
    protected TaskRepository $taskRepository;

    protected NotificationService $notificationService;

    protected OutlookCalendarService $outlookCalendarService;

    protected MailService $mailService;

    public function __construct(
        TaskRepository $taskRepository,
        NotificationService $notificationService,
        OutlookCalendarService $outlookCalendarService,
        MailService $mailService
    ) {
        $this->taskRepository = $taskRepository;
        $this->notificationService = $notificationService;
        $this->outlookCalendarService = $outlookCalendarService;
        $this->mailService = $mailService;
    }

    public function getAll(Request $request)
    {
        return $this->taskRepository->getAll($request);
    }

    public function create(array $data)
    {
        // Qualification intent travels with the task payload but is not a task
        // column — pull it out before persisting the task.
        $qualifying = filter_var($data['isQualified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['isQualified']);

        $task = $this->taskRepository->create($data);

        logUserActivity([
            'action' => 'created',
            'module' => Constants::MODULE_TASK,
            'moduleId' => $task->id,
            'description' => "Created task: #{$task->id} ({$task->subject})",
        ]);

        // Auto-transition enquiry status when a task is logged. Only a task that
        // carries isQualified removes the enquiry from Open Game and assigns the
        // acting agent — plain contact attempts never change owner / Open Game.
        $this->handleEnquiryStatusTransition($task, $qualifying);

        // Auto-create a calendar event for this task
        $this->createCalendarEventForTask($task);

        // If status is closeFollowUp or done, create a duplicate task with pending status
        $this->createPendingFollowUpIfNeeded($task);

        // If task is of type "call", log it under the resolved contact's notes
        $this->logCallContactNote($task);

        // Notify the assigned user about the new task (skip if assigned to self)
        if (isset($data['assignedTo']) && $data['assignedTo'] && $data['assignedTo'] != user()->id) {
            $assignedUser = User::find($data['assignedTo']);

            if ($assignedUser) {
                $moduleName = $this->getModuleName($task->module ?? null, $task->moduleId ?? null);

                $this->notificationService->notifyUser(
                    userId: $data['assignedTo'],
                    actorId: user()->id,
                    type: Constants::NOTIFICATION_TYPES['task_assigned'],
                    title: "New task assigned to you: {$task->subject}",
                    message: user()->name." assigned you a new task: {$task->subject}".($moduleName ? " for {$moduleName}" : ''),
                    module: $task->module,
                    moduleId: $task->moduleId,
                    extraData: [
                        'taskId' => $task->id,
                        'taskSubject' => $task->subject,
                        'dueDate' => $task->dueDate,
                        'priority' => $task->priority,
                    ]
                );

                // Send email to assigned user
                try {

                    $dueDate = Carbon::parse($task->dueDate)
                        ->format('d/m/Y h:i A');
                    $this->mailService->sendEmail(
                        Constants::EMAIL_SLUGS['task']['task_assigned'],
                        $assignedUser->email,
                        [
                            'name' => ucwords($assignedUser->name),
                            'assigned_by' => ucwords(user()->name),
                            'task_title' => $task->subject,
                            'type' => $task->type ?? 'N/A',
                            'priority' => $task->priority ?? 'N/A',
                            'due_date' => $dueDate ?? 'N/A',
                            'comments' => $task->comments ?? 'N/A',
                            'company_name' => getCompanyName(),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('TaskService : create : sendEmail : '.$e->getMessage());
                }
            }
        }

        return $task;
    }

    /**
     * Bulk-create one task per user id — used both for team assignment (the
     * expanded team-member ids) and for an individual assignment that names
     * several users. Side-effects (notifications + emails) are deferred to a
     * queued job so the request stays fast. Enquiry status transition and
     * call-note logging run once (they only depend on module/moduleId).
     */
    public function createForUsers(array $baseData, array $userIds): array
    {
        $qualifying = filter_var($baseData['isQualified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($baseData['isQualified']);

        $tasks = [];
        foreach ($userIds as $userId) {
            $data = $baseData;
            $data['assignedTo'] = $userId;

            $task = $this->taskRepository->create($data);

            logUserActivity([
                'action' => 'created',
                'module' => Constants::MODULE_TASK,
                'moduleId' => $task->id,
                'description' => "Created task: #{$task->id} ({$task->subject})",
            ]);

            $tasks[] = $task;
        }

        if (! empty($tasks)) {
            $first = $tasks[0];
            $this->handleEnquiryStatusTransition($first, $qualifying);
            $this->logCallContactNote($first);
        }

        $actorId = user()?->id;
        if ($actorId) {
            NotifyTaskAssignedJob::dispatch(array_map(fn ($t) => $t->id, $tasks), $actorId);
        }

        return $tasks;
    }

    public function getById(int $id)
    {
        return $this->taskRepository->findById($id);
    }

    public function update(int $id, array $data)
    {
        $oldTask = $this->taskRepository->findById($id);

        if (! $oldTask) {
            return null;
        }

        $task = $this->taskRepository->update($id, $data);

        if (! $task) {
            return null;
        }

        $this->handleEnquiryTransitionOnTaskUpdate($task, $data);

        logUserActivity([
            'action' => 'updated',
            'module' => Constants::MODULE_TASK,
            'moduleId' => $task->id,
            'description' => "Updated task: #{$task->id} ({$task->subject})",
        ]);

        // Sync the linked calendar event
        $this->updateCalendarEventForTask($task);

        // If status changed to closeFollowUp or done, create a duplicate task with pending status
        if (isset($data['status']) && in_array($data['status'], Constants::TASK_CLOSED_STATUSES)) {
            $this->createPendingFollowUpIfNeeded($task);
        }

        $moduleName = $this->getModuleName($task->module ?? null, $task->moduleId ?? null);

        // Notify if task status changed to Completed
        if (isset($data['status']) && $data['status'] === Constants::TASK_STATUS_DONE) {
            if ($task->createdBy && $task->createdBy != user()->id) {
                $this->notificationService->notifyUser(
                    userId: $task->createdBy,
                    actorId: user()->id,
                    type: Constants::NOTIFICATION_TYPES['task_completed'],
                    title: "Task completed: {$task->subject}",
                    message: user()->name." marked the task '{$task->subject}' as completed".($moduleName ? " for {$moduleName}" : ''),
                    module: $task->module,
                    moduleId: $task->moduleId,
                    extraData: [
                        'taskId' => $task->id,
                        'taskSubject' => $task->subject,
                        'completedBy' => user()->name,
                    ]
                );
            }
        }

        // Notify on ANY status change (fires only if old != new)
        if (isset($data['status']) && $oldTask->status !== $data['status']) {
            $this->notifyTaskStatusChange($task, $oldTask->status, $data['status'], $moduleName);
        }

        // Notify if task was reassigned to a different user (skip if assigned to self)
        if (isset($data['assignedTo']) && $data['assignedTo'] && $data['assignedTo'] != user()->id) {
            $assignedUser = User::find($data['assignedTo']);

            if ($assignedUser) {
                $this->notificationService->notifyUser(
                    userId: $data['assignedTo'],
                    actorId: user()->id,
                    type: Constants::NOTIFICATION_TYPES['task_assigned'],
                    title: "Task assigned to you: {$task->subject}",
                    message: user()->name." assigned you a task: {$task->subject}".($moduleName ? " for {$moduleName}" : ''),
                    module: $task->module,
                    moduleId: $task->moduleId,
                    extraData: [
                        'taskId' => $task->id,
                        'taskSubject' => $task->subject,
                        'dueDate' => $task->dueDate,
                        'priority' => $task->priority,
                    ]
                );

                // Send email to reassigned user
                try {
                    $this->mailService->sendEmail(
                        Constants::EMAIL_SLUGS['task']['task_assigned'],
                        $assignedUser->email,
                        [
                            'name' => ucwords($assignedUser->name),
                            'assigned_by' => ucwords(user()->name),
                            'task_title' => $task->subject,
                            'type' => $task->type ?? 'N/A',
                            'priority' => $task->priority ?? 'N/A',
                            'due_date' => $task->dueDate ?? 'N/A',
                            'comments' => $task->comments ?? 'N/A',
                            'company_name' => getCompanyName(),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('TaskService : update : sendEmail : '.$e->getMessage());
                }
            }
        }

        return $task;
    }

    /**
     * Notify the task creator and assigned user on any task status change.
     * Skips the actor who made the change.
     */
    protected function notifyTaskStatusChange($task, ?string $oldStatus, string $newStatus, ?string $moduleName): void
    {
        $actor = user();
        if (! $actor) {
            return;
        }

        $statusLabel = Constants::TASK_STATUS[$newStatus] ?? $newStatus;

        $recipientIds = array_values(array_unique(array_filter(
            [$task->createdBy, $task->assignedTo],
            fn ($id) => $id && $id != $actor->id
        )));

        if (empty($recipientIds)) {
            return;
        }

        $recipients = User::whereIn('id', $recipientIds)->get(['id', 'name', 'email']);

        foreach ($recipients as $recipient) {
            $this->notificationService->notifyUser(
                userId: $recipient->id,
                actorId: $actor->id,
                type: Constants::NOTIFICATION_TYPES['task_status_change'],
                title: "Task status changed: {$task->subject}",
                message: "{$actor->name} changed the status of task '{$task->subject}' to \"{$statusLabel}\"".($moduleName ? " for {$moduleName}" : ''),
                module: $task->module,
                moduleId: $task->moduleId,
                extraData: [
                    'taskId' => $task->id,
                    'taskSubject' => $task->subject,
                    'statusLabel' => $statusLabel,
                ]
            );

            if ($recipient->email) {
                try {
                    $this->mailService->sendEmail(
                        Constants::EMAIL_SLUGS['task']['task_status_change'],
                        $recipient->email,
                        [
                            'recipient_name' => ucwords($recipient->name),
                            'task_title' => $task->subject,
                            'status' => $statusLabel,
                            'changed_by' => ucwords($actor->name),
                            'changed_date' => now()->setTimezone(appTimezone())->format('F d, Y H:i'),
                            'company_name' => getCompanyName(),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('TaskService : notifyTaskStatusChange : sendEmail : '.$e->getMessage());
                }
            }
        }
    }

    public function delete(int $id)
    {
        // Delete linked calendar event from Outlook first, then from DB
        $calendarEvent = CalendarEvent::where('taskId', $id)->first();

        if ($calendarEvent) {
            if ($calendarEvent->outlookEventId) {
                $connection = $this->getOutlookConnection($calendarEvent->createdBy ?? $calendarEvent->assignedTo);
                if ($connection) {
                    $this->outlookCalendarService->deleteEventFromOutlook($calendarEvent->outlookEventId, $connection);
                }
            }
            $calendarEvent->delete();
        }

        return $this->taskRepository->delete($id);
    }

    /**
     * Create a calendar event entry for a task.
     */
    protected function createCalendarEventForTask($task): void
    {
        if (! $task->dueDate) {
            return;
        }

        $eventType = $task->type ?? 'Follow Up';
        $dateTimes = $this->taskCalendarDateTimes($task);

        $calendarEvent = CalendarEvent::create([
            'title' => $task->subject,
            'description' => $task->comments,
            'eventType' => $eventType,
            'startDateTime' => $dateTimes['startDateTime'],
            'endDateTime' => $dateTimes['endDateTime'],
            'allDay' => $dateTimes['allDay'],
            'module' => $task->module,
            'moduleId' => $task->moduleId,
            'taskId' => $task->id,
            'assignedTo' => $task->assignedTo,
            'createdBy' => $task->createdBy,
            'status' => $task->status === Constants::TASK_STATUS_DONE ? 'completed' : 'scheduled',
            'priority' => $task->priority,
            'color' => Constants::CALENDAR_EVENT_COLORS[$eventType] ?? '#6B7280',
            'reminderSet' => $task->reminderSet ?? false,
            'reminderDate' => $task->reminderDate,
            'reminderTime' => $task->reminderTime,
        ]);

        // Auto-sync to Outlook
        $this->syncToOutlook($calendarEvent, 'create');
    }

    /**
     * Update the linked calendar event when a task is updated.
     */
    protected function updateCalendarEventForTask($task): void
    {
        $calendarEvent = CalendarEvent::where('taskId', $task->id)->first();

        $eventType = $task->type ?? 'Follow Up';
        $dateTimes = $this->taskCalendarDateTimes($task);

        $eventData = [
            'title' => $task->subject,
            'description' => $task->comments,
            'eventType' => $eventType,
            'startDateTime' => $dateTimes['startDateTime'],
            'endDateTime' => $dateTimes['endDateTime'],
            'allDay' => $dateTimes['allDay'],
            'module' => $task->module,
            'moduleId' => $task->moduleId,
            'assignedTo' => $task->assignedTo,
            'status' => $task->status === Constants::TASK_STATUS_DONE ? 'completed' : 'scheduled',
            'priority' => $task->priority,
            'color' => Constants::CALENDAR_EVENT_COLORS[$eventType] ?? '#6B7280',
            'reminderSet' => $task->reminderSet ?? false,
            'reminderDate' => $task->reminderDate,
            'reminderTime' => $task->reminderTime,
        ];

        if ($calendarEvent) {
            $calendarEvent->update($eventData);
            // Auto-sync update to Outlook
            $this->syncToOutlook($calendarEvent, 'update');
        } elseif ($task->dueDate) {
            // Create one if it doesn't exist yet
            $eventData['taskId'] = $task->id;
            $eventData['createdBy'] = $task->createdBy;
            $newEvent = CalendarEvent::create($eventData);
            // Auto-sync new event to Outlook
            $this->syncToOutlook($newEvent, 'create');
        }
    }

    protected function taskCalendarDateTimes($task): array
    {
        if (! $task->dueDate) {
            return [
                'startDateTime' => null,
                'endDateTime' => null,
                'allDay' => true,
            ];
        }

        $endDate = $task->endDate ?: $task->dueDate;

        if (! $task->startTime) {
            return [
                'startDateTime' => Carbon::parse($task->dueDate)->startOfDay(),
                'endDateTime' => Carbon::parse($endDate)->endOfDay(),
                'allDay' => true,
            ];
        }

        $startDateTime = Carbon::parse($task->dueDate.' '.$task->startTime);
        $endDateTime = $task->endTime
            ? Carbon::parse($endDate.' '.$task->endTime)
            : $startDateTime->copy()->addHour();

        if ($endDateTime->lessThanOrEqualTo($startDateTime)) {
            $endDateTime->addDay();
        }

        return [
            'startDateTime' => $startDateTime,
            'endDateTime' => $endDateTime,
            'allDay' => false,
        ];
    }

    /**
     * Get a human-readable name for the module.
     */
    protected function getModuleName(?string $module, ?int $moduleId): ?string
    {
        if (! $module || ! $moduleId) {
            return null;
        }

        return match ($module) {
            Constants::MODULE_PROPERTY => Property::where('id', $moduleId)->value('title') ?? "Property #{$moduleId}",
            Constants::MODULE_ENQUIRY => "Enquiry #{$moduleId}",
            default => "{$module} #{$moduleId}",
        };
    }

    /**
     * Auto-sync a calendar event to Outlook (non-blocking).
     */
    protected function syncToOutlook(CalendarEvent $event, string $action): void
    {
        try {
            $userId = $event->createdBy ?? $event->assignedTo;
            $connection = $this->getOutlookConnection($userId);

            if (! $connection) {
                return;
            }

            if ($action === 'create') {
                $this->outlookCalendarService->pushEventToOutlook($event, $connection);
            } elseif ($action === 'update') {
                $this->outlookCalendarService->updateEventInOutlook($event, $connection);
            }
        } catch (\Exception $e) {
            // Don't fail the main operation if Outlook sync fails
            Log::warning('Outlook auto-sync failed for task calendar event', [
                'eventId' => $event->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the Outlook connection for a user.
     */
    protected function getOutlookConnection(?int $userId): ?OutlookConnection
    {
        if (! $userId) {
            return null;
        }

        return OutlookConnection::where('userId', $userId)
            ->where('status', Constants::OUTLOOK_SYNC_STATUS['connected'])
            ->first();
    }

    /**
     * Create a duplicate task with pending status when the original task
     * is created/updated with closeFollowUp or done status.
     */
    protected function createPendingFollowUpIfNeeded($task): void
    {
        if ($task->status !== Constants::TASK_STATUS_CLOSE_FOLLOW_UP) {
            return;
        }

        $nextDueDate = Carbon::tomorrow()->format('Y-m-d');

        $duplicateData = [
            'module' => $task->module,
            'moduleId' => $task->moduleId,
            'subject' => $task->subject,
            'assignedTo' => $task->assignedTo,
            'type' => $task->type,
            'dueDate' => $nextDueDate,
            'endDate' => $task->endDate,
            'startTime' => $task->startTime,
            'endTime' => $task->endTime,
            'reminderSet' => $task->reminderSet ?? 0,
            'reminderDate' => $task->reminderDate,
            'reminderTime' => $task->reminderTime,
            'comments' => $task->comments,
            'status' => Constants::TASK_STATUS_PENDING,
            'priority' => $task->priority,
            'createdBy' => $task->createdBy,
        ];

        $pendingTask = $this->taskRepository->create($duplicateData);

        $this->createCalendarEventForTask($pendingTask);
    }

    /**
     * On task update, forward the qualification intent (isQualified) to the
     * shared transition handler when the task is marked Done.
     */
    protected function handleEnquiryTransitionOnTaskUpdate($task, array $data): void
    {
        if (strtolower((string) $task->module) !== 'enquiry' || ! $task->moduleId) {
            return;
        }

        if (! isset($data['status']) || $data['status'] !== Constants::TASK_STATUS_DONE) {
            return;
        }

        $qualifying = array_key_exists('isQualified', $data)
            && filter_var($data['isQualified'], FILTER_VALIDATE_BOOLEAN);

        $this->handleEnquiryStatusTransition($task, $qualifying);
    }

    /**
     * Auto-transition enquiry status when a task/activity is logged.
     *
     * Ownership rules:
     * - ONLY a task that carries isQualified (while being added or updated)
     *   removes the enquiry from Open Game and assigns the acting agent as the
     *   enquiry owner. This applies to an Open Game enquiry at ANY status.
     * - Plain contact attempts (calls, follow-up tasks, reminders) never change
     *   the owner or the Open Game flag.
     *
     * Status flow:
     * - Qualified on New Lead / Contacting → Property Search.
     * - New Lead + not qualified → Contacting.
     * - Contacting + not qualified → stays Contacting (inactivity window resets).
     */
    protected function handleEnquiryStatusTransition($task, bool $qualifying = false): void
    {
        if (strtolower($task->module) !== 'enquiry' || ! $task->moduleId) {
            return;
        }

        $enquiry = Enquiry::find($task->moduleId);

        if (! $enquiry) {
            return;
        }

        // Receiving isQualified on the task is the ONLY trigger that qualifies the
        // enquiry, removes it from Open Game and assigns the acting agent as owner.
        if ($qualifying) {
            if (! $enquiry->isQualified) {
                $enquiry->update(['isQualified' => true]);

                logEnquiryHistory([
                    'enquiryId' => $enquiry->id,
                    'field' => 'isQualified',
                    'originalValue' => json_encode(['message' => '0']),
                    'newValue' => json_encode(['message' => '1']),
                ]);
            }

            if ($enquiry->isOpenGame) {
                $actorId = $task->assignedTo ?: $task->createdBy;
                $oldAgentId = $enquiry->agentId;

                $updateData = ['isOpenGame' => false];

                if ($actorId && $actorId !== $oldAgentId) {
                    $updateData['agentId'] = $actorId;

                    $enquiryNameOrType = $enquiry->contact?->masterOrSelf()->name ?? $enquiry->enquiryType;
                    $agentName = User::find($actorId)?->name ?? 'Agent';

                    logUserActivity([
                        'action' => 'take_ownership',
                        'module' => Constants::MODULE_ENQUIRY,
                        'moduleId' => $enquiry->id,
                        'description' => "Took ownership of enquiry: #{$enquiry->id} by qualifying it",
                    ]);

                    logEnquiryHistory([
                        'enquiryId' => $enquiry->id,
                        'field' => 'take_ownership',
                        'originalValue' => null,
                        'newValue' => json_encode(['message' => $agentName.' took ownership of this enquiry by qualifying it']),
                    ]);

                    notifyModule(Constants::MODULE_ENQUIRY, $enquiry->id, Constants::NOTIFICATION_TYPES['enquiry_update'], 'ownership_taken', $enquiryNameOrType);

                    if ($oldAgentId) {
                        $this->notificationService->notifyUser(
                            userId: $oldAgentId,
                            actorId: $actorId,
                            type: Constants::NOTIFICATION_TYPES['enquiry_update'],
                            title: "Enquiry ownership transferred: {$enquiryNameOrType}",
                            message: $agentName." took ownership of enquiry {$enquiryNameOrType}",
                            module: Constants::MODULE_ENQUIRY,
                            moduleId: $enquiry->id,
                            extraData: [
                                'moduleTitle' => $enquiryNameOrType,
                                'moduleId' => $enquiry->id,
                            ]
                        );
                    }
                }

                $enquiry->update($updateData);
            }
        }

        $oldStatus = $enquiry->status;
        $isQualified = $enquiry->isQualified;

        // Qualified on New Lead or Contacting → Property Search.
        if ($isQualified && in_array($oldStatus, [Constants::ENQUIRY_NEW_LEAD, Constants::ENQUIRY_CONTACTING])) {
            $enquiry->update(['status' => Constants::ENQUIRY_PROPERTY_SEARCH]);

            logEnquiryHistory([
                'enquiryId' => $enquiry->id,
                'field' => 'status',
                'originalValue' => json_encode(['message' => $oldStatus]),
                'newValue' => json_encode(['message' => Constants::ENQUIRY_PROPERTY_SEARCH]),
            ]);

            return;
        }

        // New Lead → Contacting on first activity (not qualified). Owner unchanged;
        // an Open Game enquiry stays Open Game so multiple agents can keep trying.
        if ($oldStatus === Constants::ENQUIRY_NEW_LEAD) {
            $enquiry->update(['status' => Constants::ENQUIRY_CONTACTING]);

            logEnquiryHistory([
                'enquiryId' => $enquiry->id,
                'field' => 'status',
                'originalValue' => json_encode(['message' => Constants::ENQUIRY_NEW_LEAD]),
                'newValue' => json_encode(['message' => Constants::ENQUIRY_CONTACTING]),
            ]);
        }

        // Contacting + activity without "Qualified" → stays Contacting, owner and
        // Open Game status unchanged. The inactivity window resets automatically
        // since a new task was created (the cron checks latest task created_at).
    }

    /**
     * Log a contact note (type=call) when a task of type "call" is created.
     * Resolves the contact for the task's source module.
     */
    protected function logCallContactNote($task): void
    {
        if ($task->type !== 'call') {
            return;
        }

        $contactId = $this->resolveContactIdForTask($task);

        if (! $contactId) {
            return;
        }

        ContactNote::create([
            'contactId' => $contactId,
            // Tag the mirror note with its source task so KPI call counts can
            // exclude it and not double count against the call task itself.
            'taskId' => $task->id,
            'content' => trim($task->subject.($task->comments ? "\n".$task->comments : '')),
            'type' => 'call',
            'callStatus' => Constants::CONTACT_NOTE_CALL_STATUS_TALKED,
            'parent' => 0,
            'createdBy' => $task->createdBy,
        ]);
    }

    /**
     * Resolve the contact id linked to a task based on its source module.
     * - contact  → moduleId itself
     * - enquiry  → enquiries.contactId
     * - property → properties.physicalOwnerId
     * - offer    → offers.buyerId
     * - closing  → closings.buyerId
     */
    protected function resolveContactIdForTask($task): ?int
    {
        if (! $task->module || ! $task->moduleId) {
            return null;
        }

        return match ($task->module) {
            Constants::MODULE_CONTACT => (int) $task->moduleId,
            Constants::MODULE_ENQUIRY => Enquiry::where('id', $task->moduleId)->value('contactId'),
            Constants::MODULE_PROPERTY => Property::where('id', $task->moduleId)->value('physicalOwnerId'),
            Constants::MODULE_OFFER => Offer::where('id', $task->moduleId)->value('buyerId'),
            Constants::MODULE_CLOSING => Closing::where('id', $task->moduleId)->value('buyerId'),
            default => null,
        };
    }
}
