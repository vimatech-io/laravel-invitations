<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Exceptions;

class InvitationConfigurationException extends InvitationException
{
    public static function hmacKeyTooShort(int $length): self
    {
        return new self(
            "invitation.token_hmac_key is {$length} characters; it must be at least 32. "
            .'Generate one with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;". '
            .'Leave it unset to keep deriving token hashes from APP_KEY.'
        );
    }
}
