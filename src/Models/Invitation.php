<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Vimatech\Invitation\Enums\InvitationStatus;

/**
 * @property int $id
 * @property string $uuid
 * @property string $email
 * @property string $token_hash
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $inviter_type
 * @property int|null $inviter_id
 * @property string|null $accepted_by_type
 * @property int|null $accepted_by_id
 * @property InvitationStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $cancelled_at
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model|null $subject
 * @property-read Model|null $inviter
 * @property-read Model|null $acceptedBy
 */
class Invitation extends Model
{
    protected $fillable = [
        'uuid',
        'email',
        'token_hash',
        'subject_type',
        'subject_id',
        'inviter_type',
        'inviter_id',
        'accepted_by_type',
        'accepted_by_id',
        'status',
        'expires_at',
        'accepted_at',
        'declined_at',
        'cancelled_at',
        'meta',
    ];

    /**
     * Plain token (transient, never persisted to database).
     */
    public ?string $plainToken = null;

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('invitation.table', 'invitations');
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation): void {
            if (empty($invitation->uuid)) {
                $invitation->uuid = (string) Str::uuid();
            }
            if (empty($invitation->status)) {
                $invitation->status = InvitationStatus::Pending;
            }
        });
    }

    // ──── Relations ────

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function inviter(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function acceptedBy(): MorphTo
    {
        return $this->morphTo('accepted_by');
    }

    // ──── Scopes ────

    /** @param Builder<Invitation> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', InvitationStatus::Pending);
    }

    /** @param Builder<Invitation> $query */
    public function scopeAccepted(Builder $query): void
    {
        $query->where('status', InvitationStatus::Accepted);
    }

    /** @param Builder<Invitation> $query */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', InvitationStatus::Expired);
    }

    /** @param Builder<Invitation> $query */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', InvitationStatus::Cancelled);
    }

    /** @param Builder<Invitation> $query */
    public function scopeDeclined(Builder $query): void
    {
        $query->where('status', InvitationStatus::Declined);
    }

    /** @param Builder<Invitation> $query */
    public function scopeForEmail(Builder $query, string $email): void
    {
        $query->where('email', $email);
    }

    /** @param Builder<Invitation> $query */
    public function scopeForSubject(Builder $query, Model $model): void
    {
        $query->where('subject_type', $model->getMorphClass())
            ->where('subject_id', $model->getKey());
    }

    /** @param Builder<Invitation> $query */
    public function scopeInvitedBy(Builder $query, Model $model): void
    {
        $query->where('inviter_type', $model->getMorphClass())
            ->where('inviter_id', $model->getKey());
    }

    // ──── Helpers ────

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === InvitationStatus::Accepted;
    }

    public function isExpired(): bool
    {
        if ($this->status === InvitationStatus::Expired) {
            return true;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    public function isCancelled(): bool
    {
        return $this->status === InvitationStatus::Cancelled;
    }

    public function isDeclined(): bool
    {
        return $this->status === InvitationStatus::Declined;
    }
}
