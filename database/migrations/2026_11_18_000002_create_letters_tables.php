<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Templates, letters, numbers, sequences, revisions, activities, bulk jobs
 * (TASK-03..09, DATABASE.md §3.2–3.5 + additions documented in DATABASE.md §6).
 * Status columns are stored as short strings backed by PHP enums (App\Enums\*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 20); // global|workspace
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->json('variables_schema');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('source_template_id')->nullable()->constrained('letter_templates')->nullOnDelete();
            $table->boolean('is_deprecated')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['scope', 'workspace_id']);
        });

        Schema::create('letter_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 16);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['workspace_id', 'period_key']);
        });

        Schema::create('bulk_jobs', function (Blueprint $table) {
            $table->string('id', 40)->primary(); // bulk_<ulid>
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('letter_templates');
            $table->foreignId('created_by')->constrained('users');
            $table->string('perihal');
            $table->date('letter_date');
            $table->json('shared_variables');
            $table->json('recipients');
            $table->json('failed_rows')->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->string('output_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('letter_templates');
            $table->foreignId('created_by')->constrained('users');
            $table->string('bulk_job_id', 40)->nullable();
            $table->foreign('bulk_job_id')->references('id')->on('bulk_jobs')->nullOnDelete();
            $table->foreignId('duplicated_from_id')->nullable()->constrained('letters')->nullOnDelete();
            $table->string('perihal');
            $table->date('letter_date');
            $table->string('status', 20)->default('draft');
            $table->json('field_values');
            $table->string('generated_file_path')->nullable();
            $table->string('preview_pdf_path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->text('cancel_reason')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'perihal']);
        });

        Schema::create('letter_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('formatted_number', 120);
            $table->unsignedInteger('sequence');
            $table->string('period_key', 16);
            $table->string('status', 20)->default('aktif');
            $table->text('revision_reason')->nullable();
            $table->foreignId('revision_of')->nullable()->constrained('letter_numbers')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('voided_at')->nullable();
            $table->unique(['workspace_id', 'formatted_number']);
            $table->index('letter_id');
        });

        Schema::create('letter_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('mode', 20); // new_number|keep_number
            $table->foreignId('old_number_id')->nullable()->constrained('letter_numbers')->nullOnDelete();
            $table->foreignId('new_number_id')->nullable()->constrained('letter_numbers')->nullOnDelete();
            $table->text('reason');
            $table->json('changes')->nullable();
            $table->string('status_before', 20);
            $table->foreignId('actor_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['letter_id', 'version']);
        });

        Schema::create('letter_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30); // created|edited|status_changed|revised|cancelled|duplicated
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['letter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_activities');
        Schema::dropIfExists('letter_revisions');
        Schema::dropIfExists('letter_numbers');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('bulk_jobs');
        Schema::dropIfExists('letter_number_sequences');
        Schema::dropIfExists('letter_templates');
    }
};
