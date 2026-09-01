<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Vimatech\Invitation\Exceptions\InvitationConfigurationException;

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
        return hash_hmac('sha256', $plainToken, static::hmacKey());
    }

    /**
     * Falls back to APP_KEY, verbatim, because that is what already-stored hashes
     * were built with. Changing the fallback would invalidate every pending token.
     */
    protected static function hmacKey(): string
    {
        $key = config('invitation.token_hmac_key');

        if (is_string($key) && $key !== '') {
            if (strlen($key) < 32) {
                throw InvitationConfigurationException::hmacKeyTooShort(strlen($key));
            }

            return $key;
        }

        /** @var string $appKey */
        $appKey = config('app.key');

        return $appKey;
    }
}
