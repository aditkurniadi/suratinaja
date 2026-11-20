<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvelopeTemplate extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'preset_size',
        'file_path',
        'version',
        'is_default',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sizeLabel(): string
    {
        return match ($this->preset_size) {
            'dl' => 'Panjang No. 90 / DL (230 x 110 mm)',
            'c5' => 'Sedang C5 (229 x 162 mm)',
            'a4' => 'Besar / Map A4 (210 x 297 mm)',
            default => 'Kustom',
        };
    }
}
