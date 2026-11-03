<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DocumentExpiryNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExpiryNotificationController extends Controller
{
    public function __construct(
        protected DocumentExpiryNotificationService $expiryService
    ) {}

    /**
     * Daily background process / API trigger for document expiries (Visa, Passport, Emirates ID, Insurance, Labour Card)
     */
    public function runExpiryCron(Request $request)
    {
        $today = Carbon::today();
        $employees = User::with(['meta', 'role', 'teams'])->get();

        $triggeredAlerts = [];
        $notifications = [];

        $scheduleBreakdown = [
            'days30'  => 0,
            'days15'  => 0,
            'days7'   => 0,
            'today'   => 0,
            'expired' => 0,
        ];

        $docTypes = [
            'visa'        => ['label' => 'Visa', 'keys' => ['_visaExpiryDate', 'visa_expiry_date', 'visaExpiry']],
            'passport'    => ['label' => 'Passport', 'keys' => ['_passportExpiryDate', 'passport_expiry_date', 'passportExpiry']],
            'emirates_id' => ['label' => 'Emirates ID', 'keys' => ['_emiratesIdExpiryDate', 'emirates_id_expiry_date', 'emiratesIdExpiry']],
            'insurance'   => ['label' => 'Insurance', 'keys' => ['_insuranceExpiryDate', 'insurance_expiry_date', 'insuranceExpiry']],
            'labour_card' => ['label' => 'Labour Card', 'keys' => ['_labourCardExpiryDate', '_labourContractExpiryDate', 'labour_card_expiry_date', 'labourCardExpiry']],
        ];

        $totalDocsChecked = 0;

        foreach ($employees as $emp) {
            $meta = [];
            if ($emp->meta) {
                foreach ($emp->meta as $m) {
                    $meta[$m->metaKey] = $m->metaValue;
                }
            }
            if (is_array($emp->userMetaDetails)) {
                $meta = array_merge($meta, $emp->userMetaDetails);
            }

            foreach ($docTypes as $docKey => $docMeta) {
                $totalDocsChecked++;
                $expiryStr = null;

                foreach ($docMeta['keys'] as $k) {
                    if (! empty($meta[$k])) { $expiryStr = $meta[$k]; break; }
                    if (! empty($emp->$k)) { $expiryStr = $emp->$k; break; }
                }

                if (! $expiryStr) {
                    continue;
                }

                try {
                    $expiryDate = Carbon::parse($expiryStr)->startOfDay();
                    $diffDays = (int) $today->diffInDays($expiryDate, false);

                    $forceAll = $request->input('forceAll', false);
                    $daysFilter = $request->input('days', null);

                    if ($daysFilter !== null && (int) $daysFilter !== $diffDays && ! $forceAll) {
                        continue;
                    }

                    // Alert schedule: 30, 15, 7, 0 days & expired
                    $isScheduleMatch = in_array($diffDays, [30, 15, 7, 0]) || $diffDays < 0 || $forceAll;

                    if ($isScheduleMatch && $diffDays <= 60) {
                        if ($diffDays < 0) {
                            $scheduleLabel = 'EXPIRED ('.abs($diffDays).' days ago)';
                            $scheduleBreakdown['expired']++;
                        } elseif ($diffDays === 0) {
                            $scheduleLabel = 'Expires TODAY';
                            $scheduleBreakdown['today']++;
                        } elseif ($diffDays === 7) {
                            $scheduleLabel = 'Expires in 7 Days';
                            $scheduleBreakdown['days7']++;
                        } elseif ($diffDays === 15) {
                            $scheduleLabel = 'Expires in 15 Days';
                            $scheduleBreakdown['days15']++;
                        } elseif ($diffDays === 30) {
                            $scheduleLabel = 'Expires in 30 Days';
                            $scheduleBreakdown['days30']++;
                        } else {
                            $scheduleLabel = "Expires in {$diffDays} Days";
                        }

                        $triggeredAlerts[] = [
                            'employee_id'    => $emp->id,
                            'employee_name'  => $emp->name ?? $emp->email,
                            'document_type'  => $docMeta['label'],
                            'expiry_date'    => $expiryDate->toDateString(),
                            'days_remaining' => $diffDays,
                            'schedule_label' => $scheduleLabel,
                        ];

                        // Resolve recipients according to:
                        // Case 1 (Agent): Agent + Team Manager(s) + HR Admins
                        // Case 2 (Manager): Manager + HR Admins
                        // Case 3 (HR Admin): Only that specific HR Admin
                        $recipientIds = $this->expiryService->resolveNotificationRecipients($emp);

                        foreach ($recipientIds as $rcpId) {
                            $notifications[] = [
                                'title'        => "Upcoming Expiry: {$docMeta['label']}",
                                'message'      => "{$docMeta['label']} of {$emp->name} {$scheduleLabel}. Please renew and upload updated copy.",
                                'recipient_id' => $rcpId,
                                'type'         => 'document_expiry',
                                'module'       => 'employee_document',
                                'module_id'    => $emp->id,
                                'is_read'      => false,
                                'created_at'   => now()->toDateTimeString(),
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        $dispatchedCount = 0;
        if ($request->input('dispatch', true)) {
            try {
                $dispatchedCount = $this->expiryService->checkAndDispatchDailyNotifications();
            } catch (\Throwable $e) {
                // Silently fallback if background lock or db is offline
            }
        }

        return response()->json([
            'success' => true,
            'timestamp' => now()->toDateTimeString(),
            'summary' => [
                'total_employees_scanned'     => count($employees),
                'total_documents_checked'     => $totalDocsChecked,
                'total_alerts_triggered'      => count($triggeredAlerts),
                'in_crm_notifications_queued' => count($notifications),
                'notifications_dispatched'    => $dispatchedCount,
                'schedule_breakdown'          => $scheduleBreakdown,
            ],
            'alerts' => $triggeredAlerts,
            'notifications' => $notifications,
        ]);
    }
}
