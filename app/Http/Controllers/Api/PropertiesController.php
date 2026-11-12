<?php

namespace App\Http\Controllers\Api;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyApprovalHistoryResource\PropertyApprovalHistoryResource;
use App\Http\Resources\PropertyMediaResource\PropertyMediaResource;
use App\Http\Resources\PropertyResource\GetAllPropertyResource;
use App\Http\Resources\PropertyResource\GetOthersPropertyResource;
use App\Http\Resources\PropertyResource\GetPropertyByUserResource;
use App\Http\Resources\PropertyResource\GetPropertyResource;
use App\Http\Resources\PropertyResource\PropertyCrmResource;
use App\Http\Resources\PropertyResource\PropertyCustomEmailTemplateResource;
use App\Jobs\GenerateGenericFeedJob;
use App\Jobs\GeneratePropertyPdfsJob;
use App\Jobs\NotifyDataExportJob;
use App\Jobs\NotifyListingMediaUpdatedJob;
use App\Jobs\NotifyPublishedListingKeyDataChangedJob;
use App\Jobs\SyncPropertyToPortalsJob;
use App\Mail\DynamicMail;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\PropertyApprovalHistory;
use App\Models\PropertyCustomEmailTemplate;
use App\Models\PropertyListingHistory;
use App\Models\PropertyMediaFile;
use App\Models\PropertyMeta;
use App\Models\PropertyVideos;
use App\Models\Task;
use App\Repositories\PropertyRepository;
use App\Services\FileStorageService;
use App\Services\MailService;
use App\Services\PropertyPdfService;
use App\Services\PropertyService;
use App\Services\PropertySocialImageService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PropertiesController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PropertyService $propertyService,
        protected PropertyRepository $propertyRepository,
        protected MailService $mailService,
        protected PropertyPdfService $propertyPdfService,
        protected PropertySocialImageService $propertySocialImageService,
        protected FileStorageService $fileStorage
    ) {}

    public function selectProperties(Request $request)
    {
        try {
            $query = Property::select('id', 'title', 'referenceNumber', 'category', 'listingType', 'status');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('referenceNumber', 'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $statuses = explode(',', $request->status);
                $query->whereIn('status', $statuses);
            }

            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            if ($request->filled('listingType')) {
                $query->where('listingType', $request->listingType);
            }

            if ($request->filled('agentId')) {
                $query->where('agentId', $request->agentId);
            }

            if ($request->filled('createdBy')) {
                $query->where('createdBy', $request->createdBy);
            }

            $orderBy = $request->input('orderBy') ?: 'created_at';
            $order = $request->input('order', 'desc');
            $order = in_array(strtolower($order), ['asc', 'desc']) ? strtolower($order) : 'desc';

            $properties = $query->orderBy($orderBy, $order)->limit(50)->get()->map(function ($property) {
                return [
                    'id' => $property->id,
                    'title' => $property->title,
                    'referenceNumber' => $property->referenceNumber,
                    'identifier' => $property->referenceNumber
                        ? '#'.$property->referenceNumber.' - '.$property->title
                        : $property->title,
                ];
            });

            return $this->success($properties, 'Properties retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : selectProperties', $e);
        }
    }

    public function properties(Request $request)
    {
        try {
            $properties = $this->propertyService->listProperties($request);

            return $this->success(
                GetAllPropertyResource::collection($properties),
                'Properties retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : properties', $e);
        }
    }

    public function getAllMatchingProperties(Request $request)
    {
        try {
            $properties = $this->propertyService->getAllMatchingProperties($request);

            return $this->success($properties, 'Matching properties retrieved successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : getAllMatchingProperties', $e);
        }
    }

    public function addProperty(Request $request)
    {
        foreach (['pfAmenities', 'bayutAmenities', 'videos'] as $field) {
            if ($request->filled($field) && is_string($request->$field)) {
                $request->merge([
                    $field => json_decode($request->$field, true) ?? [],
                ]);
            }
        }

        foreach (['flyerData', 'termsConditions', 'paymentPlans', 'spaceDetails'] as $field) {
            if ($request->has($field) && is_string($request->$field)) {
                $decoded = json_decode($request->$field, true);
                if (is_array($decoded)) {
                    $request->merge([$field => $decoded]);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'nullable',
            'category' => 'nullable',
            'listingType' => 'nullable',
            'completionStatus' => 'nullable',
            'sizeArea' => 'nullable|numeric',
            'plotSize' => 'nullable|numeric',
            'bedrooms' => 'nullable|integer',
            'bathrooms' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'reraPermitNo' => 'nullable',
            'agentId' => 'nullable|integer',
            'listingOwnerId' => 'nullable|integer|exists:users,id',
            'physicalOwnerId' => 'nullable|integer|exists:contacts,id',
            'propertyType' => 'nullable|string',
            'pfCity' => 'nullable',
            'pfCommunity' => 'nullable',
            'pfSubCommunity' => 'nullable',
            'pfBuilding' => 'nullable',
            'pfLatitude' => 'nullable|numeric',
            'pfLongitude' => 'nullable|numeric',
            'pfAmenities' => 'nullable|array',
            'pfAmenities.*' => 'nullable',
            'pfPriceFrequency' => 'nullable',
            'pfCheques' => 'nullable',
            'bayutLocation' => 'nullable',
            'bayutLocationId' => 'nullable|numeric',
            'bayutAmenities' => 'nullable|array',
            'bayutAmenities.*' => 'nullable',
            'bayutOccupancyStatus' => 'nullable',
            'bayutMaintenanceFeePaidBy' => 'nullable',
            'bayutMinimumContractPeriod' => 'nullable',
            'bayutVacatingNoticePeriod' => 'nullable',
            'bayutMaintenanceFee' => 'nullable|numeric',
            'status' => 'nullable',
            'approvalStatus' => 'nullable',
            'managedType' => 'nullable',
            'annualRent' => 'nullable|numeric',
            'salePrice' => 'nullable|numeric',
            'titleDeedNumber' => 'nullable|string',
            'access' => 'nullable|string',
            'propertySalesValue' => 'nullable|numeric',
            'annualRentalPrice' => 'nullable|numeric',
            'pfUnitNumber' => 'nullable|string',
            'unitNo' => 'nullable|string',
            'listingLeadSource2' => 'nullable|string',
            'view' => 'nullable|string',
            'developmentText' => 'nullable|string',
            'projectText' => 'nullable|string',
            'deposit' => 'nullable|numeric',
            'sold' => 'nullable|boolean',
            'agencyFee' => 'nullable|numeric',
            'agencyFeePercent' => 'nullable|numeric',
            'ownershipTerm' => 'nullable|string',
            'yearBuilt' => 'nullable|integer|digits:4',
            'parkingSpaces' => 'nullable|string|max:125',
            'pubDate' => 'nullable|date',
            'flyerData' => 'nullable',
            'termsConditions' => 'nullable',
            'paymentPlans' => 'nullable',
            'unitType1' => 'nullable|numeric',
            'unitTypeBuildUpArea1' => 'nullable|numeric',
            'unitTypePrice1' => 'nullable|numeric',
            'unitType2' => 'nullable',
            'unitTypeBuildUpArea2' => 'nullable|numeric',
            'unitTypePrice2' => 'nullable|numeric',
            'unitType3' => 'nullable',
            'unitTypeBuildUpArea3' => 'nullable|numeric',
            'unitTypePrice3' => 'nullable|numeric',
            'unitType4' => 'nullable',
            'unitTypeBuildUpArea4' => 'nullable|numeric',
            'unitTypePrice4' => 'nullable|numeric',
            'unitType5' => 'nullable',
            'unitTypeBuildUpArea5' => 'nullable|numeric',
            'unitTypePrice5' => 'nullable|numeric',
            'unitType6' => 'nullable',
            'unitTypeBuildUpArea6' => 'nullable|numeric',
            'unitTypePrice6' => 'nullable|numeric',
            'spaceDetails' => 'nullable',
            'keyFeaturesForWebsite' => 'nullable|string',
            'locationProximityForWebsite' => 'nullable|string',
            'adminComments' => 'nullable|string',
            'portalNotes' => 'nullable|string',
            'uploadPhotos' => 'nullable|array',
            'uploadPhotos.*' => 'nullable|file|mimes:jpg,jpeg,png',
            'floorPlans' => 'nullable|array',
            'floorPlans.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'uploadDocuments' => 'nullable|array',
            'uploadDocuments.*' => 'nullable|file|mimes:pdf,doc,docx',
            'videos' => 'sometimes|array',
            'videos.*.videoUrl' => 'nullable|url',
            'videos.*.title' => 'nullable',
            'videos.*.platform' => 'nullable',
            'videos.*.isDefault' => 'nullable',
            'createdBy' => 'sometimes|required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $validated = $validator->validated();
            $validated['createdBy'] = ! empty($validated['createdBy']) ? $validated['createdBy'] : user()->id;
            $validated['listingOwnerId'] = ! empty($validated['listingOwnerId']) ? $validated['listingOwnerId'] : user()->id;
            $validated['pfAmenities'] = $validated['pfAmenities'] ?? null;
            $validated['bayutAmenities'] = $validated['bayutAmenities'] ?? null;
            $statusProvided = ! empty($validated['status']);
            $validated['status'] = $statusProvided ? $validated['status'] : Constants::PROPERTY_STATUS_UPCOMING;

            $statusValidationStatuses = [
                Constants::PROPERTY_STATUS_UPCOMING,
                Constants::PROPERTY_STATUS_DRAFT,
                Constants::PROPERTY_STATUS_POCKET_LISTING,
                Constants::PROPERTY_STATUS_PENDING,
            ];
            if ($statusProvided && in_array($validated['status'], $statusValidationStatuses)) {
                $missing = $this->propertyService->getMissingFieldsForListingStatus($validated, $validated['status']);

                if (! empty($missing)) {
                    $statusLabel = Constants::PROPERTY_STATUS[$validated['status']] ?? $validated['status'];

                    return $this->error("To save status as {$statusLabel}, the following field(s) are required: ".implode(', ', $missing).'.', 422);
                }
            }
            unset($validated['propertySalesValue']);
            unset($validated['annualRentalPrice']);
            unset($validated['pfUnitNumber']);
            unset($validated['unitNo']);

            if (! empty($validated['propertyType'])) {
                $validated = array_merge($validated, $this->propertyService->resolvePropertyTypes($validated['propertyType']));
            }
            unset($validated['propertyType']);

            $validated['referenceNumber'] = $this->propertyService->generateReferenceNumber($validated);

            $property = $this->propertyService->createProperty($validated, $request);

            if (! empty($validated['videos'])) {
                foreach ($validated['videos'] as $video) {
                    PropertyVideos::create([
                        'propertyId' => $property->id,
                        'videoUrl' => $video['videoUrl'] ?? null,
                        'platform' => $video['platform'] ?? null,
                        'title' => $video['title'] ?? null,
                        'isDefault' => isset($video['isDefault']) ? (int) $video['isDefault'] : 0,
                    ]);
                }
            }

            $params = [
                'action' => 'created',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $property->id,
                'description' => "Created property: {$property->title}",
            ];
            logUserActivity($params);

            logPropertyHistory([
                'propertyId' => $property->id,
                'field' => 'created',
                'newValue' => json_encode(['message' => "Property created: {$property->title}"]),
                'comments' => 'New property listing created',
            ]);

            if (! empty($property->physicalOwnerId)) {
                $owner = Contact::find($property->physicalOwnerId);

                if ($owner && ! empty($owner->email)) {
                    $guideSlug = match (strtolower((string) $property->listingType)) {
                        'sale' => Constants::EMAIL_SLUGS['contact']['sellers_guide'],
                        'rent' => Constants::EMAIL_SLUGS['contact']['landlord_guide'],
                        default => null,
                    };

                    $contactType = $property->listingType == 'rent'
                        ? Constants::CONTACT_TYPE_LANDLORD
                        : Constants::CONTACT_TYPE_SELLER;

                    // Merge — an owner may also be a buyer/tenant/agent elsewhere.
                    $owner->addContactTypes($contactType);

                    if ($guideSlug) {
                        try {
                            $this->mailService->sendEmail(
                                $guideSlug,
                                $owner->email,
                                [
                                    'contact_name' => ucwords($owner->name ?? 'Contact'),
                                    'agent_name' => ucwords(user()->name ?? ''),
                                    'company_name' => getCompanyName(),
                                ]
                            );

                            logPropertyHistory([
                                'propertyId' => $property->id,
                                'field' => 'automation_email_sent',
                                'newValue' => json_encode([
                                    'message' => 'Automation guide sent to owner based on listing type: '.$property->listingType,
                                ]),
                            ]);
                        } catch (\Throwable $e) {
                            Log::error('PropertiesController : addProperty : sendGuideEmail : '.$e->getMessage());
                        }
                    }
                }
            }

            return $this->success(
                new GetPropertyResource($property->load('meta')),
                'Property created successfully'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : addProperty', $e);
        }
    }

    public function updateProperty(Request $request, $id)
    {
        $property = Property::find($id);

        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        foreach (['pfAmenities', 'bayutAmenities', 'videos'] as $field) {

            if ($request->has($field)) {

                $value = $request->$field;

                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $value = is_array($decoded) ? $decoded : [];
                }

                if (empty($value)) {
                    $value = [];
                }

                $request->merge([$field => $value]);
            }
        }

        foreach (['flyerData', 'termsConditions', 'paymentPlans', 'spaceDetails'] as $field) {
            if ($request->has($field) && is_string($request->$field)) {
                $decoded = json_decode($request->$field, true);
                if (is_array($decoded)) {
                    $request->merge([$field => $decoded]);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required',
            'description' => 'nullable',
            'referenceNumber' => 'nullable',
            'category' => 'nullable',
            'listingType' => 'nullable',
            'completionStatus' => 'nullable',
            'sizeArea' => 'nullable|numeric',
            'plotSize' => 'nullable|numeric',
            'bedrooms' => 'nullable|integer',
            'bathrooms' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'reraPermitNo' => 'nullable',
            'agentId' => 'nullable|integer',
            'listingOwnerId' => 'nullable|integer|exists:users,id',
            'physicalOwnerId' => 'nullable|integer|exists:contacts,id',
            'propertyType' => 'nullable|string',
            'pfCity' => 'nullable',
            'pfCommunity' => 'nullable',
            'pfSubCommunity' => 'nullable',
            'pfBuilding' => 'nullable',
            'pfLatitude' => 'nullable|numeric',
            'pfLongitude' => 'nullable|numeric',

            'pfAmenities' => 'sometimes|array',
            'pfAmenities.*' => 'nullable',

            'bayutAmenities' => 'sometimes|array',
            'bayutAmenities.*' => 'nullable',

            'videos' => 'sometimes|array',
            'videos.*.videoUrl' => 'nullable|url',
            'videos.*.title' => 'nullable|string|max:255',
            'videos.*.platform' => 'nullable|string|max:100',
            'videos.*.isDefault' => 'nullable|boolean',

            'pfPriceFrequency' => 'nullable',
            'pfCheques' => 'nullable',
            'bayutLocation' => 'nullable',
            'bayutLocationId' => 'nullable|numeric',
            'bayutOccupancyStatus' => 'nullable',
            'bayutMinimumContractPeriod' => 'nullable',
            'bayutVacatingNoticePeriod' => 'nullable',
            'bayutMaintenanceFee' => 'nullable|numeric',
            'bayutMaintenanceFeePaidBy' => 'nullable',
            'status' => 'nullable',
            'approvalStatus' => 'nullable',
            'managedType' => 'nullable',
            'uploadPhotos' => 'nullable|array',
            'uploadPhotos.*' => 'nullable|file|mimes:jpg,jpeg,png',
            'floorPlans' => 'nullable|array',
            'floorPlans.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'uploadDocuments' => 'nullable|array',
            'uploadDocuments.*' => 'nullable|file|mimes:pdf,doc,docx',
            'annualRent' => 'nullable|numeric',
            'salePrice' => 'nullable|numeric',
            'titleDeedNumber' => 'nullable|string',
            'access' => 'nullable|string',
            'propertySalesValue' => 'nullable|numeric',
            'annualRentalPrice' => 'nullable|numeric',
            'pfUnitNumber' => 'nullable|string',
            'unitNo' => 'nullable|string',
            'listingLeadSource2' => 'nullable|string',
            'view' => 'nullable|string',
            'developmentText' => 'nullable|string',
            'projectText' => 'nullable|string',
            'deposit' => 'nullable|numeric',
            'sold' => 'nullable|boolean',
            'agencyFee' => 'nullable|numeric',
            'agencyFeePercent' => 'nullable|numeric',
            'ownershipTerm' => 'nullable|string',
            'yearBuilt' => 'nullable|integer|digits:4',
            'parkingSpaces' => 'nullable|string|max:125',
            'pubDate' => 'nullable|date',
            'flyerData' => 'nullable',
            'termsConditions' => 'nullable',
            'paymentPlans' => 'nullable',
            'unitType1' => 'nullable',
            'unitTypeBuildUpArea1' => 'nullable|numeric',
            'unitTypePrice1' => 'nullable|numeric',
            'unitType2' => 'nullable',
            'unitTypeBuildUpArea2' => 'nullable|numeric',
            'unitTypePrice2' => 'nullable|numeric',
            'unitType3' => 'nullable',
            'unitTypeBuildUpArea3' => 'nullable|numeric',
            'unitTypePrice3' => 'nullable|numeric',
            'unitType4' => 'nullable',
            'unitTypeBuildUpArea4' => 'nullable|numeric',
            'unitTypePrice4' => 'nullable|numeric',
            'unitType5' => 'nullable',
            'unitTypeBuildUpArea5' => 'nullable|numeric',
            'unitTypePrice5' => 'nullable|numeric',
            'unitType6' => 'nullable',
            'unitTypeBuildUpArea6' => 'nullable|numeric',
            'unitTypePrice6' => 'nullable|numeric',
            'spaceDetails' => 'nullable',
            'keyFeaturesForWebsite' => 'nullable|string',
            'locationProximityForWebsite' => 'nullable|string',
            'adminComments' => 'nullable|string',
            'portalNotes' => 'nullable|string',
            'createdBy' => 'sometimes|required|numeric',
            'expireOn' => 'nullable|string',
            'onlyStatusUpdate' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {

            $validated = $validator->validated();

            // Only reassign the original author when a createdBy is explicitly provided;
            // an empty/absent value must leave the existing createdBy untouched.
            if (empty($validated['createdBy'])) {
                unset($validated['createdBy']);
            }

            $newStatus = $validated['status'] ?? null;
            $currentStatus = $property->getOriginal('status');
            $targetStatus = $newStatus ?? $currentStatus;

            $statusValidationStatuses = [
                Constants::PROPERTY_STATUS_UPCOMING,
                Constants::PROPERTY_STATUS_DRAFT,
                Constants::PROPERTY_STATUS_POCKET_LISTING,
                Constants::PROPERTY_STATUS_PENDING,
                Constants::PROPERTY_STATUS_PUBLISHED,
            ];

            if (! isSuperAdmin() && ! isManager() && $newStatus !== Constants::PROPERTY_STATUS_UNPUBLISHED && in_array($targetStatus, $statusValidationStatuses, true)) {
                $onlyStatusUpdate = filter_var($request->input('onlyStatusUpdate', false), FILTER_VALIDATE_BOOLEAN);

                $hasVideos = $request->filled('videos');
                $validationData = $onlyStatusUpdate
                    ? array_merge(
                        $property->only($property->getFillable()),
                        $property->meta()->pluck('metaValue', 'metaKey')->toArray(),
                        ['status' => $targetStatus],
                    )
                    : ($hasVideos
                        ? array_merge(
                            $property->only($property->getFillable()),
                            $property->meta()->pluck('metaValue', 'metaKey')->toArray(),
                            $validated,
                            ['status' => $targetStatus],
                        )
                        : $validated);

                $missing = $this->propertyService->getMissingFieldsForListingStatus($validationData, $targetStatus, $property);

                if (! empty($missing)) {
                    $statusLabel = Constants::PROPERTY_STATUS[$targetStatus] ?? $targetStatus;

                    return $this->error("To save status as {$statusLabel}, the following field(s) are required: ".implode(', ', $missing).'.', 422);
                }
            }
            unset($validated['propertySalesValue']);
            unset($validated['annualRentalPrice']);
            unset($validated['pfUnitNumber']);
            unset($validated['unitNo']);

            if (! empty($validated['propertyType'])) {
                $validated = array_merge($validated, $this->propertyService->resolvePropertyTypes($validated['propertyType']));
            }
            unset($validated['propertyType']);

            // Separate non-DB fields before passing to repository:
            // file uploads + internal-notes (separate table) + every key registered in PROPERTY_META_KEYS.
            $nonDbFields = array_merge(
                ['uploadPhotos', 'floorPlans', 'uploadDocuments', 'videos', 'adminComments', 'portalNotes'],
                array_keys(Constants::PROPERTY_META_KEYS)
            );
            $dbData = array_diff_key($validated, array_flip($nonDbFields));

            $oldValues = $property->only(array_keys($dbData));
            $oldReraStatus = $property->reraPermitNo;
            $newReraStatus = $dbData['reraPermitNo'] ?? null;

            // Videos are persisted by the service (syncPropertyVideos) so the portal-sync
            // diff can see them — a video-only edit changes no column and no meta key.
            $property = $this->propertyService->updateProperty($id, $dbData, $request);

            $params = [
                'action' => 'updated',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $property->id,
                'description' => "Updated property: {$property->title}",
                'oldValues' => $oldValues,
                'newValues' => $property->only(array_keys($dbData)),
            ];
            logUserActivity($params);

            // Notify admins, managers and the assigned agent whenever the listing status changes
            $oldStatus = $oldValues['status'] ?? null;
            $newStatus = $dbData['status'] ?? null;

            if ($newStatus && $oldStatus !== $newStatus) {
                $statusLabel = Constants::PROPERTY_STATUS[$newStatus] ?? $newStatus;
                PropertyService::notifyAdminsManagersOnStatusChange($property, $statusLabel);
            }

            if (! empty($property->physicalOwnerId)) {
                $owner = Contact::find($property->physicalOwnerId);

                if ($owner) {
                    $contactType = $property->listingType == 'rent'
                        ? Constants::CONTACT_TYPE_LANDLORD
                        : Constants::CONTACT_TYPE_SELLER;

                    // Merge — an owner may also be a buyer/tenant/agent elsewhere.
                    $owner->addContactTypes($contactType);
                }
            }

            return $this->success(
                new GetPropertyResource($property->fresh()->load('meta')),
                'Property updated successfully'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : updateProperty', $e);
        }
    }

    public function uploadPropertyMediaFiles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'propertyId' => 'required|exists:properties,id',
            'uploadPhotos' => 'nullable|array|min:1',
            'uploadPhotos.*' => 'nullable|file',
            'uploadVideos' => 'nullable|array',
            'uploadVideos.*' => 'nullable|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'The provided data is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();
            $validated['createdBy'] = user()->id;

            $uploaded = $this->propertyService->uploadPropertyMediaFiles(
                $validated['propertyId'],
                $validated,
                $request
            );

            $params = [
                'action' => 'uploaded_media',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $validated['propertyId'],
                'description' => "Uploaded media for property ID: {$validated['propertyId']}",
            ];
            logUserActivity($params);

            logPropertyHistory([
                'propertyId' => $validated['propertyId'],
                'field' => 'uploaded_media',
                'newValue' => json_encode(['message' => 'Media uploaded']),
            ]);
            // Auto-generate PDFs if property has website media
            // GeneratePropertyPdfsJob::dispatch((int) $validated['propertyId']);

            // Notify admins + listing agent when media changes on a published listing — queued.
            if ($actor = user()) {
                NotifyListingMediaUpdatedJob::dispatch((int) $validated['propertyId'], 'added', count($uploaded), $actor->id);
            }

            $baseUrl = config('app.url');
            $data = array_map(function ($item) use ($baseUrl) {
                $record = collect($item)->except(['originalPath', 'thumbnailPath'])->toArray();
                if (! empty($record['compressedPath'])) {
                    $record['compressedPath'] = $baseUrl.$record['compressedPath'];
                }
                if (! empty($record['filePath'])) {
                    $record['filePath'] = $baseUrl.$record['filePath'];
                }

                return $record;
            }, $uploaded);

            return response()->json([
                'status' => true,
                'message' => 'Property media uploaded successfully',
                'count' => count($data),
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : uploadPropertyMediaFiles', $e);
        }
    }

    public function updateMediaAppearance(Request $request)
    {
        $validator = Validator::make($request->post(), [
            'propertyId' => 'required|exists:properties,id',
            'type' => 'required|in:isWebsite,isPortals,isFlyer',
            'action' => 'required|in:check,uncheck',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'The provided data is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();

            $affected = $this->propertyService->updateMediaAppearance(
                (int) $validated['propertyId'],
                $validated['type'],
                $validated['action']
            );

            logUserActivity([
                'action' => 'updated_media',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $validated['propertyId'],
                'description' => "Bulk {$validated['action']} {$validated['type']} on all media for property ID: {$validated['propertyId']}",
            ]);

            logPropertyHistory([
                'propertyId' => $validated['propertyId'],
                'field' => 'updated_media',
                'newValue' => json_encode(['message' => "Bulk {$validated['action']} {$validated['type']} on all media"]),
            ]);

            if ($validated['type'] === 'isWebsite') {
                // GeneratePropertyPdfsJob::dispatch((int) $validated['propertyId']);
            }

            return response()->json([
                'status' => true,
                'message' => 'Media appearance updated successfully',
                'affected' => $affected,
            ]);
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : updateMediaAppearance', $e);
        }
    }

    public function updateUploadPropertyMediaFiles(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'fileName' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string',
            'isWebsite' => 'sometimes|nullable',
            'isPortals' => 'sometimes|nullable',
            'isFlyer' => 'sometimes|nullable',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'The provided data is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mediaFile = PropertyMediaFile::find($id);

        if (! $mediaFile) {
            return response()->json([
                'status' => false,
                'message' => 'Media file not found',
            ], 422);
        }

        try {
            $validated = $validator->validated();

            $trackFields = ['category', 'isWebsite', 'isPortals', 'isFlyer', 'fileName', 'tags'];
            $oldValues = $mediaFile->only($trackFields);

            $mediaFile->update($validated);

            $updatedMedia = $mediaFile->fresh();
            $newValues = $updatedMedia->only($trackFields);

            // Log activity
            logUserActivity([
                'action' => 'updated_media',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $mediaFile->propertyId,
                'description' => "Updated media file (ID: {$id}) for property ID: {$mediaFile->propertyId}",
            ]);

            // Log property history
            $originalParts = [];
            $newParts = [];
            foreach ($trackFields as $field) {
                if (($oldValues[$field] ?? null) != ($newValues[$field] ?? null)) {
                    $oldDisplay = is_array($oldValues[$field] ?? null) ? implode('|', $oldValues[$field]) : ($oldValues[$field] ?? '');
                    $newDisplay = is_array($newValues[$field] ?? null) ? implode('|', $newValues[$field]) : ($newValues[$field] ?? '');
                    $originalParts[] = "{$field}: {$oldDisplay}";
                    $newParts[] = "{$field}: {$newDisplay}";
                }
            }

            logPropertyHistory([
                'propertyId' => $mediaFile->propertyId,
                'field' => 'updated_media',
                'originalValue' => ! empty($originalParts) ? json_encode(['message' => implode(', ', $originalParts)]) : null,
                'newValue' => ! empty($newParts) ? json_encode(['message' => implode(', ', $newParts)]) : json_encode(['message' => "Updated media file (ID: {$id})"]),
            ]);

            // Notify agent and listing owner
            notifyModule(
                module: Constants::MODULE_PROPERTY,
                moduleId: $mediaFile->propertyId,
                type: Constants::NOTIFICATION_TYPES['property_update'],
                action: 'updated media',
            );

            // Auto-generate PDFs if property has website media
            // GeneratePropertyPdfsJob::dispatch((int) $mediaFile->propertyId);

            // Notify admins + listing agent when media changes on a published listing — queued.
            if ($actor = user()) {
                NotifyListingMediaUpdatedJob::dispatch((int) $mediaFile->propertyId, 'updated', null, $actor->id);
            }

            $media = collect($updatedMedia)->except(['originalPath', 'thumbnailPath'])->toArray();
            $baseUrl = config('app.url');
            if (! empty($media['compressedPath'])) {
                $media['compressedPath'] = $baseUrl.$media['compressedPath'];
            }
            if (! empty($media['filePath'])) {
                $media['filePath'] = $baseUrl.$media['filePath'];
            }

            return response()->json([
                'status' => true,
                'message' => 'Property media updated successfully',
                'data' => $media,
            ]);
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : updateUploadPropertyMediaFiles', $e);
        }
    }

    public function getPropertyById($id)
    {
        try {
            $query = Property::with([
                'mediaCompressed',
                'propertyCategories',
                'propertyListingHistory',
                'tasks.assignedUser',
                'creator',
                'meta',
                'internalNotes',
                'agent.meta',
                'salesforceIdMap',
            ])->withCount('acceptedOffers');

            $property = $query->find($id);

            if (! $property) {
                return $this->error('The requested property was not found.', 422);
            }

            $resource = (isAgent() && $property->agentId !== user()->id)
                ? new GetOthersPropertyResource($property)
                : new GetPropertyResource($property);

            return $this->success($resource, 'Property retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : getPropertyById', $e);
        }
    }

    public function deleteProperty(Request $request)
    {
        if (isAgent()) {
            return $this->error('Agents are not allowed to delete properties.', 403);
        }
        $ids = $request->input('propertyIds');
        if (empty($ids)) {
            return $this->error('Property id(s) is required', 422);
        }
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        if (! is_array($ids)) {
            return $this->error('Invalid property id format', 422);
        }
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return $this->error('Invalid property id(s)', 422);
        }
        try {
            $deletedCount = $this->propertyService->deleteProperties($ids);
            if ($deletedCount === 0) {
                return $this->error('The requested property was not found.', 422);
            }
            $message = $deletedCount === 1
                ? 'Property deleted successfully'
                : "{$deletedCount} properties deleted successfully";

            $params = [
                'action' => 'deleted',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $ids[0],
                'description' => "Deleted {$deletedCount} property(ies) with IDs: ".implode(', ', $ids),
            ];
            logUserActivity($params);

            logPropertyHistory([
                'propertyId' => $ids[0],
                'field' => 'deleted',
                'newValue' => json_encode(['message' => "Deleted {$deletedCount} property(ies)"]),
                'comments' => json_encode([
                    'action' => 'deleted',
                    'createdBy' => user()->name ?? 'System',
                    'items' => $ids,
                ]),
            ]);

            return $this->success(
                ['deleted_count' => $deletedCount],
                $message
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : deleteProperty', $e);
        }
    }

    public function generatePdf(Request $request, $id)
    {
        try {
            $templateSlug = $request->query('templateSlug', 'a4_single_page_flyer');

            // Check if pre-generated PDF exists in storage
            $storagePath = "propertymediapdf/{$id}/{$templateSlug}/{$templateSlug}.pdf";

            // Fallback: generate PDF on-the-fly

            $property = $this->getPropertyForDocumentGeneration($id, false);
            if (! $property) {
                return $this->error('The requested property was not found.', 422);
            }

            if (! array_key_exists($templateSlug, Constants::PDF_TEMPLATES)) {
                return $this->error('The selected template is not available.', 422);
            }

            $templateName = Constants::PDF_TEMPLATES[$templateSlug];

            $reqStart = microtime(true);
            Log::info('[PropertyPdf] controller:start', [
                'propertyId' => $property->id,
                'template' => $templateSlug,
            ]);

            $t = microtime(true);
            $pdf = $this->propertyPdfService->generatePdf($property, $templateSlug);
            Log::info('[PropertyPdf] controller: service->generatePdf', ['ms' => round((microtime(true) - $t) * 1000, 1)]);

            $fileName = 'property_'.$property->id.'_'.$templateSlug.'_'.now()->format('Ymd_His').'.pdf';

            // $pdf->output() is where DomPDF actually renders the document.
            $t = microtime(true);
            $pdfOutput = $pdf->output();
            Log::info('[PropertyPdf] controller: pdf->output() [DomPDF render]', ['ms' => round((microtime(true) - $t) * 1000, 1)]);

            // Save PDF to storage and return URL
            $t = microtime(true);
            $url = $this->fileStorage->putPublic($storagePath, $pdfOutput);
            Log::info('[PropertyPdf] controller: putPublic (S3 upload)', ['ms' => round((microtime(true) - $t) * 1000, 1)]);

            Log::info('[PropertyPdf] controller:done (total)', ['ms' => round((microtime(true) - $reqStart) * 1000, 1)]);

            $params = [
                'action' => 'generated_pdf',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $property->id,
                'description' => "Generated PDF ({$templateName}) for property: {$property->title}",
            ];
            logUserActivity($params);

            logPropertyHistory([
                'propertyId' => $property->id,
                'field' => 'generated_pdf',
                'newValue' => json_encode(['message' => "PDF generated: {$templateName}"]),
            ]);

            return $this->success(['url' => $url, 'fileName' => $fileName], 'PDF generated successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : generatePdf', $e);
        }
    }

    public function getPdfTemplates()
    {
        try {
            $templates = $this->propertyPdfService->getTemplates();

            return $this->success($templates, 'PDF templates retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : getPdfTemplates', $e);
        }
    }

    public function generateSocialImage(Request $request, $id)
    {
        try {
            $property = $this->getPropertyForDocumentGeneration($id, false);

            if (! $property) {
                return $this->error('The requested property was not found.', 422);
            }

            $bannerTitle = $request->query('socialImageTitle', 'for_rent');

            $imageContent = $this->propertySocialImageService->generateImage($property, $bannerTitle);

            if (empty($imageContent)) {
                return $this->error('No data found to generate social image.', 404);
            }

            $fileName = 'social_image_'.$bannerTitle.'.png';
            $filePath = 'social-media/'.$property->id.'/'.$fileName;

            $url = $this->fileStorage->putPublic($filePath, $imageContent);

            logUserActivity([
                'action' => 'generated_social_image',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $property->id,
                'description' => "Generated social image for property: {$property->title}",
            ]);

            return $this->success([
                'url' => $url,
                'fileName' => $fileName,
            ], 'Social image generated successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : generateSocialImage', $e);
        }
    }

    private function getPropertyForDocumentGeneration(int $id, $agentCheck = true): ?Property
    {
        $query = Property::with([
            'mediaCompressed',
            'mediaThumbnails',
            'propertyCategories',
            'propertyListingHistory',
            'tasks.assignedUser',
            'city',
            'community',
            'subCommunity',
            'buildingTower',
            'creator',
            'agent',
        ]);

        if ($agentCheck && isAgent()) {
            $query->where('agentId', user()->id);
        }

        return $query->find($id);
    }

    public function cloneListing($id)
    {
        $query = Property::with(['propertyListingHistory']);
        if (isAgent()) {
            $query->where('agentId', user()->id);
        }
        $property = $query->find($id);
        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        try {
            $newProperty = $this->propertyService->cloneProperty($property);

            $params = [
                'action' => 'cloned',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $newProperty->id,
                'description' => "Cloned property: {$property->title} (original ID: {$property->id}) to new property ID: {$newProperty->id}",
            ];
            logUserActivity($params);

            logPropertyHistory([
                'propertyId' => $property->id,
                'field' => 'cloned',
                'originalValue' => json_encode(['message' => "Property ID: {$property->id}"]),
                'newValue' => json_encode(['message' => "Cloned to Property ID: {$newProperty->id}"]),
            ]);

            return $this->success(
                new GetPropertyResource($newProperty->load('meta')),
                'Listing clone successfully'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : cloneListing', $e);
        }
    }

    public function getPropertyHistoryById(Request $request, $id)
    {
        try {
            $property = Property::find($id);

            $query = PropertyListingHistory::with(['properties:id,title', 'user:id,name'])
                ->where('propertyId', $id);

            if ($property) {
                $query->where('created_at', '>=', $property->created_at);
            }

            // if (isAgent()) {
            //     $query->where('userId', user()->id);
            // }

            $perPage = $request->input('per_page', 10);

            if ($perPage > 100) {
                $perPage = 100;
            }

            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('field', 'like', "%{$search}%")
                        ->orWhere('originalValue', 'like', "%{$search}%")
                        ->orWhere('newValue', 'like', "%{$search}%")
                        ->orWhere('comments', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $history = $query->latest()->paginate($perPage);

            $history->getCollection()->transform(function ($item) {
                // Returned as a plain array on purpose. Assigning a converted string
                // back onto the model would be re-cast to Carbon and re-serialised as
                // UTC, so the browser would shift it a second time.
                $data = $item->toArray();

                $data['originalValue'] = json_decode($item->originalValue);
                $data['newValue'] = json_decode($item->newValue);
                $data['comments'] = json_decode($item->comments);

                // Raw, these timestamps go out as UTC ISO and the frontend renders them
                // in the viewer's own timezone. Convert to the app timezone, matching
                // the enquiry timeline and every other endpoint.
                $data['created_at'] = convertToTimezone($item->created_at);
                $data['updated_at'] = convertToTimezone($item->updated_at);

                return $data;
            });

            return $this->success($history, 'Property history retrieved successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : getPropertyHistoryById', $e);
        }
    }

    public function getPropertyApprovalHistoryById(Request $request, $id)
    {
        $query = Property::query();

        if (isAgent()) {
            $query->where('agentId', user()->id);
        }

        $property = $query->find($id);

        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        try {
            $perPage = $request->input('per_page', 10);

            $query = PropertyApprovalHistory::with(['auditor'])
                ->where('listingId', $id);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'LIKE', "%{$search}%")
                        ->orWhereRaw('LOWER(status) LIKE ?', ['%'.strtolower($search).'%'])
                        ->orWhereHas('auditor', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('email', 'LIKE', "%{$search}%");
                        });
                });
            }

            $history = $query->orderBy('id', 'desc')->paginate($perPage);

            return $this->success(
                [
                    'data' => PropertyApprovalHistoryResource::collection($history->items()),
                    'current_page' => $history->currentPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'last_page' => $history->lastPage(),
                    'from' => $history->firstItem(),
                    'to' => $history->lastItem(),
                ],
                $history->isEmpty()
                    ? 'No approval history found for this property.'
                    : 'Property approval history retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : getPropertyApprovalHistoryById', $e);
        }
    }

    public function exportPropertiesCsv(Request $request)
    {
        if (isAgent()) {
            if ($actor = user()) {
                NotifyDataExportJob::dispatch($actor->id, Constants::MODULE_PROPERTY, 0, now()->toISOString(), 'blocked');
            }

            return $this->error('Agents are not allowed to export data.', 403);
        }

        try {
            if (! $request->filled('per_page')) {
                $request->merge(['per_page' => 100000]);
            }

            $paginator = $this->propertyService->listProperties($request);
            $properties = $paginator->getCollection();
            $properties->loadMissing(['physicalOwner:id,name', 'listingOwner:id,name', 'meta']);

            $fileName = 'properties_'.now()->format('Y_m_d_H_i_s').'.csv';

            $resolveConstant = function ($value, $map) {
                if (isset($map[$value])) {
                    return $map[$value];
                }
                $camel = Str::camel($value);
                if (isset($map[$camel])) {
                    return $map[$camel];
                }
                $lower = lcfirst($value);
                if (isset($map[$lower])) {
                    return $map[$lower];
                }
                $lower2 = strtolower($value);
                if (isset($map[$lower2])) {
                    return $map[$lower2];
                }

                return $value;
            };
            $viewMap = [
                'sea' => 'Sea View',
                'city' => 'City View',
                'marina' => 'Marina View',
                'garden' => 'Garden View',
                'pool' => 'Pool View',
                'park' => 'Park View',
                'golf' => 'Golf View',
                'burj_khalifa' => 'Burj Khalifa View',
                'lake' => 'Lake View',
                'community' => 'Community View',
                'street' => 'Street View',
                'courtyard' => 'Courtyard View',
                'landmark' => 'Landmark View',
            ];
            $listingSourceMap = [
                'owner' => 'Owner',
                'developer' => 'Developer',
                'exclusive' => 'Exclusive',
                'open' => 'Open Listing',
            ];

            $properties->transform(function ($p) use ($resolveConstant, $viewMap, $listingSourceMap) {
                $p->category = $p->category ? $resolveConstant($p->category, Constants::PROPERTY_CLASSIFICATION_TYPE) : null;
                $p->listingType = $resolveConstant($p->listingType, Constants::PROPERTY_PURPOSE_TYPE);
                $p->propertyType = $this->propertyService->getPropertyTypeLabel($p->pfPropertyType, $p->bayutPropertyType);
                $p->completionStatus = $resolveConstant($p->completionStatus, Constants::COMPLETION_STATUS);

                $p->agent_name = $p->agent?->name;
                // A physical owner merged into another contact exports as the surviving contact.
                $p->owner_name = $p->physicalOwner?->masterOrSelf()->name;
                $p->listing_owner_name = $p->listingOwner?->name;

                $p->city_name = $p->city?->name;
                $p->community_name = $p->community?->name;
                $p->sub_community_name = $p->subCommunity?->name;
                $p->building_name = $p->buildingTower?->name;

                $p->pfAmenities = is_array($p->pfAmenities)
                    ? implode(', ', array_map(fn ($key) => $resolveConstant($key, Constants::PF_AMENITIES['all']), $p->pfAmenities))
                    : $p->pfAmenities;

                $p->bayutAmenities = is_array($p->bayutAmenities)
                    ? implode(', ', array_map(fn ($key) => $resolveConstant($key, Constants::PROPERTY_BAYUT_AMENITIES_KEYS), $p->bayutAmenities))
                    : $p->bayutAmenities;

                $p->pfPriceFrequency = $p->pfPriceFrequency ? $resolveConstant($p->pfPriceFrequency, Constants::PROPERTY_PRICE_FREQUENCIES) : null;
                $p->pfCheques = $p->pfCheques ? $resolveConstant($p->pfCheques, Constants::PF_NUMBER_OF_CHEQUES) : null;
                $p->pfUnitNumber = $p->meta?->firstWhere('metaKey', 'pfUnitNumber')?->metaValue
                    ?? $p->meta?->firstWhere('metaKey', 'unitNo')?->metaValue
                    ?? $p->unitNo
                    ?? null;

                $p->bayutOccupancyStatus = $p->bayutOccupancyStatus ? $resolveConstant($p->bayutOccupancyStatus, Constants::BAYUT_OCCUPANCY_STATUS) : null;
                $p->bayutMaintenanceFeePaidBy = $p->bayutMaintenanceFeePaidBy ? $resolveConstant($p->bayutMaintenanceFeePaidBy, Constants::BAYUT_MAINTENANCE_PAID_BY) : null;
                $p->access = $p->access ? $resolveConstant($p->access, [
                    'Key' => 'Key',
                    'Access Code' => 'Access Code',
                    'Security/Guard' => 'Security/Guard',
                ]) : null;
                $p->listingLeadSource2 = $p->listingLeadSource2 ? $resolveConstant($p->listingLeadSource2, $listingSourceMap) : null;
                $p->view = $p->view ? $resolveConstant($p->view, $viewMap) : null;
                $p->sold = $p->sold ? 'Yes' : 'No';

                $managedRaw = $p->managedType;
                if (is_string($managedRaw) && $managedRaw !== '') {
                    $decoded = json_decode($managedRaw, true);
                    if (is_array($decoded)) {
                        $managedRaw = $decoded;
                    } else {
                        $managedRaw = array_values(array_filter(array_map('trim', explode(',', $managedRaw))));
                    }
                }
                if (is_array($managedRaw)) {
                    $p->managedType = implode(', ', array_map(fn ($key) => $resolveConstant($key, Constants::PROPERTY_MANAGED_TYPE), $managedRaw));
                } else {
                    $p->managedType = $managedRaw ? $resolveConstant($managedRaw, Constants::PROPERTY_MANAGED_TYPE) : null;
                }

                $p->govtQR = $p->govtQR ? getImageUrl($p->govtQR) : null;

                $p->status = $p->status ? $resolveConstant($p->status, Constants::PROPERTY_STATUS) : null;
                $p->approvalStatus = $p->approvalStatus ? $resolveConstant($p->approvalStatus, Constants::PROPERTY_APPROVAL_STATUS) : null;

                $p->createdBy = $p->creator?->name;
                $p->created_at = convertToTimezone($p->created_at);

                return $p;
            });
            $columnMap = [
                'title' => ['label' => 'Title', 'value' => fn ($e) => $e->title],
                'description' => ['label' => 'Description', 'value' => fn ($e) => $e->description],
                'referenceNumber' => ['label' => 'Reference No', 'value' => fn ($e) => $e->referenceNumber],
                'category' => ['label' => 'Category', 'value' => fn ($e) => $e->category],
                'listingType' => ['label' => 'Listing Type', 'value' => fn ($e) => $e->listingType],
                'managedType' => ['label' => 'Managed Type', 'value' => fn ($e) => $e->managedType],
                'sizeArea' => ['label' => 'Size Area', 'value' => fn ($e) => $e->sizeArea],
                'plotSize' => ['label' => 'Plot Size', 'value' => fn ($e) => $e->plotSize],
                'bedrooms' => ['label' => 'Bedrooms', 'value' => fn ($e) => $e->bedrooms],
                'bathrooms' => ['label' => 'Bathrooms', 'value' => fn ($e) => formatDecimal($e->bathrooms)],
                // Furnishing lives in property meta (pfFurnishingType / bayutFurnishingStatus),
                // not a column — resolve it to a human label for the export.
                'furnishing' => ['label' => 'Furnished', 'value' => function ($e) {
                    $raw = optional($e->meta->firstWhere('metaKey', 'pfFurnishingType'))->metaValue
                        ?? optional($e->meta->firstWhere('metaKey', 'bayutFurnishingStatus'))->metaValue;

                    return $raw ? (Constants::PROPERTY_FURNISHING_TYPE[$raw] ?? ucwords(str_replace('-', ' ', $raw))) : null;
                }],
                'completionStatus' => ['label' => 'Completion Status', 'value' => fn ($e) => $e->completionStatus],
                'price' => ['label' => 'Price', 'value' => fn ($e) => $e->price],
                'reraPermitNo' => ['label' => 'RERA Permit No', 'value' => fn ($e) => $e->reraPermitNo],
                'govtQR' => ['label' => 'Govt QR', 'value' => fn ($e) => $e->govtQR],
                'agentId' => ['label' => 'Agent', 'value' => fn ($e) => $e->agent_name],
                'listingOwnerId' => ['label' => 'Listing Owner', 'value' => fn ($e) => $e->listing_owner_name],
                'physicalOwnerId' => ['label' => 'Physical Owner', 'value' => fn ($e) => $e->owner_name],
                'propertyType' => ['label' => 'Property Type', 'value' => fn ($e) => $e->propertyType],
                'pfCity' => ['label' => 'City', 'value' => fn ($e) => $e->city_name],
                'pfCommunity' => ['label' => 'Community', 'value' => fn ($e) => $e->community_name],
                'pfSubCommunity' => ['label' => 'Sub Community', 'value' => fn ($e) => $e->sub_community_name],
                'pfBuilding' => ['label' => 'Building/Tower', 'value' => fn ($e) => $e->building_name],
                'pfUnitNumber' => ['label' => 'Pf Unit Number', 'value' => fn ($e) => $e->pfUnitNumber],
                'pfLatitude' => ['label' => 'PF Latitude', 'value' => fn ($e) => $e->pfLatitude],
                'pfLongitude' => ['label' => 'PF Longitude', 'value' => fn ($e) => $e->pfLongitude],
                'pfAmenities' => ['label' => 'PF Amenities', 'value' => fn ($e) => $e->pfAmenities],
                'bayutLocation' => ['label' => 'Bayut Location', 'value' => fn ($e) => $e->bayutLocation],
                'bayutLocationId' => ['label' => 'Bayut Location ID', 'value' => fn ($e) => $e->bayutLocationId],
                'bayutAmenities' => ['label' => 'Bayut Amenities', 'value' => fn ($e) => $e->bayutAmenities],
                'pfPriceFrequency' => ['label' => 'PF Price Frequency', 'value' => fn ($e) => $e->pfPriceFrequency],
                'pfCheques' => ['label' => 'PF Cheques', 'value' => fn ($e) => $e->pfCheques],
                'bayutOccupancyStatus' => ['label' => 'Bayut Occupancy Status', 'value' => fn ($e) => $e->bayutOccupancyStatus],
                'bayutMinimumContractPeriod' => ['label' => 'Bayut Min Contract Period', 'value' => fn ($e) => $e->bayutMinimumContractPeriod],
                'bayutVacatingNoticePeriod' => ['label' => 'Bayut Vacating Notice Period', 'value' => fn ($e) => $e->bayutVacatingNoticePeriod],
                'bayutMaintenanceFee' => ['label' => 'Bayut Maintenance Fee', 'value' => fn ($e) => $e->bayutMaintenanceFee],
                'bayutMaintenanceFeePaidBy' => ['label' => 'Bayut Maintenance Fee Paid By', 'value' => fn ($e) => $e->bayutMaintenanceFeePaidBy],
                'createdBy' => ['label' => 'Created By', 'value' => fn ($e) => $e->createdBy],
                'created_at' => ['label' => 'Created Date', 'value' => fn ($e) => convertToTimezone($e->created_at)],
                'status' => ['label' => 'Status', 'value' => fn ($e) => $e->status],
                'pocketListingDate' => ['label' => 'Pocket Listing Date', 'value' => fn ($e) => optional($e->meta->firstWhere('metaKey', Constants::PROPERTY_META_KEYS['pocketListingDate']))->metaValue],
                'upcomingDate' => ['label' => 'Upcoming Date', 'value' => fn ($e) => optional($e->meta->firstWhere('metaKey', Constants::PROPERTY_META_KEYS['upcomingDate']))->metaValue],
                'access' => ['label' => 'Access', 'value' => fn ($e) => $e->access],
                'listingLeadSource2' => ['label' => 'Listing Lead Source2', 'value' => fn ($e) => $e->listingLeadSource2],
                'view' => ['label' => 'View', 'value' => fn ($e) => $e->view],
                'developmentText' => ['label' => 'Development Text', 'value' => fn ($e) => $e->developmentText],
                'deposit' => ['label' => 'Deposit', 'value' => fn ($e) => $e->deposit],
                'sold' => ['label' => 'Sold', 'value' => fn ($e) => $e->sold],
                'agencyFee' => ['label' => 'Agency Fee', 'value' => fn ($e) => $e->agencyFee],
                'agencyFeePercent' => ['label' => 'Agency Fee Percent', 'value' => fn ($e) => $e->agencyFeePercent],
                'ownershipTerm' => ['label' => 'Ownership Term', 'value' => fn ($e) => $e->ownershipTerm],
                'yearBuilt' => ['label' => 'Year Built', 'value' => fn ($e) => $e->yearBuilt],
                'parkingSpaces' => ['label' => 'Parking Spaces', 'value' => fn ($e) => $e->parkingSpaces],
                'pubDate' => ['label' => 'Pub Date', 'value' => fn ($e) => $e->pubDate],
                'annualRent' => ['label' => 'Annual Rent', 'value' => fn ($e) => $e->annualRent],
                'salePrice' => ['label' => 'Sale Price', 'value' => fn ($e) => $e->salePrice],
                'titleDeedNumber' => ['label' => 'Title Deed Number', 'value' => fn ($e) => $e->titleDeedNumber],
            ];

            if ($request->filled('fields')) {
                $selectedFields = $request->input('fields');
                if (is_string($selectedFields)) {
                    $selectedFields = explode(',', trim($selectedFields, '[] '));
                }

                $selectedFields = array_map(
                    fn ($field) => trim($field) === 'unitNo' ? 'pfUnitNumber' : trim($field),
                    $selectedFields
                );

                $request->merge(['fields' => $selectedFields]);
            }

            if ($actor = user()) {
                NotifyDataExportJob::dispatch($actor->id, Constants::MODULE_PROPERTY, $properties->count(), now()->toISOString());
            }

            return exportCsvStream($request, $properties, $columnMap, $fileName);
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : exportPropertiesCsv', $e);
        }
    }

    public function sendWhatsAppProperty(Request $request)
    {
        $request->validate([
            'propertyId' => 'required',
            'contactPhones' => 'required|array|min:1',
            'type' => 'nullable|string|in:flyer,link,brochure,custom',
            'customMessage' => 'nullable|string',
            'mediaUrl' => 'nullable|string',
            'templateSlug' => 'nullable|string',
            'flyerFormat' => 'nullable|string',
        ]);

        try {
            $property = Property::with([
                'media:id,propertyId,mediaType,compressedPath,originalPath,filePath,fileName',
                'agent:id,name,email,phone',
                'community',
                'subCommunity',
                'buildingTower'
            ])->find($request->propertyId);

            if (! $property) {
                return $this->error('The requested property was not found.', 422);
            }

            $contactPhones = $request->contactPhones;
            $type = $request->type ?? 'link';
            $customMessage = $request->customMessage ?? '';
            $mediaUrl = $request->mediaUrl ?? '';
            $templateSlug = $request->templateSlug;
            $flyerFormat = $request->flyerFormat ?? 'square';

            // If brochure PDF was requested and mediaUrl not provided, generate PDF on-the-fly
            if ($type === 'brochure' && empty($mediaUrl) && ! empty($templateSlug)) {
                try {
                    $storagePath = "propertymediapdf/{$property->id}/{$templateSlug}/{$templateSlug}.pdf";
                    $propertyForDoc = $this->getPropertyForDocumentGeneration($property->id, false);
                    if ($propertyForDoc && array_key_exists($templateSlug, Constants::PDF_TEMPLATES)) {
                        $pdf = $this->propertyPdfService->generatePdf($propertyForDoc, $templateSlug);
                        $mediaUrl = $this->fileStorage->putPublic($storagePath, $pdf->output());
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[WhatsApp send brochure PDF generation error]', ['err' => $e->getMessage()]);
                }
            }

            // Construct the customer message that goes out
            if (! empty($customMessage)) {
                $customerMessage = $customMessage;
                // Only append mediaUrl as text link for non-flyer or when type is link
                if ($type !== 'flyer' && ! empty($mediaUrl) && ! str_contains($customerMessage, $mediaUrl)) {
                    $customerMessage .= "\n" . $mediaUrl;
                }
            } elseif ($type === 'flyer') {
                $details = array_filter([
                    ($property->referenceNumber ?? $property->referenceNo) ? 'Ref: ' . ($property->referenceNumber ?? $property->referenceNo) : null,
                    $property->bedrooms ? $property->bedrooms . ' Bed' : null,
                    $property->price ? 'Price: AED ' . number_format($property->price) : null,
                ]);
                $detailsStr = ! empty($details) ? ' (' . implode(' | ', $details) . ')' : '';
                $customerMessage = ($property->title ?? 'Property Details') . $detailsStr;
            } elseif ($type === 'brochure') {
                $customerMessage = ($property->title ?? 'Property Brochure') . ($mediaUrl ? "\n" . $mediaUrl : '');
            } else {
                $customerMessage = "Property: {$property->title}" . ($mediaUrl ? "\nLink: {$mediaUrl}" : '');
            }

            $metaToken = env('WHATSAPP_ACCESS_TOKEN');
            $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');

            $sendResults = [];

            // Case A: Meta WhatsApp Cloud API is configured
            if (! empty($metaToken) && ! empty($phoneNumberId)) {
                foreach ($contactPhones as $phone) {
                    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
                    $payload = [
                        'messaging_product' => 'whatsapp',
                        'recipient_type' => 'individual',
                        'to' => $cleanPhone,
                    ];

                    if ($type === 'flyer' && ! empty($mediaUrl)) {
                        $payload['type'] = 'image';
                        $payload['image'] = [
                            'link' => $mediaUrl,
                            'caption' => $customMessage ?: ($property->title ?? 'Property Flyer'),
                        ];
                    } elseif ($type === 'brochure' && ! empty($mediaUrl)) {
                        $templateName = (!empty($templateSlug) && isset(Constants::PDF_TEMPLATES[$templateSlug])) 
                            ? Constants::PDF_TEMPLATES[$templateSlug] 
                            : 'Brochure';
                        $cleanRef = preg_replace('/[^a-zA-Z0-9_-]/', '_', ($property->referenceNumber ?? $property->referenceNo ?? $property->id));
                        $filename = 'Property_' . $cleanRef . '_' . ($templateSlug ?: 'Brochure') . '.pdf';
                        $payload['type'] = 'document';
                        $payload['document'] = [
                            'link' => $mediaUrl,
                            'caption' => $customMessage ?: ($property->title ?? 'Property ' . $templateName),
                            'filename' => $filename,
                        ];
                    } else {
                        $payload['type'] = 'text';
                        $payload['text'] = [
                            'preview_url' => true,
                            'body' => $customerMessage,
                        ];
                    }

                    try {
                        $response = \Illuminate\Support\Facades\Http::withToken($metaToken)
                            ->timeout(15)
                            ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", $payload);

                        $sendResults[] = [
                            'phone' => $phone,
                            'status' => $response->status(),
                            'result' => $response->json(),
                        ];
                    } catch (\Throwable $e) {
                        $sendResults[] = [
                            'phone' => $phone,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            } else {
                // Case B: metaToken is empty / not configured (Dev / Mock Mode)
                \Illuminate\Support\Facades\Log::info('[WhatsApp Property Send - Dev/Mock]:', [
                    'propertyId' => $property->id,
                    'reference' => $property->referenceNo ?? $property->id,
                    'contactPhones' => $contactPhones,
                    'type' => $type,
                    'message' => $customerMessage,
                    'mediaUrl' => $mediaUrl,
                    'flyerFormat' => $flyerFormat,
                ]);

                foreach ($contactPhones as $phone) {
                    $sendResults[] = [
                        'phone' => $phone,
                        'status' => 200,
                        'message' => 'WhatsApp queued successfully (Mock/Test mode)',
                    ];
                }
            }

            // Entry in Activity Table (logs customer message)
            try {
                $phonesList = implode(', ', $contactPhones);
                $activityDescription = "Sent WhatsApp to {$phonesList}: {$customerMessage}";

                $params = [
                    'action' => 'sent_whatsapp',
                    'module' => Constants::MODULE_PROPERTY,
                    'moduleId' => $property->id,
                    'description' => $activityDescription,
                    'newValues' => [
                        'message' => $customerMessage,
                        'contactPhones' => $contactPhones,
                        'type' => $type,
                        'mediaUrl' => $mediaUrl,
                        'flyerFormat' => $flyerFormat,
                    ],
                    'extraData' => [
                        'message' => $customerMessage,
                        'contactPhones' => $contactPhones,
                    ],
                ];
                logUserActivity($params);

                // Also log on matched Contact records if available
                $matchedContacts = Contact::whereIn('phone', $contactPhones)->get();
                foreach ($matchedContacts as $contact) {
                    logUserActivity([
                        'action' => 'sent_whatsapp',
                        'module' => Constants::MODULE_CONTACT,
                        'moduleId' => $contact->id,
                        'description' => "Sent WhatsApp for property {$property->title}: {$customerMessage}",
                        'newValues' => [
                            'propertyId' => $property->id,
                            'message' => $customerMessage,
                        ],
                    ]);
                }

                logPropertyHistory([
                    'propertyId' => $property->id,
                    'field' => 'whatsapp_sent',
                    'newValue' => json_encode([
                        'message' => $customerMessage,
                        'contactPhones' => $contactPhones,
                    ]),
                ]);
            } catch (\Throwable $t) {
                // ignore activity log failures
            }

            return $this->success([
                'sentCount' => count($contactPhones),
                'results' => $sendResults,
            ], 'WhatsApp message sent successfully');

        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : sendWhatsAppProperty', $e);
        }
    }

    public function sendEmailProperty(Request $request, $id)
    {
        $request->validate([
            'contactEmails' => 'required|array|min:1',
            'contactEmails.*' => 'required|email',
        ]);

        $property = Property::with(['media:id,propertyId,mediaType,compressedPath,originalPath,filePath,fileName', 'physicalOwner:id,name', 'agent:id,name,email', 'city', 'community', 'subCommunity', 'buildingTower'])->find($id);
        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        $validContacts = Contact::whereIn('email', $request->contactEmails)
            ->pluck('email')
            ->toArray();

        $skippedEmails = array_diff($request->contactEmails, $validContacts);

        if (empty($validContacts)) {
            return $this->error('None of the provided emails exist in contacts', 422);
        }

        try {
            $attachmentPhotos = [];
            $attachmentVideos = [];
            $attachmentFloorPlans = [];
            $attachmentDocuments = [];

            foreach ($property->media as $mediaFile) {
                try {
                    if (in_array($mediaFile->mediaType, ['uploadPhotos', 'uploadVideos'])) {
                        $storedPath = $mediaFile->compressedPath ?? $mediaFile->originalPath ?? $mediaFile->filePath;
                    } else {
                        $storedPath = $mediaFile->originalPath ?? $mediaFile->filePath;
                    }

                    if (empty($storedPath)) {
                        continue;
                    }

                    $relativePath = preg_replace('#^/?storage/#', '', $storedPath);
                    $filePath = storage_path('app/public/'.$relativePath);
                    $fileName = $mediaFile->fileName ?? basename($storedPath);

                    if (! file_exists($filePath)) {
                        Log::warning("Attachment file not found for property {$id}: {$filePath}");

                        continue;
                    }

                    $attachment = [
                        'path' => $filePath,
                        'name' => $fileName,
                        'url' => getImageUrl($storedPath),
                    ];

                    if ($mediaFile->mediaType === 'uploadPhotos') {
                        $attachmentPhotos[] = $attachment;
                    } elseif ($mediaFile->mediaType === 'uploadVideos') {
                        $attachmentVideos[] = $attachment;
                    } elseif ($mediaFile->mediaType === 'floorPlans') {
                        $attachmentFloorPlans[] = $attachment;
                    } elseif ($mediaFile->mediaType === 'uploadDocuments') {
                        $attachmentDocuments[] = $attachment;
                    }
                } catch (\Exception $e) {
                    return $this->exceptionResponse('PropertiesController : sendEmailProperty : processingMediaFile', $e);
                }
            }

            $attachmentListHtml = view('EmailTemplate.partials.propertyAttachmentList', [
                'photos' => $attachmentPhotos,
                'videos' => $attachmentVideos,
                'floorPlans' => $attachmentFloorPlans,
                'documents' => $attachmentDocuments,
            ])->render();

            $allAttachments = array_merge(
                $attachmentPhotos,
                $attachmentVideos,
                $attachmentFloorPlans,
                $attachmentDocuments
            );

            $totalFiles = count($attachmentPhotos) + count($attachmentFloorPlans) + count($attachmentDocuments);
            $totalVideos = count($attachmentVideos);

            $totalEmailsSent = 0;
            $failedEmails = [];

            $location = collect([
                $property->city?->name,
                $property->community?->name,
                $property->subCommunity?->name,
                $property->buildingTower?->name,
            ])->filter()->implode(', ');

            $emailSlug = Constants::EMAIL_SLUGS['property']['sent_property_details_contact'];
            $emailData = [
                'fullName' => ucwords($property->physicalOwner?->masterOrSelf()->name ?? ''),
                'companyName' => getCompanyName(),
                'referenceNumber' => $property->referenceNumber ?? $property->id,
                'propertyTitle' => $property->title,
                'city' => $property->city?->name ?? 'N/A',
                'community' => $property->community?->name ?? 'N/A',
                'subCommunity' => $property->subCommunity?->name ?? 'N/A',
                'tower' => $property->buildingTower?->name ?? 'N/A',
                'propertyLocation' => $location ?: 'N/A',
                'listingStatus' => $property->status ?? 'N/A',
                'listingPrice' => $property->price ?? 'N/A',
                'attachmentList' => $attachmentListHtml,
                'companyEmail' => config('mail.from.address'),
                'companyPhone' => config('app.phone', ''),
            ];

            $fromEmail = $property->agent?->email;
            $fromName = $property->agent?->name;

            foreach ($validContacts as $contactEmail) {
                try {
                    $this->mailService->sendEmail(
                        $emailSlug,
                        $contactEmail,
                        $emailData,
                        $allAttachments,
                        [],
                        $fromEmail,
                        $fromName
                    );
                    $totalEmailsSent++;
                } catch (\Exception $e) {
                    return $this->exceptionResponse('PropertiesController : sendEmailProperty : sendEmail', $e);
                }

            }

            if ($totalEmailsSent === 0) {
                return $this->error('Failed to send emails to all contacts', 500);
            }

            logPropertyHistory([
                'propertyId' => $property->id,
                'field' => 'email_sent',
                'newValue' => json_encode(['message' => 'Email sent to '.$totalEmailsSent." contact(s) with {$totalFiles} file(s) and {$totalVideos} video(s)"]),
            ]);

            $response = [
                'emails_sent' => $totalEmailsSent,
                'total_emails' => count($request->contactEmails),
                'valid_emails' => count($validContacts),
            ];

            if (! empty($skippedEmails)) {
                $response['skipped_emails'] = array_values($skippedEmails);
                $response['skipped_count'] = count($skippedEmails);
            }

            if (! empty($failedEmails)) {
                $response['failed_emails'] = $failedEmails;
                $response['failed_count'] = count($failedEmails);
            }

            $message = "Email sent successfully to {$totalEmailsSent} contact(s)";
            if (! empty($skippedEmails)) {
                $message .= '. Skipped '.count($skippedEmails).' email(s) not found in contacts';
            }
            if (! empty($failedEmails)) {
                $message .= '. Failed to send to '.count($failedEmails).' contact(s)';
            }

            $params = [
                'action' => 'sent_email',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $property->id,
                'description' => "Sent property email for: {$property->title} to {$totalEmailsSent} contact(s)",
            ];
            logUserActivity($params);

            return $this->success($response, $message);
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : sendEmailProperty', $e);
        }
    }

    public function sendEnquiryQuotationByProperty(Request $request)
    {
        $request->validate([
            'propertyId' => 'required|exists:properties,id',
            'enquiryIds' => 'required|array|min:1',
            'enquiryIds.*' => 'exists:enquiries,id',
        ]);

        $property = Property::with('agent:id,name,email')->find($request->propertyId);

        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        $ownerEmail = $property->physicalOwner?->masterOrSelf()->email;
        $ownerName = $property->physicalOwner?->masterOrSelf()->name;

        if (empty($ownerEmail)) {
            return $this->error('Property owner email is not available.', 422);
        }

        $enquiriesQuery = Enquiry::with(['agent', 'contact'])
            ->whereIn('id', $request->enquiryIds);

        $enquiries = $enquiriesQuery->get();

        if ($enquiries->isEmpty()) {
            return $this->error('No enquiries found', 422);
        }

        $cellStyle = 'padding:10px 12px; text-align:left; border:1px solid #e5e7eb;';
        $matchingTableRows = $enquiries->values()->map(function ($enquiry, $index) use ($property, $ownerName, $cellStyle) {
            return sprintf(
                '<tr><td style="%s">%d</td><td style="%s">%s</td><td style="%s">%s</td><td style="%s">%s</td><td style="%s">%s</td></tr>',
                $cellStyle, $index + 1,
                $cellStyle, $property->referenceNo ?? $property->id,
                $cellStyle, $enquiry->reference_no ?? $enquiry->id,
                $cellStyle, $enquiry->agent?->name ?? 'N/A',
                $cellStyle, $ownerName ?? 'N/A'
            );
        })->implode('');

        $replacements = [
            'name' => ucwords($ownerName ?? 'Client'),
            'email' => $ownerEmail,
            'matching_table_rows' => $matchingTableRows,
            'agent_name' => ucwords(user()->name ?? ''),
            'company_name' => getCompanyName(),
        ];
        try {
            $this->mailService->sendEmail(
                Constants::EMAIL_SLUGS['enquiry']['daily_listing_matching'],
                $ownerEmail,
                $replacements,
                [],
                [],
                $property->agent?->email,
                $property->agent?->name
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : sendEnquiryQuotationByProperty', $e);
        }

        try {
            logPropertyHistory([
                'propertyId' => $property->id,
                'field' => 'daily_listing_matching',
                'newValue' => json_encode(['message' => 'Quotation email sent with selected enquiries']),
            ]);

            $params = [
                'action' => 'sent_email',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $property->id,
                'description' => "Sent enquiry quotation email for property: {$property->title} to {$ownerEmail}",
            ];
            logUserActivity($params);

            return $this->success(null, 'Quotation email sent successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : sendEnquiryQuotationByProperty', $e);
        }
    }

    public function getPropertyMedia(Request $request, $propertyId)
    {
        try {
            $perPage = $request->input('per_page', 10);

            $files = PropertyMediaFile::where('propertyId', $propertyId)
                ->orderBy('ordering', 'asc')
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Property media retrieved successfully',
                'data' => PropertyMediaResource::collection($files),
                'meta' => [
                    'current_page' => $files->currentPage(),
                    'from' => $files->firstItem(),
                    'last_page' => $files->lastPage(),
                    'per_page' => $files->perPage(),
                    'to' => $files->lastItem(),
                    'total' => $files->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : getPropertyMedia', $e);
        }
    }

    public function getUploadPropertyMediaFiles($id)
    {
        try {
            $mediaFile = PropertyMediaFile::where('id', $id)
                ->whereIn('mediaType', ['uploadPhotos', 'uploadVideos'])
                ->first();

            if (! $mediaFile) {
                return $this->error('Media file not found', 422);
            }

            $filePath = in_array($mediaFile->mediaType, ['uploadPhotos', 'uploadVideos'])
                ? ($mediaFile->compressedPath ?? $mediaFile->filePath)
                : $mediaFile->filePath;

            $formattedData = [
                'id' => $mediaFile->id,
                'propertyId' => $mediaFile->propertyId,
                'category' => $mediaFile->category,
                'isWebsite' => $mediaFile->isWebsite,
                'isPortals' => $mediaFile->isPortals,
                'isFlyer' => $mediaFile->isFlyer,
                'fileName' => $mediaFile->fileName,
                'compressedPath' => getImageUrl($filePath),
                'mediaType' => $mediaFile->mediaType,
                'ordering' => $mediaFile->ordering,
                'createdBy' => $mediaFile->createdBy,
                'created_at' => convertToTimezone($mediaFile->created_at),
            ];

            return $this->success(
                $formattedData,
                'Property media files retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : getUploadPropertyMediaFiles', $e);
        }
    }

    public function deletePropertyMedia(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:property_media_files,id',
        ]);

        try {
            $ids = $request->ids;
            $propertyIds = [];
            $deletedIdsByProperty = [];

            foreach ($ids as $id) {
                $mediaFile = PropertyMediaFile::find($id);
                if (! $mediaFile) {
                    continue;
                }

                $propertyIds[] = $mediaFile->propertyId;
                $deletedIdsByProperty[$mediaFile->propertyId][] = $id;

                $this->propertyService->deletePropertyMedia($id);

                logUserActivity([
                    'action' => 'deleted_media',
                    'module' => Constants::MODULE_PROPERTY,
                    'moduleId' => $mediaFile->propertyId,
                    'description' => "Deleted media file (ID: {$id}) from property ID: {$mediaFile->propertyId}",
                ]);
            }

            // Auto-generate PDFs for affected properties
            $deletedCounts = array_count_values($propertyIds);
            $actor = user();
            foreach (array_unique($propertyIds) as $propertyId) {
                // GeneratePropertyPdfsJob::dispatch((int) $propertyId);

                // One timeline entry per property, not one per file — a bulk delete
                // of N images otherwise wrote N identical "deleted media files" rows.
                $deletedIds = $deletedIdsByProperty[$propertyId] ?? [];
                $deletedCount = count($deletedIds);

                logPropertyHistory([
                    'propertyId' => $propertyId,
                    'field' => 'deleted_media',
                    'originalValue' => json_encode(['message' => 'Media IDs: '.implode(', ', $deletedIds)]),
                    'newValue' => json_encode(['message' => $deletedCount.' media file(s) deleted']),
                ]);

                // Notify admins + listing agent when media changes on a published listing — queued.
                if ($actor) {
                    NotifyListingMediaUpdatedJob::dispatch((int) $propertyId, 'removed', $deletedCounts[$propertyId] ?? null, $actor->id);
                }
            }

            return $this->success(null, count($ids).' media file(s) deleted successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : deletePropertyMedia', $e);
        }
    }

    /**
     * Delete a single property video by its id.
     * DELETE /deletePropertyVideo  { "id": <videoId> }
     */
    public function deletePropertyVideo(Request $request)
    {
        $id = $request->input('id');

        if (empty($id)) {
            return $this->error('Video id is required', 422);
        }

        try {
            $video = PropertyVideos::find($id);

            if (! $video) {
                return $this->error('The requested video was not found.', 422);
            }

            $propertyId = $video->propertyId;
            $video->delete();

            logUserActivity([
                'action' => 'deleted_video',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $propertyId,
                'description' => "Deleted video (ID: {$id}) from property ID: {$propertyId}",
            ]);

            return $this->success(null, 'Property video deleted successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : deletePropertyVideo', $e);
        }
    }

    /**
     * Step 1: Agent requests a permit number for an Upcoming property.
     * POST /property/{propertyId}/request-permit
     */
    public function requestPermit($propertyId)
    {
        $property = Property::find($propertyId);

        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        // if ($property->status !== 'upcoming') {
        //     return $this->error('Permit request can only be sent for properties with Upcoming status', 422);
        // }

        try {
            if (! $this->propertyService->hasMinimumPropertyImages($property, [], 10)) {
                return $this->error('Permit request requires at least 10 images.', 422);
            }

            $property = $this->propertyService->requestPermit($property, $this->mailService);

            return $this->success(
                ['property' => $property],
                'Permit request sent successfully.'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : requestPermit', $e);
        }
    }

    /**
     * Step 2: Admin enters the permit/DCIM number. Status changes to Draft & agent is notified.
     * POST /property/{propertyId}/enter-permit
     */
    public function enterPermitNumber(Request $request, $propertyId)
    {
        $property = Property::find($propertyId);

        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        // if (! in_array($property->approvalStatus, [Constants::PERMIT_REQUESTED, Constants::APPROVAL_REJECTED])) {
        //     return $this->error('Permit number can only be entered for properties with Permit Requested or Rejected status', 422);
        // }

        $validator = Validator::make($request->all(), [
            'reraPermitNo' => 'required|string|max:255',
            'transactionNumber' => 'required|string|max:255',
            'govtQR' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'expireOn' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $property = $this->propertyService->enterPermitNumber(
                $property,
                $request->reraPermitNo,
                $this->mailService,
                $request->file('govtQR'),
                $request->transactionNumber,
                $request->expireOn
            );

            return $this->success(
                ['property' => $property],
                'Permit number entered successfully.'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : enterPermitNumber', $e);
        }
    }

    public function updatePermitDetails(Request $request, $propertyId)
    {
        $property = Property::find($propertyId);

        if (! $property) {
            return $this->error('Property not found', 422);
        }

        $validator = Validator::make($request->all(), [
            'reraPermitNo' => 'sometimes|nullable|string|max:255',
            'transactionNumber' => 'sometimes|nullable|string|max:255',
            'govtQR' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'expireOn' => 'sometimes|nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $publishedListingKeyDataBefore = $this->propertyService->getPublishedListingKeyDataSnapshot($property);
            $updateData = [];

            // $reraPermitNoChanged = $request->filled('reraPermitNo') && $request->reraPermitNo !== $property->reraPermitNo;

            if ($request->filled('reraPermitNo')) {
                $updateData['reraPermitNo'] = $request->reraPermitNo;
            }

            if ($request->hasFile('govtQR')) {
                $file = $request->file('govtQR');
                $fileName = time().'_govtQR_'.$property->id.'.'.$file->getClientOriginalExtension();
                $base = $this->fileStorage->recordPrefix('properties', $property->referenceNumber, $property->id);
                $updateData['govtQR'] = $this->fileStorage->uploadPublicFile($file, $base.'/govtQR', $fileName);
            }

            $hasUpdates = ! empty($updateData);

            if ($request->filled('transactionNumber')) {
                PropertyMeta::updateOrCreate(
                    ['propertyId' => $property->id, 'metaKey' => Constants::PROPERTY_META_KEYS['transactionNumber']],
                    ['metaValue' => $request->transactionNumber],
                );
                $hasUpdates = true;
            }

            if ($request->filled('expireOn')) {
                PropertyMeta::updateOrCreate(
                    ['propertyId' => $property->id, 'metaKey' => Constants::PROPERTY_META_KEYS['expireOn']],
                    ['metaValue' => $request->expireOn],
                );
                $hasUpdates = true;
            }

            if ($hasUpdates) {
                if (! empty($updateData)) {
                    $property->update($updateData);
                }
                $property->refresh();

            }

            $property->refresh();
            $property->loadMissing('meta');

            // Permit details (re)entered → a new government QR is no longer pending.
            PropertyMeta::updateOrCreate(
                ['propertyId' => $property->id, 'metaKey' => 'newQrRequired'],
                ['metaValue' => 0],
            );

            // Notify admins (CRM + email) when key data of a published listing changes — queued.
            if (! empty($publishedListingKeyDataBefore) && ($actor = user())) {
                NotifyPublishedListingKeyDataChangedJob::dispatch($property->id, $publishedListingKeyDataBefore, $actor->id);
            }

            return response()->json([
                'status' => true,
                'message' => 'Permit details updated successfully',
                'data' => [
                    'id' => $property->id,
                    'reraPermitNo' => $property->reraPermitNo,
                    'govtQR' => $property->govtQR ? getImageUrl($property->govtQR) : null,
                    'transactionNumber' => optional($property->meta->firstWhere('metaKey', Constants::PROPERTY_META_KEYS['transactionNumber']))->metaValue,
                    'expireOn' => optional($property->meta->firstWhere('metaKey', Constants::PROPERTY_META_KEYS['expireOn']))->metaValue,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : updatePermitDetails', $e);
        }
    }

    /**
     * Step 3: Agent requests approval for a draft property.
     * POST /property/{propertyId}/request-approval
     */
    public function requestApproval($propertyId)
    {
        $property = Property::find($propertyId);

        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        if ($property->status !== 'draft') {
            return $this->error('Approval can only be requested for properties with Draft status.', 422);
        }

        try {
            $property = $this->propertyService->requestApproval($property, $this->mailService);

            return $this->success(
                ['property' => $property],
                'Approval request sent successfully.'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : requestApproval', $e);
        }
    }

    /**
     * Step 4: Manager/Admin approves or rejects a pending property.
     * POST /property/{propertyId}/approve-reject
     */
    public function approveRejectProperty(Request $request, $propertyId)
    {
        $validator = Validator::make($request->all(), [
            'approvalStatus' => 'required|in:'.Constants::APPROVAL_APPROVED.','.Constants::APPROVAL_REJECTED,
            'comment' => 'required_if:approvalStatus,'.Constants::APPROVAL_REJECTED.'|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $property = Property::find($propertyId);

        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        if (! in_array($property->listingType, Constants::PROPERTY_INT_AND_OFF_LISTING_TYPES, true) && $property->status !== 'pending') {
            return $this->error('Only properties with Pending status can be approved or rejected.', 422);
        }

        if (isAgent()) {
            return $this->error('Only managers or admins can approve or reject properties.', 403);
        }

        try {
            $status = $request->approvalStatus;
            $comment = $request->comment ?? '';

            if ($status === Constants::APPROVAL_APPROVED
                && ! $this->propertyService->hasMinimumPropertyImages($property, [], 10)) {
                return $this->error('Approval requires at least 10 images.', 422);
            }

            $property = $this->propertyService->approveRejectProperty($property, $status, $comment, $this->mailService);

            $statusLabel = $status === 'approved' ? 'approved' : 'rejected';

            return $this->success(
                ['property' => $property],
                "Property {$statusLabel} successfully."
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : approveRejectProperty', $e);
        }
    }

    /**
     * Step 5: Only the manager/admin who approved the listing can publish it.
     * POST /property/{propertyId}/publish
     */
    public function publishProperty($propertyId)
    {
        $property = Property::find($propertyId);

        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        if ($property->approvalStatus !== Constants::APPROVAL_APPROVED) {
            return $this->error('Only approved properties can be published', 422);
        }

        // Only the manager/admin who approved can publish
        if (! isAdmin() && $property->approvedBy !== user()->id) {
            return $this->error('Only the admin or manager who approved this listing can publish it', 403);
        }

        try {
            if (! $this->propertyService->hasMinimumPropertyImages($property, [], 10)) {
                return $this->error('Property must have at least 10 images to be published.', 422);
            }

            $property = $this->propertyService->publishProperty($property, $this->mailService);

            return $this->success(
                ['property' => $property],
                'Property published successfully.'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : publishProperty', $e);
        }
    }

    public function getPropertyByUserId(Request $request, $id)
    {
        try {
            $properties = $this->propertyService
                ->getPropertyByUserId($id, $request);

            return $this->success(
                GetPropertyByUserResource::collection($properties),
                'Properties retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : getPropertyByUserId', $e);
        }
    }

    public function sendEmailBySelectedId($propertyId)
    {
        $property = Property::with(['media', 'physicalOwner:id,name,email', 'city', 'community', 'subCommunity', 'buildingTower'])->find($propertyId);

        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        $ownerEmail = $property->physicalOwner?->masterOrSelf()->email;
        $ownerName = $property->physicalOwner?->masterOrSelf()->name;

        if (empty($ownerEmail)) {
            return $this->error('Property owner email not available', 422);
        }

        try {
            $attachmentPhotos = [];
            $attachmentVideos = [];
            $attachmentFloorPlans = [];
            $attachmentDocuments = [];

            foreach ($property->media as $mediaFile) {
                try {
                    if (in_array($mediaFile->mediaType, ['uploadPhotos', 'uploadVideos'])) {
                        $storedPath = $mediaFile->compressedPath ?? $mediaFile->originalPath ?? $mediaFile->filePath;
                    } else {
                        $storedPath = $mediaFile->originalPath ?? $mediaFile->filePath;
                    }

                    if (empty($storedPath)) {
                        continue;
                    }

                    $relativePath = preg_replace('#^/?storage/#', '', $storedPath);
                    $filePath = storage_path('app/public/'.$relativePath);
                    $fileName = $mediaFile->fileName ?? basename($storedPath);

                    if (! file_exists($filePath)) {
                        continue;
                    }

                    $attachment = [
                        'path' => $filePath,
                        'name' => $fileName,
                        'url' => getImageUrl($storedPath),
                    ];

                    if ($mediaFile->mediaType === 'uploadPhotos') {
                        $attachmentPhotos[] = $attachment;
                    } elseif ($mediaFile->mediaType === 'uploadVideos') {
                        $attachmentVideos[] = $attachment;
                    } elseif ($mediaFile->mediaType === 'floorPlans') {
                        $attachmentFloorPlans[] = $attachment;
                    } elseif ($mediaFile->mediaType === 'uploadDocuments') {
                        $attachmentDocuments[] = $attachment;
                    }
                } catch (\Exception $e) {
                    return $this->exceptionResponse('PropertiesController : sendEmailBySelectedId : processingMediaFile', $e);
                }
            }

            $attachmentListHtml = view('EmailTemplate.partials.propertyAttachmentList', [
                'photos' => $attachmentPhotos,
                'videos' => $attachmentVideos,
                'floorPlans' => $attachmentFloorPlans,
                'documents' => $attachmentDocuments,
            ])->render();

            $allAttachments = array_merge(
                $attachmentPhotos,
                $attachmentVideos,
                $attachmentFloorPlans,
                $attachmentDocuments
            );

            $location = collect([
                $property->city?->name,
                $property->community?->name,
                $property->subCommunity?->name,
                $property->buildingTower?->name,
            ])->filter()->implode(', ');

            $this->mailService->sendEmail(
                Constants::EMAIL_SLUGS['property']['sent_property_details'],
                $ownerEmail,
                [
                    'fullName' => ucwords($ownerName ?? ''),
                    'companyName' => getCompanyName(),
                    'referenceNumber' => $property->referenceNumber ?? $property->id,
                    'propertyTitle' => $property->title,
                    'city' => $property->city?->name ?? 'N/A',
                    'community' => $property->community?->name ?? 'N/A',
                    'subCommunity' => $property->subCommunity?->name ?? 'N/A',
                    'tower' => $property->buildingTower?->name ?? 'N/A',
                    'propertyLocation' => $location ?: 'N/A',
                    'listingStatus' => $property->status ?? 'N/A',
                    'listingPrice' => $property->price ?? 'N/A',
                    'attachmentList' => $attachmentListHtml,
                    'companyEmail' => config('mail.from.address'),
                    'companyPhone' => config('app.phone', ''),
                ],
                $allAttachments
            );

            $totalFiles = count($attachmentPhotos) + count($attachmentFloorPlans) + count($attachmentDocuments);
            $totalVideos = count($attachmentVideos);

            logPropertyHistory([
                'propertyId' => $property->id,
                'field' => 'email_sent',
                'newValue' => json_encode(['message' => "Email sent to owner ({$ownerEmail}) with {$totalFiles} file(s) and {$totalVideos} video(s)"]),
            ]);

            $params = [
                'action' => 'sent_email',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $property->id,
                'description' => "Sent email for property: {$property->title} to owner ({$ownerEmail})",
            ];
            logUserActivity($params);

            return $this->success(null, 'Email sent successfully to '.$ownerEmail);
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : sendEmailBySelectedId', $e);
        }
    }

    public function sendManualMail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'propertyId' => 'required|integer',
            'ownerEmail' => 'required|email',
            'subject' => 'required',
            'emailContent' => 'required',
            'attachments.*' => 'nullable|file|max:20480',
        ]);
        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }
        try {
            $fileName = null;
            $filePath = null;
            $attachments = [];
            if ($request->hasFile('attachments')) {
                $files = $request->file('attachments');
                if (! is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $file) {
                    // Store the attachment on S3 (public) but attach the still-valid
                    // local upload to the email, which is sent within this request.
                    $localPath = $file->getRealPath();
                    $fileUrl = $this->fileStorage->uploadPublicFile($file, 'property-custom-mail');
                    $fileName = $file->getClientOriginalName();
                    $filePath = $fileUrl;
                    $attachments[] = [
                        'path' => $localPath,
                        'name' => $fileName,
                    ];
                }
            }
            $emailRecord = PropertyCustomEmailTemplate::create([
                'propertyId' => $request->propertyId,
                'ownerEmail' => $request->ownerEmail,
                'subject' => $request->subject,
                'emailContent' => $request->emailContent,
                'fileName' => $fileName,
                'filePath' => $filePath,
            ]);
            $html = view('EmailTemplate.templateLayout', [
                'title' => $emailRecord->subject,
                'body' => $emailRecord->emailContent,
            ])->render();

            $property = Property::with('agent:id,name,email')->find($request->propertyId);
            $agentUser = $property?->agent;

            Mail::to($emailRecord->ownerEmail)->send(
                new DynamicMail(
                    $emailRecord->subject,
                    $html,
                    $attachments,
                    $agentUser?->email,
                    $agentUser?->name
                )
            );

            $this->mailService->logContactEmail(
                $emailRecord->ownerEmail,
                $emailRecord->emailContent
            );

            $params = [
                'action' => 'sent_custom_email',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $request->propertyId,
                'description' => "Sent custom email for property ID: {$request->propertyId} to {$request->ownerEmail}",
            ];
            logUserActivity($params);

            logPropertyHistory([
                'propertyId' => $request->propertyId,
                'field' => 'sent_custom_email',
                'newValue' => json_encode(['message' => "Custom email sent to {$request->ownerEmail}"]),
            ]);

            return $this->success(
                new PropertyCustomEmailTemplateResource($emailRecord),
                'Email saved and sent successfully'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : sendManualMail', $e);
        }
    }

    public function composeEmail(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|email',
            'cc' => 'nullable|array',
            'cc.*' => 'email',
            'subject' => 'required|string',
            'body' => 'required|string',
            'attachOption' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $property = Property::with(['agent:id,name,email', 'city', 'community', 'subCommunity', 'buildingTower'])->find($id);
        if (! $property) {
            return $this->error('The requested property was not found.', 422);
        }

        try {
            $emailBody = $request->body;

            if ($request->attachOption === 'inline' || $request->attachOption === 'both') {
                $location = collect([
                    $property->city?->location ?? null,
                    $property->community?->community ?? null,
                    $property->subCommunity?->subCommunity ?? null,
                    $property->buildingTower?->tower ?? null,
                ])->filter()->implode(', ');

                $emailBody .= '<hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">';
                $emailBody .= '<h3 style="margin-bottom: 10px;">Property Details</h3>';
                $emailBody .= '<table style="width: 100%; border-collapse: collapse; font-size: 14px;">';
                $emailBody .= '<tr><td style="padding: 6px 10px; font-weight: bold; width: 40%;">Reference No.</td><td style="padding: 6px 10px;">'.htmlspecialchars($property->referenceNumber ?? '-').'</td></tr>';
                $emailBody .= '<tr><td style="padding: 6px 10px; font-weight: bold;">Title</td><td style="padding: 6px 10px;">'.htmlspecialchars($property->title ?? '-').'</td></tr>';
                $emailBody .= '<tr><td style="padding: 6px 10px; font-weight: bold;">Category</td><td style="padding: 6px 10px;">'.htmlspecialchars($property->category ?? '-').'</td></tr>';
                $emailBody .= '<tr><td style="padding: 6px 10px; font-weight: bold;">Listing Type</td><td style="padding: 6px 10px;">'.htmlspecialchars($property->listingType ?? '-').'</td></tr>';
                $emailBody .= '<tr><td style="padding: 6px 10px; font-weight: bold;">Bedrooms</td><td style="padding: 6px 10px;">'.htmlspecialchars($property->bedrooms ?? '-').'</td></tr>';
                $emailBody .= '<tr><td style="padding: 6px 10px; font-weight: bold;">Bathrooms</td><td style="padding: 6px 10px;">'.htmlspecialchars(formatDecimal($property->bathrooms) ?? '-').'</td></tr>';
                $emailBody .= '<tr><td style="padding: 6px 10px; font-weight: bold;">Size/Area (sqft)</td><td style="padding: 6px 10px;">'.htmlspecialchars($property->sizeArea ?? '-').'</td></tr>';
                $emailBody .= '<tr><td style="padding: 6px 10px; font-weight: bold;">Price</td><td style="padding: 6px 10px;">'.htmlspecialchars($property->price ?? '-').'</td></tr>';
                $emailBody .= '<tr><td style="padding: 6px 10px; font-weight: bold;">Completion Status</td><td style="padding: 6px 10px;">'.htmlspecialchars($property->completionStatus ?? '-').'</td></tr>';
                $emailBody .= '</table>';
            }

            $html = view('EmailTemplate.templateLayout', [
                'title' => $request->subject,
                'body' => $emailBody,
            ])->render();

            $mail = Mail::to($request->to);

            if (! empty($request->cc)) {
                $mail->cc($request->cc);
            }

            $mail->send(
                new DynamicMail(
                    $request->subject,
                    $html,
                    [],
                    $property->agent?->email,
                    $property->agent?->name
                )
            );

            $this->mailService->logContactEmail($request->to, $request->body);

            if (! empty($request->cc)) {
                foreach ($request->cc as $ccEmail) {
                    $this->mailService->logContactEmail($ccEmail, $request->body);
                }
            }

            $params = [
                'action' => 'compose_email',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $id,
                'description' => "Composed email for property ID: {$id} to {$request->to}".(! empty($request->cc) ? ' cc: '.implode(', ', $request->cc) : ''),
                'extraData' => [
                    'to' => $request->to,
                    'cc' => ! empty($request->cc) ? implode(', ', $request->cc) : null,
                    'subject' => $request->subject,
                    'emailContent' => $request->body,
                ],
            ];
            logUserActivity($params);

            logPropertyHistory([
                'propertyId' => $id,
                'field' => 'compose_email',
                'newValue' => json_encode(['message' => "Email composed and sent to {$request->to}".(! empty($request->cc) ? ' cc: '.implode(', ', $request->cc) : '')]),
                'extraData' => [
                    'to' => $request->to,
                    'cc' => ! empty($request->cc) ? implode(', ', $request->cc) : null,
                    'subject' => $request->subject,
                    'emailContent' => $request->body,
                ],
            ]);

            Task::create([
                'subject' => $request->subject,
                'assignedTo' => user()->id,
                'moduleId' => $id,
                'module' => Constants::MODULE_PROPERTY,
                'type' => 'email',
                'dueDate' => now(),
                'status' => 'done',
                'comments' => "Email sent to {$request->to}".(! empty($request->cc) ? ' cc: '.implode(', ', $request->cc) : ''),
                'createdBy' => user()->id,
            ]);

            return $this->success(null, 'Email sent successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : composeEmail', $e);
        }
    }

    public function reorderPropertyMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'propertyId' => 'required|integer|exists:properties,id',
            'orderings' => 'required|array',
            'orderings.*.id' => 'required|integer|exists:property_media_files,id',
            'orderings.*.ordering' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            foreach ($request->orderings as $item) {
                PropertyMediaFile::where('id', $item['id'])
                    ->where('propertyId', $request->propertyId)
                    ->update(['ordering' => $item['ordering']]);
            }

            $params = [
                'action' => 'reordered_media',
                'module' => Constants::MODULE_PROPERTY,
                'moduleId' => $request->propertyId,
                'description' => "Reordered media files for property ID: {$request->propertyId}",
            ];
            logUserActivity($params);

            logPropertyHistory([
                'propertyId' => $request->propertyId,
                'field' => 'reordered_media',
                'newValue' => json_encode(['message' => 'Media reordered']),
            ]);

            // Auto-generate PDFs if property has website media
            // GeneratePropertyPdfsJob::dispatch((int) $request->propertyId);

            // Image order is part of the portal payload — push the new ordering to
            // any published Bayut/PF listings.
            SyncPropertyToPortalsJob::dispatch(
                (int) $request->propertyId,
                SyncPropertyToPortalsJob::MODE_SYNC,
                ['media'],
            );

            return $this->success(null, 'Media ordering updated successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : reorderPropertyMedia', $e);
        }
    }

    public function getPropertyByCreatedBy($createdBy)
    {
        try {
            if (! is_numeric($createdBy)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid createdBy ID',
                ], 400);
            }

            $data = Property::where('createdBy', $createdBy)->get();
            if ($data->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Record Found',
                    'data' => [],
                ], 422);
            }

            return response()->json([
                'status' => true,
                'message' => 'Properties fetched successfully',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : getPropertyByCreatedBy', $e);
        }
    }

    public function updatePublishFlags(Request $request, $propertyId)
    {
        $validator = Validator::make($request->all(), [
            'publishOnWebsite' => 'sometimes|boolean',
            'publishOnGeneric' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        if (! $request->has('publishOnWebsite') && ! $request->has('publishOnGeneric')) {
            return $this->error('At least one of publishOnWebsite or publishOnGeneric is required', 422);
        }

        $property = Property::find($propertyId);

        if (! $property) {
            return $this->error('Property not found', 422);
        }

        try {
            $changed = [];

            foreach (['publishOnWebsite', 'publishOnGeneric'] as $flag) {
                if (! $request->has($flag)) {
                    continue;
                }

                $metaKey = Constants::PROPERTY_META_KEYS[$flag];

                $oldValue = PropertyMeta::where('propertyId', $property->id)
                    ->where('metaKey', $metaKey)
                    ->value('metaValue') === '1';
                $newValue = $request->boolean($flag);

                if ($newValue) {
                    PropertyMeta::updateOrCreate(
                        ['propertyId' => $property->id, 'metaKey' => $metaKey],
                        ['metaValue' => '1'],
                    );
                } else {
                    PropertyMeta::where('propertyId', $property->id)
                        ->where('metaKey', $metaKey)
                        ->delete();
                }

                if ($oldValue !== $newValue) {
                    $changed[$flag] = ['old' => $oldValue, 'new' => $newValue];

                    logPropertyHistory([
                        'propertyId' => $property->id,
                        'field' => $flag,
                        'originalValue' => json_encode(['message' => $oldValue ? 'enabled' : 'disabled']),
                        'newValue' => json_encode(['message' => $newValue ? 'enabled' : 'disabled']),
                    ]);
                }
            }

            if (! empty($changed)) {
                logUserActivity([
                    'action' => 'updated_publish_flags',
                    'module' => Constants::MODULE_PROPERTY,
                    'moduleId' => $property->id,
                    'description' => 'Updated publish flags for property ID: '.$property->id.' ('.implode(', ', array_keys($changed)).')',
                ]);
            }

            // Toggling publishOnGeneric changes what appears in the generic XML
            // feed, but only writes/deletes a meta row (no Property save), so the
            // model-event rebuild doesn't fire here — dispatch it explicitly.
            if (isset($changed['publishOnGeneric'])) {
                GenerateGenericFeedJob::dispatch()->afterCommit();
            }

            $property->loadMissing('meta');
            $metaByKey = $property->meta->pluck('metaValue', 'metaKey');

            return $this->success([
                'id' => $property->id,
                'publishOnWebsite' => (string) $metaByKey->get(Constants::PROPERTY_META_KEYS['publishOnWebsite']) === '1',
                'publishOnGeneric' => (string) $metaByKey->get(Constants::PROPERTY_META_KEYS['publishOnGeneric']) === '1',
            ], 'Publish flags updated successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('PropertiesController : updatePublishFlags', $e);
        }
    }

    public function exclusiveLinksListings(Request $request)
    {
        // Raise limits for this endpoint only (large feed pages can be heavy).
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        try {
            // Single-property lookup (debugging / re-import). When ?id=, ?prop_id= or
            // ?referenceNumber= is supplied, return just that property in the same
            // payload shape — and intentionally WITHOUT the feed's status/website
            // filters, so a draft or un-published listing can still be inspected.
            $singleId = $request->input('id', $request->input('prop_id'));
            $singleRef = $request->input('referenceNumber');

            if ($singleId !== null || $singleRef !== null) {
                $property = Property::with([
                    'mediaThumbnails', 'media', 'documentCenters', 'videos', 'agent', 'city', 'community',
                    'subCommunity', 'buildingTower', 'meta', 'salesforceIdMap',
                ])
                    ->when($singleId !== null, fn ($q) => $q->where('id', $singleId))
                    ->when($singleId === null && $singleRef !== null, fn ($q) => $q->where('referenceNumber', $singleRef))
                    ->first();

                $listings = $property
                    ? [(new PropertyCrmResource($property))->toArray($request)]
                    : [];

                $payload = [
                    'page' => 0,
                    'listingsPerPage' => 1,
                    'numberOfListings' => count($listings),
                    'fatalError' => false,
                    'errorMessages' => $property ? [] : ['Property not found.'],
                    'listings' => $listings,
                ];

                return response()->json($payload);
            }

            $paginator = $this->propertyService->getPortalFeedListings(
                $request,
                Constants::PROPERTY_META_KEYS['publishOnWebsite']
            );

            $listings = collect($paginator->items())
                ->map(fn ($property) => (new PropertyCrmResource($property))->toArray($request))
                ->all();

            $payload = [
                'page' => max(0, $paginator->currentPage() - 1),
                'listingsPerPage' => $paginator->perPage(),
                'numberOfListings' => $paginator->total(),
                'fatalError' => false,
                'errorMessages' => [],
                'listings' => $listings,
            ];

            return response()->json($payload);
        } catch (\Throwable $e) {
            return $this->exceptionResponse('PropertiesController : exclusiveLinksListings', $e);
        }
    }

    /** Absolute path of the pre-built generic feed file. */
    public static function genericFeedPath(): string
    {
        return storage_path('app/generic-feed.xml');
    }

    public function exclusiveLinksListingsXml(Request $request)
    {
        $metaKey = Constants::PROPERTY_META_KEYS['publishOnGeneric'];

        // Filtered pulls (updated_after/before, listingType) return a small subset,
        // so they are cheap to build live and streamed.
        if ($request->hasAny(['updated_after', 'updated_before', 'listingType'])) {
            return $this->streamGenericFeed($this->genericFeedQuery($request, $metaKey));
        }

        // Full feed: serve the pre-built file only. Generation runs in the background
        // command (app:generate-generic-feed), NEVER in the web request, so a large
        // catalogue can never time out / crash the web tier — this is just a file read.
        $path = self::genericFeedPath();
        if (is_file($path)) {
            return response()->file($path, [
                'Content-Type' => 'application/xml; charset=utf-8',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        // File not built yet (before the first scheduled/manual run). Return 503 so the
        // portal retries, rather than an empty feed it might read as "0 listings".
        return response('Feed is being prepared, please retry shortly.', 503, [
            'Retry-After' => '60',
        ]);
    }

    /**
     * Generic portal feed query — restricted to FOR SALE and FOR RENT listings.
     * Off-plan and international listings are "Own Website" only and must never
     * appear in the Generic XML feed.
     */
    private function genericFeedQuery(Request $request, string $metaKey)
    {
        return $this->propertyService->portalFeedQuery($request, $metaKey)
            ->with('agent.meta') // for listing_agent_about_me (avoids an N+1 per listing)
            ->whereIn('listingType', [
                Constants::PROPERTY_PURPOSE_SALE,
                Constants::PROPERTY_PURPOSE_RENT,
            ]);
    }

    /**
     * Build the generic feed file and return its path. Called by the
     * app:generate-generic-feed command (CLI — no web timeout).
     */
    public function generateGenericFeedFile(): string
    {
        $path = self::genericFeedPath();
        $this->writeGenericFeedToFile(new Request, Constants::PROPERTY_META_KEYS['publishOnGeneric'], $path);

        return $path;
    }

    /**
     * Build the full feed to a file in constant memory (chunked), then atomically
     * swap it in. Runs off the web timeout so a large catalogue can't crash it.
     */
    private function writeGenericFeedToFile(Request $request, string $metaKey, string $path): void
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $tmp = $path.'.'.getmypid().'.tmp';
        $fh = fopen($tmp, 'w');
        if ($fh === false) {
            return;
        }

        fwrite($fh, '<?xml version="1.0" encoding="utf-8"?><propertybase_feed>');

        $this->genericFeedQuery($request, $metaKey)
            ->chunkById(200, function ($listings) use ($fh) {
                $buffer = '';
                foreach ($listings as $property) {
                    $doc = new \DOMDocument('1.0', 'utf-8');
                    $buffer .= $doc->saveXML($this->buildListingNode($doc, $property));
                }
                fwrite($fh, $buffer);
            });

        fwrite($fh, '</propertybase_feed>');
        fclose($fh);

        @rename($tmp, $path); // atomic: readers always see a complete file
    }

    /**
     * Stream the feed directly (used for filtered pulls). chunkById keeps memory flat
     * and bytes are flushed as they are produced.
     */
    private function streamGenericFeed($query)
    {
        return response()->stream(function () use ($query) {
            echo '<?xml version="1.0" encoding="utf-8"?><propertybase_feed>';

            try {
                $query->chunkById(200, function ($listings) {
                    $buffer = '';
                    foreach ($listings as $property) {
                        $doc = new \DOMDocument('1.0', 'utf-8');
                        $buffer .= $doc->saveXML($this->buildListingNode($doc, $property));
                    }
                    echo $buffer;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('exclusiveLinksListingsXml stream: '.$e->getMessage());
            }

            echo '</propertybase_feed>';
        }, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function buildListingNode(\DOMDocument $dom, $property): \DOMElement
    {
        $meta = $property->relationLoaded('meta')
            ? $property->meta->pluck('metaValue', 'metaKey')
            : collect();

        $isChange = $property->updated_at && $property->created_at
            && $property->updated_at->diffInSeconds($property->created_at) > 1;

        $listing = $dom->createElement('listing');
        $listing->setAttribute('created_at', $property->created_at?->format('Y-m-d\TH:i:sP') ?? '');
        $listing->setAttribute('mode', $isChange ? 'CHANGE' : 'NEW');
        $listing->setAttribute('timestamp', $property->updated_at?->format('Y-m-d\TH:i:sP') ?? '');

        $this->appendXmlText($dom, $listing, 'id', $property->referenceNumber);

        $listing->appendChild($this->buildAddressNode($dom, $property, $meta));
        $listing->appendChild($this->buildGeneralNode($dom, $property));
        $listing->appendChild($this->buildAgentNode($dom, $property));
        $listing->appendChild($this->buildMediaNode($dom, $property));
        $listing->appendChild($this->buildCustomFieldsNode($dom, $property, $meta));

        return $listing;
    }

    private function buildAddressNode(\DOMDocument $dom, $property, $meta): \DOMElement
    {
        $address = $dom->createElement('address_information');
        $this->appendXmlText($dom, $address, 'city', optional($property->city)->name);
        $this->appendXmlText($dom, $address, 'address', $meta->get('propertyAddress'));
        $this->appendXmlText($dom, $address, 'latitude', $property->pfLatitude);
        $this->appendXmlText($dom, $address, 'longitude', $property->pfLongitude);

        return $address;
    }

    private function buildGeneralNode(\DOMDocument $dom, $property): \DOMElement
    {
        $general = $dom->createElement('general_listing_information');
        $this->appendXmlText($dom, $general, 'listing_title', $property->title);
        $this->appendXmlText($dom, $general, 'listingprice', $property->price);
        $this->appendXmlText($dom, $general, 'currency_iso_code', 'AED');
        $this->appendXmlText($dom, $general, 'listingtype', $property->listingType ? ucfirst($property->listingType) : null);
        $this->appendXmlText($dom, $general, 'measurement', 'Non-Metric');
        // Reference uses the Salesforce record id here; fall back to the numeric id
        // for CRM-native listings that were never imported from Salesforce.
        $this->appendXmlText($dom, $general, 'property', $property->salesforceIdMap?->salesforceId ?? $property->id);
        $this->appendXmlText($dom, $general, 'propertytype', $property->pfPropertyType);
        $this->appendXmlText($dom, $general, 'status', 'Active');
        $this->appendXmlText($dom, $general, 'totalarea', $property->sizeArea);
        $this->appendXmlText($dom, $general, 'description', strip_tags((string) $property->description) ?: null);
        $this->appendXmlText($dom, $general, 'bedrooms', $property->bedrooms);
        $this->appendXmlText($dom, $general, 'fullbathrooms', $property->bathrooms);

        return $general;
    }

    private function buildAgentNode(\DOMDocument $dom, $property): \DOMElement
    {
        $agent = $property->agent;
        $node = $dom->createElement('listing_agent');

        if (! $agent) {
            return $node;
        }

        $nameParts = $agent->name ? explode(' ', $agent->name) : [];
        $first = $nameParts[0] ?? null;
        $last = count($nameParts) > 1 ? end($nameParts) : null;

        $this->appendXmlText($dom, $node, 'listing_agent_email', $agent->email);
        $this->appendXmlText($dom, $node, 'listing_agent_firstname', $first);
        $this->appendXmlText($dom, $node, 'listing_agent_lastname', $last);
        $this->appendXmlText($dom, $node, 'listing_agent_licence', $agent->agentCode);
        $this->appendXmlText($dom, $node, 'listing_agent_phone', $agent->phone);
        $this->appendXmlText($dom, $node, 'listing_agent_photo', $agent->profilePhoto ? getImageUrl($agent->profilePhoto) : null);

        return $node;
    }

    private function buildMediaNode(\DOMDocument $dom, $property): \DOMElement
    {
        $node = $dom->createElement('listing_media');
        $imagesNode = $dom->createElement('images');

        if ($property->relationLoaded('mediaThumbnails')) {
            foreach ($property->mediaThumbnails as $media) {
                $url = getImageUrl($media->originalPath ?? $media->filePath);
                $imageEl = $dom->createElement('image');
                $this->appendXmlText($dom, $imageEl, 'title', $media->fileName);
                $this->appendXmlText($dom, $imageEl, 'url', $url);
                $imagesNode->appendChild($imageEl);
            }
        }

        $node->appendChild($imagesNode);

        return $node;
    }

    private function buildCustomFieldsNode(\DOMDocument $dom, $property, $meta): \DOMElement
    {
        $cf = $dom->createElement('custom_fields');
        $agent = $property->agent;
        $cityName = optional($property->city)->name;
        $imageCount = $property->relationLoaded('mediaThumbnails') ? $property->mediaThumbnails->count() : 0;

        $managedTypes = array_filter(array_map(
            fn ($v) => trim(strtolower($v)),
            explode(',', (string) $property->managedType)
        ));

        $this->appendXmlText($dom, $cf, 'pdf_template_title', $property->title);
        $this->appendXmlText($dom, $cf, 'owner_mobile', optional($agent)->phone);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__city_propertyfinder', $cityName);
        $this->appendXmlText($dom, $cf, 'qr_code', $property->govtQR);

        $this->appendXmlText($dom, $cf, 'listing_agent_name',
            $agent && $agent->name ? str_replace(' ', '-', strtolower($agent->name)) : null);

        $this->appendXmlText($dom, $cf, 'pba__yearbuilttext_pb', $property->yearBuilt);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__private_amenities',
            is_array($property->pfAmenities) ? implode(';', $property->pfAmenities) : $property->pfAmenities);
        $this->appendXmlText($dom, $cf, 'pba__propertyownercontact_pb', $property->physicalOwnerId);
        $this->appendXmlText($dom, $cf, 'pba__totalbathrooms', $property->bathrooms !== null ? number_format((float) $property->bathrooms, 1, '.', '') : null);
        $this->appendXmlText($dom, $cf, 'year_build_text', $property->yearBuilt);
        $this->appendXmlText($dom, $cf, 'listing_agent_profile_pic',
            $agent && $agent->profilePhoto ? getImageUrl($agent->profilePhoto) : null);

        $this->appendXmlText($dom, $cf, 'pba__geocodeaccuracy_pb', $meta->get('geocodeAccuracy') ?: 'Address');
        $this->appendXmlText($dom, $cf, 'active_portal_images_pb', $imageCount);
        $this->appendXmlText($dom, $cf, 'pba__systemallowedforportals',
            $property->status === Constants::PROPERTY_STATUS_PUBLISHED ? 'true' : 'false');

        $this->appendXmlText($dom, $cf, 'pba_uaefields__propertyfinder_region', $meta->get('pfRegion'));
        $this->appendXmlText($dom, $cf, 'pb_autonumber', $property->id);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__location_source', 'Propertyfinder');
        $this->appendXmlText($dom, $cf, 'pba_uaefields__sub_community_propertyfinder', optional($property->subCommunity)->name);
        $this->appendXmlText($dom, $cf, 'transaction_number', $meta->get('transactionNumber'));
        $this->appendXmlText($dom, $cf, 'property_owner_lead_source', $property->listingLeadSource2);
        $this->appendXmlText($dom, $cf, 'pba__listingagentname', optional($agent)->name);
        $this->appendXmlText($dom, $cf, 'request_permit', $property->permitRequestedAt ? 'true' : 'false');
        $this->appendXmlText($dom, $cf, 'record_owner_full_name', optional($agent)->name);

        $this->appendXmlText($dom, $cf, 'pba_uaefields__community_propertyfinder', optional($property->community)->name);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__property_sub_type', $meta->get('pfSubType'));
        $this->appendXmlText($dom, $cf, 'pba__longitude_property', $property->pfLongitude);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__developer_name', $meta->get('pfDeveloper'));
        $this->appendXmlText($dom, $cf, 'annual_rental_yeild', $meta->get('annualRentalPrice'));
        $this->appendXmlText($dom, $cf, 'num_baths', $property->bathrooms);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__parking', $property->parkingSpaces);

        $this->appendXmlText($dom, $cf, 'property_owner_id', $property->physicalOwnerId);
        $this->appendXmlText($dom, $cf, 'record_owner_id', $property->createdBy);
        $this->appendXmlText($dom, $cf, 'recordtypeid', $meta->get('recordTypeId'));
        $this->appendXmlText($dom, $cf, 'community_name', optional($property->community)->name);
        $this->appendXmlText($dom, $cf, 'access', $property->access);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__furnished',
            $this->yesNoFromFurnished($meta->get('bayutFurnishingStatus') ?? $meta->get('pfFurnishingType')));
        $this->appendXmlText($dom, $cf, 'expire_on', $meta->get('expireOn'));
        $this->appendXmlText($dom, $cf, 'pba__websiteupdatedat',
            $property->updated_at?->format('Y-m-d\TH:i:s.000+0000'));

        $this->appendXmlText($dom, $cf, 'pba_uaefields__property_propertyfinder', optional($property->buildingTower)->name);
        $this->appendXmlText($dom, $cf, 'template_description_rich', $property->description);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__city_dubizzle', $cityName);
        $this->appendXmlText($dom, $cf, 'pba__addresstext_pb', $meta->get('propertyAddress'));

        $this->appendXmlText($dom, $cf, 'pba_uaefields__completion_status', $this->humanizeCompletionStatus($property->completionStatus));
        $completionDate = $meta->get('completionDate');
        $this->appendXmlText($dom, $cf, 'pba__completiondate_pb', $completionDate
            ? \Illuminate\Support\Carbon::parse($completionDate)->format('Y-m-d\TH:i:s.000+0000')
            : null);
        // Reference shows "Studio" for a zero-bedroom unit.
        $this->appendXmlText($dom, $cf, 'num_beds',
            ($property->bedrooms === null || (int) $property->bedrooms === 0) ? 'Studio' : (string) $property->bedrooms);
        $this->appendXmlText($dom, $cf, 'managed_type', $property->managedType);
        $this->appendXmlText($dom, $cf, 'pba__unitnumber', $meta->get('pfUnitNumber'));

        $this->appendXmlText($dom, $cf, 'agent_code', optional($agent)->agentCode);
        $this->appendXmlText($dom, $cf, 'furnished_type', $this->humanize($meta->get('pfFurnishingType')));
        $this->appendXmlText($dom, $cf, 'web_site_url_formula_for_vt', $meta->get('websiteUrl'));

        $this->appendXmlText($dom, $cf, 'pba__latitude_property', $property->pfLatitude);
        $this->appendXmlText($dom, $cf, 'community', $property->pfCommunity);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__external_id', $meta->get('pfExternalId'));

        $this->appendXmlText($dom, $cf, 'pba_uaefields__price_unit',
            $property->pfPriceFrequency ? 'Price '.ucfirst($property->pfPriceFrequency) : null);

        $this->appendXmlText($dom, $cf, 'pba_uaefields__rera_permit_number', $property->reraPermitNo);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__view', $property->view);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__title_deed', $property->titleDeedNumber);
        $this->appendXmlText($dom, $cf, 'pba_uaefields__available_from', $meta->get('pfAvailableFrom'));
        $this->appendXmlText($dom, $cf, 'pba_uaefields__availability_date', $meta->get('pfAvailableFrom'));
        $this->appendXmlText($dom, $cf, 'deposit', $property->deposit);

        $this->appendXmlText($dom, $cf, 'is_featured', in_array(Constants::MANAGED_TYPE_FEATURE, $managedTypes, true) ? 'true' : 'false');
        $this->appendXmlText($dom, $cf, 'website_url', $meta->get('websiteUrl'));

        // Additional fields to mirror the reference Propertybase feed.
        $sfId = $property->salesforceIdMap?->salesforceId;
        $this->appendXmlText($dom, $cf, 'listing_lead_source_2', $property->listingLeadSource2);
        $this->appendXmlText($dom, $cf, 'listing_agent_about_me',
            $agent?->meta?->firstWhere('metaKey', '_description')?->metaValue);
        $this->appendXmlText($dom, $cf, 'portals_enabled', collect([
            $meta->get('publishOnWebsite') ? 'website' : null,
            $meta->get('publishOnGeneric') ? 'generic' : null,
            $meta->get('bayutPortalListingData') ? 'bayut' : null,
            $meta->get('pfPortalListingData') ? 'propertyfinder' : null,
        ])->filter()->implode(';') ?: null);
        $this->appendXmlText($dom, $cf, 'approval_completed',
            $property->approvalStatus === Constants::APPROVAL_COMPLETED ? 'true' : 'false');
        $this->appendXmlText($dom, $cf, 'completion_quarter', $meta->get('completionQuarter'));
        $this->appendXmlText($dom, $cf, 'pba__tdlink', '<a href=" " target="_blank">No TD Record Available</a>');
        $this->appendXmlText($dom, $cf, 'pba__zipformlink', '<a href=" " target="_blank">No Zipform Record Available</a>');
        $this->appendXmlText($dom, $cf, 'listing', $sfId
            ? sprintf('<a href="https://my-exclusivelinksrebrokersdubai.lightning.force.com/%s" target="_blank">%s</a>', $sfId, $property->referenceNumber)
            : null);
        $this->appendXmlText($dom, $cf, 'error_email', 'technik@propertybase.com');

        $this->appendXmlText($dom, $cf, 'portal', 'Generic');
        $this->appendXmlText($dom, $cf, 'generator', 'generic');
        $this->appendXmlText($dom, $cf, 'provider_software', 'Propertybase');
        $this->appendXmlText($dom, $cf, 'provider_software_version', '3.0');

        return $cf;
    }

    private function appendXmlText(\DOMDocument $dom, \DOMElement $parent, string $name, $value): void
    {
        // Omit empty fields entirely — the reference Propertybase feed only emits
        // fields that have a value, so skipping null/blank ones keeps the feed lean
        // and faster (no <tag/> placeholders).
        if ($value === null || $value === '') {
            return;
        }

        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode((string) $value));
        $parent->appendChild($element);
    }

    private function yesNoFromFurnished($val): ?string
    {
        if (! $val) {
            return null;
        }

        return strtolower($val) === 'unfurnished' ? 'No' : 'Yes';
    }

    private function humanize($val): ?string
    {
        return $val ? ucfirst($val) : null;
    }

    private function humanizeCompletionStatus($val): ?string
    {
        if (! $val) {
            return null;
        }
        $map = [
            'completedReady' => 'Completed',
            'underOffPlanUnderConstruction' => 'Off Plan / Under Construction',
            'off_plan' => 'Off Plan',
        ];

        return $map[$val] ?? $val;
    }
}
