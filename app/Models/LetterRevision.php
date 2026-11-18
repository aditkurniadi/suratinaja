<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterRevision extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $fillable = [
        'letter_id',
        'workspace_id',
        'version',
        'mode',
        'old_number_id',
        'new_number_id',
        'reason',
        'changes',
        'status_before',
        'actor_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class)->withoutGlobalScopes();
    }

    public function oldNumber(): BelongsTo
    {
        return $this->belongsTo(LetterNumber::class, 'old_number_id')->withoutGlobalScopes();
    }

    public function newNumber(): BelongsTo
    {
        return $this->belongsTo(LetterNumber::class, 'new_number_id')->withoutGlobalScopes();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
