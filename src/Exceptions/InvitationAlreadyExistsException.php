<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Exceptions;

class InvitationAlreadyExistsException extends InvitationException
{
    public function __construct(string $message = 'A pending invitation already exists for this email.')
    {
        parent::__construct($message);
    }
}
