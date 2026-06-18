<?php

declare(strict_types=1);

namespace Vimatech\Invitation;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Vimatech\Invitation\Models\Invitation;

class PendingInvitation
{
    private ?Model $inviter = null;

    private ?CarbonInterface $expiresAt = null;

    private bool $neverExpires = false;

    /** @var array<string, mixed> */
    private array $meta = [];

    public function __construct(
        private readonly string $email,
        private readonly ?Model $subject = null,
    ) {}

    public function invitedBy(?Model $inviter): static
    {
        $this->inviter = $inviter;

        return $this;
    }

    public function expiresAt(?CarbonInterface $date): static
    {
        $this->expiresAt = $date;

        return $this;
    }

    public function expiresInDays(int $days): static
    {
        $this->expiresAt = now()->addDays($days);
        $this->neverExpires = false;

        return $this;
    }

    public function neverExpires(): static
    {
        $this->neverExpires = true;
        $this->expiresAt = null;

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function send(): Invitation
    {
        return $this->buildManager()->send();
    }

    public function create(): Invitation
    {
        return $this->buildManager()->create();
    }

    private function buildManager(): InvitationManager
    {
        $manager = app(InvitationManager::class)
            ->to($this->email);

        if ($this->subject) {
            $manager->for($this->subject);
        }

        if ($this->inviter) {
            $manager->invitedBy($this->inviter);
        }

        if ($this->neverExpires) {
            $manager->neverExpires();
        } elseif ($this->expiresAt) {
            $manager->expiresAt($this->expiresAt);
        }

        if (! empty($this->meta)) {
            $manager->withMeta($this->meta);
        }

        return $manager;
    }
}
