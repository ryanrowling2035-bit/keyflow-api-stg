<?php

namespace App\Http\Controllers\Api;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Models\GlobalSetting;
use App\Models\MarketingAsset;
use App\Services\LeadSourceService;
use App\Services\ModuleFieldService;
use App\Traits\ApiResponse;
use App\Traits\WordFormatter;

class GlobalConstantController extends Controller
{
    use ApiResponse, WordFormatter;

    public function __construct(
        protected ModuleFieldService $moduleFieldService,
        protected LeadSourceService $leadSourceService
    ) {}

    public function getGlobalConstant()
    {
        try {
            $user = auth('api')->user();
            $permissions = [];

            if ($user && $user->roleId && $user->role) {
                $permissions = $user->role->permissions
                    ->groupBy('module')
                    ->map(function ($modulePermissions, $module) {
                        $result = ['module' => $module];

                        foreach (['view', 'create', 'edit', 'delete', 'approve'] as $action) {
                            if ($modulePermissions->contains('action', $action)) {
                                $result[$action] = true;
                            }
                        }

                        return $result;
                    })
                    ->values()
                    ->toArray();
            }

            $moduleFields = $this->moduleFieldService->getAllModuleFields();

            $marketingAssetCategoryCounts = MarketingAsset::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category');

            $marketingAssetCategory = collect(Constants::MARKETING_ASSET_CATEGORY)->map(fn ($label, $key) => [
                'value' => $key,
                'label' => $label,
                'count' => (int) ($marketingAssetCategoryCounts[$key] ?? 0),
            ])->values()->toArray();

            $constants = [

                // User
                'crmUserRoles' => Constants::CRM_USER_ROLES,
                'agentType' => $this->toValueLabelArray(Constants::AGENT_TYPE),
                'userStatus' => $this->toValueLabelArray(Constants::USER_STATUS),
                'userResidencyStatus' => $this->toValueLabelArray(Constants::USER_RESIDENCY_STATUS),
                'userMetaKeys' => Constants::USER_META_KEYS,

                // Property
                'listingType' => $this->toValueLabelArray(Constants::PROPERTY_PURPOSE_TYPE),
                'propertyClassificationType' => $this->toValueLabelArray(Constants::PROPERTY_CLASSIFICATION_TYPE),
                'propertyStatus' => $this->toValueLabelArray(Constants::PROPERTY_STATUS),
                'propertyIntOffStatus' => $this->toValueLabelArray(Constants::PROPERTY_INT_AND_OFF_STATUS),
                'completionStatus' => $this->toValueLabelArray(Constants::COMPLETION_STATUS),
                'propertyFinishingType' => $this->toValueLabelArray(Constants::PROPERTY_FINISHING_TYPE),
                'propertyFurnishingType' => $this->toValueLabelArray(Constants::PROPERTY_FURNISHING_TYPE),
                'propertyMediaCategories' => $this->toValueLabelArray(Constants::PROPERTY_MEDIA_CATEGORIES),
                'propertyApprovalStatus' => $this->toValueLabelArray(Constants::PROPERTY_APPROVAL_STATUS),
                'propertyManagedType' => $this->toValueLabelArray(Constants::PROPERTY_MANAGED_TYPE),
                'propertyMetaKeys' => Constants::PROPERTY_META_KEYS,
                'propertyMediaKeys' => Constants::PROPERTY_MEDIA_KEYS,
                'documentTags' => collect(Constants::DOCUMENT_TAGS)->map(fn ($tags) => $this->toValueLabelArray($tags))->toArray(),
                // Image/video tags are stored verbatim (value === the exact label), so value == label.
                'imageTags' => collect(Constants::IMAGE_TAGS)->map(fn ($tag) => ['value' => $tag, 'label' => $tag])->values()->toArray(),
                'videoTags' => collect(Constants::VIDEO_TAGS)->map(fn ($tag) => ['value' => $tag, 'label' => $tag])->values()->toArray(),
                'documentTypes' => collect(Constants::DOCUMENT_TYPES)->map(fn ($types) => $this->toValueLabelArray($types))->toArray(),
                'propertyPdfTemplate' => $this->toValueLabelArray(Constants::PDF_TEMPLATES),
                'socialImagePlatforms' => collect(Constants::SOCIAL_IMAGE_PLATFORMS)->map(fn ($config, $key) => [
                    'value' => $key,
                    'label' => $config['label'],
                    'width' => $config['width'],
                    'height' => $config['height'],
                ])->values()->toArray(),
                'socialImageTitles' => $this->toValueLabelArray(Constants::SOCIAL_IMAGE_TITLE),

                // Property - Unified
                'propertyTypes' => collect(Constants::PROPERTY_TYPE_MAPPING)->map(fn ($row, $key) => [
                    'value' => $key,
                    'label' => $row['label'],
                    'category' => $row['category'],
                ])->values()->toArray(),

                // Property - PF
                'listingDocumentTypes' => $this->toValueLabelArray(Constants::LISTING_DOCUMENT_TYPE),
                'pfAmenities' => Constants::PF_AMENITIES,
                'pfPriceFrequency' => $this->toValueLabelArray(Constants::PROPERTY_PRICE_FREQUENCIES),
                'pfNumberOfCheques' => $this->toValueLabelArray(Constants::PF_NUMBER_OF_CHEQUES),

                // Property - Bayut
                'bayutAmenities' => $this->toValueLabelArray(Constants::PROPERTY_BAYUT_AMENITIES_KEYS),
                'bayutOccupancyStatus' => $this->toValueLabelArray(Constants::BAYUT_OCCUPANCY_STATUS),
                'bayutMaintenancePaidBy' => $this->toValueLabelArray(Constants::BAYUT_MAINTENANCE_PAID_BY),

                // Enquiry
                'enquiryType' => $this->toValueLabelArray(Constants::ENQUIRY_TYPE),
                'enquiryAllStatus' => $this->toValueLabelArray(Constants::ENQUIRY_ALL_STATUS),
                'enquirySaleStatus' => $this->toValueLabelArray(Constants::ENQUIRY_SALE_STATUS),
                'enquiryRentStatus' => $this->toValueLabelArray(Constants::ENQUIRY_RENT_STATUS),
                'enquiryActiveStatuses' => Constants::ENQUIRY_ACTIVE_STATUSES,
                'enquiryListingType' => $this->toValueLabelArray(Constants::ENQUIRY_LISTING_TYPE),
                'enquiryPropertyType' => $this->toValueLabelArray(Constants::ENQUIRY_PROPERTY_TYPE),
                'leadSource' => $this->toValueLabelArray(Constants::leadSourceOptions()),
                // 'leadSource' => $this->leadSourceService->select(),
                'technicalSource' => $this->toValueLabelArray(Constants::TECHNICAL_SOURCE),

                // Contact
                'contactType' => $this->toValueLabelArray(Constants::CONTACT_TYPE),
                'contactCategory' => $this->toValueLabelArray(Constants::CONTACT_CATEGORIES),
                'contactCommunityPreference' => $this->toValueLabelArray(Constants::CONTACT_COMMUNITY_PREFERENCE),
                'contactNoteType' => $this->toValueLabelArray(Constants::CONTACT_NOTE_TYPE),
                'contactNoteCallStatus' => $this->toValueLabelArray(Constants::CONTACT_NOTE_CALL_STATUS),

                // Task
                'activityType' => $this->toValueLabelArray(Constants::ACTIVITY_TYPES),
                'taskPriority' => $this->toValueLabelArray(Constants::TASK_PRIORITY),
                'taskStatus' => $this->toValueLabelArray(Constants::TASK_STATUS),
                'taskType' => $this->toValueLabelArray(Constants::TASK_TYPE),

                // Offer & Closing
                'offerStatus' => $this->toValueLabelArray(Constants::OFFER_STATUS),
                'offerLetterTypes' => $this->toValueLabelArray(Constants::OFFER_LETTER_TYPES),
                'closeStatus' => $this->toValueLabelArray(Constants::CLOSE_STATUS),
                'closeListingType' => $this->toValueLabelArray(Constants::CLOSE_LISTING_TYPE),
                'closingChecklist' => $this->toValueLabelArray(Constants::CLOSING_CHECKLIST),

                // Calendar
                'calendarEventStatus' => $this->toValueLabelArray(Constants::CALENDAR_EVENT_STATUS),
                'calendarEventColors' => Constants::CALENDAR_EVENT_COLORS,
                'calendarModules' => $this->toValueLabelArray(Constants::CALENDAR_MODULES),

                // Role
                'roleStatus' => $this->toValueLabelArray(Constants::ROLE_STATUS),

                // Boost
                'boostStatus' => $this->toValueLabelArray(Constants::BOOST_STATUS),
                'boostType' => $this->toValueLabelArray(Constants::BOOST_TYPE),

                // Marketing Assets
                'marketingAssetStatus' => $this->toValueLabelArray(Constants::MARKETING_ASSET_STATUS),
                'marketingAssetCategory' => $marketingAssetCategory,

                // Modules
                'crmModules' => $this->toValueLabelArray(Constants::CRM_MODULES),

                // Notifications — the Type-filter categories for the notifications page.
                'notificationFilterTypes' => $this->toValueLabelArray(Constants::NOTIFICATION_FILTER_TYPES),

                // Auth — the admin-configured password policy plus the effective
                // minimum length (falls back to the default when unset).
                'passwordPolicy' => passwordPolicy(),
                'passwordMinLength' => passwordMinLength(),

                // Settings & Permissions
                'globalSettingKeys' => Constants::GLOBAL_SETTINGS,
                'globalSettings' => GlobalSetting::whereNotIn('settingKey', [
                    '_smtpHost', '_smtpPort', '_smtpUsername', '_smtpPassword', '_smtpEncryption', '_sendFromEmail', '_sendFromName',
                ])->pluck('settingValue', 'settingKey'),
                'permissions' => $permissions,
                'moduleFields' => $moduleFields,

                // Current User
                'currentUser' => $this->getCurrentUser($user),
            ];

            return $this->success($constants, 'Constants retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('GlobalConstantController : getGlobalConstant', $e);
        }
    }

    private function getCurrentUser($user): array
    {
        $photoPath = ! empty($user->thumbnailPath) ? $user->thumbnailPath : $user->profilePhoto;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profilePhoto' => getImageUrl($photoPath),
            'role' => $user->role?->name,
        ];
    }
}
