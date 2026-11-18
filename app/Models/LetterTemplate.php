<?php

namespace App\Models;

use App\Enums\TemplateScope;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'scope',
        'name',
        'description',
        'file_path',
        'variables_schema',
        'has_embedded_kop',
        'version',
        'source_template_id',
        'is_deprecated',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scope' => TemplateScope::class,
            'variables_schema' => 'array',
            'has_embedded_kop' => 'boolean',
            'version' => 'integer',
            'is_deprecated' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('workspace_or_global', function (Builder $builder) {
            $wsId = app(CurrentWorkspace::class)->id();
            if ($wsId !== null) {
                $builder->where(function (Builder $q) use ($wsId) {
                    $q->where('letter_templates.workspace_id', $wsId)
                        ->orWhere('letter_templates.scope', TemplateScope::Global->value);
                });
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_template_id')->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class, 'template_id');
    }

    public function bulkJobs(): HasMany
    {
        return $this->hasMany(BulkJob::class, 'template_id');
    }

    public function isGlobal(): bool
    {
        return $this->scope === TemplateScope::Global;
    }

    public function hasEmbeddedKop(): bool
    {
        return (bool) $this->has_embedded_kop;
    }

    public function isAccessibleBy(Workspace $workspace): bool
    {
        return $this->isGlobal() || (int) $this->workspace_id === (int) $workspace->id;
    }
}
