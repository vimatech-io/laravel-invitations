# Changelog

All notable changes to `vimatech/laravel-invitation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] - 2026-09-27

### Fixed

- `cancel()`, `decline()`, `resend()` and the expiry marking inside `accept()` checked the status of the in-memory model and then wrote unconditionally. Starting from an instance loaded before a concurrent change, `cancel()` could record an already accepted invitation as cancelled while the acceptance stood, `decline()` could overwrite an acceptance, the expiry marking could overwrite a cancellation or an acceptance, and `resend()` could put an accepted (or cancelled) invitation back to pending with a new token, so it could be accepted a second time. Every transition is now a single `UPDATE` constrained to the statuses it is allowed to leave: `cancel()` from pending or expired, `resend()` from pending or expired, `decline()` and the acceptance itself from pending and not past `expires_at` (checked in the same statement), and the expiry marking from pending. When no row is updated, the model is refreshed and the exception matching the status actually stored is thrown (`InvitationAlreadyAcceptedException`, `InvitationCancelledException`, `InvitationDeclinedException`, `InvitationExpiredException`). Consequence for callers: `accept()` on an invitation cancelled concurrently now throws `InvitationCancelledException` instead of `InvitationAlreadyAcceptedException`.
- `accept()` on an invitation already stored as expired no longer dispatches `InvitationExpired` again on every attempt. It is now dispatched once, when the status actually changes.
- The status update and the acceptance handler (the `acceptedUsing()` callback or `invitation.acceptance_handler`) now run in one database transaction on the invitation model's connection. A handler that threw used to leave the invitation accepted with nothing created for it, and it could then never be accepted again. It now leaves the invitation pending instead. `InvitationAccepted` is dispatched after the transaction commits. Note for consumers: the handler runs inside a transaction, so a queued job it dispatches should use `afterCommit` if that job needs to read what the handler wrote.
- The duplicate guard (`invitation.duplicates.allow_pending_for_same_email_and_subject` set to `false`) ignored `expires_at`, so an expired invitation still stored as pending blocked inviting the same address to the same subject again with `InvitationAlreadyExistsException`. Expired invitations no longer count towards the guard. They still appear in the `pending()` scope and in `pendingInvitations()`; that is unchanged.

No public signature changes. There is nothing to do to adopt this release.

## [1.2.0] - 2026-09-01

### Added

- `invitation.token_hmac_key` (env `INVITATION_TOKEN_HMAC_KEY`), so the `hmac` token strategy no longer has to key on `APP_KEY`. Rotating `APP_KEY` previously stopped every outstanding invitation token from matching, and the holder simply saw "invitation not found". Left unset the key still falls back to `APP_KEY`, byte for byte, so no stored token changes on upgrade. A dedicated key shorter than 32 characters is refused rather than accepted quietly.

### Fixed

- Resending an invitation now uses the same expiry rule as creating one. `resend()` computed `now()->addDays((int) config('invitation.expires_after_days', 7))`, and the inline default never applied because the key exists: an application configured with `null` (the documented setting for invitations that never expire) got `(int) null`, so the resent invitation expired the moment it was issued and the recipient was told it had expired. Both paths share one implementation.
- The `Invitations` facade no longer caches the manager. The manager is a mutable builder and the facade held one instance, so a chain that was abandoned or that failed its duplicate check left its subject, inviter, expiry and metadata behind. The next invitation built through the facade then inherited them. An invitation could be created against the wrong subject with the wrong metadata. Under a worker process the same instance also spanned requests.

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
