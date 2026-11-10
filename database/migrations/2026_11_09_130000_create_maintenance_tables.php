<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trade', 100)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 20);
            $table->string('title');
            $table->text('description');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('scheduled_for')->nullable();
            $table->string('preferred_time', 100)->nullable();
            $table->text('access_instructions')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('cost_amount')->nullable();
            $table->unsignedTinyInteger('tenant_rating')->nullable();
            $table->text('tenant_feedback')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('maintenance_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('uploaded_by_vendor')->default(false);
            $table->timestamps();
        });

        Schema::create('maintenance_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->text('body')->nullable();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        // Expenses screens arrive in Phase 10; the table exists now because a
        // repair's recorded cost becomes an expense (spec 8.7).
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('category', 20);
            $table->unsignedBigInteger('amount');
            $table->date('incurred_on');
            $table->string('description');
            $table->string('receipt_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'incurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('maintenance_updates');
        Schema::dropIfExists('maintenance_attachments');
        Schema::dropIfExists('maintenance_requests');
        Schema::dropIfExists('vendors');
    }
};
