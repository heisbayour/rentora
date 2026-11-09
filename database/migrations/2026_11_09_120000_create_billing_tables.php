<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('description')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->date('due_date');
            $table->string('status', 20)->default('unpaid');
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            // Set only on late-fee invoices; unique so a parent gets at most one late fee (spec 8.5).
            $table->foreignId('parent_invoice_id')->nullable()->unique()->constrained('invoices')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            // Makes generation idempotent: one invoice of a type per lease period (spec 5.4).
            $table->unique(['lease_id', 'type', 'period_start']);
            $table->index(['tenant_id', 'status']);
            $table->index(['due_date', 'status']);
        });

        // Payments arrive in Phase 6; the tables exist from Phase 5 because a tenant's
        // credit is the unallocated part of their payments, applied to new invoices.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('receipt_number', 32)->nullable()->unique();
            $table->string('verification_code', 10)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('status', 20)->default('pending');
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'method', 'created_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
    }
};
