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

    public static function invitationUrlUnavailable(string $routeName): self
    {
        return new self(
            "Cannot build the invitation link: the route [{$routeName}] does not exist and invitation.url_generator is not set. "
            .'Enable the package routes, point invitation.route_name at an existing route, or set invitation.url_generator.'
        );
    }
}
