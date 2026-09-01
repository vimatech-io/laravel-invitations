<?php

use Vimatech\Invitation\Models\Invitation;
use Vimatech\Invitation\Notifications\InvitationNotification;

return [
    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    */
    'table' => 'invitations',

    /*
    |--------------------------------------------------------------------------
    | Invitation Model
    |--------------------------------------------------------------------------
    */
    'model' => Invitation::class,

    /*
    |--------------------------------------------------------------------------
    | Token Expiration
    |--------------------------------------------------------------------------
    | Number of days before an invitation expires.
    | Set to null for invitations that never expire.
    */
    'expires_after_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Notification Class
    |--------------------------------------------------------------------------
    */
    'notification' => InvitationNotification::class,

    /*
    |--------------------------------------------------------------------------
    | Acceptance Handler
    |--------------------------------------------------------------------------
    | A class implementing AcceptsInvitations contract.
    | Set to null to use default behavior.
    */
    'acceptance_handler' => null,

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'invitations',
        'middleware' => ['web'],
        'throttle' => 'throttle:30,1', // Per-IP rate limit (requests,minutes). Set to null to disable.
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Name for URL Generation
    |--------------------------------------------------------------------------
    */
    'route_name' => 'invitations.preview',

    /*
    |--------------------------------------------------------------------------
    | Route Names
    |--------------------------------------------------------------------------
    | Named routes used in the preview view for form actions.
    */
    'route_names' => [
        'preview' => 'invitations.preview',
        'accept' => 'invitations.accept',
        'decline' => 'invitations.decline',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom URL Generator
    |--------------------------------------------------------------------------
    | A callable or class that generates the invitation URL.
    | Receives the plain token as argument.
    | Set to null to use default route-based URL.
    */
    'url_generator' => null,

    /*
    |--------------------------------------------------------------------------
    | Duplicate Invitation Policy
    |--------------------------------------------------------------------------
    */
    'duplicates' => [
        'allow_pending_for_same_email_and_subject' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Hashing Strategy
    |--------------------------------------------------------------------------
    | Supported: "hmac" (hash_hmac with app key, recommended), "hash" (Hash::make/check)
    | "hmac" allows direct DB lookups (O(1)), "hash" requires iterating all records (O(n)).
    */
    'token_strategy' => 'hmac',

    /*
    |--------------------------------------------------------------------------
    | Token HMAC Key
    |--------------------------------------------------------------------------
    | The key used by the "hmac" strategy. At least 32 characters.
    |
    | Left unset, token hashes are derived from APP_KEY. That works, but it ties
    | every pending invitation to it: rotating APP_KEY stops every outstanding
    | token from matching, and holders see "invitation not found". Setting a
    | dedicated key here decouples the two.
    |
    | To adopt one without invalidating tokens already sent, set it to your
    | current APP_KEY value first — the hashes are identical — then rotate both
    | independently once the outstanding invitations have expired.
    */
    'token_hmac_key' => env('INVITATION_TOKEN_HMAC_KEY'),
];
