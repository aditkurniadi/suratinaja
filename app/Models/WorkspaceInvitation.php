<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvitation extends Model
{
    protected $fillable = [
        'workspace_id',
        'invited_by',
        'email',
        'role',
        'token',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at ? $this->expires_at->isPast() : false;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }

    public function getRoleDisplayName(): string
    {
        $raw = (string) $this->role;
        $enum = WorkspaceRole::tryFrom($raw);

        return $enum ? $enum->label() : $raw;
    }
}
