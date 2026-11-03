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

        $html = $this->renderHtml();

        return $this->subject($subject)->html($html);
    }

    protected function renderHtml(): string
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

        $badgeColor = match ($type) {
            'approved' => '#059669',
            'rejected' => '#dc2626',
            default => '#2563eb'
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
            <div style='margin-top: 16px; padding: 12px 16px; background-color: #fef2f2; border-left: 4px solid #dc2626; border-radius: 4px;'>
                <p style='margin: 0; color: #991b1b; font-size: 13px; font-weight: bold;'>Rejection Reason:</p>
                <p style='margin: 4px 0 0; color: #7f1d1d; font-size: 14px;'>{$rejectionReason}</p>
            </div>";
        }

        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>{$statusTitle}</title>
</head>
<body style='font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b;'>
    <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);'>
        <div style='background-color: {$badgeColor}; padding: 18px 24px; color: #ffffff;'>
            <h2 style='margin: 0; font-size: 20px; font-weight: 600;'>Keyflow HR &bull; {$statusTitle}</h2>
        </div>
        
        <div style='padding: 24px;'>
            <p style='font-size: 15px; line-height: 1.5; margin-top: 0;'>{$statusMessage}</p>
            
            <table style='width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px;'>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; color: #64748b; width: 40%;'>Employee Name</td>
                    <td style='padding: 10px 0; font-weight: 600; color: #0f172a;'>{$employeeName}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; color: #64748b;'>Leave Type</td>
                    <td style='padding: 10px 0; font-weight: 600; color: #0f172a;'>{$leaveType}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; color: #64748b;'>Dates</td>
                    <td style='padding: 10px 0; color: #0f172a;'>{$startDate} to {$endDate}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; color: #64748b;'>Working Days</td>
                    <td style='padding: 10px 0; font-weight: 600; color: #0f172a;'>{$workingDays} day(s)</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; color: #64748b;'>Manager Pre-Approved</td>
                    <td style='padding: 10px 0; color: #0f172a;'>{$hasManagerApproved}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; color: #64748b;'>Work & Property Handover</td>
                    <td style='padding: 10px 0; color: #0f172a;'>{$handoverTo}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; color: #64748b;'>Doctor's Sick Note</td>
                    <td style='padding: 10px 0; color: #0f172a;'>{$hasDoctorNote}</td>
                </tr>
                <tr>
                    <td style='padding: 10px 0; color: #64748b; vertical-align: top;'>Reason</td>
                    <td style='padding: 10px 0; color: #0f172a;'>{$reason}</td>
                </tr>
            </table>

            {$rejectionBlock}
        </div>

        <div style='background-color: #f8fafc; padding: 14px 24px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; text-align: center;'>
            This is an automated notification from Keyflow CRM Leave Management System.
        </div>
    </div>
</body>
</html>";
    }
}

