<?php

namespace App\Models;

use App\Enums\LetterStatus;
use App\Enums\NumberStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Letter extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'template_id',
        'created_by',
        'bulk_job_id',
        'duplicated_from_id',
        'perihal',
        'letter_date',
        'status',
        'field_values',
        'generated_file_path',
        'preview_pdf_path',
        'link_drive',
        'is_manual_number',
        'version',
        'cancel_reason',
        'signed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LetterStatus::class,
            'letter_date' => 'date',
            'field_values' => 'array',
            'version' => 'integer',
            'is_manual_number' => 'boolean',
            'signed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'template_id')->withoutGlobalScopes();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_id')->withoutGlobalScopes();
    }

    public function bulkJob(): BelongsTo
    {
        return $this->belongsTo(BulkJob::class, 'bulk_job_id')->withoutGlobalScopes();
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(LetterNumber::class)->withoutGlobalScopes()->orderBy('id');
    }

    public function activeNumber(): HasOne
    {
        return $this->hasOne(LetterNumber::class)
            ->withoutGlobalScopes()
            ->where('status', NumberStatus::Aktif->value)
            ->latestOfMany('id');
    }

    public function latestNumber(): HasOne
    {
        return $this->hasOne(LetterNumber::class)
            ->withoutGlobalScopes()
            ->latestOfMany('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(LetterRevision::class)->withoutGlobalScopes()->orderByDesc('version');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LetterActivity::class)->withoutGlobalScopes()->orderByDesc('id');
    }

    public function getFormattedNumberAttribute(): ?string
    {
        return $this->activeNumber?->formatted_number ?? $this->latestNumber?->formatted_number;
    }
}
