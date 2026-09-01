<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Vimatech\Invitation\Facades\Invitations;

beforeEach(function () {
    Notification::fake();
});

it('does not expire a resent invitation immediately when invitations never expire', function () {
    config()->set('invitation.expires_after_days', null);

    $invitation = Invitations::to('a@example.com')->create();

    expect($invitation->expires_at)->toBeNull();

    $resent = Invitations::resend($invitation);

    expect($resent->expires_at)->toBeNull();
});

it('applies the configured window when resending', function () {
    config()->set('invitation.expires_after_days', 3);

    $invitation = Invitations::to('b@example.com')->create();
    $resent = Invitations::resend($invitation);

    expect($resent->expires_at->isFuture())->toBeTrue()
        ->and($resent->expires_at->diffInDays(now()))->toBeLessThanOrEqual(3);
});
