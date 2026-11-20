<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_masuks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('nomor_agenda', 60);
            $table->string('nomor_surat_pengirim', 150);
            $table->date('tanggal_surat');
            $table->date('tanggal_diterima');
            $table->string('pengirim', 200);
            $table->string('perihal', 255);
            $table->text('ringkasan')->nullable();
            $table->string('sifat_surat', 20)->default('biasa'); // biasa, penting, segera
            $table->date('tanggal_acara_atau_deadline')->nullable();
            $table->string('status', 25)->default('baru'); // baru, menunggu_disposisi, didisposisi, selesai, diarsipkan
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('document_url')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['workspace_id', 'nomor_agenda']);
            $table->index(['workspace_id', 'nomor_surat_pengirim']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'sifat_surat']);
            $table->index(['workspace_id', 'tanggal_diterima']);
        });

        Schema::create('disposisis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_masuk_id')->constrained('surat_masuks')->cascadeOnDelete();
            $table->foreignId('dari_user_id')->constrained('users');
            $table->foreignId('kepada_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tujuan_divisi', 100)->nullable();
            $table->text('instruksi');
            $table->date('batas_waktu')->nullable();
            $table->string('status_tindak_lanjut', 25)->default('belum_diproses'); // belum_diproses, sedang_diproses, selesai
            $table->text('catatan_tindak_lanjut')->nullable();
            $table->timestamp('diselesaikan_pada')->nullable();
            $table->timestamps();

            $table->index(['surat_masuk_id', 'status_tindak_lanjut']);
            $table->index(['kepada_user_id', 'status_tindak_lanjut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposisis');
        Schema::dropIfExists('surat_masuks');
    }
};
