<?php

declare(strict_types=1);

use Vimatech\Invitation\Exceptions\InvitationConfigurationException;
use Vimatech\Invitation\Support\InvitationToken;

beforeEach(function () {
    config()->set('invitation.token_strategy', 'hmac');
    config()->set('invitation.token_hmac_key', null);
});

it('keeps deriving from APP_KEY when no dedicated key is set', function () {
    $token = 'a-plain-token';

    expect(InvitationToken::hash($token))
        ->toBe(hash_hmac('sha256', $token, (string) config('app.key')));
});

it('uses the dedicated key when one is set', function () {
    $token = 'a-plain-token';
    $appKeyHash = InvitationToken::hash($token);

    config()->set('invitation.token_hmac_key', str_repeat('k', 40));

    expect(InvitationToken::hash($token))
        ->not->toBe($appKeyHash)
        ->and(InvitationToken::verify($token, InvitationToken::hash($token)))->toBeTrue();
});

it('survives an APP_KEY rotation once a dedicated key is set', function () {
    config()->set('invitation.token_hmac_key', str_repeat('k', 40));

    [$plain, $hashed] = InvitationToken::generate();

    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    expect(InvitationToken::verify($plain, $hashed))->toBeTrue();
});

it('stops matching after an APP_KEY rotation when no dedicated key is set', function () {
    [$plain, $hashed] = InvitationToken::generate();

    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    expect(InvitationToken::verify($plain, $hashed))->toBeFalse();
});

it('refuses a dedicated key shorter than 32 characters', function () {
    config()->set('invitation.token_hmac_key', 'too-short');

    expect(fn () => InvitationToken::hash('a-plain-token'))
        ->toThrow(InvitationConfigurationException::class, 'at least 32');
});
