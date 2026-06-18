<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Vimatech\Invitation\Enums\InvitationStatus;
use Vimatech\Invitation\Models\Invitation;
use Vimatech\Invitation\PendingInvitation;

/**
 * @mixin Model
 *
 * @phpstan-require-extends Model
 */
trait HasInvitations
{
    /** @return MorphMany<Invitation, $this> */
    public function invitations(): MorphMany
    {
        /** @var class-string<Invitation> $modelClass */
        $modelClass = config('invitation.model', Invitation::class);

        return $this->morphMany($modelClass, 'subject');
    }

    /** @return MorphMany<Invitation, $this> */
    public function pendingInvitations(): MorphMany
    {
        return $this->invitations()->where('status', InvitationStatus::Pending);
    }

    public function invite(string $email): PendingInvitation
    {
        return new PendingInvitation($email, $this);
    }

    public function inviteUser(Model $user): PendingInvitation
    {
        $email = $user->getAttribute('email');

        if (! is_string($email) || $email === '') {
            throw new \InvalidArgumentException('The given model does not have an email attribute.');
        }

        return $this->invite($email);
    }
}
