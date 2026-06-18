<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Facades;

use Illuminate\Support\Facades\Facade;
use Vimatech\Invitation\InvitationManager;

/**
 * @method static \Vimatech\Invitation\InvitationManager to(string $email)
 * @method static \Vimatech\Invitation\InvitationManager toUser(\Illuminate\Database\Eloquent\Model $user)
 * @method static \Vimatech\Invitation\InvitationManager for(?\Illuminate\Database\Eloquent\Model $subject)
 * @method static \Vimatech\Invitation\InvitationManager invitedBy(?\Illuminate\Database\Eloquent\Model $inviter)
 * @method static \Vimatech\Invitation\InvitationManager expiresAt(?\Carbon\CarbonInterface $date)
 * @method static \Vimatech\Invitation\InvitationManager expiresInDays(int $days)
 * @method static \Vimatech\Invitation\InvitationManager neverExpires()
 * @method static \Vimatech\Invitation\InvitationManager withMeta(array<string, mixed> $meta)
 * @method static \Vimatech\Invitation\Models\Invitation send()
 * @method static \Vimatech\Invitation\Models\Invitation create()
 * @method static \Vimatech\Invitation\Models\Invitation accept(string $token, ?\Illuminate\Database\Eloquent\Model $user = null)
 * @method static \Vimatech\Invitation\Models\Invitation acceptForNewUser(string $token, \Illuminate\Database\Eloquent\Model $user)
 * @method static \Vimatech\Invitation\Models\Invitation cancel(\Vimatech\Invitation\Models\Invitation $invitation)
 * @method static \Vimatech\Invitation\Models\Invitation decline(string $token)
 * @method static \Vimatech\Invitation\Models\Invitation resend(\Vimatech\Invitation\Models\Invitation $invitation)
 * @method static \Vimatech\Invitation\Models\Invitation findByToken(string $token)
 * @method static void acceptedUsing(?\Closure $callback)
 *
 * @see InvitationManager
 */
class Invitations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return InvitationManager::class;
    }
}
