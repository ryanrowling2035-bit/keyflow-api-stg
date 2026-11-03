<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    // Google Maps Static API — used to render the location map image in property
    'google_maps' => [
        'static_key' => env('GOOGLE_MAPS_STATIC_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_SES_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('AWS_SES_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
        'region' => env('AWS_SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'salesforce' => [
        'client_id' => env('SALESFORCE_CLIENT_ID'),
        'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
        'login_url' => env('SALESFORCE_LOGIN_URL'),
        'user_name' => env('SALESFORCE_USERNAME'),
        'password' => env('SALESFORCE_PASSWORD'),
    ],

    'whatsapp_360' => [
        'enabled' => env('WHATSAPP_360_ENABLED', true),
        'endpoint' => env('WHATSAPP_360_ENDPOINT', 'https://api.360degreeapps.com/v1/sendSms'),
        'token' => env('WHATSAPP_360_TOKEN'),
        'sender' => env('WHATSAPP_360_SENDER'),
        'language' => env('WHATSAPP_360_LANGUAGE', 'en'),
        'fallback_image' => env('WHATSAPP_360_FALLBACK_IMAGE', 'https://picsum.photos/536/354'),
        'webhook_secret' => env('WHATSAPP_360_WEBHOOK_SECRET'),
        'keyflow_new_lead_fallback_enabled' => (bool) env('KEYFLOW_NEW_LEAD_FALLBACK_ENABLED', true),

        'templates' => [
            'new_lead' => env('WHATSAPP_360_TEMPLATE_NEW_LEAD', 'enquiry_response_to_agent'),
            // "KeyFlow Notification – New Lead" fallback template. Params:
            // {{1}} enquiry reference, {{2}} assigned agent, {{3}} enquiry link.
            // Create/approve this template in the 360dialog dashboard.
            'new_lead_keyflow' => env('WHATSAPP_360_TEMPLATE_NEW_LEAD_KEYFLOW', 'keyflow_new_lead'),
            'interested_listing' => env('WHATSAPP_360_TEMPLATE_INTERESTED_LISTING', 'new_listing_client_version'),
            'random_whatsapp' => env('WHATSAPP_360_TEMPLATE_RANDOM', 'whatsapp_random_noti_agent'),
            'hot_lead' => env('WHATSAPP_360_TEMPLATE_HOT_LEAD', 'notification_for_hot_lead_toagennt'),
            'feedback_received' => env('WHATSAPP_360_TEMPLATE_FEEDBACK_RECEIVED', 'feedback_received_for_agent_to_cst'),
            'initial_followup' => env('WHATSAPP_360_TEMPLATE_INITIAL_FOLLOWUP', 'script_a_initial_followup_with_details_new'),
            'second_followup' => env('WHATSAPP_360_TEMPLATE_SECOND_FOLLOWUP', 'script_b__second_followup'),
            'reactivation' => env('WHATSAPP_360_TEMPLATE_REACTIVATION', 'script_c_reactivation_automation_with_details'),
            'feedback_closed' => env('WHATSAPP_360_TEMPLATE_FEEDBACK_CLOSED', 'feedback_for_closed_lead'),
            'after_sales' => env('WHATSAPP_360_TEMPLATE_AFTER_SALES', 'script_c_cta2_reponse'),
            'feedback_lost' => env('WHATSAPP_360_TEMPLATE_FEEDBACK_LOST', 'feedback_for_lost_leads'),
        ],
    ],

    'keyflow_public_api' => [
        'api_key' => env('KEYFLOW_PUBLIC_API_KEY'),
    ],

];
