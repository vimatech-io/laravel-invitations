<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vimatech\Invitation\Models\Invitation;

class InvitationDeclined
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Invitation $invitation,
    ) {}
}
