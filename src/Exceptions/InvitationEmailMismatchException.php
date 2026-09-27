<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Exceptions;

class InvitationEmailMismatchException extends InvitationNotFoundException
{
    public function __construct(string $message = 'The invitation was sent to a different email address.')
    {
        parent::__construct($message);
    }
}
