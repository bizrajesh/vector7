<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brokers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('mobile', 15)->nullable();
            $t->string('email')->nullable();
            $t->decimal('commission_pct', 5, 2)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('social_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('platform', 20);
            $t->text('caption');
            $t->string('hashtags', 500)->nullable();
            $t->string('image_suggestion', 500)->nullable();
            $t->foreignId('image_file_id')->nullable();
            $t->timestamp('scheduled_at')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->string('external_id')->nullable();
            $t->string('error', 500)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('promo_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code', 30);
            $t->string('discount_type', 10)->default('percent');
            $t->decimal('value', 12, 2);
            $t->date('valid_from');
            $t->date('valid_to');
            $t->unsignedInteger('max_uses')->default(0); // 0 = unlimited
            $t->unsignedInteger('used_count')->default(0);
            $t->json('project_ids')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'code']);
        });

        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('booking_no', 40);
            $t->foreignId('project_id')->constrained();
            $t->foreignId('plot_id')->constrained();
            $t->foreignId('customer_id')->constrained();
            $t->string('status', 20)->default('active');
            $t->string('source', 20)->default('workspace');
            $t->date('booked_on');
            $t->date('valid_till');
            $t->decimal('actual_price', 16, 2);
            $t->decimal('offer_price', 16, 2)->nullable();
            $t->string('offer_text', 200)->nullable();
            $t->decimal('price_used', 16, 2);
            $t->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('discount_amount', 16, 2)->default(0);
            $t->decimal('net_price', 16, 2);
            $t->timestamp('disclaimer_accepted_at')->nullable();
            $t->string('disclaimer_ip', 45)->nullable();
            $t->timestamp('reminder_sent_at')->nullable();
            $t->timestamp('released_at')->nullable();
            // Holds plot_id while the booking is active; NULL otherwise. Unique index => one active booking per plot.
            $t->unsignedBigInteger('active_plot_lock')->nullable()->unique();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'booking_no']);
            $t->index(['tenant_id', 'status', 'valid_till']);
        });

        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('sale_no', 40);
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('plot_id')->constrained();
            $t->foreignId('customer_id')->constrained();
            $t->string('status', 20)->default('sale_init');
            $t->date('started_on');
            $t->date('window_end');
            $t->decimal('actual_price', 16, 2);
            $t->decimal('offer_price', 16, 2)->nullable();
            $t->decimal('price_used', 16, 2);
            $t->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('discount_amount', 16, 2)->default(0);
            $t->decimal('net_price', 16, 2);
            $t->decimal('paid_amount', 16, 2)->default(0);
            $t->decimal('due_amount', 16, 2)->default(0);
            $t->foreignId('broker_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('broker_commission_pct', 5, 2)->default(0);
            $t->decimal('broker_commission_amount', 16, 2)->default(0);
            $t->timestamp('disclaimer_accepted_at')->nullable();
            $t->string('disclaimer_ip', 45)->nullable();
            $t->unsignedBigInteger('active_plot_lock')->nullable()->unique();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'sale_no']);
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('promo_code_uses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('discount_amount', 16, 2);
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('instalments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('seq');
            $t->string('name', 60);
            $t->decimal('percent', 5, 2);
            $t->decimal('amount', 16, 2);
            $t->date('due_date');
            $t->decimal('paid_amount', 16, 2)->default(0);
            $t->string('status', 10)->default('due');
            $t->timestamp('reminder_sent_at')->nullable();
            $t->timestamp('overdue_notified_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status', 'due_date']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('plot_id')->constrained();
            $t->string('kind', 10)->default('sale');
            $t->string('transaction_no', 40);
            $t->decimal('amount', 16, 2);
            $t->string('mode', 20);
            $t->string('reference_no', 60)->nullable();
            $t->date('paid_on');
            $t->foreignId('proof_file_id')->nullable();
            $t->string('notes', 500)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'transaction_no']);
            $t->index(['tenant_id', 'paid_on']);
        });

        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('receipt_no', 40);
            $t->date('issued_on');
            $t->timestamp('emailed_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'receipt_no']);
        });

        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->timestamps();
            $t->unique(['tenant_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('transaction_no', 40);
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_stage_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('estimate_line_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('vendor', 150)->nullable();
            $t->string('description', 255);
            $t->decimal('amount', 16, 2);
            $t->date('spent_on');
            $t->string('mode', 20)->nullable();
            $t->string('reference_no', 60)->nullable();
            $t->foreignId('bill_file_id')->nullable();
            $t->foreignId('refund_id')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'transaction_no']);
            $t->index(['tenant_id', 'spent_on']);
        });

        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('plot_id')->constrained();
            $t->decimal('paid_amount', 16, 2);
            $t->unsignedSmallInteger('days_late');
            $t->decimal('penalty_amount', 16, 2);
            $t->decimal('refund_amount', 16, 2);
            $t->string('status', 20)->default('pending');
            $t->foreignId('requested_by')->nullable();
            $t->foreignId('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_notes', 500)->nullable();
            $t->foreignId('expense_id')->nullable();
            $t->timestamps();
        });

        Schema::create('registrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('plot_id')->unique()->constrained();
            $t->foreignId('sale_id')->constrained();
            $t->foreignId('customer_id')->constrained();
            $t->string('status', 20)->default('draft');
            $t->date('registration_date')->nullable();
            $t->foreignId('sro_id')->nullable()->constrained()->nullOnDelete();
            $t->json('checklist')->nullable();
            $t->string('pr_numbers', 255)->nullable();
            $t->string('document_writer', 150)->nullable();
            $t->string('registered_doc_no', 60)->nullable();
            $t->date('registered_doc_date')->nullable();
            $t->string('physical_file_no', 60)->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('sold_at')->nullable();
            $t->timestamp('disclaimer_accepted_at')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('registration_parties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $t->string('party_type', 10);
            $t->string('name', 150);
            $t->string('relation_name', 150)->nullable();
            $t->unsignedTinyInteger('age')->nullable();
            $t->string('address', 500)->nullable();
            $t->string('mobile', 15)->nullable();
            $t->text('pan_encrypted')->nullable();
            $t->timestamps();
        });

        Schema::create('registration_witnesses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('relation_name', 150)->nullable();
            $t->unsignedTinyInteger('age')->nullable();
            $t->string('address', 500)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['registration_witnesses', 'registration_parties', 'registrations', 'refunds', 'expenses', 'expense_categories', 'receipts', 'payments', 'instalments', 'promo_code_uses', 'sales', 'bookings', 'promo_codes', 'social_posts', 'brokers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
