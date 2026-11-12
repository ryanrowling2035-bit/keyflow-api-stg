<?php

namespace App\Models;

use App\Constants\Constants;
use App\Jobs\GenerateGenericFeedJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'preview_token',
        'description',
        'referenceNumber',
        'category',
        'listingType',
        'sizeArea',
        'plotSize',
        'bedrooms',
        'bathrooms',
        'completionStatus',
        'price',
        'reraPermitNo',
        'govtQR',
        'agentId',
        'listingOwnerId',
        'physicalOwnerId',
        'pfPropertyType',
        'bayutPropertyType',
        'pfCity',
        'pfCommunity',
        'pfSubCommunity',
        'pfBuilding',
        'pfLatitude',
        'pfLongitude',
        'pfAmenities',
        'bayutLocation',
        'bayutLocationId',
        'bayutAmenities',
        'pfPriceFrequency',
        'pfCheques',
        'bayutOccupancyStatus',
        'bayutMinimumContractPeriod',
        'bayutVacatingNoticePeriod',
        'bayutMaintenanceFee',
        'bayutMaintenanceFeePaidBy',
        'createdBy',
        'status',
        'approvalStatus',
        'latestApprovalComment',
        'approvedBy',
        'publishedBy',
        'permitRequestedAt',
        'approvedAt',
        'publishedAt',
        'managedType',
        'annualRent',
        'salePrice',
        'titleDeedNumber',
        'access',
        'listingLeadSource2',
        'view',
        'developmentText',
        'projectText',
        'deposit',
        'sold',
        'agencyFee',
        'agencyFeePercent',
        'ownershipTerm',
        'yearBuilt',
        'parkingSpaces',
        'pubDate',
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'pfAmenities' => 'array',
        'bayutAmenities' => 'array',
        'sold' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'upcoming',
    ];

    protected static function boot()
    {
        parent::boot();

        // Keep the pre-built generic XML feed fresh in near real time: whenever a
        // feed-eligible listing is created, updated or deleted, rebuild the feed
        // file on the queue instead of waiting for the 10-minute scheduled run.
        static::saved(function ($property) {
            if ($property->wasRecentlyCreated || $property->wasChanged('status')) {
                self::syncStatusDateMeta($property);
            }

            self::refreshGenericFeedIfRelevant($property);
        });

        static::deleted(function ($property) {
            self::refreshGenericFeedIfRelevant($property);
        });
    }

    /**
     * Stamp the date a listing entered Pocket / Upcoming status and drop the stamp the
     * moment it leaves, so app:send-listing-aged-reminder ages off a date that is
     * guaranteed to belong to the status the listing is still sitting in.
     *
     * Runs after save rather than on creating/updating because the meta row needs the
     * property id. Salesforce imports bypass model events — ListingImporter writes the
     * same two keys through its own meta upsert.
     */
    private static function syncStatusDateMeta($property): void
    {
        $statusForKey = [
            Constants::PROPERTY_META_KEYS['pocketListingDate'] => Constants::PROPERTY_STATUS_POCKET_LISTING,
            Constants::PROPERTY_META_KEYS['upcomingDate'] => Constants::PROPERTY_STATUS_UPCOMING,
        ];

        foreach ($statusForKey as $metaKey => $status) {
            if ($property->status === $status) {
                PropertyMeta::updateOrCreate(
                    ['propertyId' => $property->id, 'metaKey' => $metaKey],
                    ['metaValue' => now()->toDateString()],
                );
            } else {
                PropertyMeta::where('propertyId', $property->id)
                    ->where('metaKey', $metaKey)
                    ->delete();
            }
        }
    }

    /**
     * Rebuild the generic XML portal feed (GET /api/generic-xml) when a listing
     * that belongs in it changes. The feed only contains FOR SALE / FOR RENT
     * listings in a feed-visible status (published, pocket listing, live on
     * website) with the publishOnGeneric flag on — see genericFeedQuery().
     *
     * We check BOTH the current and previous status/listingType so a listing
     * entering the feed AND one leaving it (e.g. unpublished, or switched to
     * off-plan) both trigger a refresh. GenerateGenericFeedJob is ShouldBeUnique,
     * so a burst of saves collapses into a single background rebuild, and the
     * file swap is atomic, so readers always get a complete feed.
     */
    protected static function refreshGenericFeedIfRelevant(self $property): void
    {
        $feedStatuses = [
            Constants::PROPERTY_STATUS_PUBLISHED,
            Constants::PROPERTY_STATUS_POCKET_LISTING,
            Constants::PROPERTY_STATUS_LIVE_ON_WEBSITE,
        ];
        $feedTypes = [
            Constants::PROPERTY_PURPOSE_SALE,
            Constants::PROPERTY_PURPOSE_RENT,
        ];

        $statusRelevant = in_array($property->status, $feedStatuses, true)
            || in_array($property->getOriginal('status'), $feedStatuses, true);
        $typeRelevant = in_array($property->listingType, $feedTypes, true)
            || in_array($property->getOriginal('listingType'), $feedTypes, true);

        if ($statusRelevant && $typeRelevant) {
            GenerateGenericFeedJob::dispatch()->afterCommit();
        }
    }

    public function city()
    {
        return $this->belongsTo(PropertyLocation::class, 'pfCity', 'locationId');
    }

    public function community()
    {
        return $this->belongsTo(PropertyCommunity::class, 'pfCommunity', 'communityId');
    }

    public function subCommunity()
    {
        return $this->belongsTo(PropertySubCommunity::class, 'pfSubCommunity', 'propertySubCommunityId');
    }

    public function buildingTower()
    {
        return $this->belongsTo(PropertyTower::class, 'pfBuilding', 'propertyTowerId');
    }

    public function propertyCategories()
    {
        return $this->belongsTo(PropertyCategory::class, 'propertyCategoryId');
    }

    public function follows()
    {
        return $this->hasMany(Follow::class, 'moduleId')->where('module', Constants::MODULE_PROPERTY);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'moduleId')->where('module', Constants::MODULE_PROPERTY);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'propertyId');
    }

    public function propertyListingHistory()
    {
        return $this->hasMany(PropertyListingHistory::class, 'propertyId', 'id')->orderBy('created_at', 'desc');
    }

    public function approvalHistory()
    {
        return $this->hasMany(PropertyApprovalHistory::class, 'listingId', 'id')->orderBy('created_at', 'desc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy', 'id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agentId', 'id');
    }

    public function listingOwner()
    {
        return $this->belongsTo(User::class, 'listingOwnerId', 'id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approvedBy', 'id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publishedBy', 'id');
    }

    public function physicalOwner()
    {
        return $this->belongsTo(Contact::class, 'physicalOwnerId', 'id');
    }

    public function acceptedOffers()
    {
        return $this->hasMany(Offer::class, 'listingId')->where('status', Constants::OFFER_STATUS_ACCEPTED);
    }

    public function linkedEnquiries()
    {
        return $this->hasMany(EnquiryLinkedListing::class, 'propertyId');
    }

    public function enquiries()
    {
        return $this->belongsToMany(Enquiry::class, 'enquiry_linked_listings', 'propertyId', 'enquiryId')
            ->withTimestamps();
    }

    public function meta()
    {
        return $this->hasMany(PropertyMeta::class, 'propertyId', 'id');
    }

    public function internalNotes()
    {
        return $this->hasMany(InternalNote::class, 'moduleId', 'id')
            ->where('module', Constants::MODULE_PROPERTY)
            ->with('creator');
    }

    public function salesforceIdMap()
    {
        return $this->hasOne(SalesforceIdMap::class, 'moduleId', 'id')
            ->where('module', Constants::MODULE_PROPERTY);
    }

    public function media()
    {
        return $this->hasMany(PropertyMediaFile::class, 'propertyId', 'id');
    }

    /**
     * Documents attached to this listing in the Document Center (module = property). This is where
     * the Salesforce import lands listing files; the website feed surfaces them as media.documents.
     */
    public function documentCenters()
    {
        return $this->hasMany(DocumentCenter::class, 'moduleId', 'id')
            ->where('module', Constants::MODULE_PROPERTY);
    }

    public function mediaThumbnails()
    {
        return $this->hasMany(PropertyMediaFile::class, 'propertyId', 'id')->where('mediaType', 'uploadPhotos')->orderBy('ordering', 'asc');
    }

    /**
     * The single representative photo (lowest ordering) for listing cards — one row per
     * property, so the api/properties list returns one media item instead of the whole gallery.
     */
    public function primaryMediaThumbnail()
    {
        return $this->hasOne(PropertyMediaFile::class, 'propertyId', 'id')->ofMany(
            ['ordering' => 'min'],
            fn ($query) => $query->where('mediaType', 'uploadPhotos'),
        );
    }

    public function mediaCompressed()
    {
        return $this->hasMany(PropertyMediaFile::class, 'propertyId', 'id')->orderBy('ordering', 'asc');
    }

    public function videos()
    {
        // Default video first (isDefault=1 before 0/NULL), then stable by insertion order.
        return $this->hasMany(PropertyVideos::class, 'propertyId')
            ->orderByDesc('isDefault')->orderBy('id');
    }

    public function communities()
    {
        return $this->hasMany(PropertyCommunity::class, 'cityId', 'id');
    }

    public function propertyVideos()
    {
        // Default video first (isDefault=1 before 0/NULL), then stable by insertion order.
        return $this->hasMany(PropertyVideos::class, 'propertyId', 'id')
            ->orderByDesc('isDefault')->orderBy('id');
    }
}
