<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\GreetingTemplate;
use App\Jobs\SendGreetingEmailJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AutomatedGreetingsQueueTest extends TestCase
{
    public function test_command_dispatches_jobs_for_matching_holiday_template(): void
    {
        Queue::fake();

        $template = GreetingTemplate::create([
            'name'            => 'New Year 2027',
            'type'            => 'Holiday',
            'holiday_date'    => '2027-01-01',
            'send_time'       => '10:00',
            'status'          => 'Active',
            'target_audience' => 'Clients',
            'subject'         => 'Happy New Year {Contact_First_Name}!',
            'body'            => 'Wishing you a joyful new year from {Company_Name}.',
        ]);

        $contact = Contact::create([
            'name'  => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);

        Artisan::call('greetings:send-automated', [
            '--date' => '2027-01-01',
            '--time' => '10:00',
        ]);

        Queue::assertPushedOn('greetings', SendGreetingEmailJob::class, function ($job) use ($template, $contact) {
            return $job->templateId === $template->id 
                && $job->recipientType === 'contact' 
                && $job->recipientId === $contact->id;
        });
    }
}

