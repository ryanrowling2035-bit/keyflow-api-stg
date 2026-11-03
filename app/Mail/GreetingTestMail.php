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
        $body = $this->template->body ?? '<p>This is a test greeting.</p>';

        $agent = \App\Models\User::first();

        $photoHtml = $agent && $agent->profilePhoto 
            ? '<img src="' . env('APP_URL') . '/storage/' . $agent->profilePhoto . '" alt="Agent" style="width:90px; height:90px; object-fit:cover; border-radius:8px;" />' 
            : '<img src="https://ui-avatars.com/api/?name=' . urlencode($agent ? $agent->name : 'Agent') . '" alt="Agent" style="width:90px; height:90px; border-radius:8px;" />';
            
        $agentName = $agent && $agent->name ? $agent->name : 'Sarah Jenkins';
        $agentPhone = $agent && $agent->phone ? $agent->phone : '+971 58 556 1813';
        $agentEmail = $agent && $agent->email ? $agent->email : 'agent@exclusive-links.com';
        $brokerId = $agent ? $agent->meta()->where('metaKey', '_brokerId')->value('metaValue') : null;
        $agentBrn = $brokerId ? 'BRN #: ' . $brokerId : 'BRN #: 02435356';

        $signatureHtml = '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:20px; font-family: Arial, sans-serif;">
            <tr>
                <td style="padding-right: 15px; vertical-align: top;">' . $photoHtml . '</td>
                <td style="vertical-align: top; font-size: 14px; color: #333; line-height: 1.5;">
                    <strong>' . $agentName . '</strong><br>
                    ' . $agentPhone . '<br>
                    <a href="mailto:' . $agentEmail . '" style="color: #0056b3; text-decoration: none;">' . $agentEmail . '</a><br><br>
                    <span style="font-size:12px; color:#666;">' . $agentBrn . '</span>
                </td>
            </tr>
        </table>';

        $vars = [
            '{Contact_First_Name}' => 'John',
            '{Contact_Last_Name}' => 'Doe',
            '{Agent_Name}' => $agentName,
            '{Agent_Phone}' => $agentPhone,
            '{Agent_Email}' => $agentEmail,
            '{Agent_BRN}' => $agentBrn,
            '{Agent_Photo}' => $photoHtml,
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
