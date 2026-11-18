<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_workspace_id',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['role', 'joined_at']);
    }

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /** Returns the user's active workspace if still a member, otherwise first available workspace. */
    public function activeWorkspace(): ?Workspace
    {
        if ($this->current_workspace_id) {
            $ws = $this->workspaces()->where('workspaces.id', $this->current_workspace_id)->first();
            if ($ws) {
                return $ws;
            }
        }

        $first = $this->workspaces()->orderBy('workspaces.id')->first();
        if ($first && $this->current_workspace_id !== $first->id) {
            $this->forceFill(['current_workspace_id' => $first->id])->saveQuietly();
        }

        return $first;
    }

    public function getActiveWorkspaceIdAttribute(): ?int
    {
        return app(CurrentWorkspace::class)->id() ?? $this->activeWorkspace()?->id;
    }

    public function switchWorkspace(Workspace $workspace): void
    {
        if (! $workspace->hasMember($this)) {
            return;
        }
        $this->forceFill(['current_workspace_id' => $workspace->id])->save();
        app(CurrentWorkspace::class)->set($workspace);
    }

    public function roleIn(Workspace $workspace): ?WorkspaceRole
    {
        return $workspace->roleOf($this);
    }
}
