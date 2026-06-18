<?php

use Vimatech\Invitation\Enums\InvitationStatus;
use Vimatech\Invitation\Models\Invitation;
use Vimatech\Invitation\Tests\Fixtures\Project;
use Vimatech\Invitation\Tests\Fixtures\User;

it('can scope pending invitations', function () {
    Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Pending,
    ]);
    Invitation::create([
        'email' => 'b@test.com',
        'token_hash' => 'hash2',
        'status' => InvitationStatus::Accepted,
    ]);

    expect(Invitation::pending()->count())->toBe(1);
});

it('can scope accepted invitations', function () {
    Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Accepted,
    ]);

    expect(Invitation::accepted()->count())->toBe(1);
});

it('can scope expired invitations', function () {
    Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Expired,
    ]);

    expect(Invitation::expired()->count())->toBe(1);
});

it('can scope cancelled invitations', function () {
    Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Cancelled,
    ]);

    expect(Invitation::cancelled()->count())->toBe(1);
});

it('can scope by email', function () {
    Invitation::create([
        'email' => 'john@example.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Pending,
    ]);
    Invitation::create([
        'email' => 'jane@example.com',
        'token_hash' => 'hash2',
        'status' => InvitationStatus::Pending,
    ]);

    expect(Invitation::forEmail('john@example.com')->count())->toBe(1);
});

it('can scope by subject', function () {
    $project = Project::create(['name' => 'Test Project']);

    Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Pending,
        'subject_type' => $project->getMorphClass(),
        'subject_id' => $project->getKey(),
    ]);
    Invitation::create([
        'email' => 'b@test.com',
        'token_hash' => 'hash2',
        'status' => InvitationStatus::Pending,
    ]);

    expect(Invitation::forSubject($project)->count())->toBe(1);
});

it('can scope by inviter', function () {
    $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Pending,
        'inviter_type' => $user->getMorphClass(),
        'inviter_id' => $user->getKey(),
    ]);

    expect(Invitation::invitedBy($user)->count())->toBe(1);
});

it('detects expired invitation by date', function () {
    $invitation = Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Pending,
        'expires_at' => now()->subDay(),
    ]);

    expect($invitation->isExpired())->toBeTrue();
});

it('detects non-expired invitation', function () {
    $invitation = Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Pending,
        'expires_at' => now()->addDay(),
    ]);

    expect($invitation->isExpired())->toBeFalse();
});

it('can scope declined invitations', function () {
    Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Declined,
    ]);

    expect(Invitation::declined()->count())->toBe(1);
});

it('detects declined invitation', function () {
    $invitation = Invitation::create([
        'email' => 'a@test.com',
        'token_hash' => 'hash1',
        'status' => InvitationStatus::Declined,
        'declined_at' => now(),
    ]);

    expect($invitation->isDeclined())->toBeTrue();
});
