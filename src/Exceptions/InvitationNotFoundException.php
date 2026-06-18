<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Exceptions;

class InvitationNotFoundException extends InvitationException
{
    public function __construct(string $message = 'Invitation not found.')
    {
        parent::__construct($message);
    }
}
