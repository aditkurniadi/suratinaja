<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterActivity extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $fillable = [
        'letter_id',
        'workspace_id',
        'actor_id',
        'action',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class)->withoutGlobalScopes();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return match ($this->action) {
            'created' => 'Membuat draft surat',
            'edited' => 'Memperbarui draft surat',
            'status_changed' => 'Mengubah status surat',
            'revised' => 'Merevisi surat',
            'cancelled' => 'Membatalkan surat',
            'duplicated' => 'Menduplikasi surat',
            default => $this->action,
        };
    }
}
