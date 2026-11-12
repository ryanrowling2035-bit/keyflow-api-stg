<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'salutation',
        'contactType',
        'contactCategory',
        'phone',
        'alternatePhone',
        'otherPhone',
        'email',
        'source',
        'contactSource',
        'dob',
        'address',
        'notes',
        'contactProfile',
        'nationality',
        'residencyStatus',
        'createdBy',
        'contactOwner',
        'leadSourceDetails',
        'bayutWhatsappInitiated',
        'tenantWhatsappInitiated',
        'offPlanWhatsappInitiated',
        'lastContacted',
        'routingSource',
        'outcome',
        'qualificationContact',
        'leadRating',
        'whatsappLead',
        'referralSource',
        'referralCompany',
        'referralPercentDue',
        'referralContact',
        'referralFeePaidThru',
        'country',
        'state',
        'city',
        'zipCode',
        'otherAddress',
        'otherCountry',
        'otherState',
        'otherCity',
        'otherZipCode',
        'company',
        'pastClient',
        'title',
        'communityPreference',
        'companyName',
        'mergedIntoId',
        'mergedBy',
        'mergedAt',
        'revertedBy',
        'revertedAt',
    ];

    protected $casts = [
        'bayutWhatsappInitiated' => 'boolean',
        'tenantWhatsappInitiated' => 'boolean',
        'offPlanWhatsappInitiated' => 'boolean',
        'whatsappLead' => 'boolean',
        'pastClient' => 'boolean',
        'communityPreference' => 'array',
        'contactType' => 'array',
        'lastContacted' => 'date',
        'mergedAt' => 'datetime',
        'revertedAt' => 'datetime',
    ];

    /**
     * Keep phoneKey in step with phone on every write path — the model event
     * covers the services, the controllers and the importers alike, so no
     * callsite has to remember it.
     */
    protected static function booted(): void
    {
        static::saving(function (self $contact) {
            if ($contact->isDirty('phone')) {
                $contact->phoneKey = self::phoneKeyFor($contact->phone);
            }
        });
    }

    /**
     * Normalise a contactType value (scalar, comma-separated string, or array)
     * into a clean, de-duplicated array of type keys.
     */
    public static function normalizeContactTypes($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $types = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map(
            fn ($t) => trim((string) $t),
            $types
        ), fn ($t) => $t !== '')));
    }

    /**
     * Merge one or more contact types into this contact's existing types without
     * removing any (a contact can be buyer + seller + agent, etc.). Persists only
     * when something new is actually added.
     */
    public function addContactTypes($newTypes): void
    {
        $existing = self::normalizeContactTypes($this->contactType);
        $merged = array_values(array_unique(array_merge(
            $existing,
            self::normalizeContactTypes($newTypes)
        )));

        if ($merged !== $existing) {
            $this->contactType = $merged;
            $this->save();
        }
    }

    /**
     * THE phone normaliser. Every phone comparison in the CRM goes through this —
     * matching, duplicate detection, lead intake, CSV import. Returns the number's
     * canonical comparable form: E.164 digits with the country code kept and all
     * punctuation dropped.
     *
     *   "+971 50 123 4567"  "00971501234567"  "0501234567"  ->  "971501234567"
     *
     * The country code is part of the identity. "+20 1000000000" and
     * "+971 000000000" are different numbers in different countries and must never
     * compare equal, however similar their trailing digits look.
     *
     * Returns null when the value cannot be a phone number at all — a placeholder
     * like "0" or "-", or a run of identical/sequential digits ("000000000",
     * "123456789"). Those normalise cleanly but carry no information, so letting
     * them through would collide every junk record with every other.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $canonical = normalizePhoneE164($phone);

        // normalizePhoneE164() returns the input untouched (no leading +) when it
        // cannot place the number. A single 0 in front of a full international
        // number is the usual reason — "02348033211334" is "+2348033211334" with a
        // 0 typed where a + or 00 belonged. Retry once without it rather than
        // throwing the number away; a real local form like "0501234567" never gets
        // here, because that one normalises cleanly on the first pass.
        if ($canonical && ! str_starts_with($canonical, '+') && str_starts_with(ltrim($canonical), '0')) {
            $canonical = normalizePhoneE164(ltrim(ltrim($canonical), '0'));
        }

        if (! $canonical || ! str_starts_with($canonical, '+')) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $canonical);

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return self::isPlaceholderNumber($digits) ? null : $digits;
    }

    /**
     * A number made of one repeated digit ("00000000000") or a straight run
     * ("123456789", "876543210") is a placeholder somebody typed to get past a
     * required field, not a phone number.
     */
    private static function isPlaceholderNumber(string $digits): bool
    {
        $subscriber = substr($digits, -9);

        if (preg_match('/^(\d)\1+$/', $subscriber)) {
            return true;
        }

        for ($i = 1; $i < strlen($subscriber); $i++) {
            $step = (int) $subscriber[$i] - (int) $subscriber[$i - 1];

            if ($step !== 1 && $step !== -1) {
                return false;
            }
        }

        return true;
    }

    /**
     * The indexed lookup key: the last 9 digits of the normalised number. This is
     * only a *candidate* finder — it deliberately ignores the country code so one
     * cheap index probe gathers everything worth comparing, and isSamePerson()
     * then decides. Nine is the length of a national number here.
     */
    public static function phoneKeyFor(?string $phone): ?string
    {
        $digits = self::normalizePhone($phone);

        return $digits === null ? null : substr($digits, -9);
    }

    /**
     * Whether two contacts are the same person, given their numbers share a
     * phoneKey. The rule, in one place:
     *
     *   - the full normalised numbers are equal (country code and all), OR
     *   - the numbers differ only beyond the last 9 digits AND the names match.
     *
     * The second clause exists for mistyped country codes — "+974 66631814" and
     * "+971 466631814" are one "Aamir Dawawala" — and is safe only because it
     * demands the name agree. Without it a UAE number and a UK number that happen
     * to end alike would read as the same person.
     */
    public static function isSamePerson(self $a, self $b): bool
    {
        $pa = self::normalizePhone($a->phone);
        $pb = self::normalizePhone($b->phone);

        if ($pa === null || $pb === null) {
            return false;
        }

        if ($pa === $pb) {
            return true;
        }

        $na = self::nameKeyFor($a->name);

        return $na !== '' && $na === self::nameKeyFor($b->name);
    }

    /**
     * Normalised name for duplicate comparison: lowercased, trimmed, and internal
     * whitespace collapsed, so "John  Smith", "john smith" and " John Smith " are
     * one name.
     *
     * Returns '' for a nameless contact. Callers must treat '' as "no name to
     * compare" and never as a match, or every unnamed record would collide.
     */
    public static function nameKeyFor(?string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $name)));
    }

    /**
     * Narrow to contacts whose number ends in the same 9 digits. An indexed key
     * lookup rather than the table scan this used to be.
     *
     * This is a CANDIDATE finder, not a match: it ignores the country code, so
     * "+20 1000000000" and "+971 000000000" both land here. Confirm with
     * isSamePerson() or a full normalizePhone() comparison before treating a
     * result as the same person — resolveMasterByPhone() does exactly that.
     *
     * Oldest match wins, so lookups stay stable now that duplicates are legal.
     * Soft-deleted rows are excluded by the model's global scope, so a contact
     * merged away is never returned here.
     */
    public function scopeMatchingPhone($query, ?string $phone)
    {
        $key = self::phoneKeyFor($phone);

        if ($key === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('phoneKey', $key)->orderBy('id');
    }

    public function scopeAccessibleByAgent($query, $agentId)
    {
        return $query->where(function ($q) use ($agentId) {
            $q->where('createdBy', $agentId)
                ->orWhereHas('ownedListings', fn ($sub) => $sub->where('agentId', $agentId))
                ->orWhereHas('enquiries', fn ($sub) => $sub->where('agentId', $agentId));
        });
    }

    /**
     * Scope to exclude contacts who have opted out of automated emails or all emails.
     */
    public function scopeNotOptedOutOfAutomatedEmail($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('communityPreference')
                ->orWhere(function ($sub) {
                    $sub->whereJsonDoesntContain('communityPreference', 'automatedEmailOptOut')
                        ->whereJsonDoesntContain('communityPreference', 'emailOptOut');
                });
        });
    }

    /**
     * Check if this contact has opted out of automated emails or all emails.
     */
    public function hasOptedOutOfAutomatedEmail(): bool
    {
        $prefs = $this->communityPreference;
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true);
        }

        if (empty($prefs) || ! is_array($prefs)) {
            return false;
        }

        return in_array('automatedEmailOptOut', $prefs, true)
            || in_array('emailOptOut', $prefs, true);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy');
    }

    public function contactOwnerUser()
    {
        return $this->belongsTo(User::class, 'contactOwner');
    }

    /** The surviving contact this one is an alias of, if it was merged away. */
    public function mergedInto()
    {
        return $this->belongsTo(self::class, 'mergedIntoId');
    }

    /**
     * Contacts merged into this one and not since unmerged — this contact's aliases.
     * They are ordinary live rows (merging soft-deletes nothing), just hidden from
     * the listings by scopeNotMerged().
     */
    public function mergedContacts()
    {
        return $this->hasMany(self::class, 'mergedIntoId');
    }

    public function mergedByUser()
    {
        return $this->belongsTo(User::class, 'mergedBy');
    }

    public function revertedByUser()
    {
        return $this->belongsTo(User::class, 'revertedBy');
    }

    /**
     * Merged away. revertMerge() clears mergedIntoId, so its presence alone is the
     * state — revertedAt is an audit stamp, not part of the condition.
     */
    public function isMergedAway(): bool
    {
        return $this->mergedIntoId !== null;
    }

    /**
     * Hide contacts that have been merged away. Belongs on every surface where a
     * contact is browsed, searched, picked or counted — the listing, the select
     * lookup, global search, the dashboard tile.
     *
     * Deliberately NOT a global scope: a merged contact keeps its enquiries,
     * listings, tasks and notes, and the reports read its nationality by id. A
     * global scope would hide it from those relation loads too and silently drop
     * rows out of lists and reports that have nothing to do with merging.
     */
    public function scopeNotMerged($query)
    {
        return $query->whereNull('mergedIntoId');
    }

    /** The reverse: only the aliases, for the merge-review screen. */
    public function scopeMergedAway($query)
    {
        return $query->whereNotNull('mergedIntoId');
    }

    /**
     * This contact's own id plus every alias merged into it. The rollup key: a
     * master's enquiries, listings, offers, closings and notes are those of the
     * whole family, since merging never moved them off the alias.
     *
     * Returns just [id] for a contact with no aliases, so callers can use it
     * unconditionally.
     */
    public function aliasIds(): array
    {
        return [
            $this->id,
            ...self::query()->where('mergedIntoId', $this->id)->pluck('id')->all(),
        ];
    }

    /**
     * Whether another live contact still shares this number.
     *
     * False once this contact has been merged away — the duplicate question is
     * settled at that point, and mergedIntoId says where it went. An EXISTS check
     * on the indexed phoneKey, so it costs one cheap query.
     */
    public function hasPotentialDuplicate(): bool
    {
        return self::flagDuplicates(new Collection([$this]))[$this->id] ?? false;
    }

    /**
     * Resolve the duplicate flag for a whole page of contacts in one query.
     *
     * phoneKey gathers the candidates off its index, then isSamePerson() decides
     * each pair — the country code and name comparisons live in PHP rather than
     * SQL, which is what lets the rule change without a schema change. A duplicate
     * group is two or three rows, so the candidate set stays tiny however large
     * the page is.
     *
     * @param  Collection<int, self>  $contacts
     * @return array<int, bool> contact id => is a duplicate
     */
    public static function flagDuplicates(Collection $contacts): array
    {
        $flags = [];
        $keys = [];

        foreach ($contacts as $contact) {
            $flags[$contact->id] = false;

            // A contact merged away has had its duplicate question settled already.
            if (! $contact->isMergedAway() && $contact->phoneKey !== null) {
                $keys[$contact->phoneKey] = true;
            }
        }

        if ($keys === []) {
            return $flags;
        }

        $candidates = self::query()
            ->notMerged()
            ->whereIn('phoneKey', array_keys($keys))
            ->get(['id', 'name', 'phone', 'phoneKey', 'mergedIntoId'])
            ->groupBy('phoneKey');

        foreach ($contacts as $contact) {
            if ($contact->isMergedAway() || $contact->phoneKey === null) {
                continue;
            }

            foreach ($candidates->get($contact->phoneKey, []) as $other) {
                if ($other->id !== $contact->id && self::isSamePerson($contact, $other)) {
                    $flags[$contact->id] = true;
                    break;
                }
            }
        }

        return $flags;
    }

    /**
     * The contact to display for a record that references this one. A merged-away
     * alias renders as its master everywhere: the enquiry's contact, the listing's
     * physical owner, the offer's and closing's buyer, and the CSV export of all
     * four. Merging moves nothing, so the stored foreign key still points at the
     * alias — this is what makes one person show as one identity regardless.
     *
     * Returns $this unchanged for an ordinary contact, so callers can use it
     * unconditionally.
     */
    public function masterOrSelf(): self
    {
        // A column-restricted eager load ("contact:id,name,phone") never selects the
        // merge columns, and assuming "not merged" there would quietly render the
        // alias — the exact bug this whole feature exists to prevent. Fetch the two
        // columns instead: one extra query beats being silently wrong. Select
        // mergedIntoId in such a load to skip it.
        if (! array_key_exists('mergedIntoId', $this->attributes)) {
            $state = static::query()->whereKey($this->getKey())->first(['id', 'mergedIntoId']);

            if (! $state) {
                return $this;
            }

            $this->setAttribute('mergedIntoId', $state->mergedIntoId);
        }

        if (! $this->isMergedAway()) {
            return $this;
        }

        return $this->mergedInto ?? $this;
    }

    /**
     * Resolve a phone number to the contact that should own it — following the
     * merge to the master when the number matches an alias. Lead intake runs
     * through here so a lead arriving on a merged-away number lands on the
     * surviving record, which is the whole point of having merged it.
     *
     * Only one hop: a master is never itself merged away (the merge guard
     * forbids it), so there is no chain to walk.
     */
    public static function resolveMasterByPhone(?string $phone): ?self
    {
        $digits = self::normalizePhone($phone);

        if ($digits === null) {
            return null;
        }

        // matchingPhone() only narrows by the last 9 digits, so confirm the whole
        // number before attaching a lead. Without this a UK lead would land on a
        // UAE contact whose number happens to end the same way. Oldest match wins
        // (the scope orders by id), so this stays stable as duplicates accumulate.
        $contact = self::matchingPhone($phone)
            ->get()
            ->first(fn (self $c) => self::normalizePhone($c->phone) === $digits);

        if ($contact && $contact->isMergedAway()) {
            return $contact->mergedInto ?? $contact;
        }

        return $contact;
    }

    public function enquiries()
    {
        return $this->hasMany(Enquiry::class, 'contactId');
    }

    public function latestEnquiry()
    {
        return $this->hasOne(Enquiry::class, 'contactId')->latestOfMany();
    }

    public function listings()
    {
        return $this->hasMany(Property::class, 'createdBy');
    }

    public function ownedListings()
    {
        return $this->hasMany(Property::class, 'physicalOwnerId');
    }

    public function offers()
    {
        return $this->hasMany(Offer::class, 'buyerId');
    }

    public function closings()
    {
        return $this->hasMany(Closing::class, 'buyerId');
    }
}
