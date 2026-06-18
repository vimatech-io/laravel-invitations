<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Exceptions;

class InvitationCancelledException extends InvitationException
{
    public function __construct(string $message = 'Invitation has been cancelled.')
    {
        parent::__construct($message);
    }
}
