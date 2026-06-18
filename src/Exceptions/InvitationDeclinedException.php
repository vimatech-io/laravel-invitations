<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Exceptions;

class InvitationDeclinedException extends InvitationException
{
    public function __construct(string $message = 'Invitation has been declined.')
    {
        parent::__construct($message);
    }
}
