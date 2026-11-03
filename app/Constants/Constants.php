<?php

namespace App\Constants;

class Constants
{
    // ==================== AUTH ====================

    /** Default minimum password length — used when the admin setting is unset. */
    const MIN_PASSWORD_LENGTH = 8;

    // ==================== USER ====================

    const SUPER_ADMIN = 'SuperAdmin';

    const MANAGER = 'Manager';

    const AGENT = 'Agent';

    const HR = 'HR';

    const CRM_USER_ROLES = [
        self::SUPER_ADMIN,
        self::MANAGER,
        self::AGENT,
        self::HR,
    ];

    const AGENT_TYPE = [
        'agent' => 'Agent',
        'seniorAgent' => 'Senior Agent',
        'leadAgent' => 'Lead Agent',
    ];

    const USER_STATUS_ACTIVE = 'active';

    const USER_STATUS_INACTIVE = 'inActive';

    const USER_STATUS = [
        self::USER_STATUS_ACTIVE => 'Active',
        self::USER_STATUS_INACTIVE => 'InActive',
    ];

    const USER_RESIDENCY_STATUS = [
        'resident' => 'Resident',
        'nonResident' => 'Non-Resident',
    ];

    const USER_META_KEYS = [
        'phone' => '_phone',
        'whatsappNumber' => '_whatsappNumber',
        'gender' => '_gender',
        'nationality' => '_nationality',
        'address' => '_address',
        'country' => '_country',
        'state' => '_state',
        'city' => '_city',
        'zipCode' => '_zipCode',
        'licenseNumber' => '_licenseNumber',
        'licenseExpiryDate' => '_licenseExpiryDate',
        'passportNumber' => '_passportNumber',
        'passportExpiryDate' => '_passportExpiryDate',
        'emiratesIdExpiryDate' => '_emiratesIdExpiryDate',
        'insuranceExpiryDate' => '_insuranceExpiryDate',
        'visaExpiryDate' => '_visaExpiryDate',
        'description' => '_description',
        'idCard' => '_idCard',
        'passportFile' => '_passportFile',
        'emiratesIdFile' => '_emiratesIdFile',
        'visaFile' => '_visaFile',
        'labourContractFile' => '_labourContractFile',
        'ndaFile' => '_ndaFile',
        'employmentContractFile' => '_employmentContractFile',
        'certificatesFile' => '_certificatesFile',
        'insuranceFile' => '_insuranceFile',
        'labourContractExpiryDate' => '_labourContractExpiryDate',
        'employmentContractExpiryDate' => '_employmentContractExpiryDate',
        'ndaExpiryDate' => '_ndaExpiryDate',
        'certificatesExpiryDate' => '_certificatesExpiryDate',
        'labourCardExpiryDate' => '_labourCardExpiryDate',
        'personalEmail' => '_personalEmail',
        'employmentType' => '_employmentType',
        'managerId' => '_managerId',
        'annualLeaveAllowed' => '_annualLeaveAllowed',
        'leaveBalance' => '_leaveBalance',
        'brokerId' => '_brokerId',
        'pfPublicProfileId' => '_pfPublicProfileId',
        'defaultAdmin' => '_defaultAdmin',
    ];

    // ==================== ROLE ====================

    const ROLE_STATUS = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    // ==================== PROPERTY ====================

    const PROPERTY_STATUS_PUBLISHED = 'published';

    const PROPERTY_STATUS_UPCOMING = 'upcoming';

    const PROPERTY_STATUS_DRAFT = 'draft';

    const PROPERTY_STATUS_PENDING = 'pending';

    const PROPERTY_STATUS_UNDER_OFFER = 'underOffer';

    const PROPERTY_STATUS_RENTED_SOLD = 'rentSold';

    const PROPERTY_STATUS_UNPUBLISHED = 'unpublished';

    const PROPERTY_STATUS_POCKET_LISTING = 'pocketListing';

    const PROPERTY_STATUS_LIVE_ON_WEBSITE = 'liveOnWebsite';

    const PROPERTY_STATUS = [
        self::PROPERTY_STATUS_UPCOMING => 'Upcoming',
        self::PROPERTY_STATUS_DRAFT => 'Draft',
        self::PROPERTY_STATUS_PENDING => 'Pending',
        self::PROPERTY_STATUS_PUBLISHED => 'Published',
        self::PROPERTY_STATUS_UNDER_OFFER => 'Under Offer',
        self::PROPERTY_STATUS_RENTED_SOLD => 'Rented/Sold',
        self::PROPERTY_STATUS_UNPUBLISHED => 'Unpublished',
        self::PROPERTY_STATUS_POCKET_LISTING => 'Pocket Listing',
    ];

    const PROPERTY_INT_AND_OFF_STATUS = [
        self::PROPERTY_STATUS_UPCOMING => 'Upcoming',
        self::PROPERTY_STATUS_LIVE_ON_WEBSITE => 'Live on Website',
        self::PROPERTY_STATUS_POCKET_LISTING => 'Pocket Listing',
    ];

    const MATCHING_ALLOWED_STATUSES = [
        self::PROPERTY_STATUS_PUBLISHED,
        self::PROPERTY_STATUS_POCKET_LISTING,
        self::PROPERTY_STATUS_LIVE_ON_WEBSITE,
    ];

    // Statuses under which a listing is live on the external portals (Bayut / PF).
    // Leaving any of these (e.g. on a closing) triggers a portal unpublish.
    const PROPERTY_PORTAL_SYNC_STATUSES = [
        self::PROPERTY_STATUS_PUBLISHED,
        self::PROPERTY_STATUS_POCKET_LISTING,
        self::PROPERTY_STATUS_UNDER_OFFER,
    ];

    const PERMIT_REQUESTED = 'permitRequested';

    const PERMIT_ENTERED = 'permitEntered';

    const APPROVAL_REQUESTED = 'approvalRequested';

    const APPROVAL_APPROVED = 'approved';

    const APPROVAL_REJECTED = 'rejected';

    const APPROVAL_COMPLETED = 'completed';

    const PROPERTY_APPROVAL_STATUS = [
        self::PERMIT_REQUESTED => 'Permit Requested',
        self::PERMIT_ENTERED => 'Permit Entered',
        self::APPROVAL_REQUESTED => 'Approval Requested',
        self::APPROVAL_APPROVED => 'Approved',
        self::APPROVAL_REJECTED => 'Rejected',
        self::APPROVAL_COMPLETED => 'Completed',
    ];

    const PROPERTY_META_KEYS = [
        'flyerData' => 'flyerData',
        'termsConditions' => 'termsConditions',
        'paymentPlans' => 'paymentPlans',

        // Unit types for offplan & International
        'unitType1' => 'unitType1',
        'unitTypeBuildUpArea1' => 'unitTypeBuildUpArea1',
        'unitTypePrice1' => 'unitTypePrice1',
        'unitType2' => 'unitType2',
        'unitTypeBuildUpArea2' => 'unitTypeBuildUpArea2',
        'unitTypePrice2' => 'unitTypePrice2',
        'unitType3' => 'unitType3',
        'unitTypeBuildUpArea3' => 'unitTypeBuildUpArea3',
        'unitTypePrice3' => 'unitTypePrice3',
        'unitType4' => 'unitType4',
        'unitTypeBuildUpArea4' => 'unitTypeBuildUpArea4',
        'unitTypePrice4' => 'unitTypePrice4',
        'unitType5' => 'unitType5',
        'unitTypeBuildUpArea5' => 'unitTypeBuildUpArea5',
        'unitTypePrice5' => 'unitTypePrice5',
        'unitType6' => 'unitType6',
        'unitTypeBuildUpArea6' => 'unitTypeBuildUpArea6',
        'unitTypePrice6' => 'unitTypePrice6',

        'spaceDetails' => 'spaceDetails',
        'keyFeaturesForWebsite' => 'keyFeaturesForWebsite',
        'locationProximityForWebsite' => 'locationProximityForWebsite',
        'websiteUrl' => 'websiteUrl',
        'expireOn' => 'expireOn',
        // Dates the listing entered Pocket / Upcoming status — aged off by
        // app:send-listing-aged-reminder to nudge the agent after 2 weeks.
        'pocketListingDate' => 'pocketListingDate',
        'upcomingDate' => 'upcomingDate',
        'propertySalesValue' => 'propertySalesValue',
        'annualRentalPrice' => 'annualRentalPrice',
        'propertyOwnerLeadSource' => 'propertyOwnerLeadSource',
        'facilities' => 'facilities',
        'pfRegion' => 'pfRegion',
        'pfSubType' => 'pfSubType',
        'pfExternalId' => 'pfExternalId',
        'dubizzleRegion' => 'dubizzleRegion',
        'bayutDubizzleFeed' => 'bayutDubizzleFeed',
        'recordType' => 'recordType',
        'recordTypeId' => 'recordTypeId',
        'measurement' => 'measurement',
        'transactionNumber' => 'transactionNumber',
        'trakheesiPermit' => 'trakheesiPermit',
        'completionDate' => 'completionDate',
        'marketType' => 'marketType',

        // Bayut portal-specific (1:1 keys)
        'bayutFurnishingStatus' => 'bayutFurnishingStatus',
        'bayutConstructionStatus' => 'bayutConstructionStatus',
        'bayutOwnershipStatus' => 'bayutOwnershipStatus',
        'bayutFinancingAvailable' => 'bayutFinancingAvailable',
        'bayutFinancingInstitute' => 'bayutFinancingInstitute',
        'bayutSaleType' => 'bayutSaleType',
        'bayutLocationText' => 'bayutLocationText',

        // Property Address
        'propertyAddress' => 'propertyAddress',
        'propertyCountry' => 'propertyCountry',
        'propertyLat' => 'propertyLat',
        'propertyLong' => 'propertyLong',
        'geocodeAccuracy' => 'geocodeAccuracy',

        // Property Finder portal-specific (1:1 keys)
        'pfProjectStatus' => 'pfProjectStatus',
        'pfFurnishingType' => 'pfFurnishingType',
        'pfFinishingType' => 'pfFinishingType',
        'pfUnitNumber' => 'pfUnitNumber',
        'pfFloorNumber' => 'pfFloorNumber',
        'pfNumberOfFloors' => 'pfNumberOfFloors',
        'pfAge' => 'pfAge',
        'pfAvailableFrom' => 'pfAvailableFrom',
        'pfAvailableTo' => 'pfAvailableTo',
        'pfBuiltUpArea' => 'pfBuiltUpArea',
        'pfPlotNumber' => 'pfPlotNumber',
        'pfLandNumber' => 'pfLandNumber',
        'pfDeveloper' => 'pfDeveloper',
        'pfOwnerName' => 'pfOwnerName',
        'pfHasGarden' => 'pfHasGarden',
        'pfHasKitchen' => 'pfHasKitchen',
        'pfHasParkingOnSite' => 'pfHasParkingOnSite',
        'pfHasParkingSpace' => 'pfHasParkingSpace',
        'pfStreetDirection' => 'pfStreetDirection',
        'pfStreetWidth' => 'pfStreetWidth',
        'pfDownPayment' => 'pfDownPayment',
        'pfDepositPercentage' => 'pfDepositPercentage',
        'pfPaymentMethods' => 'pfPaymentMethods',
        'pfUtilitiesInclusive' => 'pfUtilitiesInclusive',
        'pfMinRentalPeriod' => 'pfMinRentalPeriod',
        'pfPriceOnRequest' => 'pfPriceOnRequest',
        'pfNumberOfMortgageYears' => 'pfNumberOfMortgageYears',
        'pfPriceDaily' => 'pfPriceDaily',
        'pfPriceWeekly' => 'pfPriceWeekly',
        'pfPriceMonthly' => 'pfPriceMonthly',
        'pfPriceYearly' => 'pfPriceYearly',
        'pfComplianceType' => 'pfComplianceType',
        'pfListingAdvertisementNumber' => 'pfListingAdvertisementNumber',
        'pfIssuingClientLicenseNumber' => 'pfIssuingClientLicenseNumber',
        'pfAdvertisementLicenseIssuanceDate' => 'pfAdvertisementLicenseIssuanceDate',

        // Published Portal Meta
        'pfPortalListingData' => 'pfPortalListingData',
        'bayutPortalListingData' => 'bayutPortalListingData',
        'publishOnWebsite' => 'publishOnWebsite',
        'publishOnGeneric' => 'publishOnGeneric',
        'systemAllowedForPortals' => 'systemAllowedForPortals',

        // One-shot flag set to true when a listing is cloned, so the frontend can
        // unlock the listing-type field on the copy (a cloned rental may instead be
        // listed for sale, and vice versa). Reset to false on the first update.
        'isClonedListing' => 'isClonedListing',
    ];

    const PROPERTY_PURPOSE_SALE = 'sale';

    const PROPERTY_PURPOSE_RENT = 'rent';

    const PROPERTY_PURPOSE_OFFPLAN = 'offPlan';

    const PROPERTY_PURPOSE_INTERNATIONAL = 'international';

    const PROPERTY_PURPOSE_TYPE = [
        self::PROPERTY_PURPOSE_SALE => 'Sale',
        self::PROPERTY_PURPOSE_RENT => 'Rent',
        self::PROPERTY_PURPOSE_OFFPLAN => 'Off Plan Project (Own Website)',
        self::PROPERTY_PURPOSE_INTERNATIONAL => 'International (Own Website)',
    ];

    const PROPERTY_INT_AND_OFF_LISTING_TYPES = [
        self::PROPERTY_PURPOSE_OFFPLAN,
        self::PROPERTY_PURPOSE_INTERNATIONAL,
    ];

    const PROPERTY_CATEGORY_RESIDENTIAL = 'residential';

    const PROPERTY_CATEGORY_COMMERCIAL = 'commercial';

    const PROPERTY_CLASSIFICATION_TYPE = [
        self::PROPERTY_CATEGORY_RESIDENTIAL => 'Residential',
        self::PROPERTY_CATEGORY_COMMERCIAL => 'Commercial',
    ];

    const PROPERTY_FINISHING_TYPE = [
        'fully-finished' => 'Fully Finished',
        'semi-finished' => 'Semi Finished',
        'unfinished' => 'Unfinished',
    ];

    const PROPERTY_FURNISHING_TYPE = [
        'furnished' => 'Furnished',
        'semi-furnished' => 'Semi Furnished',
        'unfurnished' => 'Unfurnished',
        // Commercial fit-out states (kept so the Salesforce values aren't lost on import);
        // these are not PropertyFinder furnishing values — see PropertyFinderService::mapFurnishing.
        'white-goods' => 'White goods',
        'fitted' => 'Fitted',
        'shell-core' => 'Shell & Core',
    ];

    const PROPERTY_FURNISHING_TYPE_BAYUT = [
        'furnished' => 'furnished',
        'semi-furnished' => 'furnished',
        'unfurnished' => 'unfurnished',
    ];

    const COMPLETION_STATUS = [
        'completedReady' => 'Completed',
        'completedPrimary' => 'Completed (Primary)',
        'offPlan' => 'Off Plan',
        'offPlanPrimary' => 'Off Plan (Primary)',
    ];

    // Bayut's construction_status field accepts only these two tokens; any other value is
    // rejected by the API with a 422.
    const COMPLETION_STATUS_BAYUT = [
        'completedReady' => 'completed',
        'completedPrimary' => 'completed',
        'offPlan' => 'under-construction',
        'offPlanPrimary' => 'under-construction',
    ];

    const COMPLETION_STATUS_PF = [
        'completedReady' => 'completed',
        'completedPrimary' => 'completed_primary',
        'offPlan' => 'off_plan',
        'offPlanPrimary' => 'off_plan_primary',
    ];

    const MANAGED_TYPE_MANAGED = 'managed';

    // Properties tagged "managed" are all owned by this fixed physical owner (contact id).
    const MANAGED_PROPERTY_PHYSICAL_OWNER_ID = 115;

    const MANAGED_TYPE_VACATION_HOMES = 'vacationHomes';

    const MANAGED_TYPE_EXCLUSIVE = 'exclusive';

    const MANAGED_TYPE_ELITE = 'elite';

    const MANAGED_TYPE_SPECIAL_OFFERS = 'specialOffers';

    const MANAGED_TYPE_FEATURE = 'feature';

    const MANAGED_TYPE_ARABIC = 'arabic';

    const MANAGED_TYPE_FRENCH = 'french';

    const MANAGED_TYPE_POLISH = 'polish';

    const MANAGED_TYPE_CHINESE = 'chinese';

    const MANAGED_TYPE_RUSSIAN = 'russian';

    const PROPERTY_MANAGED_TYPE = [
        self::MANAGED_TYPE_MANAGED => 'Managed',
        self::MANAGED_TYPE_VACATION_HOMES => 'Vacation Homes',
        self::MANAGED_TYPE_EXCLUSIVE => 'Exclusive',
        self::MANAGED_TYPE_ELITE => 'Elite',
        self::MANAGED_TYPE_SPECIAL_OFFERS => 'Special Offer',
        self::MANAGED_TYPE_FEATURE => 'Feature',
        self::MANAGED_TYPE_ARABIC => 'Arabic',
        self::MANAGED_TYPE_FRENCH => 'French',
        self::MANAGED_TYPE_POLISH => 'Polish',
        self::MANAGED_TYPE_CHINESE => 'Chinese',
        self::MANAGED_TYPE_RUSSIAN => 'Russian',
    ];

    const PROPERTY_MEDIA_CATEGORIES = [
        'exterior' => 'Exterior',
        'interior' => 'Interior',
        'bedroom' => 'Bedroom',
        'bathroom' => 'Bathroom',
        'kitchen' => 'Kitchen',
        'living_room' => 'Living Room',
        'dining_room' => 'Dining Room',
        'balcony' => 'Balcony / Terrace',
        'garden' => 'Garden / Outdoor',
        'pool' => 'Pool',
        'lobby' => 'Lobby / Reception',
        'parking' => 'Parking',
        'gym' => 'Gym / Fitness',
        'view' => 'View',
        'floor_plan' => 'Floor Plan',
        'community' => 'Community / Amenities',
        'location_map' => 'Location Map',
        'other' => 'Other',
    ];

    const PROPERTY_MEDIA_KEYS = [
        'uploadVideos' => '_uploadVideos',
        'uploadPhotos' => '_uploadPhotos',
        'floorPlans' => '_floorPlans',
        'uploadDocuments' => '_uploadDocuments',
    ];

    // ==================== PROPERTY - BAYUT ====================

    const PROPERTY_BAYUT_AMENITIES_KEYS = [
        'completionYear' => 'Completion Year',
        'balconyOrTerrace' => 'Balcony or Terrace',
        'lobbyInBuilding' => 'Lobby in Building',
        'flooring' => 'Flooring',
        'elevatorsInBuilding' => 'Elevators in Building',
        'serviceElevators' => 'Service Elevators',
        'prayerRoom' => 'Prayer Room',
        'receptionWaitingRoom' => 'Reception/Waiting Room',
        'totalFloors' => 'Total Floors',
        'doubleGlazedWindows' => 'Double Glazed Windows',
        'centrallyAirConditioned' => 'Centrally Air-Conditioned',
        'centralHeating' => 'Central Heating',
        'electricityBackup' => 'Electricity Backup',
        'furnished' => 'Furnished',
        'parkingSpaces' => 'Parking Spaces',
        'storageAreas' => 'Storage Areas',
        'studyRoom' => 'Study Room',
        'firstAidMedicalCenter' => 'First Aid Medical Center',
        'gymOrHealthClub' => 'Gym or Health Club',
        'jacuzzi' => 'Jacuzzi',
        'sauna' => 'Sauna',
        'steamRoom' => 'Steam Room',
        'swimmingPool' => 'Swimming Pool',
        'facilitiesForDisabled' => 'Facilities for Disabled',
        'barbequeArea' => 'Barbeque Area',
        'dayCareCenter' => 'Day Care Center',
        'kidsPlayArea' => 'Kids Play Area',
        'lawnOrGarden' => 'Lawn or Garden',
        'cafeteriaOrCanteen' => 'Cafeteria or Canteen',
        'businessCenter' => 'Business Center',
        'conferenceRoom' => 'Conference Room',
        'securityStaff' => 'Security Staff',
        'cctvSecurity' => 'CCTV Security',
        'broadbandInternet' => 'Broadband Internet',
        'satelliteCableTV' => 'Satellite/Cable TV',
        'intercom' => 'Intercom',
        'wasteDisposal' => 'Waste Disposal',
        'maintenanceStaff' => 'Maintenance Staff',
        'cleaningServices' => 'Cleaning Services',
        'laundryRoom' => 'Laundry Room',
        'laundryFacility' => 'Laundry Facility',
        'sharedKitchen' => 'Shared Kitchen',
        'view' => 'View',
        'floor' => 'Floor',
        'otherMainFeatures' => 'Other Main Features',
        'freehold' => 'Freehold',
        'petPolicy' => 'Pet Policy',
        'otherRooms' => 'Other Rooms',
        'atmFacility' => 'ATM Facility',
        'otherFacilities' => 'Other Facilities',
        'landArea' => 'Land Area',
        'maidsRoom' => 'Maids Room',
        'numberOfBathrooms' => 'Number of Bathrooms',
        'numberOfBedrooms' => 'Number of Bedrooms',
        'nearbySchools' => 'Nearby Schools',
        'nearbyHospitals' => 'Nearby Hospitals',
        'nearbyShoppingMalls' => 'Nearby Shopping Malls',
        'distanceFromAirportKms' => 'Distance From Airport (kms)',
        'nearbyPublicTransport' => 'Nearby Public Transport',
        'otherNearbyPlaces' => 'Other Nearby Places',
        'hours24Concierge' => '24 Hours Concierge',
        'rentIsPaid' => 'Rent Is Paid',
    ];

    /**
     * Keys whose Bayut catalog slug cannot be derived by the generic camelCase→kebab
     * rule (digits, glued words, parentheses). Mapped explicitly to the real slug.
     * Consumed by BayutService::crmKeyToBayutSlug().
     */
    const PROPERTY_BAYUT_AMENITY_SLUG_OVERRIDES = [
        'hours24Concierge' => '24-hours-concierge',
        'receptionWaitingRoom' => 'receptionwaiting-room',
        'satelliteCableTV' => 'satellitecable-tv',
        'distanceFromAirportKms' => 'distance-from-airport-(kms)',
    ];

    const BAYUT_OCCUPANCY_STATUS = [
        'vacant' => 'Vacant',
        'rented' => 'Rented',
    ];

    const BAYUT_MAINTENANCE_PAID_BY = [
        'landlord' => 'Landlord',
        'tenant' => 'Tenant',
    ];
    // ==================== PROPERTY - PROPERTY FINDER ====================

    const PF_NATIVE_AMENITIES = [
        'central-ac' => 'Central AC',
        'built-in-wardrobes' => 'Built-in Wardrobes',
        'kitchen-appliances' => 'Kitchen Appliances',
        'security' => 'Security',
        'concierge' => 'Concierge',
        'maid-service' => 'Maid Service',
        'balcony' => 'Balcony',
        'private-gym' => 'Private Gym',
        'shared-gym' => 'Shared Gym',
        'private-jacuzzi' => 'Private Jacuzzi',
        'shared-spa' => 'Shared Spa',
        'covered-parking' => 'Covered Parking',
        'maids-room' => "Maid's Room",
        'study' => 'Study',
        'childrens-play-area' => "Children's Play Area",
        'pets-allowed' => 'Pets Allowed',
        'barbecue-area' => 'Barbecue Area',
        'shared-pool' => 'Shared Pool',
        'childrens-pool' => "Children's Pool",
        'private-garden' => 'Private Garden',
        'private-pool' => 'Private Pool',
        'view-of-water' => 'View of Water',
        'view-of-landmark' => 'View of Landmark',
        'walk-in-closet' => 'Walk-in Closet',
        'lobby-in-building' => 'Lobby in Building',
        'vastu-compliant' => 'Vastu Compliant',
    ];

    // Slugs Property Finder accepts on commercial listings (excluding land/farm,
    // which accept no amenities).
    const PF_COMMERCIAL_AMENITIES = [
        'shared-gym' => 'Shared Gym',
        'covered-parking' => 'Covered Parking',
        'networked' => 'Networked',
        'shared-pool' => 'Shared Pool',
        'dining-in-building' => 'Dining in Building',
        'conference-room' => 'Conference Room',
        'lobby-in-building' => 'Lobby in Building',
        'vastu-compliant' => 'Vastu Compliant',
    ];

    // Extra amenity labels that have no native PF slug, kept so Salesforce CSV
    // imports (and any non-PF slugs) still resolve to a human label on display.
    // Display-only — never sent to PF (the payload is filtered against
    // PF_NATIVE_AMENITIES / PF_COMMERCIAL_AMENITIES keys).
    const PF_EXTRA_AMENITIES = [
        'electricity' => 'Electricity',
        'waters' => 'Waters',
        'sanitation' => 'Sanitation',
        'no-services' => 'No Services',
        'fixed-phone' => 'Fixed Phone',
        'fibre-optics' => 'Fibre Optics',
        'flood-drainage' => 'Flood Drainage',
        'shops' => 'Shops',
        'restaurants' => 'Restaurants',
        'nearby-shopping-mall' => 'Near to Shopping Mall',
        'metro-station' => 'Metro Station',
        'nearby-mosque' => 'Near to Mosque',
        'childrens-nursery' => "Children's Nursery",
        'squash-court' => 'Squash Court',
        'prayer-room' => 'Prayer Room',
        'retail-in-building' => 'Retail in Building',
        'furnished' => 'Furnished',
    ];

    const PF_AMENITIES = [
        'residential' => self::PF_NATIVE_AMENITIES,
        'commercial' => self::PF_COMMERCIAL_AMENITIES,
        'extras' => self::PF_EXTRA_AMENITIES,
        'all' => self::PF_NATIVE_AMENITIES + self::PF_COMMERCIAL_AMENITIES + self::PF_EXTRA_AMENITIES,
    ];

    const PROPERTY_PRICE_FREQUENCIES = [
        'yearly' => 'Price Yearly',
        'monthly' => 'Price Monthly',
        'quarterly' => 'Price Quarterly',
        'semiannually' => 'Price Semiannually',
        'weekly' => 'Price Weekly',
        'daily' => 'Price Daily',
    ];

    const PF_NUMBER_OF_CHEQUES = [
        '1' => '1 Cheque',
        '2' => '2 Cheques',
        '3' => '3 Cheques',
        '4' => '4 Cheques',
        '6' => '6 Cheques',
        '12' => '12 Cheques',
    ];

    // ==================== PROPERTY - UNIFIED TYPE MAPPING ====================

    const PROPERTY_TYPE_MAPPING = [
        // Residential
        'apartment' => ['label' => 'Apartment', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'apartment', 'bayut' => 'apartments'],
        'penthouse' => ['label' => 'Penthouse', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'penthouse', 'bayut' => 'penthouse'],
        'townhouse' => ['label' => 'Townhouse', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'townhouse', 'bayut' => 'townhouses'],
        'villa' => ['label' => 'Villa', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'villa', 'bayut' => 'villas'],
        'hotelApartment' => ['label' => 'Hotel Apartment', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'hotel-apartment', 'bayut' => 'hotel-apartments'],
        'compound' => ['label' => 'Compound', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'compound', 'bayut' => 'villa-compound'],
        'residentialBuilding' => ['label' => 'Whole Building', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'whole-building', 'bayut' => 'residential-building'],
        'residentialFloor' => ['label' => 'Full Floor', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'full-floor', 'bayut' => 'residential-floor'],
        'residentialPlot' => ['label' => 'Land', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'land', 'bayut' => 'residential-plots'],
        'bungalow' => ['label' => 'Bungalow', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'bungalow', 'bayut' => 'villas'],
        'cabin' => ['label' => 'Cabin', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'cabin', 'bayut' => 'villas'],
        'chalet' => ['label' => 'Chalet', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'chalet', 'bayut' => 'villas'],
        'duplex' => ['label' => 'Duplex', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'duplex', 'bayut' => 'apartments'],
        'palace' => ['label' => 'Palace / Mansion', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'palace', 'bayut' => 'villas'],
        'restHouse' => ['label' => 'Rest House', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'rest-house', 'bayut' => 'villas'],
        'twinHouse' => ['label' => 'Twin House', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'twin-house', 'bayut' => 'townhouses'],
        'staff-accommodation' => ['label' => 'Staff Accommodation', 'category' => self::PROPERTY_CATEGORY_RESIDENTIAL, 'pf' => 'staff-accommodation', 'bayut' => 'labour-camps'],

        // Commercial
        'office-space' => ['label' => 'Office Space', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'office-space', 'bayut' => 'offices'],
        'shop' => ['label' => 'Shop', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'shop', 'bayut' => 'shops'],
        'warehouse' => ['label' => 'Warehouse', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'warehouse', 'bayut' => 'warehouses'],
        'factory' => ['label' => 'Factory', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'factory', 'bayut' => 'factories'],
        'labor-camp' => ['label' => 'Labor Camp', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'labor-camp', 'bayut' => 'labour-camps'],
        'commercialBuilding' => ['label' => 'Whole Building', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'whole-building', 'bayut' => 'commercial-buildings'],
        'commercialFloor' => ['label' => 'Full Floor', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'full-floor', 'bayut' => 'commercial-floor'],
        'commercialPlot' => ['label' => 'Land', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'land', 'bayut' => 'commercial-plots'],
        'industrialLand' => ['label' => 'Industrial Land', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'land', 'bayut' => 'industrial-land'],
        'show-room' => ['label' => 'Showroom', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'show-room', 'bayut' => 'showrooms'],
        'commercialVilla' => ['label' => 'Villa', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'villa', 'bayut' => 'commercial-villas'],
        'bulk-rent-unit' => ['label' => 'Bulk Rent Units', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'bulk-rent-unit', 'bayut' => 'bulk-units'],
        'bulk-sale-unit' => ['label' => 'Bulk Sale Units', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'bulk-sale-unit', 'bayut' => 'bulk-units'],
        'business-center' => ['label' => 'Business Center', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'business-center', 'bayut' => 'commerical-properties'],
        'cafeteria' => ['label' => 'Cafeteria', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'cafeteria', 'bayut' => 'commerical-properties'],
        'clinic' => ['label' => 'Clinic', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'clinic', 'bayut' => 'commerical-properties'],
        'co-working-space' => ['label' => 'Co-working Space', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'co-working-space', 'bayut' => 'commerical-properties'],
        'farm' => ['label' => 'Farm', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'farm', 'bayut' => 'mixed-use-land'],
        'half-floor' => ['label' => 'Half Floor', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'half-floor', 'bayut' => 'offices'],
        'medical-facility' => ['label' => 'Medical Facility', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'medical-facility', 'bayut' => 'commerical-properties'],
        'restaurant' => ['label' => 'Restaurant', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'restaurant', 'bayut' => 'commerical-properties'],
        'retail' => ['label' => 'Retail', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'retail', 'bayut' => 'commerical-properties'],
        'roof' => ['label' => 'Roof', 'category' => self::PROPERTY_CATEGORY_COMMERCIAL, 'pf' => 'roof', 'bayut' => 'commerical-properties'],
    ];

    // PF slugs for standalone property types (whole plots, not units inside a development).
    // PropertyFinder validates a listing's `type` against its `location`: tower AND
    // sub-community locations only accept in-development unit types (apartment, office, …)
    // and reject these with "Property type check failed". PF only accepts standalone types
    // at community level, so for these never resolve below pfCommunity — see resolveLocationId().
    const PF_STANDALONE_TYPES = [
        'villa', 'townhouse', 'compound', 'bungalow', 'cabin', 'chalet',
        'palace', 'rest-house', 'twin-house', 'land', 'farm',
    ];

    public static function propertyTypeLabels(): array
    {
        return array_map(fn ($r) => $r['label'], self::PROPERTY_TYPE_MAPPING);
    }

    public static function pfPropertyTypeLabels(): array
    {
        $out = [];
        foreach (self::PROPERTY_TYPE_MAPPING as $row) {
            if (! empty($row['pf'])) {
                $out[$row['pf']] = $row['label'];
            }
        }

        return $out;
    }

    public static function bayutPropertyTypeLabels(): array
    {
        $out = [];
        foreach (self::PROPERTY_TYPE_MAPPING as $row) {
            if (! empty($row['bayut'])) {
                $out[$row['bayut']] = $row['label'];
            }
        }

        return $out;
    }

    public static function propertyTypesByCategory(): array
    {
        $out = [
            self::PROPERTY_CATEGORY_RESIDENTIAL => [],
            self::PROPERTY_CATEGORY_COMMERCIAL => [],
        ];
        foreach (self::PROPERTY_TYPE_MAPPING as $key => $row) {
            $cat = $row['category'] ?? null;
            if ($cat && isset($out[$cat])) {
                $out[$cat][$key] = $row['label'];
            }
        }

        return $out;
    }

    public static function canonicalPropertyType(?string $pfType, ?string $bayutType): ?string
    {
        foreach (self::PROPERTY_TYPE_MAPPING as $canonical => $row) {
            if (($pfType && ($row['pf'] ?? null) === $pfType)
                || ($bayutType && ($row['bayut'] ?? null) === $bayutType)) {
                return $canonical;
            }
        }

        return null;
    }

    // ==================== ENQUIRY ====================

    const ENQUIRY_NEW_LEAD = 'newLead';

    const ENQUIRY_CONTACTING = 'contacting';

    const ENQUIRY_PROPERTY_SEARCH = 'propertySearch';

    const ENQUIRY_THINKING_ABOUT_IT = 'thinkingAboutIt';

    const ENQUIRY_UNDER_OFFER = 'underOffer';

    const ENQUIRY_RENTED = 'rented';

    const ENQUIRY_SALE_PROGRESSING = 'saleProgressing';

    const ENQUIRY_SOLD = 'sold';

    const ENQUIRY_LOST = 'lost';

    const ENQUIRY_STATUS = [
        self::ENQUIRY_NEW_LEAD => 'New Lead',
        self::ENQUIRY_CONTACTING => 'Contacting',
        self::ENQUIRY_PROPERTY_SEARCH => 'Property Search',
        self::ENQUIRY_THINKING_ABOUT_IT => 'Thinking about it',
        self::ENQUIRY_UNDER_OFFER => 'Under Offer',
    ];

    const ENQUIRY_SALE_STATUS = [
        ...self::ENQUIRY_STATUS,
        self::ENQUIRY_SALE_PROGRESSING => 'Sale Progressing',
        self::ENQUIRY_SOLD => 'Sold',
        self::ENQUIRY_LOST => 'Inactive',
    ];

    const ENQUIRY_RENT_STATUS = [
        ...self::ENQUIRY_STATUS,
        self::ENQUIRY_RENTED => 'Rented',
        self::ENQUIRY_LOST => 'Inactive',
    ];

    // Combined columns for the pipeline board's "All" tab — the shared statuses
    // plus every sale- and rent-specific outcome, so rent and sale enquiries can
    // be shown together on one board.
    const ENQUIRY_ALL_STATUS = [
        ...self::ENQUIRY_STATUS,
        self::ENQUIRY_SALE_PROGRESSING => 'Sale Progressing',
        self::ENQUIRY_SOLD => 'Sold',
        self::ENQUIRY_RENTED => 'Rented',
        self::ENQUIRY_LOST => 'Inactive',
    ];

    const ENQUIRY_ACTIVE_STATUSES = [
        self::ENQUIRY_NEW_LEAD,
        self::ENQUIRY_CONTACTING,
        self::ENQUIRY_PROPERTY_SEARCH,
        self::ENQUIRY_THINKING_ABOUT_IT,
        self::ENQUIRY_UNDER_OFFER,
        self::ENQUIRY_SALE_PROGRESSING,
    ];

    const LEAD_SCORE_HOT = 'hot';

    const LEAD_SCORE_WARM = 'warm';

    const LEAD_SCORE_COLD = 'cold';

    // The effective lead-score map is cached (shared cache store) rather than
    // memoised per process, so a long-running queue worker picks up an admin's
    // change immediately instead of holding the old bands until it restarts.
    // Invalidated in GlobalSettingRepository::saveSettings when _leadScore is written.
    const LEAD_SCORE_CACHE_KEY = 'leadScore.map';

    // Default lead-score → enquiry-statuses map. Admins can override it from
    // Settings (the _leadScore global setting, same shape); never read this
    // constant directly — call leadScoreMap() / leadScoreStatuses() /
    // leadScoreForStatus() so the configured value wins.
    const ENQUIRY_LEAD_SCORE = [
        self::LEAD_SCORE_HOT => [
            self::ENQUIRY_UNDER_OFFER,
            self::ENQUIRY_SALE_PROGRESSING,
        ],
        self::LEAD_SCORE_WARM => [
            self::ENQUIRY_NEW_LEAD,
            self::ENQUIRY_THINKING_ABOUT_IT,
            self::ENQUIRY_PROPERTY_SEARCH,
        ],
        self::LEAD_SCORE_COLD => [
            self::ENQUIRY_SOLD,
            self::ENQUIRY_RENTED,
            self::ENQUIRY_LOST,
        ],
    ];

    // A hot enquiry with no activity for this many hours is flagged to the
    // assigned agent and managers. Edit to change the agreed period.
    const HOT_ENQUIRY_NO_ACTIVITY_HOURS = 48;

    const ENQUIRY_TYPE = [
        'agent' => 'Agent',
        'buyer' => 'Buyer',
        'tenant' => 'Tenant',
        'seller' => 'Seller',
        'landlord' => 'Landlord',
        'offPlanInvestor' => 'Off-Plan Investor',
    ];

    const ENQUIRY_LISTING_TYPE = [
        'rent' => 'Rent',
        'sale' => 'Sale',
    ];

    public static function enquiryStatusMap(string|array|null $enquiryType): array
    {
        $types = is_array($enquiryType) ? $enquiryType : [$enquiryType];
        $isRent = ! empty(array_intersect($types, ['landlord', 'tenant']));

        return $isRent ? self::ENQUIRY_RENT_STATUS : self::ENQUIRY_SALE_STATUS;
    }

    /**
     * Resolve an incoming source value (a display label like "Property Finder"
     * or "WhatsApp", an already-canonical key, or a case variant) to its
     * canonical camelCase key from the given map. Unknown values pass through.
     */
    public static function normalizeSourceKey(array $map, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (array_key_exists($value, $map)) {
            return $value; // already a canonical key
        }

        foreach ($map as $key => $label) {
            if (strcasecmp(trim($value), (string) $label) === 0) {
                return $key;
            }
        }

        return $value;
    }

    const ENQUIRY_PROPERTY_TYPE = [
        'residential' => 'Residential',
        'commercial' => 'Commercial',
    ];

    const TECHNICAL_SOURCE = [
        'whatsApp' => 'WhatsApp',
        'email' => 'Email',
        'call' => 'Call',
    ];

    const LEAD_SOURCE_MANUAL = 'manual';

    const LEAD_SOURCE_PROPERTY_FINDER = 'propertyFinder';

    const LEAD_SOURCE_PROPERTY_FINDER_PLUS = 'pfPrimaryPlus';

    const LEAD_SOURCE_BAYUT = 'bayut';

    const LEAD_SOURCE_EMAIL = 'email';

    const LEAD_SOURCE_WHATSAPP = 'whatsApp';

    const LEAD_SOURCE_PHONE_CALL = 'phoneCall';

    const LEAD_SOURCE_PPROSPECTING_LANDLORD = 'prospectingLandlord';

    const LEAD_SOURCE_PROSPECTING_OWNER = 'prospectingOwner';

    const LEAD_SOURCE_PERSONAL_NETWORK = 'personalNetwork';

    const LEAD_SOURCE_REFERRAL = 'referral';

    const LEAD_SOURCE_SOCIAL_MEDIA = 'socialMedia';

    const LEAD_SOURCE_DIGITAL_MARKETING = 'digitalMarketing';

    const LEAD_SOURCE_WEBSITE = 'website';

    const LEAD_SOURCE_DM_SHOP = 'dmShop';

    const LEAD_SOURCE = [
        '221Portal' => '221 Portal',
        'leadSource70' => '70',
        'advanceHouse' => 'Advance House',
        'advertisement' => 'Advertisement',
        'agent' => 'Agent',
        'ahDamacLagoons' => 'AH - Damac Lagoons',
        'ahGaCrest' => 'AH - GA Crest',
        'ahGaDamacLagoons' => 'AH - GA Damac Lagoons',
        'ahGaDubaiSouth1' => 'AH - GA Dubai South 1',
        'ahGaDubaiSouth2' => 'AH - GA Dubai South 2',
        'ahGaWasl1' => 'AH - GA WASL 1',
        'ahSmCrest' => 'AH - SM Crest',
        'ahSmDamacLagoons' => 'AH - SM Damac Lagoons',
        'ahSmDubaiSouth1' => 'AH - SM Dubai South 1',
        'ahSmDubaiSouth2' => 'AH - SM Dubai South 2',
        'airBnb' => 'Air bnb',
        'alFahimBuilding' => 'Al Fahim Building',
        'aldarHavenRr' => 'Aldar Haven - RR',
        'armaniBeachResidencesRr' => 'Armani Beach Residences - RR',
        'armaniBeachResidencesERr' => 'Armani Beach Residences E - RR',
        'asiaAsia' => 'Asia Asia',
        'bayti' => 'BAYTI',
        self::LEAD_SOURCE_BAYUT => 'Bayut',
        'binghattiHills' => 'Binghatti Hills',
        'brandawarenessShamsa1' => 'BRANDAWARENESS SHAMSA 1',
        'britishMums' => 'British Mums',
        'brokerConnect' => 'Broker Connect',
        'buildings' => 'Buildings',
        'campaign' => 'Campaign',
        'casaCanalERr' => 'Casa Canal E - RR',
        'cloned' => 'Cloned',
        'codeYaya' => 'CodeYaya',
        'coffeeConversation' => 'Coffee & Conversation',
        'combotsRandomMessage' => 'Combots Random Message',
        'contractorsDirect' => 'Contractors Direct',
        'contractorsDirectRenovation' => 'Contractors Direct (Renovation)',
        'cyDamacLagoons' => 'CY - Damac Lagoons',
        'cyDubaiCreekHarbour' => 'CY - Dubai Creek Harbour',
        'cyDubaiSouth' => 'CY - Dubai South',
        'cyEmaarBeachfront' => 'CY - Emaar Beachfront',
        'cyGemzByDanube' => 'CY - GEMZ By Danube',
        'cySafaTwo' => 'CY - Safa Two',
        'damacGolfGreensRr' => 'Damac Golf Greens - RR',
        'damacLagoonsRr' => 'Damac Lagoons - RR',
        'damacParkGreensRr' => 'Damac Park Greens - RR',
        'database' => 'Database',
        'databaseAgent' => 'Database Agent',
        'databaseAgents' => 'Database Agents',
        'databaseEl' => 'Database EL',
        'developerStock' => 'Developer Stock',
        'dialmydata' => 'Dialmydata',
        'dib' => 'DIB',
        self::LEAD_SOURCE_DIGITAL_MARKETING => 'Digital Marketing',
        'digitalRubix' => 'Digital Rubix',
        'directDeveloper' => 'Direct Developer',
        'directOffice' => 'Direct Office',
        self::LEAD_SOURCE_DM_SHOP => 'DM Shop',
        'drCatch' => 'DR - Catch',
        'drEmaarBeachfront' => 'DR - Emaar Beachfront',
        'drEmaarCreek' => 'DR - Emaar Creek',
        'drGaAlWasl1' => 'DR - GA Al Wasl 1',
        'drNshama' => 'DR - NSHAMA',
        'drPeninsula5' => 'DR - Peninsula 5',
        'drSixSenses' => 'DR - Six Senses',
        'drVincitore' => 'DR - Vincitore',
        'drWasl1' => 'DR - Wasl 1',
        'drDamacLagoons' => 'DR-Damac Lagoons',
        'drNoor' => 'DR-Noor',
        'drSls' => 'DR-SLS',
        'dubaiIrishFc' => 'Dubai Irish FC',
        'dubaiMarinaShop' => 'Dubai Marina Shop',
        'dubizzle' => 'Dubizzle',
        'easytruck' => 'Easytruck',
        self::LEAD_SOURCE_WEBSITE => 'ELREB Website',
        self::LEAD_SOURCE_EMAIL => 'Email',
        'emailCampaign' => 'Email Campaign',
        'employeeReferral' => 'Employee Referral',
        'enroute' => 'EnRoute',
        'escrowMadeEasy' => 'Escrow Made Easy',
        'eventsElreb' => 'Events ELREB',
        'externalReferral' => 'External Referral',
        'facebook' => 'Facebook',
        'freeGoldenVisa' => 'Free Golden Visa',
        'gentiMateusz' => 'Genti Mateusz',
        'googleBusiness' => 'Google Business',
        'gulfNews' => 'Gulf News',
        'henryWiltshire' => 'Henry Wiltshire',
        'homelyAe' => 'Homely.ae',
        'houza' => 'Houza',
        'houzaCom' => 'Houza.com',
        'houzaPortal' => 'Houza Portal',
        'inhouseCampaign' => 'Inhouse Campaign',
        'inhouseLeadGen' => 'Inhouse Lead Gen',
        'insideSales' => 'Inside Sales',
        'instagram' => 'Instagram',
        'internal' => 'Internal',
        'investInMeydan' => 'Invest in Meydan',
        'justProperty' => 'Just Property',
        'justRentals' => 'Just Rentals',
        'keturahReserveApRr' => 'Keturah Reserve AP - RR',
        'kianaHomeFurnishing' => 'Kiana Home Furnishing',
        'kioskArabianRanches' => 'Kiosk - Arabian Ranches',
        'landingPage' => 'Landing Page',
        'landingPages' => 'Landing Pages',
        'leadSource' => 'Lead Source',
        'leeClarkAd' => 'Lee Clark Ad',
        'letterDrop' => 'Letter Drop',
        'liveChat' => 'Live Chat',
        'lynnXing' => 'Lynn Xing',
        self::LEAD_SOURCE_MANUAL => 'Manual',
        'marinaStarRr' => 'Marina Star - RR',
        'mercedesBenzPlacesERr' => 'Mercedes Benz Places E - RR',
        'metaAds' => 'Meta Ads',
        'meydanLeadGeneration' => 'Meydan Lead Generation',
        'mhyHomeStaging' => 'MHY Home Staging',
        'mira' => 'MIRA',
        'mortgageFinder' => 'Mortgage Finder',
        'newline' => 'NEWLINE',
        'newsletter' => 'Newsletter',
        'noor' => 'Noor',
        'openHouse' => 'Open House',
        'other' => 'Other',
        'otherPortals' => 'Other Portals',
        'others' => 'Others',
        'outrank' => 'OUTRANK',
        'palmGatewayRr' => 'Palm Gateway - RR',
        'parksideViewsExclusiveLinks3' => 'Parkside Views - Exclusive Links 3',
        'parkwayLaunchEvent' => 'Parkway Launch Event',
        'partner' => 'Partner',
        'partnersInternationalPoland' => 'Partners International - Poland',
        self::LEAD_SOURCE_PERSONAL_NETWORK => 'Personal Network',
        self::LEAD_SOURCE_PROPERTY_FINDER_PLUS => 'PF Primary Plus',
        'pfnew' => 'PFNEW',
        self::LEAD_SOURCE_PHONE_CALL => 'Phone Call',
        'pipelinesiq' => 'PipelinesIQ',
        'pixl' => 'PIXL',
        'pixlBluewatersBay' => 'Pixl - Bluewaters Bay',
        'pixlBurjBinghatti' => 'Pixl - Burj Binghatti',
        'pixlDamacBay' => 'Pixl - Damac Bay',
        'pixlDanubePetalz' => 'Pixl - Danube Petalz',
        'pixlDanubeViewz' => 'Pixl - Danube Viewz',
        'pixlDch' => 'Pixl - DCH',
        'pixlGolfGreens' => 'Pixl - Golf Greens',
        'pixlJouriHills' => 'Pixl - Jouri Hills',
        'pixlKeturah' => 'Pixl - Keturah',
        'pixlLiva' => 'Pixl - LIVA',
        'pixlMangrove' => 'Pixl - Mangrove',
        'pixlMansio' => 'Pixl - Mansio',
        'pixlPeninsula4' => 'Pixl - Peninsula 4',
        'pixlSamana' => 'Pixl - Samana',
        'pixlShamsa' => 'Pixl - Shamsa',
        'pixlSignatureMansions' => 'Pixl - Signature Mansions',
        'pixlSobhaOne' => 'Pixl - Sobha One',
        'pixlTagAlaya' => 'Pixl - TAG Alaya',
        'pixlYas' => 'Pixl - YAS',
        'pixlBrandAwareness' => 'PIXL BRAND AWARENESS',
        'pixlCloudTower' => 'Pixl-Cloud Tower',
        'pixlDamacLagoons' => 'Pixl-Damac Lagoons',
        'pixlHillcrest' => 'Pixl-Hillcrest',
        'pixlPalmBeachTower' => 'Pixl-Palm Beach Tower',
        'polandSobhaOrbis' => 'Poland - Sobha Orbis',
        'preferredPartner' => 'Preferred partner',
        'propertyBooster' => 'Property Booster',
        self::LEAD_SOURCE_PROPERTY_FINDER => 'Property Finder',
        'propertyGuru' => 'Property Guru',
        'propertyManagement' => 'Property Management',
        'propertyMonitor' => 'Property Monitor',
        // 'propertyfinderAe' => 'Propertyfinder.ae',
        // 'propertyfinderAeAgent' => 'propertyfinder.ae - agent',
        // 'propertyfinderAeListing' => 'propertyfinder.ae - listing',
        'propsearchPortal' => 'Propsearch Portal',
        'propsearchAe' => 'Propsearch.ae',
        'prospectingContact' => 'Prospecting Contact',
        self::LEAD_SOURCE_PPROSPECTING_LANDLORD => 'Prospecting Landlord',
        self::LEAD_SOURCE_PROSPECTING_OWNER => 'Prospecting Owner',
        'quickleads' => 'QuickLeads',
        'realestateComAu' => 'Realestate.com.au',
        self::LEAD_SOURCE_REFERRAL => 'Referral',
        'relocationCompany' => 'Relocation Company',
        'ritzCarltonRr' => 'Ritz Carlton - RR',
        'selectGroup' => 'Select Group',
        'signBoards' => 'Sign Boards',
        'smsCampaign' => 'SMS Campaign',
        'sobhaOneRr' => 'Sobha One - RR',
        'sobhaOpenHouse' => 'SOBHA OPEN HOUSE',
        self::LEAD_SOURCE_SOCIAL_MEDIA => 'Social Media',
        'springFair' => 'Spring Fair',
        'squarespace' => 'Squarespace',
        'stPatrickSDayDubaiIrish' => 'St Patrick\'s Day - Dubai Irish',
        'starlightParkRr' => 'Starlight Park - RR',
        'tamweel' => 'Tamweel',
        'test' => 'Test',
        'testing' => 'Testing',
        'va2u' => 'VA2U',
        'vacationHomesInstagram' => 'Vacation Homes Instagram',
        'velaVientoRr' => 'Vela Viento - RR',
        'virtuzone' => 'Virtuzone',
        'web' => 'Web',
        'website3' => 'Website',
        'website2' => 'Website 2',
        'whatsAppCampaign' => 'Whats app Campaign',
        self::LEAD_SOURCE_WHATSAPP => 'WhatsApp',
        'whatsappAgent' => 'whatsapp - agent',
        'whatsappListing' => 'whatsapp - listing',
        'wordOfMouth' => 'Word of mouth',
        'workingWomen' => 'Working Women',
        'yasGolfCollectionRr' => 'Yas Golf Collection - RR',
        'zenInteriors' => 'Zen Interiors',
        'zoomPortal' => 'Zoom Portal',
    ];

    /**
     * Labels for an exact stored lead source, roll-up NOT applied.
     *
     * For the places that must show what a record literally holds rather than
     * the group it reports under: the source-variant column and the enquiry
     * history, where folding the value would render a real edit as
     * "changed Lead Source from PIXL to PIXL".
     *
     * It adds the three legacy Salesforce spellings that stay commented out of
     * LEAD_SOURCE so they cannot be picked in a filter — absent from any label
     * map, they rendered as bare "propertyfinderAe" on 82k enquiries.
     */
    const LEAD_SOURCE_VARIANT_LABELS = self::LEAD_SOURCE + [
        'propertyfinderAe' => 'Propertyfinder.ae',
        'propertyfinderAeAgent' => 'propertyfinder.ae - agent',
        'propertyfinderAeListing' => 'propertyfinder.ae - listing',
    ];

    /**
     * Reporting roll-ups: one canonical lead source => every raw value the data
     * holds for it.
     *
     * The same source reached the CRM under several spellings — the legacy
     * Salesforce import ("propertyfinderAe"), the current portal feed
     * ("propertyFinder") and stray label-shaped values written by older imports
     * ("Property Finder"). Reports group by the roll-up so one portal is one
     * row, while the raw value stays on the record and is surfaced separately
     * as the source variant. Nothing in the enquiries is rewritten.
     *
     * Membership follows the client's "Final Lead Source" sheet (2026-09-23) and
     * is always listed explicitly, never inferred from a shared prefix. The
     * agency families are deliberately folded — every "pixl*" and "- RR"
     * campaign reports as PIXL, every "ah*" as Advance House, "cy*" as CodeYaya
     * and "dr*" as Digital Rubix — because the client wants agency-level ROI.
     * Per-campaign detail is not lost: the raw value is still the record's
     * source variant, so "Pixl - Samana" and "Pixl - Sobha One" stay tellable
     * apart underneath the one PIXL row.
     *
     * The sheet's "Technical Source" column is this source variant, not the
     * enquiries.technicalSource field — that one holds the arrival channel
     * (call / email / whatsApp) and is untouched.
     *
     * Six of the sheet's rows are deliberately not applied, pending the client:
     * "whatsAppCampaign" and the Arabic "بروبرتي فايندر" (sheet says Property
     * Finder and Others respectively, both look wrong), "email" -> Email
     * Campaign, "database" -> Database Agent, and "velaVientoRr" /
     * "yasGolfCollectionRr", which the sheet marks differently from the other
     * fourteen "- RR" campaigns.
     */
    const LEAD_SOURCE_GROUPS = [
        'advanceHouse' => [
            'advanceHouse',
            'ahDamacLagoons',
            'ahGaCrest',
            'ahGaDamacLagoons',
            'ahGaDubaiSouth1',
            'ahGaDubaiSouth2',
            'ahGaWasl1',
            'ahSmCrest',
            'ahSmDamacLagoons',
            'ahSmDubaiSouth1',
            'ahSmDubaiSouth2',
        ],
        'codeYaya' => [
            'codeYaya',
            'cyDamacLagoons',
            'cyDubaiCreekHarbour',
            'cyDubaiSouth',
            'cyEmaarBeachfront',
            'cyGemzByDanube',
            'cySafaTwo',
        ],
        'contractorsDirect' => ['contractorsDirect', 'contractorsDirectRenovation'],
        'databaseAgent' => ['databaseAgent', 'databaseAgents'],
        'digitalRubix' => [
            'digitalRubix',
            'drCatch',
            'drDamacLagoons',
            'DR - Damac Lagoons',
            'drEmaarBeachfront',
            'drEmaarCreek',
            'drGaAlWasl1',
            'drNoor',
            'drNshama',
            'drPeninsula5',
            'drSixSenses',
            'drSls',
            'drVincitore',
            'drWasl1',
        ],
        'dubaiMarinaShop' => ['dubaiMarinaShop', self::LEAD_SOURCE_DM_SHOP],
        'eventsElreb' => [
            'eventsElreb',
            'asiaAsia',
            'britishMums',
            'coffeeConversation',
            'dubaiIrishFc',
            'springFair',
            'stPatrickSDayDubaiIrish',
            'workingWomen',
        ],
        'houza' => ['houza', 'houzaCom', 'houzaPortal'],
        'inhouseLeadGen' => [
            'inhouseLeadGen',
            'binghattiHills',
            'inhouseCampaign',
            'investInMeydan',
            'lynnXing',
            'metaAds',
            'meydanLeadGeneration',
            'polandSobhaOrbis',
        ],
        'landingPage' => ['landingPage', 'landingPages'],
        // 'leadSource' and 'leadSource70' are placeholder junk from the import.
        'others' => ['others', 'leadSource', 'leadSource70', 'other', 'otherPortals'],
        // The 16 "- RR" campaigns are Pixl's too, per the client's sheet, so the
        // agency reads as one line. Every campaign stays visible as the variant.
        'pixl' => [
            'pixl',
            'aldarHavenRr',
            'armaniBeachResidencesERr',
            'armaniBeachResidencesRr',
            'brandawarenessShamsa1',
            'casaCanalERr',
            'damacGolfGreensRr',
            'damacLagoonsRr',
            'damacParkGreensRr',
            'keturahReserveApRr',
            'marinaStarRr',
            'mercedesBenzPlacesERr',
            'palmGatewayRr',
            'parksideViewsExclusiveLinks3',
            'parkwayLaunchEvent',
            // Stored with non-breaking spaces (U+00A0) by an older import, so
            // they match neither their key nor their label as written.
            "BRANDAWARENESS\u{A0}SHAMSA\u{A0}1",
            "PIXL BRAND\u{A0}AWARENESS",
            "Parkway Launch\u{A0}Event",
            "SOBHA OPEN\u{A0}HOUSE",
            'pixlBluewatersBay',
            'pixlBrandAwareness',
            'pixlBurjBinghatti',
            'pixlCloudTower',
            'pixlDamacBay',
            'pixlDamacLagoons',
            'pixlDanubePetalz',
            'pixlDanubeViewz',
            'pixlDch',
            'pixlGolfGreens',
            'pixlHillcrest',
            'pixlJouriHills',
            'pixlKeturah',
            'pixlLiva',
            'pixlMangrove',
            'pixlMansio',
            'pixlPalmBeachTower',
            'pixlPeninsula4',
            'pixlSamana',
            'pixlShamsa',
            'pixlSignatureMansions',
            'pixlSobhaOne',
            'pixlTagAlaya',
            'pixlYas',
            'ritzCarltonRr',
            'sobhaOneRr',
            'sobhaOpenHouse',
            'starlightParkRr',
        ],
        'preferredPartner' => ['preferredPartner', 'partner'],
        self::LEAD_SOURCE_PROPERTY_FINDER => [
            self::LEAD_SOURCE_PROPERTY_FINDER,
            // Primary Plus is a Property Finder product, not a portal of its
            // own, so it belongs to the PF lead source for renewal/ROI figures.
            // The raw value keeps it identifiable as a variant.
            self::LEAD_SOURCE_PROPERTY_FINDER_PLUS,
            'pfnew',
            'propertyfinderAe',
            'Propertyfinder.ae',
            'propertyfinderAeAgent',
            'propertyfinderAeListing',
            'Property Finder',
            // WhatsApp leads raised off a PF listing, agent card or project
            // page. Plain 'whatsApp' is the website button and belongs there.
            'whatsapp - project',
            'whatsappAgent',
            'whatsappListing',
        ],
        'propsearchAe' => ['propsearchAe', 'propsearchPortal'],
        'prospectingContact' => [
            'prospectingContact',
            self::LEAD_SOURCE_PPROSPECTING_LANDLORD,
            self::LEAD_SOURCE_PROSPECTING_OWNER,
        ],
        self::LEAD_SOURCE_SOCIAL_MEDIA => [
            self::LEAD_SOURCE_SOCIAL_MEDIA,
            'facebook',
            'instagram',
            'vacationHomesInstagram',
        ],
        'test' => ['test', 'testing'],
        self::LEAD_SOURCE_WEBSITE => [
            self::LEAD_SOURCE_WEBSITE,
            // Label-shaped duplicate of the same key, stored raw by an older import.
            'ELREB Website',
            'web',
            'website2',
            'website3',
            self::LEAD_SOURCE_WHATSAPP,
        ],
    ];

    /**
     * The canonical lead source a raw stored value rolls up to for reporting.
     *
     * Handles a key in any spelling the data holds ("propertyFinder",
     * "PropertyFinder", "propertyfinderAe") and label-shaped values from older
     * imports ("Property Finder", "ELREB Website"). A value belonging to no
     * group resolves to its own canonical key, so ungrouped sources are
     * unaffected and keep reporting exactly as they do today.
     */
    public static function leadSourceGroup(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        static $index = null;
        if ($index === null) {
            $index = [];
            foreach (self::LEAD_SOURCE_GROUPS as $group => $members) {
                foreach ($members as $member) {
                    $index[self::sourceSlug($member)] = $group;
                }
            }
        }

        // Resolve a label to its key first, so "Property Finder" and
        // "propertyFinder" reach the index as the same thing.
        $key = self::normalizeSourceKey(self::LEAD_SOURCE, trim($value));

        return $index[self::sourceSlug($key)] ?? $key;
    }

    /**
     * The stored values a lead source selection should match.
     *
     * Only the roll-up itself expands: choosing "Property Finder" returns the
     * whole portal (legacy import, current feed and Primary Plus), while
     * choosing a variant such as "PF Primary Plus" or "Propertyfinder.ae"
     * stays exact, so the detailed analysis the roll-up is meant to preserve
     * is still possible. Always returns something a single whereIn can use.
     *
     * @return array<int, string>
     */
    public static function leadSourceGroupMembers(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $key = self::normalizeSourceKey(self::LEAD_SOURCE, trim($value));
        $members = self::LEAD_SOURCE_GROUPS[$key] ?? [$key];

        // Enquiries store the key ("pixlSamana"), contacts store the label
        // ("Pixl - Samana"). The filter matches stored values exactly, so every
        // member contributes both spellings or the contact side silently misses.
        $withLabels = [];
        foreach ($members as $member) {
            $withLabels[] = $member;
            if (isset(self::LEAD_SOURCE[$member])) {
                $withLabels[] = self::LEAD_SOURCE[$member];
            }
        }

        return array_values(array_unique($withLabels));
    }

    /**
     * Does a stored lead source fall inside the given filter selection?
     *
     * The in-PHP counterpart of leadSourceGroupMembers, for the places that
     * filter an already-loaded row rather than the query — kept here so both
     * paths answer identically and a report's enquiry and closing sides can
     * never disagree about what a filter means.
     */
    public static function leadSourceMatchesFilter(?string $stored, ?string $filter): bool
    {
        if ($filter === null || trim($filter) === '') {
            return true;
        }

        $members = array_map(
            static fn ($member) => self::sourceSlug($member),
            self::leadSourceGroupMembers($filter)
        );

        return in_array(self::sourceSlug($stored), $members, true);
    }

    /**
     * Display label for a raw stored lead source, resolved through its roll-up.
     *
     * The legacy spellings ("propertyfinderAe", "propertyfinderAeAgent",
     * "propertyfinderAeListing") are deliberately absent from self::LEAD_SOURCE
     * so they cannot be picked in a filter, which means a direct label lookup
     * finds nothing and falls through to the raw camelCase key — 82k enquiries
     * rendered as "propertyfinderAe" in the lists, detail view and kanban.
     * Resolving the group first gives every spelling the roll-up's label
     * ("Property Finder") while leaving the stored value untouched.
     */
    public static function leadSourceGroupLabel(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::LEAD_SOURCE[self::leadSourceGroup($value)] ?? $value;
    }

    /**
     * The selectable lead sources: one entry per canonical source, key => label.
     *
     * Only the main key of each roll-up is offered — "PIXL", not its 42
     * campaigns — so the picker matches what reports show. Sources that roll up
     * into nothing are unaffected and still listed individually.
     *
     * The variants stay valid values: they remain on existing records, still
     * label correctly, and a filter on one is still honoured exactly. They are
     * simply no longer offered for selection.
     *
     * @return array<string, string>
     */
    public static function leadSourceOptions(): array
    {
        return array_filter(
            self::LEAD_SOURCE,
            static fn ($label, $key) => self::leadSourceGroup($key) === $key,
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * The canonical key for a lead source given in any spelling, label included.
     *
     * The roll-up is deliberately not applied: this is for the write path, which
     * stores the exact source a lead came from. Use leadSourceGroup() to report.
     */
    public static function leadSourceKey(?string $value): ?string
    {
        return self::normalizeSourceKey(self::LEAD_SOURCE, $value);
    }

    /**
     * Stored spellings that are real but deliberately mapped nowhere yet.
     *
     * "بروبرتي فايندر" is Arabic for Property Finder and sits on 5 contacts. The
     * client's sheet puts it under Others, which looks wrong, so its roll-up is
     * still open — but it must stay editable meanwhile, so it is recognised here
     * without being added to LEAD_SOURCE (not selectable) or to a group.
     */
    const LEAD_SOURCE_UNMAPPED = [
        'بروبرتي فايندر',
    ];

    /**
     * Is this a lead source the CRM recognises, in any spelling the data holds?
     *
     * A whitelist of keys alone rejected the value the API itself had just
     * returned and blocked the edit: 95% of contacts were imported holding a
     * label ("Manual", "Propertyfinder.ae") or a near-miss of one
     * ("Property finder", "ProspectingContact", "houza.com"). Matching on the
     * same slug the roll-up uses accepts every real spelling — key, label,
     * group member, any case or punctuation — and still rejects a value that is
     * not a lead source at all. Nothing is rewritten; the value is stored as sent.
     */
    public static function isKnownLeadSource(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true; // absence is handled by the nullable rule
        }

        static $known = null;
        if ($known === null) {
            $known = [];
            foreach (self::LEAD_SOURCE_VARIANT_LABELS as $key => $label) {
                $known[self::sourceSlug($key)] = true;
                $known[self::sourceSlug($label)] = true;
            }
            foreach (self::LEAD_SOURCE_GROUPS as $members) {
                foreach ($members as $member) {
                    $known[self::sourceSlug($member)] = true;
                }
            }
            unset($known['']);

            // sourceSlug keeps only ASCII letters and digits, so a wholly
            // non-Latin spelling slugs to nothing. Those match on a folded form
            // of the raw text instead.
            foreach (self::LEAD_SOURCE_UNMAPPED as $unmapped) {
                $known[self::sourceFold($unmapped)] = true;
            }
        }

        return isset($known[self::sourceSlug($value)]) || isset($known[self::sourceFold($value)]);
    }

    /**
     * Display label for the exact lead source a record holds, roll-up NOT applied.
     *
     * The counterpart of leadSourceGroupLabel, for the record-level views: a list
     * of enquiries shows what each one actually says ("Pixl - Samana"), while the
     * reports fold it into its roll-up ("PIXL"). Resolves the legacy Salesforce
     * spellings too, so nothing renders as bare "propertyfinderAe".
     */
    public static function leadSourceVariantLabel(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::LEAD_SOURCE_VARIANT_LABELS[trim($value)] ?? $value;
    }

    /** Comparable form of a value whose script sourceSlug cannot reduce. */
    private static function sourceFold(?string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));
    }

    /** Comparable form of a source value: letters and digits only, lowercased. */
    private static function sourceSlug(?string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value));
    }
    // ==================== CONTACT NOTE ====================

    const CONTACT_NOTE_TYPE = [
        'note' => 'Note',
        'email' => 'Email',
        'call' => 'Call',
    ];

    const CONTACT_NOTE_CALL_STATUS_BUSY = 'busy';

    const CONTACT_NOTE_CALL_STATUS_NO_ANSWER = 'noAnswer';

    const CONTACT_NOTE_CALL_STATUS_ASKED_TO_CALL_AGAIN = 'askedToCallAgain';

    const CONTACT_NOTE_CALL_STATUS_TALKED = 'talked';

    const CONTACT_NOTE_CALL_STATUS = [
        self::CONTACT_NOTE_CALL_STATUS_BUSY => 'Busy',
        self::CONTACT_NOTE_CALL_STATUS_NO_ANSWER => 'No Answer',
        self::CONTACT_NOTE_CALL_STATUS_ASKED_TO_CALL_AGAIN => 'Asked to call again',
        self::CONTACT_NOTE_CALL_STATUS_TALKED => 'Talked',
    ];

    // ==================== CONTACT ====================

    const CONTACT_INDIVIDUAL = 'individual';

    const CONTACT_COMPANY = 'company';

    const CONTACT_OFF_PLAN_INVESTOR = 'offPlanInvestor';

    const CONTACT_CATEGORIES = [
        self::CONTACT_INDIVIDUAL => 'Individual',
        self::CONTACT_COMPANY => 'Company',
        self::CONTACT_OFF_PLAN_INVESTOR => 'Off-Plan Investor',
    ];

    const CONTACT_TYPE_BUYER = 'buyer';

    const CONTACT_TYPE_SELLER = 'seller';

    const CONTACT_TYPE_LANDLORD = 'landlord';

    const CONTACT_TYPE_TENANT = 'tenant';

    const CONTACT_TYPE_AGENT = 'agent';

    const CONTACT_TYPE_OFF_PLAN_INVESTOR = 'offPlanInvestor';

    const CONTACT_TYPE = [
        self::CONTACT_TYPE_BUYER => 'Buyer',
        self::CONTACT_TYPE_SELLER => 'Seller',
        self::CONTACT_TYPE_LANDLORD => 'Landlord',
        self::CONTACT_TYPE_TENANT => 'Tenant',
        self::CONTACT_TYPE_AGENT => 'Agent',
        self::CONTACT_TYPE_OFF_PLAN_INVESTOR => 'Off-Plan Investor',
    ];

    /**
     * Map an enquiry type (see {@see ENQUIRY_TYPE}) to its equivalent contact
     * type. Enquiry types and contact types share the same keys, so the value
     * passes through when it is a known contact type; unknown values return null.
     */
    public static function contactTypeFromEnquiryType(?string $enquiryType): ?string
    {
        if ($enquiryType === null || $enquiryType === '') {
            return null;
        }

        return array_key_exists($enquiryType, self::CONTACT_TYPE) ? $enquiryType : null;
    }

    const CONTACT_COMMUNITY_PREFERENCE = [
        'emailOptOut' => 'Email Opt Out',
        'automatedEmailOptOut' => 'Automated Email Opt Out',
        'emailCampaignOptOut' => 'Email Campaign Opt Out',
        'whatsappOptOut' => 'Whatsapp Opt Out',
        'doNotCall' => 'Do Not Call',
    ];

    // ==================== TASK ====================

    const TASK_PRIORITY = [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    const TASK_TYPE = [
        'email' => 'Email',
        'call' => 'Call',
        'meeting' => 'Meeting',
        'note' => 'Note',
        'viewing' => 'Viewing',
        'whatsapp' => 'Whatsapp',
    ];

    const TASK_STATUS_PENDING = 'pending';

    const TASK_STATUS_EDIT_COMMENT = 'editComment';

    const TASK_STATUS_CHANGE_DUE_DATE = 'changeDueDate';

    const TASK_STATUS_CLOSE_FOLLOW_UP = 'closeFollowUp';

    const TASK_STATUS_DONE = 'done';

    const TASK_CLOSED_STATUSES = [self::TASK_STATUS_CLOSE_FOLLOW_UP, self::TASK_STATUS_DONE];

    const TASK_STATUS = [
        self::TASK_STATUS_PENDING => 'Pending',
        self::TASK_STATUS_EDIT_COMMENT => 'Edit Comment',
        self::TASK_STATUS_CHANGE_DUE_DATE => 'Change Due Date',
        self::TASK_STATUS_CLOSE_FOLLOW_UP => 'Close & Follow Up',
        self::TASK_STATUS_DONE => 'Done',
    ];

    // An agent is flagged to managers once their overdue follow-up (task) count
    // reaches this threshold. Edit to change the agreed limit.
    const OVERDUE_FOLLOWUP_THRESHOLD = 5;

    // ==================== OFFER ====================

    const OFFER_STATUS_NEW = 'new';

    const OFFER_STATUS_ACCEPTED = 'accepted';

    const OFFER_STATUS_REJECTED = 'rejected';

    const OFFER_STATUS_CANCELLED = 'cancelled';

    const OFFER_STATUS = [
        self::OFFER_STATUS_NEW => 'New',
        self::OFFER_STATUS_ACCEPTED => 'Accepted',
        self::OFFER_STATUS_REJECTED => 'Rejected',
        self::OFFER_STATUS_CANCELLED => 'Cancelled',
    ];

    // ==================== OFFER LETTER ====================

    const OFFER_LETTER_TYPE_BUYER = 'buyer';

    const OFFER_LETTER_TYPE_SELLER = 'seller';

    const OFFER_LETTER_TYPE_LANDLORD = 'landlord';

    const OFFER_LETTER_TYPE_TENANT = 'tenant';

    const OFFER_LETTER_TYPES = [
        self::OFFER_LETTER_TYPE_BUYER => 'Buyer',
        self::OFFER_LETTER_TYPE_SELLER => 'Seller',
        self::OFFER_LETTER_TYPE_LANDLORD => 'Landlord',
        self::OFFER_LETTER_TYPE_TENANT => 'Tenant',
    ];

    // ==================== CLOSING ====================

    const CLOSE_STATUS_OPEN = 'open';

    const CLOSE_STATUS_CLOSED = 'closed';

    const CLOSE_STATUS_CANCELLED = 'cancelled';

    const CLOSE_STATUS = [
        self::CLOSE_STATUS_OPEN => 'Open',
        self::CLOSE_STATUS_CLOSED => 'Closed',
        self::CLOSE_STATUS_CANCELLED => 'Cancelled',
    ];

    const CLOSE_LISTING_TYPE_SALE = 'sale';

    const CLOSE_LISTING_TYPE_RENT = 'rent';

    const CLOSE_LISTING_TYPE = [
        self::CLOSE_LISTING_TYPE_SALE => 'Sale',
        self::CLOSE_LISTING_TYPE_RENT => 'Rent',
    ];

    const CLOSING_CHECKLIST = [
        // Seller Documents
        'sellersDocuments' => 'Sellers Documents',
        'deedVerification' => 'Deed Verification',
        'poaSeller' => 'POA & PP - Seller (if Applicable)',
        'completedFormA' => 'Completed Form A',
        'formF' => 'Form F',
        'serviceFeesSOA' => 'Service Fee\'s SOA',
        'liabilityLetterClearance' => 'Liability Letter / Clearance',
        'dewaEjariClearance' => 'DEWA / Ejari Clearance',
        'utilityClearance' => 'Utility Clearance',
        'nocDeveloper' => 'NOC Developer',
        'tenancyContract' => 'Tenancy Contract (if rented)',
        'keyHandoverReceipt' => 'Key Handover Receipt',
        'thankYouSeller' => 'Thank you to Seller',

        // Buyer Documents
        'buyersDocuments' => 'Buyers Documents',
        'financePreApproval' => 'Finance Pre-Approval',
        'poaBuyer' => 'POA & PP - Buyer (if Applicable)',
        'completedFormB' => 'Completed Form B',
        'addendum' => 'Addendum',
        'sfReimbursement' => 'SF Reimbursement',
        'valuationCompleted' => 'Valuation Completed',
        'saleProceedsChequesCopies' => 'Sale Proceeds Cheques Copies',
        'commissionChequeCopy' => 'Commission Cheque Copy',
        'finalBankOfferLetter' => 'Final Bank Offer Letter',
        'receiptForRentDepositBuyer' => 'Receipt for Rent & Deposit from Buyer',
        'dewaUtilityRegistrationBuyer' => 'DEWA / Utility Registration for Buyer',
        'thankYouBuyer' => 'Thank you to Buyer',
    ];

    // ==================== CALENDAR ====================
    const ACTIVITY_TYPES = [
        'meeting' => 'Meeting',
        'call' => 'Call',
        'email' => 'Email',
        'followUp' => 'Follow Up',
        'siteVisit' => 'Site Visit',
        'appointment' => 'Appointment',
        'viewing' => 'Viewing',
        'ready' => 'Ready',
        'inProgress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    const CALENDAR_EVENT_TYPES = [
        'meeting' => 'Meeting',
        'call' => 'Call',
        'email' => 'Email',
        'followUp' => 'Follow Up',
        'siteVisit' => 'Site Visit',
        'appointment' => 'Appointment',
        'viewing' => 'Viewing',
        'ready' => 'Ready',
        'inProgress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    const CALENDAR_EVENT_STATUS = [
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'overdue' => 'Overdue',
    ];

    const CALENDAR_EVENT_COLORS = [
        'Meeting' => '#4F46E5',        // Indigo
        'Call' => '#0891B2',            // Cyan
        'Email' => '#059669',           // Emerald
        'Follow Up' => '#D97706',       // Amber
        'Site Visit' => '#7C3AED',      // Violet
        'Appointment' => '#2563EB',     // Blue
        'Viewing' => '#DB2777',         // Pink
        'Ready' => '#16A34A',           // Green
        'In Progress' => '#CA8A04',     // Yellow
        'Completed' => '#15803D',       // Dark Green
        'Cancelled' => '#DC2626',       // Red
        'WhatsApp' => '#00ff5eb2',
    ];

    const CALENDAR_MODULES = [
        'contact' => 'Contact',
        'enquiry' => 'Enquiry',
        'listing' => 'Listing',
        'offer' => 'Offer',
        'closing' => 'Closing',
    ];

    const OUTLOOK_SYNC_STATUS = [
        'connected' => 'connected',
        'disconnected' => 'disconnected',
        'expired' => 'expired',
    ];

    // ==================== BOOST ====================

    const BOOST_STATUS = [
        'requested' => 'Requested',
        'approved' => 'Approved',
        'goneLive' => 'Gone Live',
        'expired' => 'Expired',
        'rejected' => 'Rejected',
    ];

    const BOOST_DURATION = [
        '7' => '7 Days',
        '14' => '14 Days',
        '15' => '15 Days',
        '30' => '30 Days',
    ];

    const BOOST_TYPE = [
        'agentRequested' => 'Agent Requested',
        'strategicBoost' => 'Strategic Boost',
    ];

    // ==================== NOTIFICATION ====================

    /**
     * Notification page "Type" filter categories (value => label). The value is
     * what the frontend sends as ?type=... and NotificationRepository::applyTypeFilter
     * translates into the underlying notification-type query.
     */
    const NOTIFICATION_FILTER_TYPES = [
        'task' => 'Task',
        'property' => 'Property',
        'enquiry' => 'Enquiry',
        'offer' => 'Offer',
        'closing' => 'Closing',
        'approval' => 'Approval',
        'system' => 'System',
    ];

    const NOTIFICATION_TYPES = [
        'new_follow' => 'new_follow',
        'property_update' => 'property_update',
        'property_status_approved' => 'property_status_approved',
        'property_status_rejected' => 'property_status_rejected',
        'property_permit_entered' => 'property_permit_entered',
        'property_approval_requested' => 'property_approval_requested',
        'property_published' => 'property_published',
        'enquiry_update' => 'enquiry_update',
        'new_enquiry' => 'new_enquiry',
        'task_assigned' => 'task_assigned',
        'task_completed' => 'task_completed',
        'enquiry_open_game' => 'enquiry_open_game',
        'enquiry_status_change' => 'enquiry_status_change',
        'boost_requested' => 'boost_requested',
        'boost_approved' => 'boost_approved',
        'boost_live' => 'boost_live',
        'boost_expired' => 'boost_expired',
        'boost_rejected' => 'boost_rejected',
        'listing_status_change' => 'listing_status_change',
        'published_listing_key_data_changed' => 'published_listing_key_data_changed',
        'listing_created' => 'listing_created',
        'offer_created' => 'offer_created',
        'offer_status_change' => 'offer_status_change',
        'closing_created' => 'closing_created',
        'closing_status_change' => 'closing_status_change',
        'task_status_change' => 'task_status_change',
        'enquiry_inactivity_warning' => 'enquiry_inactivity_warning',
        'new_listing_match' => 'new_listing_match',
        'target_alert' => 'target_alert',
        'target_alert_manager' => 'target_alert_manager',
        'pocket_listing_aged' => 'pocket_listing_aged',
        'upcoming_listing_aged' => 'upcoming_listing_aged',
        'listing_media_updated' => 'listing_media_updated',
        'task_overdue' => 'task_overdue',
        'closing_completed' => 'closing_completed',
        'closing_documents_missing' => 'closing_documents_missing',
        'overdue_followups' => 'overdue_followups',
        'hot_enquiry_no_activity' => 'hot_enquiry_no_activity',
        'document_uploaded' => 'document_uploaded',
        'document_missing' => 'document_missing',
        'data_export' => 'data_export',
        'enquiry_lost_colleague' => 'enquiry_lost_colleague',
        'duplicate_enquiry' => 'duplicate_enquiry',
        'document_expiry' => 'document_expiry',
    ];

    const NOTIFICATION_MODULES = [
        'property' => 'Property',
        'enquiry' => 'Enquiry',
        'user' => 'User',
        'task' => 'Task',
        'calendarEvent' => 'CalendarEvent',
        'employee_document' => 'Employee Document',
    ];

    // Most producers re-run on every save/upload, so identical alerts used to stack up
    // in the bell. NotificationService::create() suppresses a notification when the
    // recipient, type, record, title and message all repeat the previous alert on that
    // record — so a change in content, or a change back, always gets through. A
    // suppressed alert is not stored, not broadcast, and not pushed to the browser.
    //
    // How long that suppression lasts, in minutes. Deliberately short: it collapses the
    // burst of identical alerts a single action sets off, and leaves anything further
    // apart alone, so no scheduled reminder can be swallowed by it.
    const NOTIFICATION_DEDUPE_MINUTES = 2;

    // ==================== EMAIL ====================

    // Maps each Settings → Email Automations toggle to the email template slug(s)
    // it controls. When a toggle is paused, MailService skips those templates.
    // Keys mirror EMAIL_AUTOMATION_CATEGORIES on the frontend.
    const EMAIL_AUTOMATION_SLUGS = [
        'buyer_new_lead' => ['property_buyers_guide'],
        'buyer_property_search' => ['property_buyers_guide'],
        'tenant_new_lead' => ['property_tenants_guide'],
        'tenant_property_search' => ['property_tenants_guide'],
        'landlord_guide' => ['property_landlord_guide'],
        'seller_guide' => ['property_sellers_guide'],
        'inquiry_assigned_to_agent' => ['enquiry_new_assigned_agent'],
        'matching_listing_to_agent' => ['enquiry_daily_matching_4pm'],
        'inquiry_status_update' => ['sent_enquiry_details_contact'],
        'task_assigned' => ['task_assigned_notification'],
        'task_reminder' => ['task_reminder_notification'],
        'task_overdue_alert' => ['task_overdue_followups_manager'],
        'listing_pending_admin' => ['property_rera_manager_approval'],
        'listing_approved' => ['property_listing_approved_agent'],
        'listing_rejected' => ['property_listing_rejected_agent'],
        'listing_matched_inquiries' => ['enquiry_daily_listing_matching'],
    ];

    const EMAIL_SLUGS = [
        'contact' => [
            'buyers_guide' => 'property_buyers_guide',
            'sellers_guide' => 'property_sellers_guide',
            'landlord_guide' => 'property_landlord_guide',
            'tenants_guide' => 'property_tenants_guide',
        ],
        'enquiry' => [
            'enquiry_sent_details' => 'sent_enquiry_details_contact',
            'daily_matching' => 'enquiry_daily_matching',
            'daily_listing_matching' => 'enquiry_daily_listing_matching',
            'daily_matching_4pm' => 'enquiry_daily_matching_4pm',
            'open_game_reminder' => 'enquiry_open_game_reminder',
            'new_enquiry_agent' => 'enquiry_new_assigned_agent',
            'inactivity_warning' => 'enquiry_inactivity_warning',
            'lost_colleague' => 'enquiry_lost_colleague',
            'daily_open_game_reminder' => 'enquiry_daily_open_game_reminder',
        ],
        'user' => [
            'forgot_password' => 'user_forgot_password',
            'account_created' => 'agent_account_created',
            'data_export' => 'data_export_alert',
        ],
        'property' => [
            'sent_property_details' => 'sent_property_details_owner',
            'sent_property_details_contact' => 'sent_property_details_contact',
            'sent_multiple_property_details_contact' => 'sent_multiple_property_details_contact',
            'rera_permit_request' => 'property_rera_permit_request',
            'rera_manager_approval' => 'property_rera_manager_approval',
            'ifx_payment_partner' => 'property_ifx_payment_partner',
            'mortgage_solutions' => 'property_mortgage_solutions',
            'permit_entered_agent' => 'property_permit_entered_agent',
            'listing_approved_agent' => 'property_listing_approved_agent',
            'listing_rejected_agent' => 'property_listing_rejected_agent',
            'listing_published' => 'property_listing_published',
            'listing_published_owner' => 'property_listing_published_owner',
            'listing_status_change' => 'property_listing_status_change',
            'listing_created' => 'listing_created',
            'published_listing_key_data_changed' => 'property_published_listing_key_data_changed',
            'listing_expiry_reminder' => 'property_listing_expiry_reminder',
            'pocket_listing_aged' => 'property_pocket_listing_aged',
            'upcoming_listing_aged' => 'property_upcoming_listing_aged',
        ],
        'task' => [
            'task_reminder' => 'task_reminder_notification',
            'task_assigned' => 'task_assigned_notification',
            'task_status_change' => 'task_status_change_notification',
            'overdue_followups' => 'task_overdue_followups_manager',
        ],
        'offer' => [
            'offer_created_agent' => 'offer_created_agent',
            'offer_status_change' => 'offer_status_change',
        ],
        'closing' => [
            'closing_created_agent' => 'closing_created_agent',
            'closing_status_change' => 'closing_status_change',
        ],
        'boost' => [
            'boost_request_admin' => 'boost_request_admin',
            'boost_status_agent' => 'boost_status_agent',
        ],
        'performance' => [
            'target_summary' => 'agent_target_performance',
            'manager_summary' => 'manager_target_performance',
        ],
        'hr' => [
            'employee_birthday' => 'hr_employee_birthday_greeting',
            'employee_holiday' => 'hr_employee_holiday_greeting',
        ],
    ];

    // ==================== DOCUMENT ====================
    const DOCUMENT_TAGS = [
        self::MODULE_PROPERTY => [
            'Floorplan' => 'Floorplan',
            'Brochure' => 'Brochure',
            'Master Plan' => 'Master Plan',
            'Master Plan;Site Map' => 'Master Plan;Site Map',
            'titleDeedOqood' => 'Title Deed/Oqood',
            'ownersPassportCopy' => 'Owners Passport Copy',
            'handoverCompletionCertificate' => 'Handover/Completion Certificate',
            'rentalListingForm' => 'Rental Listing Form',
            'formA' => 'Form A',
            'marketingNocDeveloperOwner' => 'Marketing NOC - Developer/Owner',
            'titleDeedOwnerVerification' => 'Title Deed & Owner Verification',
        ],
        self::MODULE_OFFER => [
            'buyerPassportCopy' => 'Buyer Passport Copy',
            'tenantPassportCopy' => 'Tenant Passport Copy',
            'offerLetterBuyer' => 'Offer Letter Buyer',
            'offerLetterSeller' => 'Offer Letter Seller',
            'formB' => 'Form B',
            'financePreApprovalLetter' => 'Finance Pre-Approval Letter',
            'serviceFeeStatement' => 'Service Fee Statement',
            'tenancyContractEjari' => 'Tenancy Contract & Ejari',
            'poaForOwner' => 'POA for Owner',
            'poaForBuyerTenant' => 'POA for Buyer/Tenant',
            'poaPassportCopy' => 'POA Passport Copy',
            'buyerTenantEmiratesIdVisa' => 'Buyer/Tenant Emirates ID & Visa',
            'ownerEmiratesIdVisa' => 'Owner Emirates ID & Visa (if applicable)',
            'kycForBuyerTenant' => 'KYC for Buyer/Tenant',
            'kycForOwner' => 'KYC for Owner',
            'renovationApprovals' => 'Renovation Approvals',
            'custodianServicesBuyer' => 'Custodian Services (Buyer)',
            'custodianServicesSeller' => 'Custodian Services (Seller)',
        ],
        self::MODULE_CLOSING => [
            'screeningBuyer' => 'Screening Buyer/s',
            'screeningSeller' => 'Screening Seller/s',
            'riskAssessmentBuyer' => 'Risk Assessment Buyer/s',
            'riskAssessmentSeller' => 'Risk Assessment Seller/s',
            'formF' => 'Form F',
            'liabilityLetterClearance' => 'Liability Letter/Clearance',
            'utilityClearance' => 'Utility Clearance',
            'transferNocFromDeveloper' => 'Transfer NOC from Developer',
            'valuationReport' => 'Valuation Report',
            'saleProceedChequeCopies' => 'Sale proceed Cheque copies',
            'commissionChequesReceipts' => 'Commission Cheques & receipts',
            'bankFinalOfferLetter' => 'Bank Final Offer Letter',
            'receiptForRentAndSecurityDeposit' => 'Receipt for Rent and Security Deposit',
            'keyHandoverReceipt' => 'Key Handover Receipt',
            'buyerUtilityConnectionReceipt' => 'Buyer Utility connection receipt',
            'others' => 'Others',
        ],
    ];

    // Image-type pba__Tags__c labels, stored verbatim in property_media_files.tags.
    const IMAGE_TAGS = [
        'Floorplan',
        'Interior',
        'Exterior',
        'Property Image',
        'Developer Logo',
        'Development Logo',
        'Brochure',
        'Dev Image',
        'Project Image',
        'Master Plan',
        'Site Map',
    ];

    // Video-type pba__Tags__c labels — the moving-media tags, kept separate from IMAGE_TAGS so
    // they map to videos rather than the photo gallery. Stored verbatim, same as IMAGE_TAGS.
    const VIDEO_TAGS = [
        'Main Video',
        'Virtual Tour',
        'Community Video',
        '360tour',
    ];

    // Document types (keys from DOCUMENT_TAGS['closing']) that must be present
    // before a closing is considered complete. Edit this list to change the rule.
    const MANDATORY_CLOSING_DOCUMENTS = [
        'screeningBuyer',
        'screeningSeller',
        'riskAssessmentBuyer',
        'riskAssessmentSeller',
        'formF',
        'liabilityLetterClearance',
    ];

    // Mandatory document types per module (keys from DOCUMENT_TAGS). Edit to change the rules.
    const MANDATORY_LISTING_DOCUMENTS = [
        'titleDeedOqood',
        'ownersPassportCopy',
        'formA',
    ];

    const MANDATORY_OFFER_DOCUMENTS = [
        'buyerPassportCopy',
        'offerLetterBuyer',
        'formB',
    ];

    const MANDATORY_DOCUMENTS_BY_MODULE = [
        self::MODULE_PROPERTY => self::MANDATORY_LISTING_DOCUMENTS,
        self::MODULE_OFFER => self::MANDATORY_OFFER_DOCUMENTS,
        self::MODULE_CLOSING => self::MANDATORY_CLOSING_DOCUMENTS,
    ];

    const LISTING_DOCUMENT_TYPE = [
        'paymentPlan' => 'Payment Plan',
        'floorPlan' => 'Floor Plan',
        'masterPlan' => 'Master Plan',
        'siteMap' => 'Site Map',
        'agreement' => 'Agreement',
        'certificate' => 'Certificate',
    ];

    const CONTACT_DOCUMENT_TYPE = [
        'passport_copy' => 'ID Proofs',
        'agreement' => 'Agreements',
        'visa_copy' => 'Visa Copy',
        'kyc_documents' => 'KYC Documents',
        'others' => 'Others',
    ];

    const DOCUMENT_TYPES = [
        self::MODULE_PROPERTY => self::LISTING_DOCUMENT_TYPE,
        self::MODULE_CONTACT => self::CONTACT_DOCUMENT_TYPE,
    ];

    // ==================== PDF TEMPLATES ====================

    const PDF_TEMPLATE_A4_SHOP_FLYER = 'a4_shop_flyer';

    const PDF_TEMPLATE_A4_SHOP_FLYER_HIGH_QUALITY = 'reizl_a4_shop_flyer_high_quality';

    const PDF_TEMPLATE_A4_SINGLE_PAGE_FLYER = 'a4_single_page_flyer';

    const PDF_TEMPLATE_BROCHURE_MULTI_PAGE = 'brochure_multi_page';

    const PDF_TEMPLATE_BROCHURE_MULTI_PAGE_USER = 'brochure_multi_page_user';

    const PDF_TEMPLATE_OFFPLAN_FACT_SHEET = 'offplan_fact_sheet';

    const PDF_TEMPLATE_OFFPLAN_FACT_SHEET_USER = 'offplan_fact_sheet_user';

    const PDF_TEMPLATE_COMPARABLE_NO_IMAGE = 'comparable_no_image';

    const PDF_TEMPLATE_COMPARABLE_SMALL_LANDSCAPE = 'comparable_small_landscape';

    const PDF_TEMPLATE_CMA = 'cma';

    const PDF_TEMPLATE_PERMIT_TEST = 'permit_test';

    const PDF_TEMPLATES = [
        self::PDF_TEMPLATE_A4_SHOP_FLYER => 'A4 Shop Flyer',
        self::PDF_TEMPLATE_A4_SINGLE_PAGE_FLYER => 'A4 Single Page Flyer',
        self::PDF_TEMPLATE_BROCHURE_MULTI_PAGE => 'Brochure Multi Page',
        self::PDF_TEMPLATE_BROCHURE_MULTI_PAGE_USER => 'Brochure Multi Page User',
        self::PDF_TEMPLATE_OFFPLAN_FACT_SHEET => 'Offplan Fact Sheet',
        self::PDF_TEMPLATE_OFFPLAN_FACT_SHEET_USER => 'Offplan Fact Sheet User',
        self::PDF_TEMPLATE_COMPARABLE_NO_IMAGE => 'Comparable No Image',
        self::PDF_TEMPLATE_COMPARABLE_SMALL_LANDSCAPE => 'Comparable Small Landscape',
        self::PDF_TEMPLATE_CMA => 'CMA',
        self::PDF_TEMPLATE_PERMIT_TEST => 'Permit Test',
        self::PDF_TEMPLATE_A4_SHOP_FLYER_HIGH_QUALITY => 'Riezl A4 Shop Flyer - High Quality Finish',
    ];

    // ==================== SOCIAL IMAGE PLATFORMS ====================

    const SOCIAL_PLATFORM_INSTAGRAM = 'instagram';

    const SOCIAL_PLATFORM_LINKEDIN = 'linkedin';

    const SOCIAL_PLATFORM_PRINT_A4 = 'print_a4';

    const SOCIAL_IMAGE_PLATFORMS = [
        self::SOCIAL_PLATFORM_INSTAGRAM => [
            'label' => 'Instagram',
            'width' => 1080,
            'height' => 1080,
        ],
        self::SOCIAL_PLATFORM_LINKEDIN => [
            'label' => 'LinkedIn',
            'width' => 1200,
            'height' => 627,
        ],
        self::SOCIAL_PLATFORM_PRINT_A4 => [
            'label' => 'Print (A4)',
            'width' => 2480,
            'height' => 3508,
        ],
    ];

    // ==================== GLOBAL SETTINGS ====================

    const GLOBAL_SETTINGS = [
        'smtpHost' => '_smtpHost',
        'smtpPort' => '_smtpPort',
        'smtpUsername' => '_smtpUsername',
        'smtpPassword' => '_smtpPassword',
        'smtpEncryption' => '_smtpEncryption',
        'sendFromName' => '_sendFromName',
        'sendFromEmail' => '_sendFromEmail',
        'websiteTitle' => '_websiteTitle',
        'appLogo' => '_appLogo',
        'watermark' => '_watermark',
        'timeZone' => '_timeZone',
        'matchingRentalPriceVariance' => '_matchingRentalPriceVariance',
        'matchingSalesPriceVarianceUpTo' => '_matchingSalesPriceVarianceUpTo',
        'matchingSalesPriceVarianceAbove' => '_matchingSalesPriceVarianceAbove',
        'matchingSalesPriceThreshold' => '_matchingSalesPriceThreshold',
        'permitRequestEmail' => '_permitRequestEmail',
        'permitRequestCustomEmails' => '_permitRequestCustomEmails',
        'excludeEmails' => '_excludeEmails',
        'twitterLink' => '_twitterLink',
        'facebookLink' => '_facebookLink',
        'instagramLink' => '_instagramLink',
        'youtubeLink' => '_youtubeLink',
        'linkedinLink' => '_linkedinLink',
        'targetKeys' => '_targetKeys',
        'minPasswordLength' => '_minPasswordLength',
        'passwordPolicy' => '_passwordPolicy',
        'leadScore' => '_leadScore',
    ];

    // ==================== MODULES ====================

    const MODULE_USER = 'user';

    const MODULE_ROLE = 'role';

    const MODULE_TEAM = 'team';

    const MODULE_PROPERTY = 'property';

    const MODULE_ENQUIRY = 'enquiry';

    const MODULE_CONTACT = 'contact';

    const MODULE_TASK = 'task';

    const MODULE_OFFER = 'offer';

    const MODULE_CLOSING = 'closing';

    const MODULE_CALENDAR_EVENT = 'calendar_event';

    const MODULE_BOOST = 'boost';

    const MODULE_FAVORITE = 'favorite';

    const MODULE_FOLLOW = 'follow';

    const MODULE_DOCUMENT = 'document';

    const MODULE_EMAIL_TEMPLATE = 'email_template';

    const MODULE_SETTING = 'setting';

    const MODULE_MARKETING_ASSET = 'marketing_asset';

    const MARKETING_ASSET_STATUS = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    const MARKETING_ASSET_CATEGORY = [
        'companyBrochures' => 'Company Brochures',
        'listingTemplates' => 'Listing Templates',
        'offerLetterTemplates' => 'Offer Letter Templates',
        'whatsappMessagePdfs' => 'WhatsApp Message PDFs',
        'brandingLogos' => 'Branding & Logos',
    ];

    const DOCUMENT_REFERENCE_PREFIX = [
        self::MODULE_PROPERTY => 'PR',
        self::MODULE_ENQUIRY => 'EN',
        self::MODULE_CONTACT => 'CO',
        self::MODULE_TASK => 'TA',
        self::MODULE_OFFER => 'OF',
        self::MODULE_CLOSING => 'CL',
        self::MODULE_CALENDAR_EVENT => 'CE',
        self::MODULE_BOOST => 'BO',
    ];

    const CRM_MODULES = [
        self::MODULE_USER => 'User',
        self::MODULE_ROLE => 'Role',
        self::MODULE_TEAM => 'Team',
        self::MODULE_PROPERTY => 'Property',
        self::MODULE_ENQUIRY => 'Enquiry',
        self::MODULE_CONTACT => 'Contact',
        self::MODULE_TASK => 'Task',
        self::MODULE_OFFER => 'Offer',
        self::MODULE_CLOSING => 'Closing',
        self::MODULE_CALENDAR_EVENT => 'Calendar Event',
        self::MODULE_BOOST => 'Boost',
        self::MODULE_FAVORITE => 'Favorite',
        self::MODULE_FOLLOW => 'Follow',
        self::MODULE_DOCUMENT => 'Document',
        self::MODULE_EMAIL_TEMPLATE => 'Email Template',
        self::MODULE_SETTING => 'Setting',
        self::MODULE_MARKETING_ASSET => 'Marketing Asset',
    ];

    const SOCIAL_IMAGE_TITLE = [
        'for_rent' => 'For Rent',
        'for_sale' => 'For Sale',
        'rented' => 'Rented',
        'sold' => 'Sold',
        'sale_agreed' => 'Sale Agreed',
        'just_listed' => 'Just Listed',
        'exclusive_listing' => 'Exclusive Listing',
        'price_reduced_sale' => 'Price Reduced For Sale',
        'price_reduced_rent' => 'Price Reduced For Rent',
    ];

    const PDF_AGENT_SCOPED_TEMPLATES = [
        self::PDF_TEMPLATE_BROCHURE_MULTI_PAGE,
        self::PDF_TEMPLATE_OFFPLAN_FACT_SHEET,
        self::PDF_TEMPLATE_A4_SHOP_FLYER,
        self::PDF_TEMPLATE_A4_SHOP_FLYER_HIGH_QUALITY,
    ];
}
