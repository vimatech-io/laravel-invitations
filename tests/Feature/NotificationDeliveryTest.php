<?php

declare(strict_types=1);

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Vimatech\Invitation\Exceptions\InvitationConfigurationException;
use Vimatech\Invitation\InvitationManager;
use Vimatech\Invitation\Models\Invitation;
use Vimatech\Invitation\Notifications\InvitationNotification;

it('encrypts the queued notification so the plain token is not stored in the queue', function () {
    Schema::create('jobs', function ($table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database', [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ]);

    $invitation = app(InvitationManager::class)->to('invitee@example.com')->send();

    $payload = DB::table('jobs')->value('payload');

    expect($payload)->toBeString()
        ->and($payload)->not->toContain($invitation->plainToken)
        ->and($payload)->not->toContain('invitee@example.com');
});

describe('when no invitation URL can be built', function () {
    beforeEach(function () {
        Notification::fake();
        config()->set('invitation.url_generator', null);
        config()->set('invitation.route_name', 'route.that.does.not.exist');
    });

    it('refuses to send before creating anything', function () {
        expect(fn () => app(InvitationManager::class)->to('invitee@example.com')->send())
            ->toThrow(InvitationConfigurationException::class);

        expect(Invitation::query()->count())->toBe(0);
        Notification::assertNothingSent();
    });

    it('refuses to resend before replacing the token', function () {
        $invitation = app(InvitationManager::class)->to('invitee@example.com')->create();
        $hashBefore = $invitation->token_hash;

        expect(fn () => app(InvitationManager::class)->resend($invitation))
            ->toThrow(InvitationConfigurationException::class);

        expect(Invitation::query()->findOrFail($invitation->id)->token_hash)->toBe($hashBefore);
        Notification::assertNothingSent();
    });

    it('still creates an invitation without sending it', function () {
        expect(app(InvitationManager::class)->to('invitee@example.com')->create()->exists)->toBeTrue();
    });

    it('lets a notification that builds its own URL through', function () {
        config()->set('invitation.notification', OwnUrlInvitationNotification::class);

        app(InvitationManager::class)->to('invitee@example.com')->send();

        Notification::assertSentOnDemand(OwnUrlInvitationNotification::class);
    });

    it('lets a url_generator through', function () {
        config()->set('invitation.url_generator', fn (string $token) => "https://app.example.com/join#{$token}");

        app(InvitationManager::class)->to('invitee@example.com')->send();

        Notification::assertSentOnDemand(InvitationNotification::class);
    });
});

class OwnUrlInvitationNotification extends InvitationNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->action('Join', 'https://app.example.com/join#'.$this->plainToken);
    }
}
