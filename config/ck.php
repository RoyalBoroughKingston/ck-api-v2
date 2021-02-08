<?php

return [

    /*
     * The default number of results to show in paginated responses.
     */
    'pagination_results' => 25,

    /*
     * The maximum number of results that can be requested.
     */
    'max_pagination_results' => 100,

    /*
     * Available drivers: 'stub', 'nominatim', 'google'
     */
    'geocode_driver' => env('GEOCODE_DRIVER', 'stub'),

    /*
     * The API key to use with the Google Geocoding API.
     */
    'google_api_key' => env('GOOGLE_API_KEY'),

    /*
     * Available drivers: 'log', 'null', 'gov'
     */
    'email_driver' => env('EMAIL_DRIVER', 'log'),

    /*
     * Available drivers: 'log', 'null', 'gov'
     */
    'sms_driver' => env('SMS_DRIVER', 'log'),

    /*
     * The GOV.UK Notify API key.
     */
    'gov_notify_api_key' => env('GOV_NOTIFY_API_KEY'),

    /*
     * The contact details for the global admin team.
     */
    'global_admin' => [
        'email' => env('GLOBAL_ADMIN_EMAIL'),
    ],

    /*
     * The URI for the backend app.
     */
    'backend_uri' => env('BACKEND_URI', ''),

    /*
     * The number of working days a service must respond within.
     */
    'working_days_for_service_to_respond' => 10,

    /*
     * If one time password authentication should be enabled.
     */
    'otp_enabled' => env('OTP_ENABLED', true),

    /*
     * The distance (in miles) that the search results should limit up to.
     */
    'search_distance' => 15,

    /*
     * The dimensions to automatically generate resized images at.
     */
    'cached_image_dimensions' => [
        150,
        350,
    ],

    /*
     * Used for GOV.UK Notify.
     */
    'notifications_template_ids' => [
        'password_reset' => [
            'email' => '8bc73398-f16d-42b0-8ef1-85aafef7d2b4',
        ],
        'otp_login_code' => [
            'sms' => '74a989eb-db89-4000-8d69-fa90826cb9b9',
        ],
        'referral_created' => [
            'notify_client' => [
                'email' => '48dce378-91de-4bf9-9ac8-77ec76229221',
                'sms' => 'bb71dc5b-1a30-4681-bf8a-69dac4494b26',
            ],
            'notify_referee' => [
                'email' => '9e38d7db-8a61-44cb-8169-8a4ecf6661f7',
                'sms' => '00664af2-4593-47ef-8e80-13b8b0f3981e',
            ],
            'notify_service' => [
                'email' => 'ef7c27d0-d731-49e3-b4ff-60e1fee686e5',
            ],
        ],
        'referral_unactioned' => [
            'notify_service' => [
                'email' => '6edd8392-a138-462c-a952-308a6746c669',
            ],
        ],
        'referral_still_unactioned' => [
            'notify_global_admin' => [
                'email' => '26979e76-1739-4824-9687-cdb528bf6e5b',
            ],
        ],
        'referral_completed' => [
            'notify_client' => [
                'email' => 'f0bc02af-4b57-4b7e-96fa-987408b7fb7f',
                'sms' => '763be3c9-90cb-486b-a2f2-d2f4e68ded0c',
            ],
            'notify_referee' => [
                'email' => '53e08e71-828c-4889-ba43-5aae50dc1bd5',
                'sms' => 'f85f8eb0-5d7f-4b25-8e49-e564e2ed96d4',
            ],
        ],
        'referral_incompleted' => [
            'notify_client' => [
                'email' => '50998ddc-0155-40e2-bba5-164f3378806a',
                'sms' => '4bea0fdb-e02c-48d3-b900-b6d96829910d',
            ],
            'notify_referee' => [
                'email' => '061ff344-9964-4a96-9bf3-6f9322610713',
                'sms' => '0eef2dad-1dbf-4cf4-8278-83e9c038c13e',
            ],
        ],
        'page_feedback_received' => [
            'notify_global_admin' => [
                'email' => 'ac05c50a-1b67-4e76-8d56-9da72fef57b7',
            ],
        ],
        'update_request_received' => [
            'notify_submitter' => [
                'email' => '14468224-add5-494f-af4d-10b30b1bf74c',
            ],
            'notify_global_admin' => [
                'email' => '8f562f1f-a215-47a7-a370-7d23cd4af779',
            ],
        ],
        'update_request_approved' => [
            'notify_submitter' => [
                'email' => '197eb9e6-6fe0-4b6b-af4e-397f3ea7124c',
            ],
        ],
        'update_request_rejected' => [
            'notify_submitter' => [
                'email' => 'fb265b95-9911-4c85-bd36-65b0a7128e65',
            ],
        ],
        'user_created' => [
            'notify_user' => [
                'email' => '2e2ae951-4f26-402e-9251-912880900637',
            ],
        ],
        'user_roles_updated' => [
            'notify_user' => [
                'email' => 'aacadcd9-81ab-42cc-a306-87dae5a3de64',
            ],
        ],
        'service_created' => [
            'notify_global_admin' => [
                'email' => '20e32c1d-667c-4e82-9a71-c4730329af91',
            ],
        ],
        'service_update_prompt' => [
            'notify_service_admin' => [
                'email' => '2e028fb4-24ca-4665-8d67-48363f9dfe15',
            ],
            'notify_global_admin' => [
                'email' => '8557ae00-57d2-4880-a4d4-357663bc86b9',
            ],
        ],
        'stale_service_disabled' => [
            'notify_global_admin' => [
                'email' => '03a062c3-0172-4604-9f38-9c8490fdc96f',
            ],
        ],
        'scheduled_report_generated' => [
            'notify_global_admin' => [
                'email' => '6d0f4c77-6a82-4a12-8056-b51fa93bb20d',
            ],
        ],
        'organisation_sign_up_form_received' => [
            'notify_submitter' => [
                'email' => '3e710bf7-750c-4499-a5a0-c0b3c76eb2dd',
            ],
            'notify_global_admin' => [
                'email' => '176b47e3-fdc4-485c-97f8-c3a33a229c86',
            ],
        ],
        'organisation_sign_up_form_approved' => [
            'notify_submitter' => [
                'email' => '0a52c55d-237c-4427-9a3d-f375be41d06d',
            ],
        ],
        'organisation_sign_up_form_rejected' => [
            'notify_submitter' => [
                'email' => '57130622-53ad-409c-ba24-410cb4426594',
            ],
        ],
    ],
];
