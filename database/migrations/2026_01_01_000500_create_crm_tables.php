<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('slug', 140)->unique();
            $t->string('summary', 255)->nullable();
            $t->text('description')->nullable();
            $t->decimal('price', 12, 2)->nullable();
            $t->boolean('price_on_request')->default(false);
            $t->string('available_to', 10)->default('both');
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('requester_type', 10);
            $t->unsignedBigInteger('requester_id');
            $t->string('requester_name', 100);
            $t->string('requester_email');
            $t->string('category', 40);
            $t->string('priority', 10)->default('medium');
            $t->string('subject', 200);
            $t->string('status', 20)->default('open');
            $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('sla_due_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'priority']);
        });

        Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->string('author_type', 10);
            $t->unsignedBigInteger('author_id');
            $t->string('author_name', 100);
            $t->text('body');
            $t->boolean('is_internal')->default(false);
            $t->foreignId('attachment_file_id')->nullable();
            $t->timestamps();
        });

        Schema::create('service_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('new');
            $t->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('enquiries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('plot_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 100);
            $t->string('email');
            $t->string('mobile', 15)->nullable();
            $t->text('message');
            $t->string('source', 20)->default('marketplace');
            $t->string('status', 20)->default('new');
            $t->string('assigned_team', 10)->default('tenant');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->date('follow_up_on')->nullable();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('social_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('platform', 20);
            $t->string('account_name', 120)->nullable();
            $t->string('account_id', 120)->nullable();
            $t->text('access_token')->nullable();
            $t->boolean('is_connected')->default(false);
            $t->timestamp('connected_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'platform']);
        });

        Schema::create('social_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('platform', 20);
            $t->date('metric_date');
            $t->unsignedBigInteger('reach')->default(0);
            $t->unsignedBigInteger('likes')->default(0);
            $t->unsignedBigInteger('comments')->default(0);
            $t->unsignedBigInteger('followers')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'platform', 'metric_date']);
        });

        Schema::create('customer_requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->text('query_text');
            $t->json('filters');
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_notified_at')->nullable();
            $t->timestamps();
        });

        Schema::create('customer_requirement_matches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_requirement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plot_id')->constrained()->cascadeOnDelete();
            $t->timestamp('notified_at')->useCurrent();
            $t->unique(['customer_requirement_id', 'plot_id'], 'req_plot_unique');
        });

        Schema::create('seo_meta', function (Blueprint $t) {
            $t->id();
            $t->string('page_type', 30);
            $t->string('page_key', 190);
            $t->string('title', 70)->nullable();
            $t->string('description', 170)->nullable();
            $t->boolean('is_manual')->default(false);
            $t->timestamps();
            $t->unique(['page_type', 'page_key']);
        });
    }

    public function down(): void
    {
        foreach (['seo_meta', 'customer_requirement_matches', 'customer_requirements', 'social_metrics', 'social_accounts', 'enquiries', 'service_requests', 'ticket_messages', 'tickets', 'services'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
