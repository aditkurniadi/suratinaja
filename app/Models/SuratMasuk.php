<?php

namespace App\Models;

use App\Enums\SifatSuratMasuk;
use App\Enums\StatusSuratMasuk;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SuratMasuk extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'nomor_agenda',
        'nomor_surat_pengirim',
        'tanggal_surat',
        'tanggal_diterima',
        'pengirim',
        'perihal',
        'ringkasan',
        'sifat_surat',
        'tanggal_acara_atau_deadline',
        'status',
        'surat_keluar_id',
        'nomor_surat_balasan',
        'catatan_balasan',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'document_url',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tanggal_diterima' => 'date',
            'tanggal_acara_atau_deadline' => 'date',
            'sifat_surat' => SifatSuratMasuk::class,
            'status' => StatusSuratMasuk::class,
            'file_size' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function disposisis(): HasMany
    {
        return $this->hasMany(Disposisi::class)->orderByDesc('id');
    }

    public function replyLetters(): HasMany
    {
        return $this->hasMany(Letter::class, 'surat_masuk_id')->withoutGlobalScopes()->orderByDesc('id');
    }

    public function linkedSuratKeluar(): BelongsTo
    {
        return $this->belongsTo(Letter::class, 'surat_keluar_id')->withoutGlobalScopes();
    }

    public function hasReply(): bool
    {
        return $this->replyLetters()->exists()
            || $this->surat_keluar_id !== null
            || ! empty(trim((string) $this->nomor_surat_balasan));
    }

    public function hasFile(): bool
    {
        return ! empty($this->file_path) && Storage::disk('local')->exists($this->file_path);
    }

    public function hasExternalUrl(): bool
    {
        return ! empty($this->document_url);
    }

    public function isPdf(): bool
    {
        return strtolower($this->file_mime ?? '') === 'application/pdf'
            || str_ends_with(strtolower($this->file_path ?? ''), '.pdf');
    }

    public function isImage(): bool
    {
        $mime = strtolower($this->file_mime ?? '');
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $path = strtolower($this->file_path ?? '');

        return str_ends_with($path, '.jpg')
            || str_ends_with($path, '.jpeg')
            || str_ends_with($path, '.png')
            || str_ends_with($path, '.webp');
    }

    /**
     * Generates the next sequential agenda number for a workspace and year (e.g. SM-001/2026).
     */
    public static function nextNomorAgenda(int $workspaceId, ?Carbon $date = null): string
    {
        $year = ($date ?? now())->year;
        $prefix = "SM-";
        $suffix = "/{$year}";

        $latest = static::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('nomor_agenda', 'LIKE', "{$prefix}%{$suffix}")
            ->orderByDesc('id')
            ->value('nomor_agenda');

        $nextSeq = 1;
        if ($latest && preg_match('/SM-(\d+)\/\d{4}/', $latest, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        $padded = str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);

        return "{$prefix}{$padded}{$suffix}";
    }
}
