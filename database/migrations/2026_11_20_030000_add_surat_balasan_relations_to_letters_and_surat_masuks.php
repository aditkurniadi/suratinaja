<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->foreignId('surat_masuk_id')
                ->nullable()
                ->after('duplicated_from_id')
                ->constrained('surat_masuks')
                ->nullOnDelete();

            $table->index(['workspace_id', 'surat_masuk_id']);
        });

        Schema::table('surat_masuks', function (Blueprint $table) {
            $table->foreignId('surat_keluar_id')
                ->nullable()
                ->after('status')
                ->constrained('letters')
                ->nullOnDelete();

            $table->string('nomor_surat_balasan', 150)->nullable()->after('surat_keluar_id');
            $table->text('catatan_balasan')->nullable()->after('nomor_surat_balasan');
        });
    }

    public function down(): void
    {
        Schema::table('surat_masuks', function (Blueprint $table) {
            $table->dropForeign(['surat_keluar_id']);
            $table->dropColumn(['surat_keluar_id', 'nomor_surat_balasan', 'catatan_balasan']);
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->dropForeign(['surat_masuk_id']);
            $table->dropIndex(['workspace_id', 'surat_masuk_id']);
            $table->dropColumn(['surat_masuk_id']);
        });
    }
};
