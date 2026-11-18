<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspaces, membership, invitations (TASK-01, DATABASE.md §3.1).
 * Additions vs DATABASE.md: number_code, seq_padding, users.current_workspace_id, users.is_super_admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('kop_logo_left')->nullable();
            $table->string('kop_logo_right')->nullable();
            $table->string('kop_header_text')->nullable();
            $table->string('kop_address')->nullable();
            $table->string('number_pattern')->default('{seq}/{kode}/{bulan_romawi}/{tahun}');
            $table->string('number_code', 40)->nullable();
            $table->unsignedTinyInteger('seq_padding')->default(3);
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamp('joined_at')->useCurrent();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email', 150);
            $table->string('role', 20);
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('pending'); // pending|accepted|revoked
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'email', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_workspace_id')->nullable()->after('password')
                ->constrained('workspaces')->nullOnDelete();
            $table->boolean('is_super_admin')->default(false)->after('current_workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_workspace_id');
            $table->dropColumn('is_super_admin');
        });
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
