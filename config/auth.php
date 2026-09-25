<?php

use App\Models\Customer;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    // 'expire' is in MINUTES, not seconds, and counts from when the token is
    // created, not when the mail is opened.
    //
    // customers: deliberately short, so a reset link is dead almost immediately
    // if it is forwarded, left in an open inbox, or read off a shared machine. A
    // customer who does not act at once gets "This password reset token is
    // invalid" and has to request a new link.
    //
    // users: 3 minutes, matching customers. This broker also issues
    // administrator invitations (App\Notifications\AdminInvitation) and the
    // admin panel's own password resets, so both links die just as quickly. The
    // invitation mail is queued and the clock starts when the token is created,
    // so a slow queue worker eats into those 3 minutes; an admin who misses the
    // window asks a super admin to resend the invitation.
    //
    // The two numbers are allowed to differ: each notification quotes its OWN
    // broker's expiry (CustomerResetPassword reads `customers`, AdminInvitation
    // reads `users`). Anything that quotes auth.defaults.passwords instead —
    // Laravel's stock ResetPassword mail does — would give customers the admin
    // window, so customer mail must keep going through CustomerResetPassword.
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 3,
            'throttle' => 60,
        ],
        'customers' => [
            'provider' => 'customers',
            'table' => 'password_reset_tokens',
            'expire' => 3,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
