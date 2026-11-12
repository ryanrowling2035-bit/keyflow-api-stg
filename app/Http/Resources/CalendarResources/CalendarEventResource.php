<?php

namespace App\Http\Resources\CalendarResources;

use App\Constants\Constants;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'eventType' => $this->eventType,
            'eventTypeLabel' => $this->eventType ? (Constants::CALENDAR_EVENT_TYPES[$this->eventType] ?? $this->eventType) : null,
            'startDateTime' => $this->startDateTime?->toIso8601String(),
            'endDateTime' => $this->endDateTime?->toIso8601String(),
            'allDay' => $this->allDay,
            'location' => $this->location,
            'module' => $this->module,
            'moduleId' => $this->moduleId,
            'taskId' => $this->taskId,
            'status' => $this->isOverdue ? 'overdue' : $this->status,
            'statusLabel' => $this->isOverdue ? (Constants::CALENDAR_EVENT_STATUS['overdue'] ?? 'Overdue') : ($this->status ? (Constants::CALENDAR_EVENT_STATUS[$this->status] ?? $this->status) : null),
            'priority' => $this->priority,
            'color' => $this->color ?? (Constants::CALENDAR_EVENT_COLORS[$this->eventType] ?? '#6B7280'),
            'reminderSet' => $this->reminderSet,
            'reminderDate' => $this->reminderDate?->format('Y-m-d'),
            'reminderTime' => $this->reminderTime,
            'isOverdue' => $this->isOverdue,
            'isFromOutlook' => $this->isFromOutlook,
            'isSyncedToOutlook' => $this->isSyncedToOutlook,
            'outlookEventId' => $this->outlookEventId,
            'isPrivate' => $this->isPrivate,
            'assignedTo' => $this->assignedUser ? [
                'id' => $this->assignedUser->id,
                'name' => $this->assignedUser->name,
            ] : null,
            'createdBy' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'linkedRecord' => $this->linkedRecord,
            'metadata' => $this->metadata,
            'createdAt' => convertToTimezone($this->created_at),
            'updatedAt' => convertToTimezone($this->updated_at),
        ];
    }
}
