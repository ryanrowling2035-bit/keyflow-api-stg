<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeaveNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $notificationType 'submitted' | 'approved' | 'rejected'
     * @param array $details [
     *   'employee_name' => string,
     *   'leave_type' => string,
     *   'start_date' => string,
     *   'end_date' => string,
     *   'working_days' => float|int,
     *   'reason' => string,
     *   'handover_to' => ?string,
     *   'has_manager_approved' => ?string,
     *   'has_doctor_note' => ?string,
     *   'rejection_reason' => ?string,
     *   'actioned_by' => ?string,
     * ]
     */
    public function __construct(
        public string $notificationType,
        public array $details
    ) {}

    public function build()
    {
        $employeeName = $this->details['employee_name'] ?? 'Employee';
        $leaveType = ucfirst($this->details['leave_type'] ?? 'Leave');
        $workingDays = $this->details['working_days'] ?? 1;

        $subject = match ($this->notificationType) {
            'submitted' => "[LEAVE APPLICATION] {$employeeName} - {$leaveType} ({$workingDays} Days)",
            'approved' => "[LEAVE APPROVED] Your {$leaveType} Request has been Approved",
            'rejected' => "[LEAVE REJECTED] Your {$leaveType} Request was Rejected",
            default => "[LEAVE NOTIFICATION] {$leaveType} Update"
        };

        $bodyContent = $this->renderBodyHtml();

        $html = view('EmailTemplate.templateLayout', [
            'title' => $subject,
            'body'  => $bodyContent,
            'year'  => date('Y'),
        ])->render();

        return $this->subject($subject)->html($html);
    }

    protected function renderBodyHtml(): string
    {
        $type = $this->notificationType;
        $d = $this->details;
        $employeeName = htmlspecialchars($d['employee_name'] ?? 'Employee');
        $leaveType = htmlspecialchars(ucfirst($d['leave_type'] ?? 'Leave'));
        $startDate = htmlspecialchars($d['start_date'] ?? '');
        $endDate = htmlspecialchars($d['end_date'] ?? '');
        $workingDays = htmlspecialchars((string) ($d['working_days'] ?? 1));
        $reason = nl2br(htmlspecialchars($d['reason'] ?? 'None provided'));
        $handoverTo = htmlspecialchars($d['handover_to'] ?? 'Not specified');
        $hasManagerApproved = htmlspecialchars($d['has_manager_approved'] ?? 'N/A');
        $hasDoctorNote = htmlspecialchars($d['has_doctor_note'] ?? 'N/A');
        $rejectionReason = nl2br(htmlspecialchars($d['rejection_reason'] ?? ''));
        $actionedBy = htmlspecialchars($d['actioned_by'] ?? 'HR / Management');

        $badgeBg = match ($type) {
            'approved' => '#f0fdf4',
            'rejected' => '#fef2f2',
            default => '#eff6ff'
        };
        $badgeBorder = match ($type) {
            'approved' => '#16a34a',
            'rejected' => '#dc2626',
            default => '#2563eb'
        };
        $badgeText = match ($type) {
            'approved' => '#15803d',
            'rejected' => '#b91c1c',
            default => '#1d4ed8'
        };

        $statusTitle = match ($type) {
            'submitted' => 'New Leave Application Submitted',
            'approved' => 'Leave Application Approved',
            'rejected' => 'Leave Application Rejected',
            default => 'Leave Status Update'
        };

        $statusMessage = match ($type) {
            'submitted' => "<strong>{$employeeName}</strong> has submitted a new leave application. Please review the details below:",
            'approved' => "Your <strong>{$leaveType}</strong> request from <strong>{$startDate}</strong> to <strong>{$endDate}</strong> has been <strong>approved</strong> by {$actionedBy}. Your leave balance has been updated automatically.",
            'rejected' => "Your <strong>{$leaveType}</strong> request from <strong>{$startDate}</strong> to <strong>{$endDate}</strong> was <strong>rejected</strong> by {$actionedBy}.",
            default => "Leave request update."
        };

        $rejectionBlock = '';
        if ($type === 'rejected' && !empty($rejectionReason)) {
            $rejectionBlock = "
            <div style='margin-top: 20px; padding: 14px 18px; background-color: #fef2f2; border-left: 4px solid #dc2626; border-radius: 4px;'>
                <p style='margin: 0; color: #991b1b; font-size: 13px; font-weight: bold;'>Rejection Reason:</p>
                <p style='margin: 6px 0 0; color: #7f1d1d; font-size: 14px; line-height: 1.5;'>{$rejectionReason}</p>
            </div>";
        }

        return "
        <!-- STATUS BANNER -->
        <div style='background-color: {$badgeBg}; border-left: 4px solid {$badgeBorder}; padding: 14px 18px; border-radius: 4px; margin-bottom: 24px;'>
            <span style='font-size: 16px; font-weight: 700; color: {$badgeText};'>HR Notification &bull; {$statusTitle}</span>
        </div>

        <p style='margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #333333;'>{$statusMessage}</p>

        <!-- DETAILS TABLE -->
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0' style='width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px; font-family: Arial, Helvetica, sans-serif;'>
            <tr style='border-bottom: 1px solid #f0f2f5;'>
                <td style='padding: 12px 0; color: #6b7280; width: 42%; font-weight: 500;'>Employee Name</td>
                <td style='padding: 12px 0; color: #111827; font-weight: 600;'>{$employeeName}</td>
            </tr>
            <tr style='border-bottom: 1px solid #f0f2f5;'>
                <td style='padding: 12px 0; color: #6b7280; font-weight: 500;'>Leave Type</td>
                <td style='padding: 12px 0; color: #111827; font-weight: 600;'>{$leaveType}</td>
            </tr>
            <tr style='border-bottom: 1px solid #f0f2f5;'>
                <td style='padding: 12px 0; color: #6b7280; font-weight: 500;'>Duration</td>
                <td style='padding: 12px 0; color: #111827; font-weight: 600;'>{$startDate} to {$endDate}</td>
            </tr>
            <tr style='border-bottom: 1px solid #f0f2f5;'>
                <td style='padding: 12px 0; color: #6b7280; font-weight: 500;'>Total Working Days</td>
                <td style='padding: 12px 0; color: #111827; font-weight: 600;'>{$workingDays} day(s)</td>
            </tr>
            <tr style='border-bottom: 1px solid #f0f2f5;'>
                <td style='padding: 12px 0; color: #6b7280; font-weight: 500;'>Manager Pre-Approved</td>
                <td style='padding: 12px 0; color: #111827;'>{$hasManagerApproved}</td>
            </tr>
            <tr style='border-bottom: 1px solid #f0f2f5;'>
                <td style='padding: 12px 0; color: #6b7280; font-weight: 500;'>Work & Property Handover</td>
                <td style='padding: 12px 0; color: #111827;'>{$handoverTo}</td>
            </tr>
            <tr style='border-bottom: 1px solid #f0f2f5;'>
                <td style='padding: 12px 0; color: #6b7280; font-weight: 500;'>Doctor\'s Sick Note</td>
                <td style='padding: 12px 0; color: #111827;'>{$hasDoctorNote}</td>
            </tr>
            <tr>
                <td style='padding: 12px 0; color: #6b7280; font-weight: 500; vertical-align: top;'>Reason</td>
                <td style='padding: 12px 0; color: #111827;'>{$reason}</td>
            </tr>
        </table>

        {$rejectionBlock}
        ";
    }
}
