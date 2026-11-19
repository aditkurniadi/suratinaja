<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_templates', function (Blueprint $table) {
            $table->boolean('has_embedded_kop')->default(false)->after('variables_schema');
        });
    }

    public function down(): void
    {
        Schema::table('letter_templates', function (Blueprint $table) {
            $table->dropColumn('has_embedded_kop');
        });
    }
};
