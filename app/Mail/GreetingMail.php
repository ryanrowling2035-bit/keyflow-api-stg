<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GreetingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectString;
    public $bodyString;

    public function __construct($subjectString, $bodyString)
    {
        $this->subjectString = $subjectString;
        $this->bodyString = $bodyString;
    }

    public function build()
    {
        $this->subject($this->subjectString);
        
        $body = $this->bodyString;
        
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
        
        return $this->html($body);
    }
}
