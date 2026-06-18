<?php

use Illuminate\Support\Facades\Notification;
use Vimatech\Invitation\InvitationManager;
use Vimatech\Invitation\Tests\Fixtures\Project;
use Vimatech\Invitation\Tests\Fixtures\User;

beforeEach(function () {
    Notification::fake();
});

it('can create invitations via HasInvitations trait', function () {
    $project = Project::create(['name' => 'My Project']);
    $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $invitation = $project->invite('john@example.com')
        ->invitedBy($user)
        ->expiresInDays(10)
        ->withMeta(['role' => 'member'])
        ->send();

    expect($invitation->email)->toBe('john@example.com');
    expect($invitation->subject_type)->toBe($project->getMorphClass());
    expect($invitation->subject_id)->toBe($project->getKey());
    expect($invitation->meta)->toBe(['role' => 'member']);
});

it('can list invitations via HasInvitations trait', function () {
    $project = Project::create(['name' => 'My Project']);

    $project->invite('john@example.com')->send();
    $project->invite('jane@example.com')->send();

    expect($project->invitations()->count())->toBe(2);
});

it('can list pending invitations via HasInvitations trait', function () {
    $project = Project::create(['name' => 'My Project']);

    $invitation = $project->invite('john@example.com')->send();
    $project->invite('jane@example.com')->send();

    app(InvitationManager::class)->cancel($invitation);

    expect($project->pendingInvitations()->count())->toBe(1);
});

it('can invite a user model via inviteUser()', function () {
    $project = Project::create(['name' => 'My Project']);
    $inviter = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
    $invitee = User::create(['name' => 'John', 'email' => 'John@Example.com']);

    $invitation = $project->inviteUser($invitee)
        ->invitedBy($inviter)
        ->send();

    expect($invitation->email)->toBe('john@example.com');
    expect($invitation->subject_id)->toBe($project->getKey());
});
