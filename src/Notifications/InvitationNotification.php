<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Vimatech\Invitation\Models\Invitation;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly ?string $plainToken = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->generateUrl();

        $message = (new MailMessage)
            ->subject($this->getSubjectLine())
            ->line($this->getGreetingLine())
            ->action($this->getActionText(), $url)
            ->line($this->getExpirationLine());

        $inviterName = $this->getInviterName();
        if ($inviterName) {
            $message->line(__('Invited by: :name', ['name' => $inviterName]));
        }

        return $message;
    }

    protected function getSubjectLine(): string
    {
        return __('You have been invited');
    }

    protected function getGreetingLine(): string
    {
        return __('You have been invited.');
    }

    protected function getActionText(): string
    {
        return __('View Invitation');
    }

    protected function getExpirationLine(): string
    {
        if (! $this->invitation->expires_at) {
            return __('This invitation does not expire.');
        }

        $date = $this->invitation->expires_at->toFormattedDateString();

        return __('This invitation will expire on :date.', ['date' => $date]);
    }

    protected function getInviterName(): ?string
    {
        $inviter = $this->invitation->inviter;

        if (! $inviter) {
            return null;
        }

        return $inviter->getAttribute('name') ?? __('Someone');
    }

    protected function generateUrl(): string
    {
        $urlGenerator = config('invitation.url_generator');

        if ($urlGenerator) {
            return app()->call($urlGenerator, ['token' => $this->plainToken]);
        }

        $routeName = config('invitation.route_name', 'invitations.preview');

        return route($routeName, ['token' => $this->plainToken]);
    }
}
