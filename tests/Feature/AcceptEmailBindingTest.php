<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Vimatech\Invitation\Enums\InvitationStatus;
use Vimatech\Invitation\Exceptions\InvitationEmailMismatchException;
use Vimatech\Invitation\Exceptions\InvitationNotFoundException;
use Vimatech\Invitation\InvitationManager;
use Vimatech\Invitation\Models\Invitation;
use Vimatech\Invitation\Tests\Fixtures\User;

beforeEach(function () {
    Notification::fake();
});

it('refuses to accept an invitation for an account with another email', function () {
    $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
    $someoneElse = User::create(['name' => 'Mallory', 'email' => 'mallory@example.com']);

    expect(fn () => app(InvitationManager::class)->accept($invitation->plainToken, $someoneElse))
        ->toThrow(InvitationEmailMismatchException::class);

    expect(Invitation::query()->findOrFail($invitation->id)->status)->toBe(InvitationStatus::Pending);
});

it('keeps the refusal catchable as InvitationNotFoundException', function () {
    $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
    $someoneElse = User::create(['name' => 'Mallory', 'email' => 'mallory@example.com']);

    app(InvitationManager::class)->accept($invitation->plainToken, $someoneElse);
})->throws(InvitationNotFoundException::class);

it('matches the email regardless of case and surrounding spaces', function () {
    $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
    $invitee = User::create(['name' => 'Invitee', 'email' => ' Invitee@Example.COM ']);

    $accepted = app(InvitationManager::class)->accept($invitation->plainToken, $invitee);

    expect($accepted->status)->toBe(InvitationStatus::Accepted);
});

it('enforces the check when a published config predates the setting', function () {
    $published = config('invitation');
    unset($published['accept']);
    config()->set('invitation', $published);

    $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
    $someoneElse = User::create(['name' => 'Mallory', 'email' => 'mallory@example.com']);

    app(InvitationManager::class)->accept($invitation->plainToken, $someoneElse);
})->throws(InvitationEmailMismatchException::class);

it('accepts for another account when the check is explicitly disabled', function () {
    config()->set('invitation.accept.require_matching_email', false);

    $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
    $delegate = User::create(['name' => 'Delegate', 'email' => 'delegate@example.com']);

    $accepted = app(InvitationManager::class)->accept($invitation->plainToken, $delegate);

    expect($accepted->accepted_by_id)->toBe($delegate->getKey());
});

it('refuses acceptForNewUser for another email even when the check is disabled', function () {
    config()->set('invitation.accept.require_matching_email', false);

    $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
    $someoneElse = User::create(['name' => 'Mallory', 'email' => 'mallory@example.com']);

    app(InvitationManager::class)->acceptForNewUser($invitation->plainToken, $someoneElse);
})->throws(InvitationEmailMismatchException::class);

it('refuses the accept route for a signed-in account with another email', function () {
    $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
    $someoneElse = User::create(['name' => 'Mallory', 'email' => 'mallory@example.com']);

    $this->actingAs($someoneElse)
        ->post(route('invitations.accept', ['token' => $invitation->plainToken]))
        ->assertSessionHasErrors(['invitation' => 'This invitation was sent to a different email address.']);

    expect(Invitation::query()->findOrFail($invitation->id)->status)->toBe(InvitationStatus::Pending);
});

it('does not put the token in the login URL when a guest accepts', function () {
    Route::get('/login', fn () => 'login')->name('login');
    $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
    $previewUrl = route('invitations.preview', ['token' => $invitation->plainToken]);

    $response = $this->post(route('invitations.accept', ['token' => $invitation->plainToken]));

    $response->assertRedirect(route('login'));
    expect($response->headers->get('Location'))->not->toContain($invitation->plainToken);
    $response->assertSessionHas('url.intended', $previewUrl);
});

it('does not put the token in the login link shown to a guest', function () {
    Route::get('/login', fn () => 'login')->name('login');
    $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
    $previewUrl = route('invitations.preview', ['token' => $invitation->plainToken]);

    $response = $this->get($previewUrl);

    $response->assertOk()->assertSee(route('login'), false);
    expect($response->getContent())->not->toContain('invitation_token');
    $response->assertSessionHas('url.intended', $previewUrl);
});
