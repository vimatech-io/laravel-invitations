<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vimatech\Invitation\Models\Invitation;

class InvitationAccepted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly ?Model $user = null,
    ) {}
}
