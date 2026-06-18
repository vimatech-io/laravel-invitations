<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Exceptions;

class InvitationAlreadyAcceptedException extends InvitationException
{
    public function __construct(string $message = 'Invitation has already been accepted.')
    {
        parent::__construct($message);
    }
}
