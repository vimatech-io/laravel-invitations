<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Vimatech\Invitation\Enums\InvitationStatus;
use Vimatech\Invitation\Events\InvitationCancelled;
use Vimatech\Invitation\Events\InvitationDeclined;
use Vimatech\Invitation\Events\InvitationExpired;
use Vimatech\Invitation\Events\InvitationResent;
use Vimatech\Invitation\Exceptions\InvitationAlreadyAcceptedException;
use Vimatech\Invitation\Exceptions\InvitationCancelledException;
use Vimatech\Invitation\Exceptions\InvitationExpiredException;
use Vimatech\Invitation\InvitationManager;
use Vimatech\Invitation\Models\Invitation;
use Vimatech\Invitation\Notifications\InvitationNotification;
use Vimatech\Invitation\Tests\Fixtures\User;

beforeEach(function () {
    Notification::fake();
});

function changeRowBehindTheModel(Invitation $invitation, array $columns): void
{
    DB::table('invitations')->where('id', $invitation->id)->update($columns);
}

function changeRowRightAfterItIsRead(array $columns): void
{
    $done = false;

    Invitation::retrieved(function (Invitation $invitation) use (&$done, $columns): void {
        if ($done) {
            return;
        }

        $done = true;
        changeRowBehindTheModel($invitation, $columns);
    });
}

function freshStatus(Invitation $invitation): InvitationStatus
{
    return Invitation::query()->findOrFail($invitation->id)->status;
}

it('does not cancel an invitation accepted since it was loaded', function () {
    $invitation = app(InvitationManager::class)->to('a@example.com')->create();
    changeRowBehindTheModel($invitation, ['status' => 'accepted', 'accepted_at' => now()]);
    Event::fake([InvitationCancelled::class]);

    expect(fn () => app(InvitationManager::class)->cancel($invitation))
        ->toThrow(InvitationAlreadyAcceptedException::class);

    expect(freshStatus($invitation))->toBe(InvitationStatus::Accepted);
    Event::assertNotDispatched(InvitationCancelled::class);
});

it('does not reopen an invitation accepted since it was loaded when resending', function () {
    $invitation = app(InvitationManager::class)->to('a@example.com')->create();
    $hashBefore = $invitation->token_hash;
    changeRowBehindTheModel($invitation, ['status' => 'accepted', 'accepted_at' => now()]);
    Event::fake([InvitationResent::class]);

    expect(fn () => app(InvitationManager::class)->resend($invitation))
        ->toThrow(InvitationAlreadyAcceptedException::class);

    $fresh = Invitation::query()->findOrFail($invitation->id);
    expect($fresh->status)->toBe(InvitationStatus::Accepted)
        ->and($fresh->token_hash)->toBe($hashBefore);
    Notification::assertNothingSent();
    Event::assertNotDispatched(InvitationResent::class);
});

it('does not resend an invitation cancelled since it was loaded', function () {
    $invitation = app(InvitationManager::class)->to('a@example.com')->create();
    changeRowBehindTheModel($invitation, ['status' => 'cancelled', 'cancelled_at' => now()]);

    expect(fn () => app(InvitationManager::class)->resend($invitation))
        ->toThrow(InvitationCancelledException::class);

    expect(freshStatus($invitation))->toBe(InvitationStatus::Cancelled);
    Notification::assertNothingSent();
});

it('does not decline an invitation accepted between lookup and write', function () {
    $invitation = app(InvitationManager::class)->to('a@example.com')->create();
    changeRowRightAfterItIsRead(['status' => 'accepted', 'accepted_at' => now()]);
    Event::fake([InvitationDeclined::class]);

    expect(fn () => app(InvitationManager::class)->decline($invitation->plainToken))
        ->toThrow(InvitationAlreadyAcceptedException::class);

    expect(freshStatus($invitation))->toBe(InvitationStatus::Accepted);
    Event::assertNotDispatched(InvitationDeclined::class);
});

it('does not overwrite a concurrent cancellation with expired', function () {
    $invitation = app(InvitationManager::class)->to('a@example.com')->expiresAt(now()->subDay())->create();
    changeRowRightAfterItIsRead(['status' => 'cancelled', 'cancelled_at' => now()]);
    Event::fake([InvitationExpired::class]);

    expect(fn () => app(InvitationManager::class)->accept($invitation->plainToken))
        ->toThrow(InvitationCancelledException::class);

    expect(freshStatus($invitation))->toBe(InvitationStatus::Cancelled);
    Event::assertNotDispatched(InvitationExpired::class);
});

it('reports a concurrent cancellation as a cancellation when accepting', function () {
    $invitation = app(InvitationManager::class)->to('a@example.com')->create();
    changeRowRightAfterItIsRead(['status' => 'cancelled', 'cancelled_at' => now()]);

    expect(fn () => app(InvitationManager::class)->accept($invitation->plainToken))
        ->toThrow(InvitationCancelledException::class);

    expect(freshStatus($invitation))->toBe(InvitationStatus::Cancelled);
});

it('does not accept an invitation whose expiry passed between lookup and write', function () {
    $invitation = app(InvitationManager::class)->to('a@example.com')->create();
    changeRowRightAfterItIsRead(['expires_at' => now()->subMinute()]);

    expect(fn () => app(InvitationManager::class)->accept($invitation->plainToken))
        ->toThrow(InvitationExpiredException::class);

    expect(freshStatus($invitation))->toBe(InvitationStatus::Pending);
});

it('does not fire InvitationExpired again for an invitation already marked expired', function () {
    $invitation = app(InvitationManager::class)->to('a@example.com')->create();
    changeRowBehindTheModel($invitation, ['status' => 'expired']);
    Event::fake([InvitationExpired::class]);

    expect(fn () => app(InvitationManager::class)->accept($invitation->plainToken))
        ->toThrow(InvitationExpiredException::class);

    Event::assertNotDispatched(InvitationExpired::class);
});

it('leaves the invitation pending when the acceptance handler fails', function () {
    $invitation = app(InvitationManager::class)->to('a@example.com')->create();
    $user = User::create(['name' => 'A', 'email' => 'a@example.com']);

    InvitationManager::acceptedUsing(function (): void {
        throw new RuntimeException('membership could not be created');
    });

    try {
        expect(fn () => app(InvitationManager::class)->accept($invitation->plainToken, $user))
            ->toThrow(RuntimeException::class, 'membership could not be created');
    } finally {
        InvitationManager::flush();
    }

    expect(freshStatus($invitation))->toBe(InvitationStatus::Pending);

    $accepted = app(InvitationManager::class)->accept($invitation->plainToken, $user);
    expect($accepted->status)->toBe(InvitationStatus::Accepted);
});

it('still cancels and resends an invitation already marked expired', function () {
    $first = app(InvitationManager::class)->to('a@example.com')->create();
    $second = app(InvitationManager::class)->to('b@example.com')->create();
    $first->update(['status' => InvitationStatus::Expired]);
    $second->update(['status' => InvitationStatus::Expired]);

    expect(app(InvitationManager::class)->cancel($first)->status)->toBe(InvitationStatus::Cancelled);

    $resent = app(InvitationManager::class)->resend($second);
    expect($resent->status)->toBe(InvitationStatus::Pending)
        ->and($resent->plainToken)->not->toBeNull();
    Notification::assertSentOnDemand(InvitationNotification::class);
});

it('lets an expired invitation be replaced by a new one', function () {
    app(InvitationManager::class)->to('a@example.com')->expiresAt(now()->subDay())->create();

    $replacement = app(InvitationManager::class)->to('a@example.com')->create();

    expect($replacement->status)->toBe(InvitationStatus::Pending);
});
