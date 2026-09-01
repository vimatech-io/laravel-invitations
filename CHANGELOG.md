# Changelog

All notable changes to `vimatech/laravel-invitation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-09-01

### Added

- `invitation.token_hmac_key` (env `INVITATION_TOKEN_HMAC_KEY`), so the `hmac` token strategy no longer has to key on `APP_KEY`. Rotating `APP_KEY` previously stopped every outstanding invitation token from matching, and the holder simply saw "invitation not found". Left unset the key still falls back to `APP_KEY`, byte for byte, so no stored token changes on upgrade. A dedicated key shorter than 32 characters is refused rather than accepted quietly.

### Fixed

- Resending an invitation now uses the same expiry rule as creating one. `resend()` computed `now()->addDays((int) config('invitation.expires_after_days', 7))`, and the inline default never applied because the key exists: an application configured with `null` — the documented setting for invitations that never expire — got `(int) null`, so the resent invitation expired the moment it was issued and the recipient was told it had expired. Both paths share one implementation.
- The `Invitations` facade no longer caches the manager. The manager is a mutable builder and the facade held one instance, so a chain that was abandoned or that failed its duplicate check left its subject, inviter, expiry and metadata behind — and the next invitation built through the facade inherited them. An invitation could be created against the wrong subject with the wrong metadata. Under a worker process the same instance also spanned requests.

## [1.1.0] - 2026-06-26

### Changed

- **Require PHP 8.3+** (dropped PHP 8.2 support).
- Test against Laravel 13 and unify CI into a single workflow.

### Added

- `CONTRIBUTING.md`, `SECURITY.md` and Dependabot configuration.
- `.gitattributes` (`export-ignore`) and Packagist badges.

## [1.0.0] - 2026-06-23

### Added

- Fluent API for creating, sending, accepting, declining, resending, and cancelling invitations
- Invite by email to any polymorphic Eloquent model (`for($model)`) or globally
- `HasInvitations` trait for any model (`invite()`, `inviteUser()`, `pendingInvitations`)
- Secure HMAC-hashed tokens (never stored in plain text); bcrypt strategy also available
- `acceptForNewUser()` method to handle post-registration acceptance with email verification
- Full event lifecycle: `InvitationCreated`, `InvitationSent`, `InvitationAccepted`, `InvitationDeclined`, `InvitationExpired`, `InvitationCancelled`, `InvitationResent`
- Typed exceptions: `InvitationNotFoundException`, `InvitationExpiredException`, `InvitationAlreadyAcceptedException`, `InvitationCancelledException`, `InvitationDeclinedException`, `InvitationAlreadyExistsException`
- Configurable expiration (`expiresInDays()`, `expiresAt()`, `neverExpires()`)
- Metadata support via `withMeta()`
- Configurable duplicate pending invitation policy
- Custom acceptance handler via callback (`InvitationManager::acceptedUsing()`) or config class
- Custom notification support via config or class extension
- Queued notifications with i18n support
- Optional public routes (`GET /invitations/{token}`, `POST .../accept`, `POST .../decline`) with per-IP rate limiting
- Query scopes: `pending()`, `accepted()`, `expired()`, `declined()`, `cancelled()`, `forEmail()`, `forSubject()`, `invitedBy()`
- Laravel 11, 12, and 13 support
- PHP 8.2+ support
