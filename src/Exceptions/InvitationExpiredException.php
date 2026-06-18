<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Exceptions;

class InvitationExpiredException extends InvitationException
{
    public function __construct(string $message = 'Invitation has expired.')
    {
        parent::__construct($message);
    }
}
