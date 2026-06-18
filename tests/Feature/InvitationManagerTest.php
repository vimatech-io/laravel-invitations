<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Vimatech\Invitation\Enums\InvitationStatus;
use Vimatech\Invitation\Events\InvitationAccepted;
use Vimatech\Invitation\Events\InvitationCancelled;
use Vimatech\Invitation\Events\InvitationCreated;
use Vimatech\Invitation\Events\InvitationDeclined;
use Vimatech\Invitation\Events\InvitationResent;
use Vimatech\Invitation\Events\InvitationSent;
use Vimatech\Invitation\Exceptions\InvitationAlreadyAcceptedException;
use Vimatech\Invitation\Exceptions\InvitationAlreadyExistsException;
use Vimatech\Invitation\Exceptions\InvitationCancelledException;
use Vimatech\Invitation\Exceptions\InvitationDeclinedException;
use Vimatech\Invitation\Exceptions\InvitationExpiredException;
use Vimatech\Invitation\Exceptions\InvitationNotFoundException;
use Vimatech\Invitation\InvitationManager;
use Vimatech\Invitation\Models\Invitation;
use Vimatech\Invitation\Notifications\InvitationNotification;
use Vimatech\Invitation\Tests\Fixtures\Project;
use Vimatech\Invitation\Tests\Fixtures\User;

beforeEach(function () {
    Notification::fake();
});

it('can create an invitation', function () {
    Event::fake();

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    expect($invitation)->toBeInstanceOf(Invitation::class);
    expect($invitation->email)->toBe('john@example.com');
    expect($invitation->status)->toBe(InvitationStatus::Pending);
    expect($invitation->uuid)->not->toBeNull();

    Event::assertDispatched(InvitationCreated::class);
});

it('can create an invitation with a subject', function () {
    $project = Project::create(['name' => 'My Project']);

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->for($project)
        ->create();

    expect($invitation->subject_type)->toBe($project->getMorphClass());
    expect($invitation->subject_id)->toBe($project->getKey());
});

it('can create an invitation with an inviter', function () {
    $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->invitedBy($user)
        ->create();

    expect($invitation->inviter_type)->toBe($user->getMorphClass());
    expect($invitation->inviter_id)->toBe($user->getKey());
});

it('can create an invitation with metadata', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->withMeta(['role' => 'admin'])
        ->create();

    expect($invitation->meta)->toBe(['role' => 'admin']);
});

it('can create and send an invitation', function () {
    Event::fake();

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->send();

    expect($invitation->email)->toBe('john@example.com');

    Event::assertDispatched(InvitationCreated::class);
    Event::assertDispatched(InvitationSent::class);

    Notification::assertSentOnDemand(InvitationNotification::class);
});

it('does not store the plain token in database', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;

    expect($plainToken)->not->toBeNull();
    expect($invitation->token_hash)->not->toBe($plainToken);

    // Reload from DB
    $fresh = Invitation::find($invitation->id);
    expect($fresh->plainToken)->toBeNull();
});

it('can accept an invitation with a valid token', function () {
    Event::fake();

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;
    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

    $accepted = app(InvitationManager::class)->accept($plainToken, $user);

    expect($accepted->status)->toBe(InvitationStatus::Accepted);
    expect($accepted->accepted_at)->not->toBeNull();
    expect($accepted->accepted_by_type)->toBe($user->getMorphClass());
    expect($accepted->accepted_by_id)->toBe($user->getKey());

    Event::assertDispatched(InvitationAccepted::class);
});

it('throws exception for invalid token', function () {
    app(InvitationManager::class)->accept('invalid-token');
})->throws(InvitationNotFoundException::class);

it('throws exception for expired invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->expiresAt(now()->subDay())
        ->create();

    $plainToken = $invitation->plainToken;

    app(InvitationManager::class)->accept($plainToken);
})->throws(InvitationExpiredException::class);

it('throws exception for already accepted invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;

    $invitation->update(['status' => InvitationStatus::Accepted, 'accepted_at' => now()]);

    app(InvitationManager::class)->accept($plainToken);
})->throws(InvitationAlreadyAcceptedException::class);

it('throws exception for cancelled invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;

    $invitation->update(['status' => InvitationStatus::Cancelled, 'cancelled_at' => now()]);

    app(InvitationManager::class)->accept($plainToken);
})->throws(InvitationCancelledException::class);

it('can cancel an invitation', function () {
    Event::fake();

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $cancelled = app(InvitationManager::class)->cancel($invitation);

    expect($cancelled->status)->toBe(InvitationStatus::Cancelled);
    expect($cancelled->cancelled_at)->not->toBeNull();

    Event::assertDispatched(InvitationCancelled::class);
});

it('throws when cancelling an already accepted invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
    app(InvitationManager::class)->accept($invitation->plainToken, $user);

    app(InvitationManager::class)->cancel($invitation->refresh());
})->throws(InvitationAlreadyAcceptedException::class);

it('throws when cancelling an already declined invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    app(InvitationManager::class)->decline($invitation->plainToken);

    app(InvitationManager::class)->cancel($invitation->refresh());
})->throws(InvitationDeclinedException::class);

it('throws when cancelling an already cancelled invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    app(InvitationManager::class)->cancel($invitation);

    app(InvitationManager::class)->cancel($invitation->refresh());
})->throws(InvitationCancelledException::class);

it('can resend an invitation', function () {
    Event::fake();

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $oldTokenHash = $invitation->token_hash;

    $resent = app(InvitationManager::class)->resend($invitation);

    expect($resent->token_hash)->not->toBe($oldTokenHash);
    expect($resent->status)->toBe(InvitationStatus::Pending);

    Event::assertDispatched(InvitationResent::class);
    Notification::assertSentOnDemand(InvitationNotification::class);
});

it('prevents duplicate pending invitations for same email and subject', function () {
    config()->set('invitation.duplicates.allow_pending_for_same_email_and_subject', false);

    $project = Project::create(['name' => 'My Project']);

    app(InvitationManager::class)
        ->to('john@example.com')
        ->for($project)
        ->create();

    app(InvitationManager::class)
        ->to('john@example.com')
        ->for($project)
        ->create();
})->throws(InvitationAlreadyExistsException::class);

it('allows duplicate pending invitations when configured', function () {
    config()->set('invitation.duplicates.allow_pending_for_same_email_and_subject', true);

    $project = Project::create(['name' => 'My Project']);

    app(InvitationManager::class)
        ->to('john@example.com')
        ->for($project)
        ->create();

    $second = app(InvitationManager::class)
        ->to('john@example.com')
        ->for($project)
        ->create();

    expect($second)->toBeInstanceOf(Invitation::class);
});

it('runs custom acceptance handler', function () {
    $handlerRan = false;

    InvitationManager::acceptedUsing(function ($invitation, $user) use (&$handlerRan) {
        $handlerRan = true;
    });

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;
    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

    app(InvitationManager::class)->accept($plainToken, $user);

    expect($handlerRan)->toBeTrue();

    // Clean up
    InvitationManager::acceptedUsing(null);
});

it('can accept invitation for a new user', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;
    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

    $accepted = app(InvitationManager::class)->acceptForNewUser($plainToken, $user);

    expect($accepted->status)->toBe(InvitationStatus::Accepted);
    expect($accepted->accepted_by_id)->toBe($user->getKey());
});

it('rejects acceptForNewUser when email does not match', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;
    $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    app(InvitationManager::class)->acceptForNewUser($plainToken, $user);
})->throws(InvitationNotFoundException::class);

it('throws exception when resending an accepted invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $invitation->update(['status' => InvitationStatus::Accepted, 'accepted_at' => now()]);

    app(InvitationManager::class)->resend($invitation);
})->throws(InvitationAlreadyAcceptedException::class);

it('throws exception when resending a cancelled invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $invitation->update(['status' => InvitationStatus::Cancelled, 'cancelled_at' => now()]);

    app(InvitationManager::class)->resend($invitation);
})->throws(InvitationCancelledException::class);

it('normalizes email to lowercase', function () {
    $invitation = app(InvitationManager::class)
        ->to('John@EXAMPLE.com')
        ->create();

    expect($invitation->email)->toBe('john@example.com');
});

it('can decline an invitation by token', function () {
    Event::fake();

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;

    $declined = app(InvitationManager::class)->decline($plainToken);

    expect($declined->status)->toBe(InvitationStatus::Declined);
    expect($declined->declined_at)->not->toBeNull();

    Event::assertDispatched(InvitationDeclined::class);
});

it('throws exception when declining an already accepted invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;

    $invitation->update(['status' => InvitationStatus::Accepted, 'accepted_at' => now()]);

    app(InvitationManager::class)->decline($plainToken);
})->throws(InvitationAlreadyAcceptedException::class);

it('throws exception when declining an already declined invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;

    $invitation->update(['status' => InvitationStatus::Declined, 'declined_at' => now()]);

    app(InvitationManager::class)->decline($plainToken);
})->throws(InvitationDeclinedException::class);

it('throws exception when declining a cancelled invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;

    $invitation->update(['status' => InvitationStatus::Cancelled, 'cancelled_at' => now()]);

    app(InvitationManager::class)->decline($plainToken);
})->throws(InvitationCancelledException::class);

it('throws exception when accepting a declined invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $plainToken = $invitation->plainToken;

    $invitation->update(['status' => InvitationStatus::Declined, 'declined_at' => now()]);

    app(InvitationManager::class)->accept($plainToken);
})->throws(InvitationDeclinedException::class);

it('throws exception when resending a declined invitation', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $invitation->update(['status' => InvitationStatus::Declined, 'declined_at' => now()]);

    app(InvitationManager::class)->resend($invitation);
})->throws(InvitationDeclinedException::class);

it('can create an invitation that never expires via method', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->neverExpires()
        ->create();

    expect($invitation->expires_at)->toBeNull();
    expect($invitation->isExpired())->toBeFalse();
});

it('can create an invitation that never expires via config', function () {
    config()->set('invitation.expires_after_days', null);

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    expect($invitation->expires_at)->toBeNull();
    expect($invitation->isExpired())->toBeFalse();
});

it('can accept an invitation that never expires', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->neverExpires()
        ->create();

    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
    $accepted = app(InvitationManager::class)->accept($invitation->plainToken, $user);

    expect($accepted->status)->toBe(InvitationStatus::Accepted);
});

it('can create an invitation using toUser()', function () {
    $user = User::create(['name' => 'John', 'email' => 'John@Example.com']);

    $invitation = app(InvitationManager::class)
        ->toUser($user)
        ->create();

    expect($invitation->email)->toBe('john@example.com');
});

it('throws when toUser() receives a model without email', function () {
    $project = Project::create(['name' => 'My Project']);

    app(InvitationManager::class)->toUser($project);
})->throws(InvalidArgumentException::class);
