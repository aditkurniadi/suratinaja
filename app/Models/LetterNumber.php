<?php

namespace App\Models;

use App\Enums\NumberStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterNumber extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $fillable = [
        'letter_id',
        'workspace_id',
        'formatted_number',
        'sequence',
        'period_key',
        'status',
        'revision_reason',
        'revision_of',
        'issued_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NumberStatus::class,
            'sequence' => 'integer',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class)->withoutGlobalScopes();
    }

    public function previousNumber(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of')->withoutGlobalScopes();
    }
}
