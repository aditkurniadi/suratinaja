<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            if (! Schema::hasColumn('letters', 'tanggal_kegiatan')) {
                $table->date('tanggal_kegiatan')->nullable()->after('letter_date');
                $table->index(['workspace_id', 'tanggal_kegiatan']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            if (Schema::hasColumn('letters', 'tanggal_kegiatan')) {
                $table->dropIndex(['workspace_id', 'tanggal_kegiatan']);
                $table->dropColumn('tanggal_kegiatan');
            }
        });
    }
};
