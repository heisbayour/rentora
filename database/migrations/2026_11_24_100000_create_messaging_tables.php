<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('event', 64)->nullable();
            $table->string('recipient', 191);
            $table->string('recipient_name', 191)->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->text('html_body')->nullable();
            $table->string('status', 10)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('send_after')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_after']);
        });

        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('remindable_type');
            $table->unsignedBigInteger('remindable_id');
            $table->string('kind', 32);
            $table->timestamp('sent_at');

            $table->unique(['remindable_type', 'remindable_id', 'kind']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience', 20);
            $table->json('audience_ids')->nullable();
            $table->json('channels');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_tenant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->unique(['announcement_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_tenant');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('reminder_logs');
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('notifications');
    }
};
