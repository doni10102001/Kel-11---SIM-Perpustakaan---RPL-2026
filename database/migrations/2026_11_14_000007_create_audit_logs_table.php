<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 7A — `audit_logs`. Desain Phase 7 §4.8.
 * Immutable: tanpa updated_at. actor_id NULL hanya untuk event sistem.
 * subject_id tanpa FK karena merujuk beberapa entitas (diverifikasi aplikasi).
 * metadata tidak boleh memuat password, hash, cookie, atau token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->dateTime('created_at', 6)->useCurrent();

            $table->index(['actor_id', 'created_at'], 'idx_audit_actor_time');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'idx_audit_subject');
            $table->index(['action', 'created_at'], 'idx_audit_action_time');

            $table->foreign('actor_id', 'fk_audit_logs_actor')
                ->references('id')->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
