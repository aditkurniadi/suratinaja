<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            if (! Schema::hasColumn('letters', 'is_manual')) {
                $table->boolean('is_manual')->default(false)->after('is_manual_number');
            }
            if (! Schema::hasColumn('letters', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('cancel_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            if (Schema::hasColumn('letters', 'is_manual')) {
                $table->dropColumn('is_manual');
            }
            if (Schema::hasColumn('letters', 'keterangan')) {
                $table->dropColumn('keterangan');
            }
        });
    }
};
