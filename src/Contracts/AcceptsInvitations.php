<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Contracts;

use Illuminate\Database\Eloquent\Model;
use Vimatech\Invitation\Models\Invitation;

interface AcceptsInvitations
{
    public function accept(Invitation $invitation, ?Model $user = null): void;
}
