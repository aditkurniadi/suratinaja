<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'kop_logo_left',
        'kop_logo_right',
        'kop_header_text',
        'kop_address',
        'number_pattern',
        'number_code',
        'seq_padding',
    ];

    protected function casts(): array
    {
        return [
            'seq_padding' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $ws) {
            if (empty($ws->slug)) {
                $base = Str::slug($ws->name) ?: 'workspace';
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $ws->slug = $slug;
            }
            if (empty($ws->number_pattern)) {
                $ws->number_pattern = config('suratinaja.numbering.default_pattern');
            }
            if (empty($ws->number_code)) {
                $ws->number_code = Str::upper(Str::slug($ws->name, ''));
            }
            if ($ws->seq_padding === null) {
                $ws->seq_padding = (int) config('suratinaja.numbering.default_padding', 3);
            }
        });
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot(['role', 'joined_at']);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(LetterTemplate::class)->withoutGlobalScopes();
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class)->withoutGlobalScopes();
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(LetterNumber::class)->withoutGlobalScopes();
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(LetterNumberSequence::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function roleOf(User $user): ?WorkspaceRole
    {
        $role = $this->members()->where('user_id', $user->id)->value('role');

        if ($role instanceof WorkspaceRole) {
            return $role;
        }

        return is_string($role) ? WorkspaceRole::tryFrom($role) : null;
    }

    public function hasKop(): bool
    {
        return filled($this->kop_header_text)
            || filled($this->kop_address)
            || filled($this->kop_logo_left)
            || filled($this->kop_logo_right);
    }
}
