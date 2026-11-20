<?php

namespace App\Models;

use App\Enums\StatusDisposisi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disposisi extends Model
{
    use HasFactory;

    protected $fillable = [
        'surat_masuk_id',
        'dari_user_id',
        'kepada_user_id',
        'tujuan_divisi',
        'instruksi',
        'batas_waktu',
        'status_tindak_lanjut',
        'catatan_tindak_lanjut',
        'diselesaikan_pada',
    ];

    protected function casts(): array
    {
        return [
            'batas_waktu' => 'date',
            'diselesaikan_pada' => 'datetime',
            'status_tindak_lanjut' => StatusDisposisi::class,
        ];
    }

    public function suratMasuk(): BelongsTo
    {
        return $this->belongsTo(SuratMasuk::class);
    }

    public function dariUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dari_user_id');
    }

    public function kepadaUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepada_user_id');
    }

    public function targetDisplay(): string
    {
        if ($this->kepadaUser) {
            return $this->kepadaUser->name;
        }

        return $this->tujuan_divisi ?: 'Semua Anggota';
    }
}
