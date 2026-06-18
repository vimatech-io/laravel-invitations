<?php

use Vimatech\Invitation\Support\InvitationToken;

it('generates a token pair with hmac strategy', function () {
    config()->set('invitation.token_strategy', 'hmac');

    [$plain, $hashed] = InvitationToken::generate();

    expect($plain)->toBeString()->toHaveLength(64);
    expect($hashed)->toBeString()->not->toBe($plain);
});

it('generates a token pair with hash strategy', function () {
    config()->set('invitation.token_strategy', 'hash');

    [$plain, $hashed] = InvitationToken::generate();

    expect($plain)->toBeString()->toHaveLength(64);
    expect($hashed)->toBeString()->not->toBe($plain);
});

it('verifies a valid token with hmac strategy', function () {
    config()->set('invitation.token_strategy', 'hmac');

    [$plain, $hashed] = InvitationToken::generate();

    expect(InvitationToken::verify($plain, $hashed))->toBeTrue();
});

it('verifies a valid token with hash strategy', function () {
    config()->set('invitation.token_strategy', 'hash');

    [$plain, $hashed] = InvitationToken::generate();

    expect(InvitationToken::verify($plain, $hashed))->toBeTrue();
});

it('rejects an invalid token with hmac strategy', function () {
    config()->set('invitation.token_strategy', 'hmac');

    [$plain, $hashed] = InvitationToken::generate();

    expect(InvitationToken::verify('invalid-token', $hashed))->toBeFalse();
});

it('rejects an invalid token with hash strategy', function () {
    config()->set('invitation.token_strategy', 'hash');

    [$plain, $hashed] = InvitationToken::generate();

    expect(InvitationToken::verify('invalid-token', $hashed))->toBeFalse();
});

it('does not store the plain token in the hash', function () {
    [$plain, $hashed] = InvitationToken::generate();

    expect($hashed)->not->toContain($plain);
});

it('hmac produces deterministic output', function () {
    config()->set('invitation.token_strategy', 'hmac');

    [$plain, $hashed] = InvitationToken::generate();

    expect(InvitationToken::hash($plain))->toBe($hashed);
});
