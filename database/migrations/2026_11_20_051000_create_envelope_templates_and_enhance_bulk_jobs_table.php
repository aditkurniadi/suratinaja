<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('envelope_templates')) {
            Schema::create('envelope_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('preset_size', 20)->default('dl'); // dl, c5, a4, custom
                $table->string('file_path');
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_default')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'is_default']);
            });
        }

        Schema::table('bulk_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('bulk_jobs', 'output_pdf_path')) {
                $table->string('output_pdf_path')->nullable()->after('output_path');
            }
        });

        Schema::table('workspaces', function (Blueprint $table) {
            if (! Schema::hasColumn('workspaces', 'kop_master_docx_path')) {
                $table->string('kop_master_docx_path')->nullable()->after('kop_address');
                $table->unsignedInteger('kop_master_version')->default(1)->after('kop_master_docx_path');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envelope_templates');

        Schema::table('bulk_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('bulk_jobs', 'output_pdf_path')) {
                $table->dropColumn('output_pdf_path');
            }
        });

        Schema::table('workspaces', function (Blueprint $table) {
            if (Schema::hasColumn('workspaces', 'kop_master_docx_path')) {
                $table->dropColumn(['kop_master_docx_path', 'kop_master_version']);
            }
        });
    }
};
