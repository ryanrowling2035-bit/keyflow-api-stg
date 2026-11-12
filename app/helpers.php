<?php

use App\Constants\Constants;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\GlobalSetting;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\ClosingService;
use App\Services\EnquiryHistoryService;
use App\Services\EnquiryService;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use App\Services\OfferService;
use App\Services\PropertyHistoryService;
use App\Services\PropertyService;
use App\Services\UserActivityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

if (! function_exists('user')) {
    /**
     * Get the authenticated user from the API guard.
     *
     * @return User|null
     */
    function user()
    {
        return auth('api')->user();
    }
}

if (! function_exists('isSuperAdmin')) {
    /**
     * Check if the authenticated user is a super admin.
     */
    function isSuperAdmin(): bool
    {
        $user = user();

        if (! $user || ! $user->role) {
            return false;
        }

        return $user->role->name === 'SuperAdmin';
    }
}

if (! function_exists('isManager')) {
    /**
     * Check if the authenticated user is a manager.
     */
    function isManager(): bool
    {
        $user = user();

        if (! $user || ! $user->role) {
            return false;
        }

        return $user->role->name === 'Manager';
    }
}

if (! function_exists('isAgent')) {
    /**
     * Check if the authenticated user is an agent.
     */
    function isAgent(): bool
    {
        $user = user();

        if (! $user || ! $user->role) {
            return false;
        }

        if ($user->role->name === 'Agent') {
            return true;
        }

        // A Manager logged in from the App (context=agent) is scoped like an agent.
        return $user->role->name === 'Manager' && actingAsAgent();
    }
}

if (! function_exists('actingAsAgent')) {
    /**
     * Whether the current JWT was issued with the agent context (App login).
     */
    function actingAsAgent(): bool
    {
        try {
            return auth('api')->payload()->get('context') === 'agent';
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (! function_exists('isHr')) {
    /**
     * Check if the authenticated user is an HR user.
     */
    function isHr(): bool
    {
        $user = user();

        if (! $user || ! $user->role) {
            return false;
        }

        return $user->role->name === 'HR';
    }
}

if (! function_exists('isAdmin')) {
    /**
     * Check if the authenticated user is an admin (neither Manager, Agent nor HR).
     */
    function isAdmin(): bool
    {
        $user = user();

        if (! $user || ! $user->role) {
            return false;
        }

        return ! in_array($user->role->name, ['Manager', 'Agent', 'HR'], true);
    }
}

if (! function_exists('getRoleNameById')) {
    /**
     * Get role name by role ID.
     */
    function getRoleNameById(?int $roleId): ?string
    {
        if (! $roleId) {
            return null;
        }

        return Role::where('id', $roleId)->value('name');
    }
}

if (! function_exists('globalSetting')) {
    /**
     * Read a value from global_settings. The whole (small) table is loaded once
     * per request and served from memory, so callers never re-query for individual
     * keys. Returns $default when the key is absent or the DB isn't ready yet.
     */
    function globalSetting(string $key, $default = null)
    {
        static $settings = null;

        if ($settings === null) {
            try {
                $settings = GlobalSetting::pluck('settingValue', 'settingKey')->all();
            } catch (\Throwable $e) {
                // DB not ready (fresh install before migrations, missing table).
                // Don't cache the failure so it retries once the table exists.
                return $default;
            }
        }

        return $settings[$key] ?? $default;
    }
}

if (! function_exists('emailAutomationEnabled')) {
    /**
     * Whether the automated email for a given template slug is allowed to send,
     * per the Settings → Email Automations toggles (stored as the
     * _emailAutomations global setting: {"globalEnabled":bool,"automations":{key:bool}}).
     *
     * Fails OPEN: anything not explicitly paused (or not a managed automation, or
     * no settings saved yet) is enabled, so this never blocks unexpectedly.
     */
    function emailAutomationEnabled(string $slug): bool
    {
        // Which toggle(s) control this slug? Transactional emails (password reset,
        // OTP, account created, …) aren't managed automations — they always send,
        // and the master switch must never touch them.
        $keys = [];
        foreach (\App\Constants\Constants::EMAIL_AUTOMATION_SLUGS as $key => $slugs) {
            if (in_array($slug, $slugs, true)) {
                $keys[] = $key;
            }
        }
        if (empty($keys)) {
            return true; // not a managed automation → always allowed
        }

        $stored = json_decode((string) globalSetting('_emailAutomations'), true);
        if (! is_array($stored)) {
            return true; // nothing configured → everything on (current behaviour)
        }

        // Master switch off = pause every managed automation.
        if (array_key_exists('globalEnabled', $stored) && $stored['globalEnabled'] === false) {
            return false;
        }

        $automations = is_array($stored['automations'] ?? null) ? $stored['automations'] : [];

        // Enabled unless ALL toggles for this slug are explicitly off (so a shared
        // template stays on while any of its toggles is on).
        foreach ($keys as $key) {
            if (($automations[$key] ?? true) !== false) {
                return true;
            }
        }

        return false; // managed + every toggle off → paused
    }
}

if (! function_exists('leadScoreMap')) {
    /**
     * The effective lead-score → enquiry-statuses map: the admin-configured
     * _leadScore setting (stored as {"hot":[...],"warm":[...],"cold":[...]})
     * falling back to Constants::ENQUIRY_LEAD_SCORE. Unknown scores/statuses in
     * the stored value are dropped and a score the admin never saved keeps its
     * default, so all three scores are always present.
     *
     * @return array<string, array<int, string>> score => statuses
     */
    function leadScoreMap(): array
    {
        // Read through the shared cache rather than a per-process static: the
        // enquiry observer runs inside queue workers, and a daemon holding a
        // stale map would re-stamp the old band on the next status change and
        // silently undo a re-rate. Only the raw stored value is cached, so a
        // change to the defaults below takes effect without a cache flush.
        try {
            $stored = Cache::rememberForever(
                Constants::LEAD_SCORE_CACHE_KEY,
                fn () => json_decode((string) GlobalSetting::where('settingKey', '_leadScore')->value('settingValue'), true)
            );
        } catch (\Throwable $e) {
            // DB/cache not ready (fresh install before migrations) — use defaults.
            $stored = null;
        }

        $map = Constants::ENQUIRY_LEAD_SCORE;

        if (is_array($stored)) {
            foreach ($stored as $score => $statuses) {
                if (! isset($map[$score]) || ! is_array($statuses)) {
                    continue;
                }
                $map[$score] = array_values(array_filter(
                    array_map(fn ($status) => trim((string) $status), $statuses),
                    fn ($status) => isset(Constants::ENQUIRY_ALL_STATUS[$status])
                ));
            }
        }

        return $map;
    }
}

if (! function_exists('leadScoreStatuses')) {
    /**
     * Enquiry statuses carrying the given lead score(s), e.g.
     * leadScoreStatuses(Constants::LEAD_SCORE_HOT).
     *
     * @param  string|array<int, string>  $scores
     * @return array<int, string>
     */
    function leadScoreStatuses($scores): array
    {
        $map = leadScoreMap();
        $statuses = [];

        foreach ((array) $scores as $score) {
            $statuses = array_merge($statuses, $map[strtolower(trim((string) $score))] ?? []);
        }

        return array_values(array_unique($statuses));
    }
}

if (! function_exists('leadScoreForStatus')) {
    /** The lead score (hot/warm/cold) an enquiry status maps to, or null when unscored. */
    function leadScoreForStatus(?string $status): ?string
    {
        if (! $status) {
            return null;
        }

        foreach (leadScoreMap() as $score => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $score;
            }
        }

        return null;
    }
}

if (! function_exists('getCompanyName')) {
    /**
     * Get the company name from global settings (_websiteTitle).
     * Falls back to config('app.name') if not set.
     */
    function getCompanyName(): string
    {
        return globalSetting('_websiteTitle') ?: config('app.name');
    }
}

if (! function_exists('userFollowedIds')) {
    /**
     * IDs the current user follows for a given module, loaded in a single query
     * per (user, module) and cached for the request. Lets resources answer
     * "is following?" with an in-memory lookup instead of one EXISTS query per row
     * (the N+1 that was saturating DB CPU on list endpoints).
     *
     * @return array<int,bool> keyed by moduleId for O(1) isset() checks
     */
    function userFollowedIds(string $module): array
    {
        static $cache = [];

        $user = user();
        if (! $user) {
            return [];
        }

        $key = $user->id.'|'.$module;
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Follow::where('userId', $user->id)
                ->where('module', $module)
                ->pluck('moduleId')
                ->flip()
                ->all();
        }

        return $cache[$key];
    }
}

if (! function_exists('userFavoritedIds')) {
    /**
     * IDs the current user has favorited for a given module — request-cached
     * counterpart to userFollowedIds().
     *
     * @return array<int,bool> keyed by moduleId for O(1) isset() checks
     */
    function userFavoritedIds(string $module): array
    {
        static $cache = [];

        $user = user();
        if (! $user) {
            return [];
        }

        $key = $user->id.'|'.$module;
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Favorite::where('userId', $user->id)
                ->where('module', $module)
                ->pluck('moduleId')
                ->flip()
                ->all();
        }

        return $cache[$key];
    }
}

if (! function_exists('userIsFollowing')) {
    /** Whether the current user follows the given module record (no per-row query). */
    function userIsFollowing(string $module, $moduleId): bool
    {
        return isset(userFollowedIds($module)[$moduleId]);
    }
}

if (! function_exists('userIsFavorite')) {
    /** Whether the current user has favorited the given module record (no per-row query). */
    function userIsFavorite(string $module, $moduleId): bool
    {
        return isset(userFavoritedIds($module)[$moduleId]);
    }
}

if (! function_exists('getImageUrl')) {
    /**
     * Get full URL for a storage image path.
     *
     * @param  string|null  $path  Relative path (e.g. "properties/images/photo.jpg" or "/storage/properties/images/photo.jpg")
     * @return string|null Full URL or null if path is empty
     */
    function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // If already a full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Strip leading "/storage/" or "storage/" prefix if present
        $path = preg_replace('#^/?storage/#', '', $path);

        return asset('storage/'.$path);
    }
}

if (! function_exists('passwordPolicy')) {
    /**
     * The admin-configured password policy (decoded from the _passwordPolicy
     * setting), or an empty array when none is set. Keys: minLength, maxLength,
     * requireUppercase/Lowercase/Number/SpecialChar, disallowUserInfo,
     * disallowCommonPasswords, expiryDays, preventReuseCount,
     * forceChangeOnFirstLogin, maxFailedAttempts, lockoutMinutes,
     * sessionIdleTimeoutMinutes.
     *
     * @return array<string, mixed>
     */
    function passwordPolicy(): array
    {
        $stored = json_decode((string) globalSetting('_passwordPolicy'), true);

        return is_array($stored) ? $stored : [];
    }
}

if (! function_exists('passwordMinLength')) {
    /**
     * Effective minimum password length: the policy's minLength, else the legacy
     * _minPasswordLength setting, else the default.
     */
    function passwordMinLength(): int
    {
        return (int) (passwordPolicy()['minLength'] ?? globalSetting('_minPasswordLength') ?: Constants::MIN_PASSWORD_LENGTH);
    }
}

if (! function_exists('passwordRules')) {
    /**
     * Validation rules enforcing the password policy: length + complexity (via
     * Laravel's Password rule), max length, "block common passwords"
     * (uncompromised) and "block name/email". Pass ['name' => ..., 'email' => ...]
     * so the name/email rule has something to check. Operational policy fields
     * (expiry, lockout, reuse, session timeout, …) are enforced elsewhere.
     *
     * @param  array{name?: ?string, email?: ?string}  $context
     * @return array<int, mixed>
     */
    function passwordRules(array $context = []): array
    {
        $policy = passwordPolicy();

        $rule = Password::min(passwordMinLength());
        if (! empty($policy['requireUppercase']) || ! empty($policy['requireLowercase'])) {
            $rule = $rule->mixedCase();
        }
        if (! empty($policy['requireNumber'])) {
            $rule = $rule->numbers();
        }
        if (! empty($policy['requireSpecialChar'])) {
            $rule = $rule->symbols();
        }
        if (! empty($policy['disallowCommonPasswords'])) {
            // Checks the password against the HaveIBeenPwned k-anonymity API; fails
            // open (treats as safe) when the service is unreachable.
            $rule = $rule->uncompromised();
        }

        $rules = [$rule];

        if (! empty($policy['maxLength'])) {
            $rules[] = 'max:'.(int) $policy['maxLength'];
        }

        // Reject a password that embeds the user's name or the email local part.
        if (! empty($policy['disallowUserInfo'])) {
            $rules[] = function ($attribute, $value, $fail) use ($context) {
                $password = mb_strtolower((string) $value);

                $needles = [];
                foreach (preg_split('/\s+/', mb_strtolower(trim((string) ($context['name'] ?? '')))) as $part) {
                    if (mb_strlen($part) >= 3) {
                        $needles[] = $part;
                    }
                }
                $emailLocal = mb_strtolower((string) strtok((string) ($context['email'] ?? ''), '@'));
                if (mb_strlen($emailLocal) >= 3) {
                    $needles[] = $emailLocal;
                }

                foreach ($needles as $needle) {
                    if ($needle !== '' && str_contains($password, $needle)) {
                        $fail('The password must not contain your name or email address.');

                        return;
                    }
                }
            };
        }

        return $rules;
    }
}

if (! function_exists('formatBytes')) {
    /**
     * Human-readable file size, e.g. 2411724 => "2.3 MB". Returns null for a null
     * size (unknown / file missing).
     */
    function formatBytes(?int $bytes, int $precision = 1): ?string
    {
        if ($bytes === null) {
            return null;
        }

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $precision).' '.$units[$power];
    }
}

if (! function_exists('formatDecimal')) {
    /**
     * Format a decimal value as a string, dropping a trailing ".0" (3.0 => "3")
     * while keeping a genuine fractional part (3.5 => "3.5").
     *
     * @param  mixed  $val
     * @return string|null Formatted value, or null if empty/non-numeric
     */
    function formatDecimal($val): ?string
    {
        if ($val === null || $val === '' || ! is_numeric($val)) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $val, 1, '.', ''), '0'), '.');
    }
}

if (! function_exists('normalizePhoneE164')) {
    /**
     * Normalise a phone/mobile number to E.164 form. UAE numbers in any common
     * shape (05XXXXXXXX, 5XXXXXXXX, 9715XXXXXXXX, 00971…) collapse to
     * +9715XXXXXXXX. A number that carries a country code — marked by a +, by a
     * 00 dialling prefix, or simply by being longer than a UAE national number —
     * keeps that country code, whichever it is (44…, 91…, 65…), when it's a sane
     * E.164 length. UAE is assumed only for a bare national-length number, which
     * is the one case that genuinely cannot be told apart. Returns null when
     * there is no real number — placeholders like "0" or "-" have no 1-9 digit.
     * When a value can't be confidently normalised, the trimmed source is
     * returned rather than dropped.
     *
     * This is the single source of truth for the Salesforce inbound import
     * (AbstractObjectImporter::formatPhone) and outbound sync
     * (SalesforceService::normalizePhone), so both agree on format — which is
     * what makes phone-based contact dedup work.
     */
    function normalizePhoneE164(?string $phone): ?string
    {
        $raw = trim((string) ($phone ?? ''));
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '' || ! preg_match('/[1-9]/', $digits)) {
            return null;
        }

        // A leading + or a 00 dialling prefix both mark an explicit country code,
        // so 00971… == +971… and 0044… == +44… — without this, a 00-prefixed
        // foreign number falls through to the UAE branch and comes back as a
        // bogus +971 number, which breaks phone-based contact dedup.
        $hasCountryCode = str_starts_with($raw, '+') || str_starts_with($digits, '00');
        $digits = preg_replace('/^00/', '', $digits);

        // Keep an explicit non-UAE country code as given, if it's a sane E.164
        // length (max 15 digits). Otherwise keep the source value rather than
        // dropping it.
        if ($hasCountryCode && ! str_starts_with($digits, '971')) {
            return strlen($digits) >= 7 && strlen($digits) <= 15 ? '+'.$digits : $raw;
        }

        // No + and no 00, but too long to be a UAE national number (9 digits, or 10
        // with the trunk 0) — so it already leads with some country code, which may
        // be any of them: 44…, 91…, 971…, 65…. Take it at face value instead of
        // assuming UAE, which used to turn 447715964767 into a bogus +971 number.
        // A leading 0 is a trunk prefix, not a country code, so it is excluded here
        // and left to the UAE branch below.
        if (! $hasCountryCode && ! str_starts_with($digits, '0') && strlen($digits) > 10) {
            return strlen($digits) <= 15 ? '+'.$digits : $raw;
        }

        // UAE: strip the 971 country code and/or national trunk 0, then re-apply +971.
        // Capped at 9 digits — the length of a UAE national number — so a 10-digit
        // foreign number written without its country code (a NANP "(202)-842-1818")
        // is returned untouched rather than fabricated into an invalid +971 number.
        $uae = ltrim(preg_replace('/^971/', '', $digits), '0');
        if (strlen($uae) >= 7 && strlen($uae) <= 9) {
            return '+971'.$uae;
        }

        // Not a UAE number stored without its leading + (e.g. 0034…): keep the
        // source value rather than dropping it.
        return $raw;
    }
}

if (! function_exists('normalizePhoneFields')) {
    /**
     * Return $data with the given phone/mobile keys normalised to E.164 via
     * normalizePhoneE164(). Only keys actually present in $data are touched, so
     * it is safe for partial update payloads (an absent key is left untouched).
     * Used by the Contact/User/Enquiry write paths so stored numbers match the
     * format the Salesforce import produces.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    function normalizePhoneFields(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = normalizePhoneE164($data[$key]);
            }
        }

        return $data;
    }
}

if (! function_exists('idCardUrl')) {
    /**
     * Resolve a stored ID-card path to a viewable URL.
     * New confidential ID cards live in the private bucket (returned as a
     * short-lived signed URL); legacy ID cards on the local disk fall back to
     * the public storage URL.
     */
    function idCardUrl(?string $filePath): ?string
    {
        if (empty($filePath)) {
            return null;
        }

        if (str_starts_with($filePath, 'documents/')) {
            return app(FileStorageService::class)->temporaryUrl($filePath);
        }

        return getImageUrl($filePath);
    }
}

if (! function_exists('notifyModule')) {
    /**
     * Send notification to owner + followers of a module (property/enquiry).
     *
     * @param  string  $module  'property' or 'enquiry'
     * @param  int  $moduleId  The ID of the property/enquiry
     * @param  string  $type  Notification type from Constants::NOTIFICATION_TYPES
     * @param  string  $action  Human-readable action e.g. 'updated', 'deleted', 'status changed'
     * @param  string|null  $moduleName  Optional display name (e.g. property title). Auto-resolved if null.
     * @param  array|null  $extraData  Additional data for the notification payload
     */
    function notifyModule(
        string $module,
        int $moduleId,
        string $type,
        string $action,
        ?string $moduleName = null,
        ?array $extraData = null,
        ?string $detail = null
    ): void {
        $actor = user();
        if (! $actor) {
            return;
        }

        if (! $moduleName) {
            $moduleName = match ($module) {
                'property' => Property::where('id', $moduleId)->value('title') ?? "#{$moduleId}",
                'enquiry' => "#{$moduleId}",
                default => "#{$moduleId}",
            };
        }

        $notificationService = app(NotificationService::class);

        $notificationService->notifyOwnerAndFollowers(
            module: $module,
            moduleId: $moduleId,
            actorId: $actor->id,
            type: $type,
            title: ucfirst($module)." {$action}: {$moduleName}",
            message: "{$actor->name} {$action} the {$module} {$moduleName}".($detail ? " — {$detail}" : ''),
            extraData: array_merge([
                'moduleTitle' => $moduleName,
                'moduleId' => $moduleId,
            ], $extraData ?? [])
        );
    }
}

if (! function_exists('logUserActivity')) {
    function logUserActivity(array $params): void
    {
        UserActivityService::log($params);
    }
}

if (! function_exists('logPropertyHistory')) {
    function logPropertyHistory(array $params): void
    {
        PropertyHistoryService::log($params);
    }
}

if (! function_exists('logEnquiryHistory')) {
    function logEnquiryHistory(array $params): void
    {
        EnquiryHistoryService::log($params);
    }
}

if (! function_exists('appTimezone')) {
    /**
     * The display/input timezone configured in global_settings._timeZone.
     * DB always stores UTC; this is only for presentation and for parsing user input.
     */
    function appTimezone(): string
    {
        static $timezone = null;

        if ($timezone === null) {
            $timezone = globalSetting('_timeZone') ?: config('app.timezone', 'UTC');
        }

        return $timezone;
    }
}

if (! function_exists('convertToTimezone')) {
    /**
     * Convert a UTC date/datetime to the application display timezone.
     */
    function convertToTimezone($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        return Carbon::parse($date)->setTimezone(appTimezone())->toDateTimeString();
    }
}

if (! function_exists('localDateTimeToUtc')) {
    /**
     * Combine a user-entered local date + time (app display timezone) and
     * return ['date' => 'Y-m-d', 'time' => 'H:i:s'] in UTC for storage.
     */
    function localDateTimeToUtc(?string $date, ?string $time): ?array
    {
        if (empty($date) || empty($time)) {
            return null;
        }

        $utc = Carbon::parse("{$date} {$time}", appTimezone())->utc();

        return ['date' => $utc->toDateString(), 'time' => $utc->toTimeString()];
    }
}

if (! function_exists('utcDateTimeToLocal')) {
    /**
     * Combine a stored UTC date + time and return ['date' => 'Y-m-d', 'time' => 'H:i:s']
     * in the app display timezone for response rendering.
     */
    function utcDateTimeToLocal(?string $date, ?string $time): ?array
    {
        if (empty($date) || empty($time)) {
            return null;
        }

        $local = Carbon::parse("{$date} {$time}", 'UTC')->setTimezone(appTimezone());

        return ['date' => $local->toDateString(), 'time' => $local->toTimeString()];
    }
}

if (! function_exists('localDayStartUtc')) {
    /**
     * Start of a user-selected calendar day (interpreted in the app display
     * timezone) expressed as a UTC datetime, for filtering UTC columns like
     * created_at. Returns null for an empty date. Pass a Carbon to use "now".
     */
    function localDayStartUtc($date = null): ?string
    {
        $date = $date ?? Carbon::now(appTimezone());

        if (empty($date)) {
            return null;
        }

        return Carbon::parse($date, appTimezone())->startOfDay()->utc()->toDateTimeString();
    }
}

if (! function_exists('localDayEndUtc')) {
    /**
     * End of a user-selected calendar day (interpreted in the app display
     * timezone) expressed as a UTC datetime, for filtering UTC columns like
     * created_at. Returns null for an empty date. Pass a Carbon to use "now".
     */
    function localDayEndUtc($date = null): ?string
    {
        $date = $date ?? Carbon::now(appTimezone());

        if (empty($date)) {
            return null;
        }

        return Carbon::parse($date, appTimezone())->endOfDay()->utc()->toDateTimeString();
    }
}

if (! function_exists('getWatermarkSource')) {
    /**
     * Resolve the watermark image as something Intervention's read() accepts:
     * a local absolute path (default/legacy) or raw image bytes (S3 public bucket).
     * Returns null when no watermark is configured.
     */
    function getWatermarkSource(): ?string
    {
        $setting = GlobalSetting::where('settingKey', '_watermark')->first();

        if (! $setting || empty($setting->settingValue)) {
            // Use default watermark if available
            $defaultWatermarkPath = public_path('watermark/default-watermark.png');

            return file_exists($defaultWatermarkPath) ? $defaultWatermarkPath : null;
        }

        $value = $setting->settingValue;

        // New format: full public S3 URL — return the raw image bytes.
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $key = app(FileStorageService::class)->keyFromPublicUrl($value);

            if ($key && Storage::disk(FileStorageService::PUBLIC_DISK)->exists($key)) {
                return Storage::disk(FileStorageService::PUBLIC_DISK)->get($key);
            }

            return null;
        }

        // Legacy format: "/storage/..." on the local public disk.
        $relativePath = preg_replace('#^/?storage/#', '', $value);

        return Storage::disk('public')->exists($relativePath)
            ? Storage::disk('public')->path($relativePath)
            : null;
    }
}

if (! function_exists('getScaledWatermarkImage')) {
    /**
     * Load the configured watermark scaled to a target width (aspect-ratio preserved).
     * Returns null if no watermark is configured.
     */
    function getScaledWatermarkImage(int $targetWidth): ?ImageInterface
    {
        $source = getWatermarkSource();
        if ($source === null || $targetWidth <= 0) {
            return null;
        }

        $manager = new ImageManager(new Driver);
        $watermark = $manager->read($source);
        $watermark->scale(width: $targetWidth);

        return $watermark;
    }
}

if (! function_exists('exportCsvStream')) {

    function exportCsvStream(Request $request, iterable $data, array $columnMap, string $fileName)
    {
        $selectedFields = $request->input('fields');

        if ($selectedFields) {
            if (is_string($selectedFields)) {
                $selectedFields = explode(',', trim($selectedFields, '[] '));
            }

            $selectedFields = array_map('trim', $selectedFields);
            $columnMap = array_intersect_key($columnMap, array_flip($selectedFields));
        }

        $headers = array_map(fn ($col) => $col['label'], $columnMap);

        $accessors = [];
        foreach ($columnMap as $key => $col) {
            $accessors[$key] = $col['value'] ?? fn ($row) => $row->{$key} ?? '';
        }

        $callback = function () use ($data, $headers, $accessors) {

            $file = fopen('php://output', 'w');

            // Excel UTF-8 support
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($file, $headers);

            // Data
            foreach ($data as $row) {
                fputcsv($file, array_map(fn ($accessor) => cleanCsvValue($accessor($row)), $accessors));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ]);
    }
}
if (! function_exists('cleanCsvValue')) {

    function cleanCsvValue($value)
    {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        // remove hidden/null chars
        $value = str_replace("\0", '', (string) $value);

        return $value;
    }
}

if (! function_exists('joinNonEmpty')) {
    /**
     * Join the given parts with a separator, dropping null/empty/whitespace-only
     * values. Prevents stray separators in composed strings — e.g. a leading
     * ", Springs 8" or a bare "," when a property has no community name.
     *
     * @param  array<int, string|null>  $parts
     */
    function joinNonEmpty(array $parts, string $separator = ', '): string
    {
        $clean = array_filter(
            array_map(static fn ($p) => trim((string) ($p ?? '')), $parts),
            static fn ($p) => $p !== ''
        );

        return implode($separator, $clean);
    }
}

if (! function_exists('generateThumbnail')) {
    /**
     * Generate a 150x150 best-fit thumbnail and store it.
     *
     * @param  string  $sourcePath  Absolute path to the source image file
     * @param  string  $uniqueName  Filename for the thumbnail
     * @param  string  $baseFolder  Per-record base folder (e.g. "properties/AC-S-000001")
     * @return string Public S3 URL of the stored thumbnail
     */
    function generateThumbnail(string $sourcePath, string $uniqueName, string $baseFolder = 'properties'): string
    {
        $manager = new ImageManager(new Driver);
        $thumbnail = $manager->read($sourcePath);
        $thumbnail->cover(150, 150);

        $extension = strtolower(pathinfo($uniqueName, PATHINFO_EXTENSION));

        $encoded = match ($extension) {
            'png' => $thumbnail->toPng(),
            'webp' => $thumbnail->toWebp(75),
            'avif' => $thumbnail->toAvif(75),
            default => $thumbnail->toJpeg(75),
        };

        return app(FileStorageService::class)->putPublic(
            rtrim($baseFolder, '/').'/thumbnails/'.$uniqueName,
            (string) $encoded
        );
    }
}

if (! function_exists('generateEnquiryReferenceNo')) {
    /**
     * Generate the next unique enquiry reference number (e.g. "R-00042").
     * Thin wrapper over EnquiryService so it can be used from anywhere.
     */
    function generateEnquiryReferenceNo(): string
    {
        return app(EnquiryService::class)->generateReferenceNo();
    }
}

if (! function_exists('generateOfferReferenceNo')) {
    /**
     * Generate the next unique offer reference number (e.g. "O-00042").
     * Thin wrapper over OfferService so it can be used from anywhere.
     */
    function generateOfferReferenceNo(): string
    {
        return app(OfferService::class)->generateReferenceNo();
    }
}

if (! function_exists('generateClosingReferenceNo')) {
    /**
     * Generate the next unique closing reference number (e.g. "C-00042").
     * Thin wrapper over ClosingService so it can be used from anywhere.
     */
    function generateClosingReferenceNo(): string
    {
        return app(ClosingService::class)->generateReferenceNo();
    }
}

if (! function_exists('generatePropertyReferenceNumber')) {
    /**
     * Generate the next unique property reference number for the given listing
     * data (e.g. "AGTDEV-S-000042"). Thin wrapper over PropertyService.
     */
    function generatePropertyReferenceNumber(array $data): string
    {
        return app(PropertyService::class)->generateReferenceNumber($data);
    }
}

if (! function_exists('formatGreetingSignatureBlock')) {
    /**
     * If a greeting template contains {Agent_Photo} along with other agent details
     * (e.g. {Agent_Name}, {Agent_Email}, {Agent_Phone}, {Agent_BRN}), cluster them
     * into a single {Agent_Signature} token so they render side-by-side (photo on left,
     * details on right) rather than stacked vertically across separate lines.
     */
    function formatGreetingSignatureBlock(?string $html): string
    {
        if (empty($html) || strpos($html, '{Agent_Photo}') === false) {
            return (string) $html;
        }

        $tagPattern = '(?:<br\s*\/?>|<\/?p>|<\/?strong>|<\/?span[^>]*>|\s)*(?:\{Agent_Name\}|\{Agent_Photo\}|\{Agent_Email\}|\{Agent_Phone\}|\{Agent_BRN\})(?:<br\s*\/?>|<\/?p>|<\/?strong>|<\/?span[^>]*>|\s)*';
        $pattern = '/((?:' . $tagPattern . '){2,})/i';

        $transformed = preg_replace_callback($pattern, function ($matches) {
            if (strpos($matches[1], '{Agent_Photo}') !== false) {
                return '</p>{Agent_Signature}<p>';
            }
            return $matches[1];
        }, $html);

        $transformed = preg_replace('/<p>\s*(?:<br\s*\/?>)?\s*<\/p>/i', '', $transformed);
        $transformed = preg_replace('/<p>\s*$/i', '', $transformed);

        return $transformed;
    }
}
