<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Vimatech\Invitation\Exceptions\InvitationAlreadyExistsException;
use Vimatech\Invitation\Facades\Invitations;
use Vimatech\Invitation\Tests\Fixtures\Project;

beforeEach(function () {
    Notification::fake();
});

it('does not carry a failed invitation subject into the next one', function () {
    $project = Project::create(['name' => 'Acme']);

    Invitations::to('first@example.com')->for($project)->create();

    try {
        Invitations::to('first@example.com')->for($project)->withMeta(['role' => 'owner'])->create();
    } catch (InvitationAlreadyExistsException) {
        // the duplicate guard throws before the builder is reset
    }

    $second = Invitations::to('second@example.com')->create();

    expect($second->subject_id)->toBeNull()
        ->and($second->subject_type)->toBeNull()
        ->and($second->meta)->toBeNull();
});

it('does not carry an abandoned chain into a later invitation', function () {
    $project = Project::create(['name' => 'Acme']);

    Invitations::to('abandoned@example.com')->for($project)->withMeta(['role' => 'admin']);

    $next = Invitations::to('next@example.com')->create();

    expect($next->subject_id)->toBeNull()
        ->and($next->meta)->toBeNull();
});
