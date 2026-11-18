<?php

namespace App\Models;

use App\Enums\BulkJobStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkJob extends Model
{
    use BelongsToWorkspace;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'template_id',
        'created_by',
        'perihal',
        'letter_date',
        'shared_variables',
        'recipients',
        'failed_rows',
        'status',
        'recipient_count',
        'processed_count',
        'output_path',
        'error',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BulkJobStatus::class,
            'letter_date' => 'date',
            'shared_variables' => 'array',
            'recipients' => 'array',
            'failed_rows' => 'array',
            'recipient_count' => 'integer',
            'processed_count' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'template_id')->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class, 'bulk_job_id')->withoutGlobalScopes();
    }

    public function progressPercent(): int
    {
        if ($this->recipient_count === 0) {
            return 0;
        }

        return (int) min(100, round(($this->processed_count / $this->recipient_count) * 100));
    }
}
