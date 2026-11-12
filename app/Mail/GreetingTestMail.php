<?php

namespace App\Mail;

use App\Models\GreetingTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GreetingTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $template;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(GreetingTemplate $template)
    {
        $this->template = $template;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = $this->template->subject ?? 'Test Greeting';
        $body = formatGreetingSignatureBlock($this->template->body ?? '<p>This is a test greeting.</p>');

        $agent = \App\Models\User::first();

        $photoHtml = $agent && $agent->profilePhoto 
            ? '<img src="' . getImageUrl($agent->profilePhoto) . '" alt="Agent" style="width:100px; height:110px; object-fit:cover; border-radius:4px; display:block;" />' 
            : '<img src="https://ui-avatars.com/api/?name=' . urlencode($agent ? $agent->name : 'Agent') . '" alt="Agent" style="width:100px; height:110px; object-fit:cover; border-radius:4px; display:block;" />';
            
        $agentName = $agent && $agent->name ? $agent->name : 'Victoria Dewar';
        $agentPhone = $agent && $agent->phone ? $agent->phone : '+971 58 556 1813';
        $agentEmail = $agent && $agent->email ? $agent->email : 'victoria.d@exclusive-links.com';
        $brokerId = $agent ? $agent->meta()->where('metaKey', '_brokerId')->value('metaValue') : null;
        $agentBrn = $brokerId ?: ($agent && $agent->agentCode ? $agent->agentCode : '02435356');

        $signatureHtml = '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:15px; font-family: Arial, sans-serif;">
            <tr>
                <td style="padding-right: 15px; vertical-align: top;">' . $photoHtml . '</td>
                <td style="vertical-align: top; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #222222;">
                    <div style="font-weight: bold; font-size: 15px; color: #111111;">' . htmlspecialchars($agentName) . '</div>
                    ' . ($agentPhone ? '<div style="color: #333333; margin-top: 2px;">' . htmlspecialchars($agentPhone) . '</div>' : '') . '
                    ' . ($agentEmail ? '<div style="margin-top: 2px;"><a href="mailto:' . htmlspecialchars($agentEmail) . '" style="color: #0044cc; text-decoration: underline;">' . htmlspecialchars($agentEmail) . '</a></div>' : '') . '
                    ' . ($agentBrn ? '<div style="margin-top: 10px; font-size: 13px; color: #333333;">BRN #: ' . htmlspecialchars($agentBrn) . '</div>' : '') . '
                </td>
            </tr>
        </table>';

        $vars = [
            '{Contact_First_Name}' => 'John',
            '{Contact_Last_Name}' => 'Doe',
            '{Employee_First_Name}' => 'Alex',
            '{Employee_Last_Name}' => 'Smith',
            '{Employee_Name}' => 'Alex Smith',
            '{Employee_Role}' => 'Senior Property Consultant',
            '{Employee_Department}' => 'Sales',
            '{Years_Completed}' => '5 Years',
            '{Work_Anniversary_Years}' => '5 Years',
            '{Joining_Date}' => '12 Nov 2021',
            '{Agent_Name}' => $agentName,
            '{Agent_Phone}' => $agentPhone,
            '{Agent_Email}' => $agentEmail,
            '{Agent_BRN}' => $agentBrn,
            '{Agent_Photo}' => $photoHtml,
            '{Agent_Signature}' => $signatureHtml,
            '{Agent_Card}' => $signatureHtml,
            '{Company_Name}' => 'Exclusive Links Real Estate'
        ];

        foreach ($vars as $key => $value) {
            $subject = str_replace($key, $value, $subject);
            $body = str_replace($key, $value, $body);
        }

        $this->subject($subject)->html($body);

        $this->withSymfonyMessage(function (\Symfony\Component\Mime\Email $message) use (&$body) {
            if (preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $body, $matches)) {
                foreach ($matches[1] as $src) {
                    if (strpos($src, '/api/greetings/image/') !== false) {
                        $parts = explode('/api/greetings/image/', $src);
                        $path = storage_path('app/greetings/' . end($parts));
                        
                        if (file_exists($path)) {
                            $cid = md5($path) . '@keyflow';
                            $message->embedFromPath($path, $cid);
                            $body = str_replace($src, 'cid:' . $cid, $body);
                        }
                    } elseif (strpos($src, '/storage/') !== false) {
                        $parts = explode('/storage/', $src);
                        $path = public_path('storage/' . $parts[1]);
                        
                        if (file_exists($path)) {
                            // Embed the image inline
                            $cid = md5($path) . '@keyflow';
                            $message->embedFromPath($path, $cid);
                            
                            // Replace the URL with the cid
                            $body = str_replace($src, 'cid:' . $cid, $body);
                        }
                    }
                }
                // Update the HTML body with the replaced CIDs
                $message->html($body);
            }
        });

        return $this;
    }
}
