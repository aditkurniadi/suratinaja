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
        'default_city',
        'signers_config',
        'number_pattern',
        'number_code',
        'activity_code',
        'is_activity',
        'letter_types_config',
        'seq_padding',
    ];

    protected function casts(): array
    {
        return [
            'seq_padding' => 'integer',
            'is_activity' => 'boolean',
            'signers_config' => 'array',
            'letter_types_config' => 'array',
        ];
    }

    public function letterTypes(): array
    {
        if (is_array($this->letter_types_config) && count($this->letter_types_config) > 0) {
            return $this->letter_types_config;
        }

        return config('suratinaja.letter_types', []);
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

        static::created(function (Workspace $ws) {
            try {
                app(\App\Services\Authorization\WorkspacePermissionRegistrar::class)->initWorkspaceRoles($ws);
            } catch (\Throwable) {
                // Silently continue if tables not yet ready or in testing seeds
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

    public function suratMasuks(): HasMany
    {
        return $this->hasMany(\App\Models\SuratMasuk::class);
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

    /** @var array<int, ?WorkspaceRole> */
    protected array $userRolesMemo = [];

    public function roleOf(User $user): ?WorkspaceRole
    {
        if (array_key_exists($user->id, $this->userRolesMemo)) {
            return $this->userRolesMemo[$user->id];
        }

        if ($this->relationLoaded('members')) {
            $member = $this->members->firstWhere('user_id', $user->id);
            $role = $member?->role;
        } else {
            $role = $this->members()->where('user_id', $user->id)->value('role');
        }

        $resolved = null;
        if ($role instanceof WorkspaceRole) {
            $resolved = $role;
        } elseif (is_string($role)) {
            $resolved = WorkspaceRole::tryFrom($role);
        }

        return $this->userRolesMemo[$user->id] = $resolved;
    }

    public function hasKop(): bool
    {
        return filled($this->kop_header_text)
            || filled($this->kop_address)
            || filled($this->kop_logo_left)
            || filled($this->kop_logo_right);
    }

    /**
     * @return array<string, array{name: string, signers: array<int, array{prefix: string, position: string, name: string, id_label: string, id_number: string, layout: string}>}>
     */
    public static function signerPresets(): array
    {
        return [
            '2_signers' => [
                'name' => '2 Penandatangan (Ketua & Ketuplak)',
                'signers' => [
                    [
                        'prefix' => '',
                        'position' => 'Ketua HIMATRIS,',
                        'name' => 'Dimas Surya Pratama',
                        'id_label' => 'NIM.',
                        'id_number' => '25.01.09.044',
                        'layout' => 'auto',
                    ],
                    [
                        'prefix' => '',
                        'position' => 'Ketua Pelaksana,',
                        'name' => 'Karisma Putri Isabela',
                        'id_label' => 'NIM.',
                        'id_number' => '25.03.15.020',
                        'layout' => 'auto',
                    ],
                ],
            ],
            '5_signers' => [
                'name' => '5 Penandatangan (Lengkap Bertingkat)',
                'signers' => [
                    [
                        'prefix' => '',
                        'position' => 'Ketua HIMATRIS,',
                        'name' => 'Dimas Surya Pratama',
                        'id_label' => 'NIM.',
                        'id_number' => '25.01.09.044',
                        'layout' => 'auto',
                    ],
                    [
                        'prefix' => '',
                        'position' => 'Ketua Pelaksana,',
                        'name' => 'Karisma Putri Isabela',
                        'id_label' => 'NIM.',
                        'id_number' => '25.03.15.020',
                        'layout' => 'auto',
                    ],
                    [
                        'prefix' => 'Menyetujui,',
                        'position' => 'Koordinator Prodi,',
                        'name' => 'Lutfi Syafirullah, S.T., M.Kom.',
                        'id_label' => 'NIP.',
                        'id_number' => '19841121202421 1 009',
                        'layout' => 'auto',
                    ],
                    [
                        'prefix' => '',
                        'position' => 'Pembina HIMATRIS,',
                        'name' => 'Lutfi Syafirullah, S.T., M.Kom.',
                        'id_label' => 'NIP.',
                        'id_number' => '19841121202421 1 009',
                        'layout' => 'auto',
                    ],
                    [
                        'prefix' => 'Mengetahui,',
                        'position' => 'Ketua Jurusan Komputer & Bisnis,',
                        'name' => 'Dwi Novia Prasetyanti, S.Kom., M.Cs.',
                        'id_label' => 'NIP.',
                        'id_number' => '19791119 202121 2 009',
                        'layout' => 'auto',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{prefix: string, position: string, name: string, id_label: string, id_number: string, layout: string}>
     */
    public function defaultSigners(): array
    {
        $configured = $this->signers_config;
        if (is_array($configured) && count($configured) > 0) {
            return array_values(array_map(fn (array $s) => [
                'prefix' => (string) ($s['prefix'] ?? ''),
                'position' => (string) ($s['position'] ?? ''),
                'name' => (string) ($s['name'] ?? ''),
                'id_label' => (string) ($s['id_label'] ?? 'NIP.'),
                'id_number' => (string) ($s['id_number'] ?? ''),
                'layout' => (string) ($s['layout'] ?? 'auto'),
            ], $configured));
        }

        return static::signerPresets()['2_signers']['signers'];
    }
}
