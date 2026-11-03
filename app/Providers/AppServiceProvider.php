<?php

namespace App\Providers;

use App\Models\GlobalSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        RateLimiter::for('external-api', function (Request $request) {
            $key = $request->user()?->id
                ?? $request->bearerToken()
                ?? $request->ip();

            return Limit::perMinute(200)->by('external-api|'.$key);
        });

        RateLimiter::for('keyflow-public-api', function (Request $request) {
            $key = $request->user()?->id
                ?? $request->bearerToken()
                ?? $request->header('X-API-KEY')
                ?? $request->ip();

            return Limit::perMinute(120)->by('keyflow-public-api|'.$key);
        });

        try {
            if (Schema::hasTable('global_settings')) {

                $settings = GlobalSetting::pluck('settingValue', 'settingKey')->toArray();

                if (! empty($settings['_smtpHost'])) {

                    Config::set('mail.default', 'smtp');

                    Config::set('mail.mailers.smtp', [
                        'transport' => 'smtp',
                        'host' => $settings['_smtpHost'],
                        'port' => (int) $settings['_smtpPort'],
                        'encryption' => $settings['_smtpEncryption'] ?? null,
                        'username' => $settings['_smtpUsername'] ?? null,
                        'password' => $settings['_smtpPassword'] ?? null,
                        'timeout' => null,
                        'auth_mode' => null,
                    ]);
                    Config::set('mail.from.address', $settings['_sendFromEmail'] ?? config('mail.from.address'));
                    Config::set('mail.from.name', $settings['_sendFromName'] ?? $settings['_websiteTitle'] ?? config('app.name'));

                    if ($this->app->resolved('mail.manager')) {
                        $this->app->forgetInstance('mail.manager');
                        $this->app->forgetInstance('mailer');
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore if database is unreachable (e.g. CLI commands, early boot, or tests)
        }
    }
}
