<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationToken
{
    /**
     * Generate a new token pair: [plainToken, hashedToken].
     *
     * @return array{0: string, 1: string}
     */
    public static function generate(): array
    {
        $plain = Str::random(64);
        $hashed = static::hash($plain);

        return [$plain, $hashed];
    }

    /**
     * Verify a plain token against a hashed token.
     */
    public static function verify(string $plainToken, string $hashedToken): bool
    {
        $strategy = config('invitation.token_strategy', 'hash');

        if ($strategy === 'hmac') {
            return hash_equals($hashedToken, static::hmacHash($plainToken));
        }

        return Hash::check($plainToken, $hashedToken);
    }

    /**
     * Hash a plain token for storage.
     */
    public static function hash(string $plainToken): string
    {
        $strategy = config('invitation.token_strategy', 'hash');

        if ($strategy === 'hmac') {
            return static::hmacHash($plainToken);
        }

        return Hash::make($plainToken);
    }

    protected static function hmacHash(string $plainToken): string
    {
        /** @var string $key */
        $key = config('app.key');

        return hash_hmac('sha256', $plainToken, $key);
    }
}
