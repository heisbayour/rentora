<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->index();
            $table->string('alt_phone', 32)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('employer', 150)->nullable();
            $table->string('id_type', 50)->nullable();
            $table->string('id_number', 100)->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending_invite')->index();
            $table->timestamp('invite_sent_at')->nullable();
            $table->json('notification_prefs')->nullable();
            $table->unsignedBigInteger('credit_balance')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('tenant_id')->constrained();
            // Points at the lease this one renews (spec 8.4 renew; used by EndExpiredLeases).
            $table->foreignId('renewal_of_id')->nullable()->constrained('leases')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('rent_amount');
            $table->string('rent_frequency', 20);
            $table->unsignedBigInteger('service_charge_amount')->default(0);
            $table->unsignedBigInteger('deposit_amount')->default(0);
            $table->string('deposit_status', 20)->default('unpaid');
            $table->unsignedTinyInteger('billing_anchor_day');
            $table->unsignedSmallInteger('invoice_lead_days');
            $table->unsignedSmallInteger('grace_days')->default(5);
            $table->string('late_fee_type', 20)->default('none');
            $table->unsignedBigInteger('late_fee_value')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
        Schema::dropIfExists('tenants');
    }
};
