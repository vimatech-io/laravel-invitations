<?php

declare(strict_types=1);

namespace Vimatech\Invitation;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Vimatech\Invitation\Contracts\AcceptsInvitations;
use Vimatech\Invitation\Enums\InvitationStatus;
use Vimatech\Invitation\Events\InvitationAccepted;
use Vimatech\Invitation\Events\InvitationCancelled;
use Vimatech\Invitation\Events\InvitationCreated;
use Vimatech\Invitation\Events\InvitationDeclined;
use Vimatech\Invitation\Events\InvitationExpired;
use Vimatech\Invitation\Events\InvitationResent;
use Vimatech\Invitation\Events\InvitationSent;
use Vimatech\Invitation\Exceptions\InvitationAlreadyAcceptedException;
use Vimatech\Invitation\Exceptions\InvitationAlreadyExistsException;
use Vimatech\Invitation\Exceptions\InvitationCancelledException;
use Vimatech\Invitation\Exceptions\InvitationDeclinedException;
use Vimatech\Invitation\Exceptions\InvitationExpiredException;
use Vimatech\Invitation\Exceptions\InvitationNotFoundException;
use Vimatech\Invitation\Models\Invitation;
use Vimatech\Invitation\Support\InvitationToken;

class InvitationManager
{
    private ?string $email = null;

    private ?Model $subject = null;

    private ?Model $inviter = null;

    private ?CarbonInterface $expiresAt = null;

    private bool $neverExpires = false;

    /** @var array<string, mixed> */
    private array $meta = [];

    protected static ?Closure $acceptedUsing = null;

    public function to(string $email): static
    {
        $validator = Validator::make(
            ['email' => $email],
            ['email' => 'required|email']
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages(['email' => 'The provided email address is invalid.']);
        }

        $this->email = strtolower($email);

        return $this;
    }

    public function toUser(Model $user): static
    {
        $email = $user->getAttribute('email');

        if (! is_string($email) || $email === '') {
            throw new \InvalidArgumentException('The given model does not have an email attribute.');
        }

        return $this->to($email);
    }

    public function for(?Model $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function invitedBy(?Model $inviter): static
    {
        $this->inviter = $inviter;

        return $this;
    }

    public function expiresAt(?CarbonInterface $date): static
    {
        $this->expiresAt = $date;

        return $this;
    }

    public function expiresInDays(int $days): static
    {
        $this->expiresAt = now()->addDays($days);
        $this->neverExpires = false;

        return $this;
    }

    public function neverExpires(): static
    {
        $this->neverExpires = true;
        $this->expiresAt = null;

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Create the invitation and send the notification.
     * Returns the invitation with the plain token set as a transient attribute.
     */
    public function send(): Invitation
    {
        $invitation = $this->create();

        $this->sendNotification($invitation);

        event(new InvitationSent($invitation));

        return $invitation;
    }

    /**
     * Create the invitation without sending notification.
     */
    public function create(): Invitation
    {
        $this->guardAgainstDuplicate();

        [$plainToken, $hashedToken] = InvitationToken::generate();

        /** @var class-string<Invitation> $modelClass */
        $modelClass = config('invitation.model', Invitation::class);

        $invitation = new $modelClass;
        $invitation->fill([
            'uuid' => (string) Str::uuid(),
            'email' => $this->email,
            'token_hash' => $hashedToken,
            'subject_type' => $this->subject?->getMorphClass(),
            'subject_id' => $this->subject?->getKey(),
            'inviter_type' => $this->inviter?->getMorphClass(),
            'inviter_id' => $this->inviter?->getKey(),
            'status' => InvitationStatus::Pending,
            'expires_at' => $this->resolveExpiration(),
            'meta' => ! empty($this->meta) ? $this->meta : null,
        ]);
        $invitation->save();

        // Store plain token as transient property (not persisted)
        $invitation->plainToken = $plainToken;

        event(new InvitationCreated($invitation));

        $this->reset();

        return $invitation;
    }

    /**
     * Accept an invitation by token.
     */
    public function accept(string $token, ?Model $user = null): Invitation
    {
        $invitation = $this->findByToken($token);

        return $this->acceptResolved($invitation, $user);
    }

    /**
     * Accept a resolved invitation instance.
     */
    private function acceptResolved(Invitation $invitation, ?Model $user = null): Invitation
    {
        if ($invitation->isCancelled()) {
            throw new InvitationCancelledException;
        }

        if ($invitation->isDeclined()) {
            throw new InvitationDeclinedException;
        }

        if ($invitation->isAccepted()) {
            throw new InvitationAlreadyAcceptedException;
        }

        if ($invitation->isExpired()) {
            $invitation->update(['status' => InvitationStatus::Expired]);
            event(new InvitationExpired($invitation));

            throw new InvitationExpiredException;
        }

        // Atomic update to prevent race conditions
        $updated = $invitation->newQuery()
            ->whereKey($invitation->getKey())
            ->where('status', InvitationStatus::Pending)
            ->update([
                'status' => InvitationStatus::Accepted->value,
                'accepted_at' => now(),
                'accepted_by_type' => $user?->getMorphClass(),
                'accepted_by_id' => $user?->getKey(),
            ]);

        if ($updated === 0) {
            $invitation->refresh();

            throw new InvitationAlreadyAcceptedException;
        }

        $invitation->refresh();

        $this->runAcceptanceHandler($invitation, $user);

        event(new InvitationAccepted($invitation, $user));

        return $invitation;
    }

    /**
     * Accept an invitation for a newly registered user.
     * Verifies the invitation email matches the user's email.
     */
    public function acceptForNewUser(string $token, Model $user): Invitation
    {
        $invitation = $this->findByToken($token);

        $userEmail = strtolower((string) $user->getAttribute('email'));

        if ($invitation->email !== $userEmail) {
            throw new InvitationNotFoundException;
        }

        return $this->acceptResolved($invitation, $user);
    }

    /**
     * Cancel an invitation.
     */
    public function cancel(Invitation $invitation): Invitation
    {
        if ($invitation->isAccepted()) {
            throw new InvitationAlreadyAcceptedException;
        }

        if ($invitation->isDeclined()) {
            throw new InvitationDeclinedException;
        }

        if ($invitation->isCancelled()) {
            throw new InvitationCancelledException;
        }

        $invitation->update([
            'status' => InvitationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        event(new InvitationCancelled($invitation));

        return $invitation;
    }

    /**
     * Decline an invitation by token (invitee refuses).
     */
    public function decline(string $token): Invitation
    {
        $invitation = $this->findByToken($token);

        if ($invitation->isAccepted()) {
            throw new InvitationAlreadyAcceptedException;
        }

        if ($invitation->isCancelled()) {
            throw new InvitationCancelledException;
        }

        if ($invitation->isExpired()) {
            throw new InvitationExpiredException;
        }

        if ($invitation->isDeclined()) {
            throw new InvitationDeclinedException;
        }

        $invitation->update([
            'status' => InvitationStatus::Declined,
            'declined_at' => now(),
        ]);

        event(new InvitationDeclined($invitation));

        return $invitation;
    }

    /**
     * Resend an invitation notification with a new token.
     */
    public function resend(Invitation $invitation): Invitation
    {
        if ($invitation->isAccepted()) {
            throw new InvitationAlreadyAcceptedException;
        }

        if ($invitation->isCancelled()) {
            throw new InvitationCancelledException;
        }

        if ($invitation->isDeclined()) {
            throw new InvitationDeclinedException;
        }

        [$plainToken, $hashedToken] = InvitationToken::generate();

        $invitation->update([
            'token_hash' => $hashedToken,
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays((int) config('invitation.expires_after_days', 7)),
        ]);

        $invitation->plainToken = $plainToken;

        $this->sendNotification($invitation);

        event(new InvitationResent($invitation));

        return $invitation;
    }

    /**
     * Register a custom acceptance callback.
     *
     * Pass null to clear the callback (recommended in Octane environments).
     */
    public static function acceptedUsing(?Closure $callback): void
    {
        static::$acceptedUsing = $callback;
    }

    /**
     * Flush static state. Useful for Laravel Octane.
     */
    public static function flush(): void
    {
        static::$acceptedUsing = null;
    }

    /**
     * Find an invitation by verifying a plain token against stored hashes.
     *
     * @throws InvitationNotFoundException
     */
    public function findByToken(string $token): Invitation
    {
        /** @var class-string<Invitation> $modelClass */
        $modelClass = config('invitation.model', Invitation::class);

        $strategy = config('invitation.token_strategy', 'hash');

        // HMAC is deterministic: we can do a direct DB lookup
        if ($strategy === 'hmac') {
            $hashed = InvitationToken::hash($token);

            /** @var Invitation|null $invitation */
            $invitation = $modelClass::query()
                ->where('token_hash', $hashed)
                ->first();

            if ($invitation) {
                return $invitation;
            }

            throw new InvitationNotFoundException;
        }

        // Bcrypt: must iterate, but stream results to limit memory usage
        $invitations = $modelClass::query()->lazyById(500);

        foreach ($invitations as $invitation) {
            if (InvitationToken::verify($token, $invitation->token_hash)) {
                return $invitation;
            }
        }

        throw new InvitationNotFoundException;
    }

    private function guardAgainstDuplicate(): void
    {
        if (config('invitation.duplicates.allow_pending_for_same_email_and_subject', false)) {
            return;
        }

        /** @var class-string<Invitation> $modelClass */
        $modelClass = config('invitation.model', Invitation::class);

        $query = $modelClass::query()
            ->where('email', $this->email)
            ->where('status', InvitationStatus::Pending);

        if ($this->subject) {
            $query->where('subject_type', $this->subject->getMorphClass())
                ->where('subject_id', $this->subject->getKey());
        } else {
            $query->whereNull('subject_type')
                ->whereNull('subject_id');
        }

        if ($query->exists()) {
            throw new InvitationAlreadyExistsException;
        }
    }

    private function sendNotification(Invitation $invitation): void
    {
        /** @var class-string $notificationClass */
        $notificationClass = config('invitation.notification');

        $plainToken = $invitation->plainToken;

        Notification::route('mail', $invitation->email)
            ->notify(new $notificationClass($invitation, $plainToken));
    }

    private function runAcceptanceHandler(Invitation $invitation, ?Model $user): void
    {
        if (static::$acceptedUsing) {
            (static::$acceptedUsing)($invitation, $user);

            return;
        }

        /** @var class-string<AcceptsInvitations>|null $handlerClass */
        $handlerClass = config('invitation.acceptance_handler');

        if ($handlerClass) {
            /** @var AcceptsInvitations $handler */
            $handler = app($handlerClass);
            $handler->accept($invitation, $user);
        }
    }

    private function reset(): void
    {
        $this->email = null;
        $this->subject = null;
        $this->inviter = null;
        $this->expiresAt = null;
        $this->neverExpires = false;
        $this->meta = [];
    }

    private function resolveExpiration(): ?CarbonInterface
    {
        if ($this->neverExpires) {
            return null;
        }

        if ($this->expiresAt) {
            return $this->expiresAt;
        }

        $days = config('invitation.expires_after_days');

        if ($days === null) {
            return null;
        }

        return now()->addDays((int) $days);
    }
}
