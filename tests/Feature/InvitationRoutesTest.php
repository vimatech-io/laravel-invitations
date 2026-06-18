<?php

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Vimatech\Invitation\InvitationManager;
use Vimatech\Invitation\Tests\Fixtures\User;

beforeEach(function () {
    Notification::fake();
});

it('shows the invitation preview page with valid token', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $response = $this->get(route('invitations.preview', ['token' => $invitation->plainToken]));

    $response->assertStatus(200);
    $response->assertViewIs('invitation::preview');
    $response->assertViewHas('invitation');
});

it('redirects when previewing with invalid token', function () {
    $token = Str::random(64);

    $response = $this->get(route('invitations.preview', ['token' => $token]));

    $response->assertRedirect();
});

it('redirects to login when accepting without auth', function () {
    Route::get('/login', fn () => 'login')->name('login');

    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $response = $this->post(route('invitations.accept', ['token' => $invitation->plainToken]));

    $response->assertRedirect();
});

it('shows error for invalid token when accepting', function () {
    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

    $token = Str::random(64);

    $response = $this->actingAs($user)
        ->post(route('invitations.accept', ['token' => $token]));

    $response->assertSessionHasErrors('invitation');
});

it('can decline an invitation via route', function () {
    $invitation = app(InvitationManager::class)
        ->to('john@example.com')
        ->create();

    $response = $this->post(route('invitations.decline', ['token' => $invitation->plainToken]));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Invitation declined.');
});

it('shows error when declining with invalid token', function () {
    $token = Str::random(64);

    $response = $this->post(route('invitations.decline', ['token' => $token]));

    $response->assertSessionHasErrors('invitation');
});
