<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoostController;
// use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ClosingController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactNoteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentCenterController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\EnquiryLinkedListingController;
use App\Http\Controllers\Api\ExternalController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\GlobalConstantController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\GlobalSettingController;
use App\Http\Controllers\Api\InternalNoteController;
use App\Http\Controllers\Api\LeadSourceController;
use App\Http\Controllers\Api\MarketingAssetCategoryController;
use App\Http\Controllers\Api\MarketingAssetController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OutlookSyncController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PortalSettingController;
use App\Http\Controllers\Api\PropertiesController;
use App\Http\Controllers\Api\PropertyCategoryController;
use App\Http\Controllers\Api\PropertyEmailController;
use App\Http\Controllers\Api\PropertyLocationController;
use App\Http\Controllers\Api\PropertyTypeController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\ThirdPartyController;
use App\Http\Controllers\Api\UserActivityController;
use App\Http\Controllers\Api\GreetingTemplateController;

use App\Http\Controllers\Api\HrEmployeeController;
use App\Http\Controllers\Api\HrAttendanceController;
use App\Http\Controllers\Api\HrDashboardController;
use App\Http\Controllers\Api\LeaveSettingController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\ExpiryNotificationController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::get('verify-reset-token', [AuthController::class, 'verifyResetToken']);
Route::post('verify-reset-token', [AuthController::class, 'verifyResetToken']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

// Broadcasting authentication is registered via withBroadcasting() in bootstrap/app.php
// (route /api/broadcasting/auth, guarded by jwt.auth) and channels in routes/channels.php

Route::middleware('jwt.auth')->group(function () {
    Route::post('auth/impersonate', [AuthController::class, 'impersonate']);

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/leaderboard', [DashboardController::class, 'leaderboard']);
    Route::get('dashboard/agent-performance', [DashboardController::class, 'agentPerformance']);
    Route::get('dashboard/agent-monitoring', [DashboardController::class, 'agentMonitoring']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('agent-productivity', [ReportsController::class, 'agentProductivity']);
        Route::get('agent-productivity/export', [ReportsController::class, 'agentProductivityExport']);
        Route::get('company-performance', [ReportsController::class, 'companyPerformance']);
        Route::get('enquiry-conversion', [ReportsController::class, 'enquiryConversion']);
        Route::get('enquiry-conversion/export', [ReportsController::class, 'enquiryConversionExport']);
        Route::get('lead-source-performance', [ReportsController::class, 'leadSourcePerformance']);
        Route::get('lead-source-performance/export', [ReportsController::class, 'leadSourcePerformanceExport']);
        Route::get('listing-performance', [ReportsController::class, 'listingPerformance']);
        Route::get('listing-performance/export', [ReportsController::class, 'listingPerformanceExport']);
        Route::get('pipeline-health', [ReportsController::class, 'pipelineHealth']);
        Route::get('pipeline-health/export', [ReportsController::class, 'pipelineHealthExport']);
    });

    // Global Search
    Route::get('global/search', [GlobalSearchController::class, 'search']);

    // Third Party Integrations — shared
    Route::get('search-locations', [ThirdPartyController::class, 'searchThirdPartyLocations']);
    Route::get('bayut/leads-import', [ThirdPartyController::class, 'importBayutLeads']);
    Route::post('generate-property-description', [ThirdPartyController::class, 'generatePropertyDescription']);
    Route::post('/property/{propertyId}/portal-unpublish', [ThirdPartyController::class, 'unpublishFromPortals']);

    // Third Party Integrations — Bayut
    Route::post('property/publish/bayut', [ThirdPartyController::class, 'publishToBayut']);
    Route::post('property/publish-draft/bayut', [ThirdPartyController::class, 'publishDraftToBayut']);
    Route::post('property/update/bayut', [ThirdPartyController::class, 'updateOnBayut']);
    Route::post('property/delete/bayut', [ThirdPartyController::class, 'deleteFromBayut']);
    Route::get('property/{propertyId}/bayut', [ThirdPartyController::class, 'fetchBayutListingById']);

    // Third Party Integrations — Property Finder
    Route::get('search-pf-locations', [ThirdPartyController::class, 'getPFLocations']);
    Route::post('webhooks/subscribe/property-finder', [ThirdPartyController::class, 'subscribePropertyFinderWebhooks']);
    Route::get('webhooks/subscriptions/property-finder', [ThirdPartyController::class, 'getPropertyFinderWebhookSubscriptions']);
    Route::post('property/publish/property-finder', [ThirdPartyController::class, 'publishToPropertyFinder']);
    Route::post('property/publish-draft/property-finder', [ThirdPartyController::class, 'publishDraftToPropertyFinder']);
    Route::post('property/update/property-finder', [ThirdPartyController::class, 'updateOnPropertyFinder']);
    Route::post('property/delete/property-finder', [ThirdPartyController::class, 'deleteFromPropertyFinder']);
    Route::get('property/listings/property-finder', [ThirdPartyController::class, 'fetchPropertyFinderListings']);
    Route::get('property/{propertyId}/property-finder', [ThirdPartyController::class, 'fetchPropertyFinderListingById']);

    // Users Routes
    Route::get('users', [AuthController::class, 'users']);
    Route::get('hr/employees', [AuthController::class, 'getHrEmployees']);
    // Leave Settings Routes
    Route::get('hr/leave-settings', [LeaveSettingController::class, 'index']);
    Route::post('hr/leave-settings', [LeaveSettingController::class, 'store']);
    Route::put('hr/leave-settings/{id}', [LeaveSettingController::class, 'update']);
    Route::delete('hr/leave-settings/bulk-delete', [LeaveSettingController::class, 'bulkDelete']);
    Route::delete('hr/leave-settings/{id}', [LeaveSettingController::class, 'destroy']);

    Route::post('addUser', [AuthController::class, 'addUser']);
    Route::post('updateUser/{id}', [AuthController::class, 'updateUser']);
    Route::get('getUserById/{id}', [AuthController::class, 'getUserById']);
    Route::delete('deleteUser/{id}', [AuthController::class, 'deleteUser']);
    Route::put('updateUserStatus/{id}', [AuthController::class, 'updateUserStatus']);
    Route::get('users/select', [AuthController::class, 'selectUsers']);
    Route::get('users/getByType', [AuthController::class, 'getUsersByUserType']);
    Route::get('users/select/roleId/{id}', [AuthController::class, 'getUsersByRoleId']);
    Route::post('users/generate-access-tokens', [AuthController::class, 'generateAccessTokens']);
    Route::post('users/{id}/tokens', [AuthController::class, 'issueUserToken']);
    Route::get('users/{id}/tokens', [AuthController::class, 'listUserTokens']);
    Route::delete('users/{id}/tokens/{tokenId}', [AuthController::class, 'revokeUserToken']);

    // HR & Operations Routes
    Route::prefix('hr')->group(function () {
        // HR Leave Management Routes (Standard Keyflow CRM specification)
        Route::get('leave-types', [LeaveController::class, 'getLeaveTypes']);
        Route::post('leaves', [LeaveController::class, 'applyLeave']);
        Route::get('leaves', [LeaveController::class, 'getAll']);
        Route::get('leaves/my', [LeaveController::class, 'getMyLeaves']);
        Route::get('leaves/balance/{userId?}', [LeaveController::class, 'getLeaveBalance']);
        Route::get('leaves/{id}', [LeaveController::class, 'getLeaveById']);
        Route::match(['put', 'post'], 'leaves/{id}/approve', [LeaveController::class, 'approveLeave']);
        Route::match(['put', 'post'], 'leaves/{id}/reject', [LeaveController::class, 'rejectLeave']);
        Route::match(['put', 'post'], 'leaves/{id}/cancel', [LeaveController::class, 'cancelLeave']);

        // Expiry Notifications (Cron & Manual Triggers)
        Route::match(['get', 'post'], 'expiry-notifications/cron', [ExpiryNotificationController::class, 'runExpiryCron']);
        Route::match(['get', 'post'], 'expiry-notifications/trigger', [ExpiryNotificationController::class, 'runExpiryCron']);

        // Attendance Routes under /hr
        Route::get('attendance/today-status', [AttendanceController::class, 'getTodayStatus']);
        Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('attendance/daily', [AttendanceController::class, 'getDailyAttendance']);
        Route::post('attendance/manual-mark', [AttendanceController::class, 'markManualAttendance']);
        Route::get('attendance/monthly-report', [AttendanceController::class, 'getMonthlyReport']);

        // HR Dashboard & Birthday Calendar
        Route::get('dashboard', [HrDashboardController::class, 'index']);
        Route::get('dashboard/birthday-calendar', [HrDashboardController::class, 'birthdayCalendar']);

        // Employee Directory & Profile
        Route::get('employees', [AuthController::class, 'getHrEmployees']);
        Route::post('employees', [HrEmployeeController::class, 'store']);
        Route::get('employees/{id}', [HrEmployeeController::class, 'show']);
        Route::put('employees/{id}', [HrEmployeeController::class, 'update']);
        Route::delete('employees/{id}', [HrEmployeeController::class, 'destroy']);
        Route::put('employees/{id}/status', [HrEmployeeController::class, 'updateStatus']);
        Route::post('employees/{id}/documents', [HrEmployeeController::class, 'uploadDocument']);
        Route::delete('documents/{docId}', [HrEmployeeController::class, 'deleteDocument']);

        // Reports
        Route::get('reports/leave-balances', [HrEmployeeController::class, 'leaveBalanceReport']);
        Route::get('reports/document-expiry', [HrEmployeeController::class, 'documentExpiryReport']);

        // Leave Management (Fallback compatibility aliases)
        Route::get('leaves/types', [LeaveController::class, 'getLeaveTypes']);
        Route::get('leaves/my-leaves', [LeaveController::class, 'getMyLeaves']);

        // Leave Settings
        Route::get('leave-settings', [LeaveSettingController::class, 'index']);
        Route::post('leave-settings', [LeaveSettingController::class, 'store']);
        Route::put('leave-settings/{id}', [LeaveSettingController::class, 'update']);
        Route::delete('leave-settings/bulk-delete', [LeaveSettingController::class, 'bulkDelete']);
        Route::delete('leave-settings/{id}', [LeaveSettingController::class, 'destroy']);

        // Attendance Tracking (Legacy / Alternative)
        Route::post('attendance/mark', [HrAttendanceController::class, 'markSingle']);
        Route::post('attendance/mark-bulk', [HrAttendanceController::class, 'markBulk']);
        Route::get('attendance/daily-summary', [HrAttendanceController::class, 'dailySummary']);
        Route::get('attendance/report', [HrAttendanceController::class, 'report']);
    });

    // Departments Routes
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::get('departments/{id}', [DepartmentController::class, 'show']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::put('departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('departments/{id}', [DepartmentController::class, 'destroy']);
    Route::patch('departments/{id}/status', [DepartmentController::class, 'toggleStatus']);

    // getGlobalConstant Routes
    Route::get('getGlobalConstant', [GlobalConstantController::class, 'getGlobalConstant']);

    // Users Roles Routes
    Route::get('roles', [RoleController::class, 'roles']);
    Route::get('roles/select', [RoleController::class, 'selectRoles']);
    Route::post('addRole', [RoleController::class, 'addRole']);
    Route::post('updateRole/{id}', [RoleController::class, 'updateRole']);
    Route::get('getRoleById/{id}', [RoleController::class, 'getRoleById']);
    Route::delete('deleteRole', [RoleController::class, 'deleteRole']);

    // Users Roles Permissions Routes
    Route::get('permissions', [PermissionController::class, 'index']);

    // Properties Routes
    Route::get('properties/select', [PropertiesController::class, 'selectProperties']);
    Route::get('properties', [PropertiesController::class, 'properties']);
    Route::post('addProperty', [PropertiesController::class, 'addProperty']);
    Route::put('updateProperty/{id}', [PropertiesController::class, 'updateProperty']);
    Route::get('getPropertyById/{id}', [PropertiesController::class, 'getPropertyById']);
    Route::delete('deleteProperty', [PropertiesController::class, 'deleteProperty']);
    Route::post('/cloneListing/{id}', [PropertiesController::class, 'cloneListing']);
    Route::get('propertyHistory/{id}', [PropertiesController::class, 'getPropertyHistoryById']);
    Route::get('propertyApprovalHistory/{id}', [PropertiesController::class, 'getPropertyApprovalHistoryById']);
    Route::get('propertiesExportCsv', [PropertiesController::class, 'exportPropertiesCsv']);
    Route::post('send/email/property/{id}', [PropertiesController::class, 'sendEmailProperty']);
    Route::post('send/email/multiple/properties', [PropertyEmailController::class, 'sendEmailMultipleProperties']);
    Route::post('property/{id}/composeEmail', [PropertiesController::class, 'composeEmail']);
    Route::post('send/email/enquiryQuotationByProperty', [PropertiesController::class, 'sendEnquiryQuotationByProperty']);
    Route::post('uploadPropertyMediaFiles', [PropertiesController::class, 'uploadPropertyMediaFiles']);
    Route::put('property/media/{id}', [PropertiesController::class, 'updateUploadPropertyMediaFiles']);
    Route::post('updateMediaAppearance', [PropertiesController::class, 'updateMediaAppearance']);
    Route::post('property/{propertyId}/update/permitNumber', [PropertiesController::class, 'updatePermitDetails']);
    Route::get('getUploadPropertyMediaFiles/{id}', [PropertiesController::class, 'getUploadPropertyMediaFiles']);
    Route::delete('/deletePropertyMedia', [PropertiesController::class, 'deletePropertyMedia']);
    Route::delete('/deletePropertyVideo', [PropertiesController::class, 'deletePropertyVideo']);
    Route::get('/getAllPropertyMedia/{propertyId}', [PropertiesController::class, 'getPropertyMedia']);
    Route::get('/sendEmailByPropertyId/{propertyId}', [PropertiesController::class, 'sendEmailBySelectedId']);
    Route::get('/getPropertyByCreatedBy/{createdBy}', [PropertiesController::class, 'getPropertyByCreatedBy']);

    Route::get('enquiry/{enquiryId}/linkedProperties', [EnquiryLinkedListingController::class, 'getAllLinkedPropertiesByEnquiry']);
    // enquiry matching in Properties
    Route::get('enquiry/{enquiryId}/matchingProperties', [PropertiesController::class, 'getAllMatchingProperties']);

    // Property Listing Workflow Routes
    Route::post('/property/{propertyId}/request-permit', [PropertiesController::class, 'requestPermit']);
    Route::post('/property/{propertyId}/enter-permit', [PropertiesController::class, 'enterPermitNumber']);
    Route::post('/property/{propertyId}/request-approval', [PropertiesController::class, 'requestApproval']);
    Route::post('/property/{propertyId}/approve-reject', [PropertiesController::class, 'approveRejectProperty']);
    Route::post('/property/{propertyId}/publish', [PropertiesController::class, 'publishProperty']);
    Route::put('/property/{propertyId}/publish-flags', [PropertiesController::class, 'updatePublishFlags']);

    // Legacy permit routes (backward compatibility)
    Route::get('getPropertyByUserId/{id}', [PropertiesController::class, 'getPropertyByUserId']);

    Route::get('getPropertyTypes', [PropertyTypeController::class, 'getPropertyTypes']);

    // property Linked Listings Routes
    Route::post('propertyLinkEnquiry', [EnquiryLinkedListingController::class, 'linkEnquiryToProperty']);
    Route::delete('propertyUnlinkEnquiry', [EnquiryLinkedListingController::class, 'unlinkEnquiryFromProperty']);
    Route::get('properties/{propertyId}/linkedEnquiries', [EnquiryLinkedListingController::class, 'getAllLinkedEnquiriesByProperty']);
    // properties matching in Enquiry
    Route::get('properties/{propertyId}/matchingEnquiries', [EnquiryController::class, 'getAllMatchingEnquiries']);
    Route::get('properties/{propertyId}/associatedEnquiries', [EnquiryController::class, 'associatedEnquiries']);

    // Properties generatePdf Routes
    Route::get('generatePdf/{id}', [PropertiesController::class, 'generatePdf']);

    // Property PDF Templates Routes
    Route::get('getPropertyPdfTemplates', [PropertiesController::class, 'getPdfTemplates']);

    // Social Image Routes
    Route::get('generateSocialImage/{id}', [PropertiesController::class, 'generateSocialImage']);

    // PropertyLocation Routes
    Route::get('propertyLocations/cities', [PropertyLocationController::class, 'getCities']);
    Route::get('propertyLocations/children', [PropertyLocationController::class, 'getChildLocations']);
    Route::post('addPropertyLocation', [PropertyLocationController::class, 'addPropertyLocation']);
    Route::put('updatePropertyLocation/{id}', [PropertyLocationController::class, 'updatePropertyLocation']);
    Route::get('getPropertyLocationById/{id}', [PropertyLocationController::class, 'getPropertyLocationById']);
    Route::delete('deletePropertyLocation/{id}', [PropertyLocationController::class, 'deletePropertyLocation']);

    Route::put('updateCommunityCode/{id}', [PropertyLocationController::class, 'updateCommunityCode']);
    Route::get('community/select/{cityId?}', [PropertyLocationController::class, 'selectCommunitiesByCity']);
    Route::get('sub-community/select/{communityId}', [PropertyLocationController::class, 'selectSubCommunitiesByCommunity']);
    Route::get('building/select/{subCommunityId}', [PropertyLocationController::class, 'selectBuildingsBySubCommunity']);
    Route::get('propertyCommunitiesCity/{cityId}', [PropertyLocationController::class, 'getCommunitiesByCity']);
    Route::put('updatePropertyCommunities/{id}', [PropertyLocationController::class, 'updatePropertyCommunity']);

    // PropertyCategory Routes
    Route::get('propertyCategory', [PropertyCategoryController::class, 'propertyCategories']);
    Route::post('addPropertyCategory', [PropertyCategoryController::class, 'addPropertyCategory']);
    Route::post('updatePropertyCategory/{id}', [PropertyCategoryController::class, 'updatePropertyCategory']);
    Route::get('getPropertyCategoryById/{id}', [PropertyCategoryController::class, 'getPropertyCategoryById']);
    Route::delete('deletePropertyCategory/{id}', [PropertyCategoryController::class, 'deletePropertyCategory']);

    // Task Routes
    Route::post('/tasks', [TaskController::class, 'addTask']);
    Route::get('/tasks/calendar-events', [TaskController::class, 'getCalendarEvents']);
    Route::get('/tasks', [TaskController::class, 'getTasks']);
    Route::get('/tasks/{id}', [TaskController::class, 'getTaskById']);
    Route::put('/tasks/{id}', [TaskController::class, 'updateTask']);
    Route::delete('/tasks/{id}', [TaskController::class, 'deleteTask']);

    // Contacts Routes
    Route::get('contacts/select', [ContactController::class, 'selectContacts']);
    Route::get('contacts', [ContactController::class, 'getAllContacts']);
    Route::post('addContact', [ContactController::class, 'addContact']);
    // Merge is non-destructive: the merged contacts become hidden aliases of the
    // master, keeping their own fields and records. Open to any authenticated
    // user — no role check, by design: merging changes no data and is reversible.
    // Declared above contacts/{id} — {id} has no numeric constraint, so it would
    // otherwise match "merge" and route the merge into update().
    Route::post('contacts/merge', [ContactController::class, 'mergeContacts']);
    Route::post('contacts/merge/revert', [ContactController::class, 'revertMergeContacts']);
    Route::get('/contacts/{id}', [ContactController::class, 'show']);
    Route::post('/contacts/{id}', [ContactController::class, 'update']);
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);
    Route::get('contacts/export/csv', [ContactController::class, 'exportContactCsv']);
    Route::post('/contacts/import/csv', [ContactController::class, 'importCsv']);
    Route::post('/contacts/{id}/send-email', [ContactController::class, 'sendEmail']);
    Route::get('/getContactByUserId/{id}', [ContactController::class, 'getContactByUserId']);
    Route::get('getLinkedEnquiriesByContactId/{id}', [ContactController::class, 'getLinkedEnquiriesByContactId']);
    Route::get('getListingsByOwnerId/{id}', [ContactController::class, 'getListingsByOwnerId']);
    Route::get('getOffersByContactId/{id}', [ContactController::class, 'getOffersByContactId']);
    Route::get('getClosingsByContactId/{id}', [ContactController::class, 'getClosingsByContactId']);

    Route::post('/contact/composeEmail', [ContactController::class, 'composeEmail']);
    Route::get('getFormatDataContacts', [ContactController::class, 'getFormatDataContacts']);

    // Contact Notes Routes
    Route::post('contact/note/add', [ContactNoteController::class, 'addNote']);
    Route::get('contact/note/{id}', [ContactNoteController::class, 'getNoteById']);
    Route::put('contact/note/{id}', [ContactNoteController::class, 'updateNote']);
    Route::delete('contact/note/{id}', [ContactNoteController::class, 'deleteNote']);
    Route::get('contact/notes/{contactId}', [ContactNoteController::class, 'getNotesByContact']);

    // Internal Notes Routes
    Route::post('internal/note/add', [InternalNoteController::class, 'addNote']);
    Route::get('internal/note/{id}', [InternalNoteController::class, 'getNoteById']);
    Route::put('internal/note/{id}', [InternalNoteController::class, 'updateNote']);
    Route::delete('internal/note/{id}', [InternalNoteController::class, 'deleteNote']);
    Route::get('internal/notes/{module}/{moduleId}', [InternalNoteController::class, 'getNotesByModule']);

    // offer
    Route::get('/offers', [OfferController::class, 'index']);
    Route::get('/offers/select', [OfferController::class, 'selectOffers']);
    Route::get('/offers/listing/{enquiryId}', [OfferController::class, 'getAllOffers']);
    Route::post('offers/add', [OfferController::class, 'addOffer']);
    Route::get('/offer/{id}', [OfferController::class, 'show']);
    Route::put('/offer/{id}', [OfferController::class, 'update']);
    Route::delete('/offer/{id}', [OfferController::class, 'destroy']);
    Route::get('offers/export/csv', [OfferController::class, 'exportOfferCsv']);
    Route::get('offer/{id}/download-offer/{type}', [OfferController::class, 'downloadOfferPdf']);

    // closing
    Route::get('/closings', [ClosingController::class, 'index']);
    Route::get('/closings/pipeline-summary', [ClosingController::class, 'pipelineSummary']);
    Route::get('/closings/select', [ClosingController::class, 'selectClosings']);
    Route::get('/closing/listing/{listingId}', [ClosingController::class, 'listByListingId']);
    Route::post('/addClose', [ClosingController::class, 'addClose']);
    Route::get('/closing/{id}', [ClosingController::class, 'show']);
    Route::put('/closing/{id}', [ClosingController::class, 'update']);
    Route::delete('/closing/{id}', [ClosingController::class, 'destroy']);
    Route::post('/closing/{id}/composeEmail', [ClosingController::class, 'composeEmail']);
    Route::get('/closings/export/csv', [ClosingController::class, 'exportClosingCsv']);

    // Favorite Routes
    Route::post('/favorite/toggle', [FavoriteController::class, 'toggleFavorite']);
    Route::delete('/favorite/{id}', [FavoriteController::class, 'deleteFavorite']);
    Route::get('/favorites', [FavoriteController::class, 'getFavorite']);
    Route::get('/favoriteListings', [FavoriteController::class, 'favoriteListings']);
    Route::get('/favoriteCount', [FavoriteController::class, 'favoriteCount']);
    Route::delete('/deleteAllFavoriteById/{userId}', [FavoriteController::class, 'deleteAllFavoriteById']);

    // Follow Routes
    Route::post('/addFollow', [FollowController::class, 'addFollow']);
    Route::post('/follow/toggle', [FollowController::class, 'toggleFollow']);
    Route::delete('/follow/{id}', [FollowController::class, 'deleteFollow']);
    Route::get('/follows', [FollowController::class, 'getFollow']);
    Route::get('/followingListings', [FollowController::class, 'followingListings']);
    Route::get('/followingCount', [FollowController::class, 'followingCount']);
    Route::delete('/deleteAllFollowsById/{userId}', [FollowController::class, 'deleteAllFollowsById']);

    // Email Templates' Routes
    Route::get('/email-templates', [EmailTemplateController::class, 'getEmailTemplates']);
    Route::post('/addEmailTemplate', [EmailTemplateController::class, 'addEmailTemplate']);
    Route::get('/getEmailTemplateById/{id}', [EmailTemplateController::class, 'getEmailTemplateById']);
    Route::put('/updateEmailTemplateById/{id}', [EmailTemplateController::class, 'updateEmailTemplateById']);
    Route::delete('/deleteEmailTemplate/{id}', [EmailTemplateController::class, 'deleteEmailTemplate']);

    // Global settings Routes
    Route::post('/settings/general', [GlobalSettingController::class, 'addSettings']);
    Route::get('/settings/general', [GlobalSettingController::class, 'getSettings']);
    Route::get('/settings/email-automations', [GlobalSettingController::class, 'getEmailAutomations']);
    Route::put('/settings/email-automations', [GlobalSettingController::class, 'updateEmailAutomations']);

    // Greeting Templates
    Route::prefix('settings/greeting-templates')->group(function () {
        Route::get('/', [GreetingTemplateController::class, 'index']);
        Route::post('/', [GreetingTemplateController::class, 'store']);
        Route::get('/logs', [GreetingTemplateController::class, 'logs']);
        Route::post('/cron', [GreetingTemplateController::class, 'triggerCron']);
        Route::post('/upload-image', [GreetingTemplateController::class, 'uploadImage']);
        Route::post('/{id}/test', [GreetingTemplateController::class, 'test']);
        Route::get('/{id}', [GreetingTemplateController::class, 'show']);
        Route::put('/{id}', [GreetingTemplateController::class, 'update']);
        Route::delete('/{id}', [GreetingTemplateController::class, 'destroy']);
    });
    // Enquiry
    Route::get('enquiry/listing', [EnquiryController::class, 'getAllEnquiryListing']);
    Route::get('enquiry/kanban', [EnquiryController::class, 'kanban']);
    Route::post('addEnquiry', [EnquiryController::class, 'addEnquiry']);
    Route::get('enquiries/select', [EnquiryController::class, 'selectEnquiries']);
    Route::get('getEnquiryById/{id}', [EnquiryController::class, 'getEnquiryById']);
    Route::put('updateEnquiryById/{id}', [EnquiryController::class, 'updateEnquiryById']);
    Route::delete('deleteEnquiryById/{id}', [EnquiryController::class, 'deleteEnquiryById']);
    Route::get('enquiryHistory/{id}', [EnquiryController::class, 'getEnquiryHistoryById']);
    Route::get('enquiries/export/csv', [EnquiryController::class, 'exportEnquiries']);

    Route::post('enquiry/takeOwnership', [EnquiryController::class, 'takeOwnership']);
    Route::post('enquiry/sendSelectedProperties', [EnquiryController::class, 'sendPropertyQuotationToEnquiryContact']);
    Route::post('enquiry/{id}/composeEmail', [EnquiryController::class, 'composeEmail']);
    Route::get('getEnquiryByUserIds/{id}', [EnquiryController::class, 'getEnquiryByUserId']);
    Route::post('/sendCustomEmail', [PropertiesController::class, 'sendManualMail']);
    Route::put('/reorderPropertyMedia', [PropertiesController::class, 'reorderPropertyMedia']);

    Route::get('/filter-layout/get', [EnquiryController::class, 'getEnquiryFilters']);
    Route::post('/filter-layout/add', [EnquiryController::class, 'enquiryFilters']);
    Route::put('/filter-layout/edit/{id}', [EnquiryController::class, 'updateEnquiryFilterById']);
    Route::delete('/filter-layout/delete/{id}', [EnquiryController::class, 'deleteEnquiryFilterById']);

    // Enquiry generatePdf Routes
    Route::post('generateEnquiryPdfById', [EnquiryController::class, 'createEnquiryPdfById']);

    // Enquiry Linked Listings Routes
    Route::post('enquiryLinkProperty', [EnquiryLinkedListingController::class, 'linkPropertyToEnquiry']);
    Route::delete('enquiryUnlinkProperty', [EnquiryLinkedListingController::class, 'unlinkPropertyFromEnquiry']);

    // Not in Use
    Route::get('enquiryLinkedProperties/{enquiryId}', [EnquiryLinkedListingController::class, 'getLinkedPropertiesByEnquiry']);
    Route::get('allEnquiryLinkedProperties/{enquiryId}', [EnquiryLinkedListingController::class, 'getAllLinkedPropertiesByEnquiry']);
    Route::get('propertyLinkedEnquiries/{propertyId}', [EnquiryLinkedListingController::class, 'getLinkedEnquiriesByProperty']);
    // Not in Use END

    Route::get('enquiry/{enquiryId}/linkedProperties', [EnquiryLinkedListingController::class, 'getAllLinkedPropertiesByEnquiry']);

    // Notification Routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'destroyAll']);

    // Browser Push (Web Push / VAPID) Routes
    Route::get('/push-subscriptions/public-key', [PushSubscriptionController::class, 'publicKey']);
    Route::get('/push-subscriptions', [PushSubscriptionController::class, 'index']);
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    // Calendar Routes (now handled via Task — TaskService auto-creates calendar events)
    // Route::get('/calendar/events', [CalendarController::class, 'getEvents']);
    // Route::post('/calendar/events', [CalendarController::class, 'createEvent']);
    // Route::get('/calendar/events/types', [CalendarController::class, 'getEventTypes']);
    // Route::get('/calendar/events/{id}', [CalendarController::class, 'getEventById'])->whereNumber('id');
    // Route::put('/calendar/events/{id}', [CalendarController::class, 'updateEvent'])->whereNumber('id');
    // Route::put('/calendar/events/{id}/reschedule', [CalendarController::class, 'rescheduleEvent'])->whereNumber('id');
    // Route::delete('/calendar/events/{id}', [CalendarController::class, 'deleteEvent'])->whereNumber('id');

    // Outlook Sync Routes (require auth)
    Route::get('/outlook/status', [OutlookSyncController::class, 'status']);
    Route::get('/outlook/disconnect', [OutlookSyncController::class, 'disconnect']);
    Route::get('/oauth/microsoft/redirect', [OutlookSyncController::class, 'redirectToMicrosoft']);

    // Document Center Routes
    Route::get('document/get', [DocumentCenterController::class, 'index']);
    Route::post('document/add', [DocumentCenterController::class, 'store']);
    Route::post('document/bulk-move', [DocumentCenterController::class, 'bulkMove']);
    Route::get('document/get/{id}', [DocumentCenterController::class, 'show']);
    Route::put('document/update/{id}', [DocumentCenterController::class, 'update']);
    Route::delete('document/delete/{id}', [DocumentCenterController::class, 'destroy']);
    Route::get('document/download/{id}', [DocumentCenterController::class, 'download']);
    Route::get('document/get/{module}/{moduleId}', [DocumentCenterController::class, 'getByModule']);

    // Generic S3 Upload Routes
    Route::post('upload/image', [FileUploadController::class, 'uploadImage']);
    Route::post('upload/document', [FileUploadController::class, 'uploadDocument']);
    Route::get('upload/document/signed-url', [FileUploadController::class, 'documentSignedUrl']);

    // Team Routes
    Route::post('team/add', [TeamController::class, 'addTeam']);
    Route::get('team/get', [TeamController::class, 'getTeams']);
    Route::get('team/select', [TeamController::class, 'selectTeams']);
    Route::get('team/get/{id}', [TeamController::class, 'getTeamById']);
    Route::put('team/edit/{id}', [TeamController::class, 'updateTeam']);
    Route::delete('team/delete/{id}', [TeamController::class, 'deleteTeam']);
    Route::post('team/members/upsert', [TeamController::class, 'assignTeamMembers']);
    Route::get('team/unassigned-members', [TeamController::class, 'getUnassignedAgents']);

    // Boost Routes
    Route::post('boost/add', [BoostController::class, 'addBoost']);
    Route::get('boost/get', [BoostController::class, 'getBoosts']);
    Route::get('boost/get/{id}', [BoostController::class, 'getBoostById']);
    Route::put('boost/update/{id}', [BoostController::class, 'updateBoost']);
    Route::delete('boost/delete/{id}', [BoostController::class, 'deleteBoost']);
    Route::get('boost/getBoostByPropertyId/{id}', [BoostController::class, 'getBoostByPropertyId']);

    // Marketing Assets Routes
    Route::get('marketing/assets/get', [MarketingAssetController::class, 'index']);
    Route::post('marketing/assets/add', [MarketingAssetController::class, 'store']);
    Route::get('marketing/assets/get/{id}', [MarketingAssetController::class, 'show']);
    Route::post('marketing/assets/update/{id}', [MarketingAssetController::class, 'update']);
    Route::delete('marketing/assets/delete/{id}', [MarketingAssetController::class, 'destroy']);
    Route::get('marketing/assets/download/{id}', [MarketingAssetController::class, 'download']);

    // Marketing Asset Categories (CRUD)
    Route::get('marketing/categories/select', [MarketingAssetCategoryController::class, 'select']);
    Route::get('marketing/categories/get', [MarketingAssetCategoryController::class, 'index']);
    Route::post('marketing/categories/add', [MarketingAssetCategoryController::class, 'store']);
    Route::put('marketing/categories/update/{id}', [MarketingAssetCategoryController::class, 'update']);
    Route::delete('marketing/categories/delete/{id}', [MarketingAssetCategoryController::class, 'destroy']);

    // Portal Settings
    Route::post('portal-settings/add', [PortalSettingController::class, 'addPortalSetting']);
    Route::get('portal-settings', [PortalSettingController::class, 'getAll']);
    Route::get('portal-settings/{id}', [PortalSettingController::class, 'getById']);
    Route::put('portal-settings/edit/{id}', [PortalSettingController::class, 'updatePortalSetting']);
    Route::delete('portal-settings/delete/{id}', [PortalSettingController::class, 'deletePortalSetting']);

    // Lead Source
    Route::post('leadSource/add', [LeadSourceController::class, 'addLeadSource']);
    Route::get('leadSource/getAll', [LeadSourceController::class, 'getAll']);
    Route::get('leadSource/select', [LeadSourceController::class, 'select']);
    Route::get('leadSource/{id}', [LeadSourceController::class, 'getById']);
    Route::put('leadSource/edit/{id}', [LeadSourceController::class, 'updateLeadSource']);
    Route::delete('leadSource/delete/{id}', [LeadSourceController::class, 'deleteLeadSource']);

    // User Activities
    Route::get('user/activities', [UserActivityController::class, 'index']);
    Route::get('user/activities/{module}/{moduleId}', [UserActivityController::class, 'getByModule']);
});

// External integration routes (Sanctum personal access token auth)
Route::middleware(['auth:sanctum', 'log.token', 'throttle:external-api'])->prefix('external')->group(function () {
    Route::get('property/{identifier}', [ExternalController::class, 'getProperty']);
    Route::post('enquiry/create', [ExternalController::class, 'createEnquiry']);
    Route::get('exclusive-links-listings', [PropertiesController::class, 'exclusiveLinksListings']);
});

// Keyflow Public API (v1) routes
require base_path('routes/api_v1.php');

Route::post('webhooks/property-finder', [ExternalController::class, 'capturePropertyFinderWebhook']);
Route::post('webhooks/bayut', [ExternalController::class, 'captureBayutWebhook']);

Route::get('/oauth/microsoft/callback', [OutlookSyncController::class, 'handleMicrosoftCallback']);
Route::get('generic-xml', [PropertiesController::class, 'exclusiveLinksListingsXml']);

// Inbound WhatsApp webhook (360degreeapps) — customer replies / free-text messages
Route::post('webhook/whatsapp/inbound', [WhatsAppWebhookController::class, 'handle']);

// Public greeting image route
Route::get('/greetings/image/{filename}', [GreetingTemplateController::class, 'showImage']);

Route::get('/documents/{path}', [\App\Http\Controllers\Api\AuthController::class, 'showDocument'])->where('path', '.*');

// HR Attendance Routes (Public / App level)
Route::prefix('hr/attendance')->group(function () {
    Route::get('/today-status', [AttendanceController::class, 'getTodayStatus']);
    Route::post('/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/check-out', [AttendanceController::class, 'checkOut']);
    Route::get('/daily', [AttendanceController::class, 'getDailyAttendance']);
    Route::post('/manual-mark', [AttendanceController::class, 'markManualAttendance']);
    Route::get('/monthly-report', [AttendanceController::class, 'getMonthlyReport']);
});

// Leave attachments & storage route
Route::get('/leaves/attachment/{filename}', function ($filename) {
    $cleanPath = str_replace(['..', '\\'], '', $filename);
    $filePath = storage_path('app/public/leaves/' . $cleanPath);
    if (file_exists($filePath)) {
        return response()->file($filePath);
    }
    $alt = storage_path('app/leaves/' . $cleanPath);
    if (file_exists($alt)) {
        return response()->file($alt);
    }
    abort(404);
});

Route::get('/storage/{path}', function ($path) {
    $cleanPath = str_replace(['..', '\\'], '', $path);
    $cleanPath = ltrim($cleanPath, '/');
    $filePath = storage_path('app/public/' . $cleanPath);
    if (file_exists($filePath)) {
        return response()->file($filePath);
    }
    $alt = storage_path('app/' . $cleanPath);
    if (file_exists($alt)) {
        return response()->file($alt);
    }
    abort(404);
})->where('path', '.*');
