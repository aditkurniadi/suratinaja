<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('activity_code', 50)->nullable()->after('number_code');
            $table->boolean('is_activity')->default(true)->after('activity_code');
            $table->json('letter_types_config')->nullable()->after('is_activity');
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->change();
            $table->string('link_drive', 500)->nullable()->after('generated_file_path');
            $table->boolean('is_manual_number')->default(false)->after('link_drive');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn(['link_drive', 'is_manual_number']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['activity_code', 'is_activity', 'letter_types_config']);
        });
    }
};
