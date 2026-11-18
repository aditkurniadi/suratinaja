<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterNumberSequence extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'period_key',
        'last_sequence',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sequence' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
