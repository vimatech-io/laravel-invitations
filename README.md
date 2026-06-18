# Laravel Invitation

[![Tests](https://github.com/vimatech-io/laravel-invitations/actions/workflows/tests.yml/badge.svg)](https://github.com/vimatech-io/laravel-invitations/actions/workflows/tests.yml)
[![PHPStan](https://github.com/vimatech-io/laravel-invitations/actions/workflows/phpstan.yml/badge.svg)](https://github.com/vimatech-io/laravel-invitations/actions/workflows/phpstan.yml)
[![Pint](https://github.com/vimatech-io/laravel-invitations/actions/workflows/pint.yml/badge.svg)](https://github.com/vimatech-io/laravel-invitations/actions/workflows/pint.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/vimatech/laravel-invitation.svg?style=flat-square)](https://packagist.org/packages/vimatech/laravel-invitation)
[![Total Downloads](https://img.shields.io/packagist/dt/vimatech/laravel-invitation.svg?style=flat-square)](https://packagist.org/packages/vimatech/laravel-invitation)
[![License](https://img.shields.io/packagist/l/vimatech/laravel-invitation.svg?style=flat-square)](https://packagist.org/packages/vimatech/laravel-invitation)

Generic email-based invitations for Laravel. Invite anyone to join, access, or accept an action related to any Eloquent model — Organization, Team, Project, Workspace, Document, and more.

## Why Laravel Invitation?

- Invite users to **any Eloquent model** — not just teams
- Secure token-based workflow (HMAC by default)
- Framework-agnostic — no dependency on Jetstream, Breeze, or any starter kit
- Extensible acceptance handlers and custom notifications
- Production-ready with queued emails, i18n, and rate-limited routes

## Quick Start

```php
// 1. Send an invitation
$invitation = Invitations::to('john@example.com')
    ->for($project)
    ->invitedBy(auth()->user())
    ->send();

// 2. Accept the invitation (via token from email)
Invitations::accept($token, auth()->user());
```

```
Invite → Email sent → User clicks link → Accept → Event dispatched
```

## Features

- Invite by email to any morphable model (or globally)
- Secure HMAC-hashed tokens (never stored in plain text)
- Fluent API for creating, sending, accepting, declining, and cancelling
- Typed exceptions for every error case
- Events dispatched for every lifecycle action
- Trait `HasInvitations` for any model
- Configurable expiration, duplicate policy, and acceptance handler
- Optional public routes with rate limiting for previewing/accepting invitations
- Queued notifications with i18n support
- Custom notification support (via config or class extension)
- Email normalization (case-insensitive)
- No dependency on any Laravel auth starter kit

## How It Works

```
┌─────────────────┐
│  Your App        │
│  (Team, Project) │
└────────┬────────┘
         │ ->invite('john@example.com')
         ▼
┌─────────────────┐
│ Create Invitation│
│ (token generated)│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Send Email      │
│  (queued)        │
└────────┬────────┘
         │ User clicks link
         ▼
┌─────────────────┐
│ Accept Invitation│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│Acceptance Handler│
│ (attach user)    │
└─────────────────┘
```

> **Subject** — The model being invited to (Project, Team, Organization, Workspace, etc.). Set via `->for($model)`. An invitation without a subject is a "global" invitation.

## Requirements

- PHP 8.2+
- Laravel 11+

## Installation

```bash
composer require vimatech/laravel-invitation
```

### Publish the configuration file

```bash
php artisan vendor:publish --tag="invitation-config"
```

### Publish and run migrations

```bash
php artisan vendor:publish --tag="invitation-migrations"
php artisan migrate
```

### Publish views (optional)

```bash
php artisan vendor:publish --tag="invitation-views"
```

## Usage

### Basic invitation

```php
use Vimatech\Invitation\Facades\Invitations;

$invitation = Invitations::to('john@example.com')->send();
```

### Invitation to a User model

If you already have the user model, you can pass it directly — the email will be extracted automatically:

```php
$invitation = Invitations::toUser($user)
    ->for($project)
    ->send();

// Or via the HasInvitations trait:
$project->inviteUser($user)->send();
```

### Invitation linked to a model

```php
use Vimatech\Invitation\Facades\Invitations;

$invitation = Invitations::to('john@example.com')
    ->for($project)
    ->invitedBy($currentUser)
    ->expiresInDays(7)
    ->withMeta(['role' => 'admin'])
    ->send();
```

### Using the `HasInvitations` trait

```php
use Illuminate\Database\Eloquent\Model;
use Vimatech\Invitation\Concerns\HasInvitations;

class Project extends Model
{
    use HasInvitations;
}

// Then:
$project->invite('john@example.com')
    ->invitedBy($user)
    ->expiresInDays(10)
    ->withMeta(['role' => 'member'])
    ->send();

// List invitations
$project->invitations;
$project->pendingInvitations;
```

### Accepting an invitation

```php
use Vimatech\Invitation\Facades\Invitations;

$invitation = Invitations::accept($token, $user);
```

### Accepting after registration (new user)

```php
use Vimatech\Invitation\Facades\Invitations;

// After user registration (verifies the invitation email matches the user's email):
$invitation = Invitations::acceptForNewUser($token, $newUser);
```

### Cancelling an invitation

```php
use Vimatech\Invitation\Facades\Invitations;

Invitations::cancel($invitation);
```

### Declining an invitation (by invitee)

The invitee can actively refuse an invitation:

```php
use Vimatech\Invitation\Facades\Invitations;

Invitations::decline($token);
```

### Resending an invitation

Resend generates a new token and resets the expiration. Only pending or expired invitations can be resent — accepted and cancelled invitations will throw an exception.

```php
use Vimatech\Invitation\Facades\Invitations;

Invitations::resend($invitation);
```

### Querying invitations

```php
use Vimatech\Invitation\Models\Invitation;

Invitation::pending()->get();
Invitation::accepted()->get();
Invitation::expired()->get();
Invitation::declined()->get();
Invitation::cancelled()->get();
Invitation::forEmail('john@example.com')->get();
Invitation::forSubject($project)->get();
Invitation::invitedBy($user)->get();
```

## Common Use Cases

### Team invitation

```php
$team->invite('john@example.com')
    ->invitedBy(auth()->user())
    ->send();
```

### Organization with role

```php
$organization->invite('john@example.com')
    ->withMeta(['role' => 'admin'])
    ->send();
```

### Project collaboration

```php
$project->invite('john@example.com')
    ->withMeta(['role' => 'editor', 'department' => 'engineering'])
    ->expiresInDays(14)
    ->send();
```

### Global invitation (no subject)

```php
Invitations::to('john@example.com')->send();
```

## Metadata

Store any custom data with an invitation:

```php
$invitation = Invitations::to('john@example.com')
    ->withMeta(['role' => 'editor', 'department' => 'engineering'])
    ->send();

// Access later:
$invitation->meta['role']; // 'editor'
```

## Expiration

Invitations expire based on the `expires_after_days` config (default: 7 days). You can also set a custom expiration:

```php
Invitations::to('john@example.com')
    ->expiresInDays(30)
    ->send();

// Or with a specific date:
Invitations::to('john@example.com')
    ->expiresAt(now()->addWeeks(2))
    ->send();
```

### No expiration

For use cases like friend requests where invitations should stay active indefinitely:

```php
// Per invitation:
Invitations::to('jane@example.com')
    ->for($user)
    ->neverExpires()
    ->send();

// Or globally via config:
// 'expires_after_days' => null,
```

## Duplicate Policy

By default, sending a second invitation to the same email for the same subject throws an `InvitationAlreadyExistsException`:

```php
$project->invite('john@example.com')->send(); // ✅
$project->invite('john@example.com')->send(); // ❌ InvitationAlreadyExistsException
```

To allow duplicate pending invitations, set this in your config:

```php
'duplicates' => [
    'allow_pending_for_same_email_and_subject' => true,
],
```

## Events

The following events are dispatched:

| Event | When |
|-------|------|
| `InvitationCreated` | Invitation record created |
| `InvitationSent` | Notification sent |
| `InvitationAccepted` | Invitation accepted |
| `InvitationDeclined` | Invitation declined by invitee |
| `InvitationExpired` | Expired invitation discovered during acceptance |
| `InvitationCancelled` | Invitation cancelled |
| `InvitationResent` | Invitation resent with new token |

All events contain the `$invitation` property. `InvitationAccepted` also contains the `$user`.

### Listening to events

```php
use Vimatech\Invitation\Events\InvitationAccepted;

Event::listen(InvitationAccepted::class, function ($event) {
    $event->invitation->subject->members()->attach($event->user);
});
```

## Custom Acceptance Handler

### Via callback

```php
use Vimatech\Invitation\InvitationManager;

InvitationManager::acceptedUsing(function ($invitation, $user) {
    $invitation->subject->members()->attach($user, [
        'role' => $invitation->meta['role'] ?? 'member',
    ]);
});
```

### Via config

Create a class implementing the `AcceptsInvitations` contract:

```php
use Vimatech\Invitation\Contracts\AcceptsInvitations;
use Vimatech\Invitation\Models\Invitation;
use Illuminate\Database\Eloquent\Model;

class MyAcceptanceHandler implements AcceptsInvitations
{
    public function accept(Invitation $invitation, ?Model $user = null): void
    {
        // Your logic here
    }
}
```

Then set it in config:

```php
// config/invitation.php
'acceptance_handler' => App\Invitations\MyAcceptanceHandler::class,
```

## Custom Notification

You can customize the invitation email in several ways:

### Extend the default notification

```php
use Vimatech\Invitation\Notifications\InvitationNotification;

class CustomInvitationNotification extends InvitationNotification
{
    protected function getSubjectLine(): string
    {
        return __('Join :team!', ['team' => $this->invitation->subject?->name]);
    }

    protected function getGreetingLine(): string
    {
        return __('You have been invited to collaborate.');
    }

    protected function getActionText(): string
    {
        return __('Accept Invitation');
    }
}
```

### Or create a fully custom notification

```php
// config/invitation.php
'notification' => App\Notifications\CustomInvitationNotification::class,
```

Your notification will receive the `Invitation` model and the plain token in its constructor.

### Translations

All notification strings use Laravel's `__()` helper. Add translations via JSON files:

```json
// lang/fr.json
{
    "You have been invited": "Vous avez été invité",
    "View Invitation": "Voir l'invitation",
    "This invitation will expire on :date.": "Cette invitation expirera le :date.",
    "Invited by: :name": "Invité par : :name"
}
```

## Public Routes

When `routes.enabled` is `true` (default), the package registers:

| Method | URI | Name |
|--------|-----|------|
| GET | `/invitations/{token}` | `invitations.preview` |
| POST | `/invitations/{token}/accept` | `invitations.accept` |
| POST | `/invitations/{token}/decline` | `invitations.decline` |

Configure in `config/invitation.php`:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'invitations',
    'middleware' => ['web'],
    'throttle' => 'throttle:30,1', // Per-IP rate limit. Set to null to disable.
],
```

### Authentication and routes

The **preview page** (`GET`) is public — anyone with the link can view the invitation details.

The **accept route** (`POST`) does not enforce authentication by default. Two common patterns:

- **Existing user**: Add `auth` middleware, then call `Invitations::accept($token, auth()->user())`
- **New user**: Redirect to registration, then call `Invitations::acceptForNewUser($token, $newUser)` after signup — this verifies the registered email matches the invitation

To require authentication, add `auth` to the route middleware in config:

```php
'middleware' => ['web', 'auth'],
```

## Database Schema

```
invitations
├── id
├── uuid
├── email
├── token_hash
├── subject_type / subject_id    (polymorphic, nullable)
├── inviter_type / inviter_id    (polymorphic, nullable)
├── accepted_by_type / accepted_by_id (polymorphic, nullable)
├── status                       (pending, accepted, declined, expired, cancelled)
├── expires_at
├── accepted_at
├── declined_at
├── cancelled_at
├── meta                         (JSON)
└── timestamps
```

## Token Security

- Tokens are generated using `Str::random(64)`
- Tokens are hashed before storage using HMAC (default) or bcrypt
- HMAC (recommended): deterministic, allows direct DB lookup (O(1)), relies on `APP_KEY`
- Bcrypt: non-deterministic, requires iterating records (O(n)), resistant to DB leaks
- The plain token is only available at the moment of creation/sending
- Token verification uses constant-time comparison
- Route tokens are validated via regex constraint (`[a-zA-Z0-9]{64}`)

## Configuration

Full config options in `config/invitation.php`:

```php
return [
    'table' => 'invitations',
    'model' => \Vimatech\Invitation\Models\Invitation::class,
    'expires_after_days' => 7, // Set to null for invitations that never expire
    'notification' => \Vimatech\Invitation\Notifications\InvitationNotification::class,
    'acceptance_handler' => null,
    'routes' => [
        'enabled' => true,
        'prefix' => 'invitations',
        'middleware' => ['web'],
        'throttle' => 'throttle:30,1',
    ],
    'route_name' => 'invitations.preview',
    'url_generator' => null,
    'duplicates' => [
        'allow_pending_for_same_email_and_subject' => false,
    ],
    'token_strategy' => 'hmac', // 'hmac' (recommended) or 'hash'
];
```

## Advanced Usage

### Using the InvitationManager directly

```php
use Vimatech\Invitation\InvitationManager;

$invitation = app(InvitationManager::class)
    ->to('john@example.com')
    ->for($project)
    ->invitedBy($user)
    ->send();
```

## Testing

```bash
composer test
```

## Exceptions

All exceptions extend `InvitationException`:

- `InvitationNotFoundException` — Token invalid or no matching invitation
- `InvitationExpiredException` — Invitation has expired
- `InvitationAlreadyAcceptedException` — Already accepted
- `InvitationCancelledException` — Invitation was cancelled
- `InvitationDeclinedException` — Invitation was declined by invitee
- `InvitationAlreadyExistsException` — Duplicate pending invitation

## Credits

Built and maintained by [Vimatech](https://vimatech.io/). Created by [Adel Zemzemi](https://github.com/adelzemzemi).

## License

MIT
