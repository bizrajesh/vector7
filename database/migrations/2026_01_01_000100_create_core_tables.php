<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('type', 20)->default('organisation');
            $t->string('name', 100);
            $t->string('slug', 120)->unique();
            $t->string('email')->index();
            $t->string('mobile', 15)->nullable();
            $t->string('status', 20)->default('active');
            $t->string('logo_path')->nullable();
            $t->string('address_line1', 150)->nullable();
            $t->string('address_line2', 150)->nullable();
            $t->string('village', 100)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('district', 100)->nullable();
            $t->string('state', 100)->nullable()->default('Tamil Nadu');
            $t->string('pin', 6)->nullable();
            $t->string('contact', 15)->nullable();
            $t->string('alt_contact', 15)->nullable();
            $t->string('support_email')->nullable();
            $t->string('website')->nullable();
            $t->string('instagram')->nullable();
            $t->string('facebook')->nullable();
            $t->string('youtube')->nullable();
            $t->string('linkedin')->nullable();
            $t->unsignedTinyInteger('onboarding_step')->default(1);
            $t->unsignedBigInteger('storage_used_bytes')->default(0);
            $t->timestamp('terms_accepted_at')->nullable();
            $t->timestamps();
        });

        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name', 60);
            $t->string('slug', 60)->unique();
            $t->text('description')->nullable();
            $t->decimal('price', 12, 2)->default(0);
            $t->string('billing_cycle', 10)->default('monthly');
            $t->unsignedSmallInteger('trial_days')->default(0);
            $t->boolean('is_active')->default(true);
            $t->boolean('is_trial_default')->default(false);
            $t->json('modules')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('plan_limits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $t->string('key', 40);
            $t->bigInteger('value')->default(0); // -1 = unlimited
            $t->unique(['plan_id', 'key']);
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained();
            $t->string('status', 20)->default('trial');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->json('alerts_sent')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('subscription_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('plan_id')->constrained();
            $t->string('number', 30)->unique();
            $t->decimal('amount', 12, 2);
            $t->decimal('tax', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->string('status', 20)->default('due');
            $t->string('method', 20)->nullable();
            $t->string('gateway_order_id')->nullable()->index();
            $t->string('gateway_payment_id')->nullable();
            $t->date('period_start');
            $t->date('period_end');
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('marked_paid_by')->nullable();
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('slug', 60);
            $t->string('base_role', 30);
            $t->json('permissions')->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
            $t->unique(['tenant_id', 'slug']);
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();
            $t->string('module', 40);
            $t->string('action', 20);
            $t->string('scope', 10);
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained();
            $t->string('user_code', 40)->nullable();
            $t->string('name', 100);
            $t->string('email')->unique();
            $t->string('mobile', 15)->nullable();
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->boolean('must_change_password')->default(false);
            $t->unsignedTinyInteger('failed_logins')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'role_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('email')->unique();
            $t->string('mobile', 15)->nullable();
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->boolean('must_change_password')->default(false);
            $t->unsignedTinyInteger('failed_logins')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->string('address', 255)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('district', 100)->nullable();
            $t->string('state', 100)->nullable();
            $t->string('pin', 6)->nullable();
            $t->rememberToken();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
        });

        Schema::create('customer_password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('customer_tenant', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('source', 20)->default('booking');
            $t->timestamps();
            $t->unique(['customer_id', 'tenant_id']);
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();
            $t->longText('value')->nullable();
            $t->boolean('is_encrypted')->default(false);
            $t->timestamps();
        });

        Schema::create('tenant_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('currency', 3)->default('INR');
            $t->decimal('sellable_pct', 5, 2)->default(55);
            $t->decimal('broker_commission_pct', 5, 2)->default(0.5);
            $t->decimal('budget_alert_pct', 5, 2)->default(80);
            $t->decimal('mrp_multiplier', 6, 2)->default(3);
            $t->unsignedSmallInteger('booking_validity_days')->default(7);
            $t->unsignedSmallInteger('sale_window_days')->default(15);
            $t->longText('disclaimer_booking')->nullable();
            $t->longText('disclaimer_sale')->nullable();
            $t->longText('disclaimer_registration')->nullable();
            $t->longText('disclaimer_refund')->nullable();
            $t->timestamps();
        });

        Schema::create('instalment_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('seq');
            $t->string('name', 60);
            $t->decimal('percent', 5, 2);
            $t->unsignedSmallInteger('due_working_days')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'seq']);
        });

        Schema::create('refund_penalty_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('from_days');
            $t->unsignedSmallInteger('to_days')->nullable();
            $t->string('penalty_type', 10)->default('percent');
            $t->decimal('value', 12, 2);
            $t->timestamps();
        });

        Schema::create('id_sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20);
            $t->string('prefix', 10);
            $t->timestamps();
            $t->unique(['tenant_id', 'type']);
        });

        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->string('name', 100);
            $t->timestamps();
            $t->unique(['tenant_id', 'date']);
        });

        Schema::create('notification_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('type', 20)->default('Email');
            $t->string('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'name']);
        });

        Schema::create('notification_group_user', function (Blueprint $t) {
            $t->foreignId('notification_group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['notification_group_id', 'user_id']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->index();
            $t->string('actor_type', 20)->default('system');
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_name', 100)->nullable();
            $t->string('action', 60);
            $t->string('auditable_type', 120)->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('storage_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->index();
            $t->string('driver', 20)->default('local');
            $t->string('path', 500);
            $t->string('original_name', 255);
            $t->string('mime', 120);
            $t->unsignedBigInteger('size');
            $t->string('category', 40)->default('document');
            $t->nullableMorphs('attachable');
            $t->string('uploaded_by_type', 20)->nullable();
            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->string('name', 100);
            $t->string('subject', 200);
            $t->text('body');
            $t->string('placeholders', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('feature', 40);
            $t->string('model', 60);
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedInteger('credits')->default(1);
            $t->string('status', 20)->default('ok');
            $t->string('error', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('usage_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('month', 7);
            $t->unsignedBigInteger('storage_bytes')->default(0);
            $t->unsignedInteger('projects')->default(0);
            $t->unsignedInteger('users')->default(0);
            $t->unsignedInteger('ai_credits')->default(0);
            $t->unsignedInteger('plots_launched')->default(0);
            $t->unsignedInteger('bookings')->default(0);
            $t->unsignedInteger('sales')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'month']);
        });
    }

    public function down(): void
    {
        foreach (['usage_snapshots', 'ai_usage_logs', 'email_templates', 'storage_files', 'audit_logs', 'notification_group_user', 'notification_groups', 'holidays', 'id_sequences', 'refund_penalty_rules', 'instalment_plans', 'tenant_settings', 'settings', 'sessions', 'customer_tenant', 'customer_password_reset_tokens', 'customers', 'password_reset_tokens', 'users', 'permissions', 'roles', 'subscription_invoices', 'subscriptions', 'plan_limits', 'plans', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
