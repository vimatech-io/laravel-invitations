# Changelog

All notable changes to `vimatech/laravel-invitation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-09-27

### Security

- `accept($token, $user)` did not check that `$user` is the person invited: it accepted for whichever user it was given, and the package's own `POST /invitations/{token}/accept` route passes the signed-in user. Anyone holding a leaked invitation link (a forwarded email, a shared inbox, a support ticket, browser history) could accept it with their own account and receive what the invitation grants. When a user is given, `accept()` now compares the user's email with the invited address (trimmed, case-insensitive) and throws the new `InvitationEmailMismatchException`, which extends `InvitationNotFoundException` so existing catch blocks keep working. The accept route now shows "This invitation was sent to a different email address." Affected: all versions before 1.3.0. This is a deliberate behaviour change: an application that intentionally lets one account accept an invitation sent to another address must set `invitation.accept.require_matching_email` to `false`. The setting is read with a default of `true`, so a config file published before 1.3.0 (which does not have the key) gets the check. A user model with no email attribute now fails the check, since it is then compared as empty: such applications must disable the setting and do their own binding. `accept($token)` with no user (anonymous acceptance) is unchanged and not checked. `acceptForNewUser()` always compares the emails, whatever the setting says. Neither method checks that the user's email address is verified: an account registered with the invitee's address but never verified passes the check. Applications should require a verified address (`MustVerifyEmail` plus the `verified` middleware) before calling either method.
- `InvitationNotification` now implements `ShouldBeEncrypted`. The queued job used to carry the plain token and the invitee's address in clear text in the queue backend and in `failed_jobs`. Custom notifications extending it inherit this; a fully custom notification class must implement `ShouldBeEncrypted` itself. The payload is encrypted with `APP_KEY`: jobs already queued before the upgrade are still processed (their unencrypted payloads remain readable), but a job encrypted before an `APP_KEY` rotation needs the old key in `APP_PREVIOUS_KEYS` to be processed.
- `send()` and `resend()` now throw `InvitationConfigurationException` before writing anything when no invitation link can be built: `invitation.url_generator` is `null` and no route exists with the name in `invitation.route_name` (typically `routes.enabled` set to `false` without a `url_generator`). Previously `send()` created the invitation and the failure only happened in the queue worker, leaving a failed job holding a live token, and a retry then hit the duplicate guard. This check is not triggered when the configured notification overrides `toMail()` or `generateUrl()`, or is not a subclass of `InvitationNotification`. `create()` is unaffected.

### Changed

- The package controller no longer copies the token into the login URL query string (`?invitation_token=`) when a guest accepts, nor into the guest login link on the preview page. The preview page URL is stored as the session's intended URL instead, so a login flow that uses `redirect()->intended()` brings the user back to the invitation. An application that read `invitation_token` from its login page must switch to the intended URL. (Previously undocumented.)

### Added

- `invitation.accept.require_matching_email` (default `true`).
- `InvitationEmailMismatchException`.
- `InvitationConfigurationException::invitationUrlUnavailable()`.

Upgrade: republishing the config is optional. Check whether your application intentionally accepts invitations across accounts; if so, set the key to `false`.

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
