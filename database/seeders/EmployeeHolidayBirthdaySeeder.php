<?php

namespace Database\Seeders;

use App\Constants\Constants;
use App\Models\GreetingTemplate;
use App\Models\ManageEmail;
use Illuminate\Database\Seeder;

class EmployeeHolidayBirthdaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedManageEmails();
        $this->seedGreetingTemplates();
    }

    /**
     * Seed ManageEmail records so they appear in /dashboard/email-templates
     */
    private function seedManageEmails(): void
    {
        $emails = [
            [
                'title' => 'Employee Birthday Greeting',
                'slug' => Constants::EMAIL_SLUGS['hr']['employee_birthday'] ?? 'hr_employee_birthday_greeting',
                'subject' => 'Happy Birthday {{employee_name}}! 🎉',
                'emailContent' => '
                    <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; background: #ffffff;">
                        <h2 style="color: #2563eb; margin-top: 0;">🎂 Happy Birthday, {{employee_name}}! 🎈</h2>
                        <p>Dear {{employee_name}},</p>
                        <p>On behalf of the entire team at <strong>{{company_name}}</strong>, we want to wish you a very Happy Birthday!</p>
                        <p>We appreciate your dedication, hard work, and the positive energy you bring to our team every day.</p>
                        <p>May this year bring you great happiness, good health, and continued success in all your endeavors.</p>
                        <br>
                        <p>Enjoy your special day!</p>
                        <p>Warmest regards,<br><strong>{{company_name}} Team</strong></p>
                    </div>
                ',
                'macro' => '{{employee_name}}, {{employee_first_name}}, {{employee_role}}, {{employee_department}}, {{company_name}}, {{company_email}}, {{year}}',
                'status' => Constants::USER_STATUS_ACTIVE,
                'createdBy' => 1,
            ],
            [
                'title' => 'Employee Holiday Greeting',
                'slug' => Constants::EMAIL_SLUGS['hr']['employee_holiday'] ?? 'hr_employee_holiday_greeting',
                'subject' => 'Warm Holiday Wishes from {{company_name}}! 🎄✨',
                'emailContent' => '
                    <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; background: #ffffff;">
                        <h2 style="color: #059669; margin-top: 0;">🌟 Season’s Greetings & Happy Holidays! 🌟</h2>
                        <p>Dear {{employee_name}},</p>
                        <p>As we celebrate this festive season, we want to extend our heartfelt gratitude for your dedication and contributions to <strong>{{company_name}}</strong>.</p>
                        <p>May this holiday season bring you, your family, and loved ones peace, joy, and wonderful moments together.</p>
                        <p>Wishing you a joyful holiday and a happy, prosperous year ahead!</p>
                        <br>
                        <p>Warmest wishes,<br><strong>Management & Team at {{company_name}}</strong></p>
                    </div>
                ',
                'macro' => '{{employee_name}}, {{employee_first_name}}, {{holiday_name}}, {{company_name}}, {{year}}',
                'status' => Constants::USER_STATUS_ACTIVE,
                'createdBy' => 1,
            ],
        ];

        foreach ($emails as $email) {
            ManageEmail::updateOrCreate(
                ['slug' => $email['slug']],
                array_merge($email, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command?->info('Employee Birthday & Holiday email templates seeded into manage_emails successfully.');
    }

    /**
     * Seed GreetingTemplate records for automated greetings cron
     */
    private function seedGreetingTemplates(): void
    {
        $greetings = [
            [
                'name' => 'Default Employee Birthday Greeting',
                'type' => 'Birthday',
                'trigger_event' => 'On Birthday',
                'holiday_date' => null,
                'send_time' => '09:00',
                'status' => 'Active',
                'target_audience' => 'Employee',
                'subject' => '🎂 Happy Birthday {Employee_First_Name}! Wishing you a wonderful day!',
                'body' => '<p>Dear <strong>{Employee_First_Name}</strong>,</p><p>Wishing you a very Happy Birthday from all of us at <strong>{Company_Name}</strong>!</p><p>We appreciate your dedication, hard work, and the energy you bring to the team every day.</p><p>May your year ahead be filled with happiness, health, and great achievements!</p><p>Warmest regards,<br/><strong>{Company_Name} Team</strong></p>',
            ],
            [
                'name' => 'Default Employee Holiday Greeting',
                'type' => 'Holiday',
                'trigger_event' => 'On Holiday Date',
                'holiday_date' => date('Y') . '-12-25',
                'send_time' => '09:00',
                'status' => 'Active',
                'target_audience' => 'Employee',
                'subject' => '🌟 Warm Holiday Wishes from {Company_Name}! 🎄',
                'body' => '<p>Dear <strong>{Employee_First_Name}</strong>,</p><p>As we enter this festive season, we would like to thank you for all your contributions to <strong>{Company_Name}</strong>.</p><p>Wishing you and your family peace, joy, and wonderful celebrations!</p><p>Warm regards,<br/><strong>Management at {Company_Name}</strong></p>',
            ],
            [
                'name' => 'Default Client Birthday Greeting',
                'type' => 'Birthday',
                'trigger_event' => 'On Birthday',
                'holiday_date' => null,
                'send_time' => '09:00',
                'status' => 'Active',
                'target_audience' => 'Clients',
                'subject' => 'Happy Birthday {Contact_First_Name} from {Company_Name}!',
                'body' => '<p>Dear <strong>{Contact_First_Name}</strong>,</p><p>Wishing you a fantastic Birthday filled with joy and celebration!</p><p>Thank you for being a valued client of <strong>{Company_Name}</strong>.</p><p>Best regards,<br/><strong>{Agent_Name}</strong><br/>{Company_Name}</p>',
            ],
            [
                'name' => 'Default Client Holiday Greeting',
                'type' => 'Holiday',
                'trigger_event' => 'On Holiday Date',
                'holiday_date' => date('Y') . '-12-25',
                'send_time' => '09:00',
                'status' => 'Active',
                'target_audience' => 'Clients',
                'subject' => 'Season’s Greetings & Happy Holidays from {Company_Name}!',
                'body' => '<p>Dear <strong>{Contact_First_Name}</strong>,</p><p>Wishing you and your loved ones a joyful holiday season and a prosperous New Year!</p><p>Warmest regards,<br/><strong>{Agent_Name}</strong><br/>{Company_Name}</p>',
            ],
        ];

        foreach ($greetings as $greeting) {
            GreetingTemplate::updateOrCreate(
                [
                    'name' => $greeting['name'],
                    'target_audience' => $greeting['target_audience'],
                ],
                $greeting
            );
        }

        $this->command?->info('Greeting templates seeded into greeting_templates successfully.');
    }
}

