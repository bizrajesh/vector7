<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('project_code', 40);
            $t->string('name', 150);
            $t->string('slug', 170)->unique();
            $t->string('approval_type', 10);
            $t->string('location', 100);
            $t->string('address', 255)->nullable();
            $t->string('district', 100)->nullable();
            $t->string('state', 100)->default('Tamil Nadu');
            $t->string('pin', 6)->nullable();
            $t->string('email')->nullable();
            $t->string('contact', 15)->nullable();
            $t->string('owner_name', 150)->nullable();
            $t->string('survey_numbers', 255)->nullable();
            $t->string('patta_numbers', 255)->nullable();
            $t->decimal('size_acres', 12, 4)->default(0);
            $t->string('land_classification', 40)->nullable();
            $t->decimal('guideline_rate', 12, 2)->default(0);
            $t->decimal('guideline_value', 16, 2)->default(0);
            $t->decimal('market_rate', 12, 2)->default(0);
            $t->decimal('market_value', 16, 2)->default(0);
            $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('draft');
            $t->string('decision', 10)->nullable();
            $t->date('decision_on')->nullable();
            $t->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('decision_notes')->nullable();
            $t->date('start_date')->nullable();
            $t->date('est_end_date')->nullable();
            $t->date('actual_end_date')->nullable();
            $t->decimal('progress_pct', 5, 2)->default(0);
            $t->decimal('sellable_pct', 5, 2)->nullable();
            $t->decimal('sellable_sqft', 14, 2)->nullable();
            $t->foreignId('layout_file_id')->nullable();
            $t->unsignedInteger('layout_width')->nullable();
            $t->unsignedInteger('layout_height')->nullable();
            $t->text('promo_text')->nullable();
            $t->json('facilities')->nullable();
            $t->json('gallery')->nullable();
            $t->string('map_url', 500)->nullable();
            $t->boolean('is_featured')->default(false);
            $t->timestamp('launched_at')->nullable();
            $t->json('budget_alerts_sent')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->softDeletes();
            $t->timestamps();
            $t->unique(['tenant_id', 'project_code']);
            $t->index(['tenant_id', 'status']);
            $t->index(['status', 'location']);
        });

        Schema::create('project_estimates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('tier', 10)->default('Basic');
            $t->decimal('facility_cost', 16, 2)->default(0);
            $t->decimal('stage_cost', 16, 2)->default(0);
            $t->decimal('other_cost', 16, 2)->default(0);
            $t->decimal('total_cost', 16, 2)->default(0);
            $t->decimal('total_sqft', 16, 2)->default(0);
            $t->decimal('sellable_pct', 5, 2)->default(55);
            $t->decimal('sellable_sqft', 16, 2)->default(0);
            $t->decimal('cost_per_sqft', 12, 2)->default(0);
            $t->decimal('mrp_multiplier', 6, 2)->default(3);
            $t->decimal('mrp_per_sqft', 12, 2)->default(0);
            $t->unsignedSmallInteger('est_duration_days')->default(0);
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
        });

        Schema::create('estimate_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_estimate_id')->constrained()->cascadeOnDelete();
            $t->string('line_type', 10);
            $t->foreignId('facility_master_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('stage_no')->nullable();
            $t->string('description', 255);
            $t->string('unit', 30)->nullable();
            $t->decimal('quantity', 14, 2)->default(1);
            $t->decimal('unit_cost', 14, 2)->default(0);
            $t->decimal('amount', 16, 2)->default(0);
            $t->decimal('actual_cost', 16, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('project_stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('stage_no');
            $t->string('name', 120);
            $t->decimal('budget', 16, 2)->default(0);
            $t->decimal('actual_cost', 16, 2)->default(0);
            $t->decimal('progress_pct', 5, 2)->default(0);
            $t->date('planned_start')->nullable();
            $t->date('planned_end')->nullable();
            $t->date('actual_start')->nullable();
            $t->date('actual_end')->nullable();
            $t->string('status', 20)->default('not_started');
            $t->timestamps();
            $t->unique(['project_id', 'stage_no']);
        });

        Schema::create('project_subtasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_stage_id')->constrained()->cascadeOnDelete();
            $t->string('task_code', 12);
            $t->string('short_name', 60);
            $t->string('name', 255);
            $t->string('responsible', 150)->nullable();
            $t->unsignedSmallInteger('duration_days')->default(1);
            $t->json('depends_on')->nullable();
            $t->string('status', 20)->default('not_started');
            $t->date('planned_start')->nullable();
            $t->date('planned_end')->nullable();
            $t->date('actual_start')->nullable();
            $t->date('actual_end')->nullable();
            $t->decimal('budget', 16, 2)->default(0);
            $t->decimal('actual_cost', 16, 2)->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'task_code']);
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('project_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_subtask_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('doc_code', 12)->nullable();
            $t->string('name', 255);
            $t->boolean('is_mandatory')->default(true);
            $t->foreignId('storage_file_id')->nullable();
            $t->timestamp('uploaded_at')->nullable();
            $t->foreignId('uploaded_by')->nullable();
            $t->timestamps();
        });

        Schema::create('plots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->string('plot_no', 20);
            $t->string('patta_number', 40);
            $t->decimal('size_sqft', 12, 2);
            $t->decimal('length_ft', 10, 2)->nullable();
            $t->decimal('width_ft', 10, 2)->nullable();
            $t->string('facing', 12);
            $t->string('east_boundary', 100)->nullable();
            $t->string('west_boundary', 100)->nullable();
            $t->string('north_boundary', 100)->nullable();
            $t->string('south_boundary', 100)->nullable();
            $t->decimal('road_width_ft', 8, 2)->nullable();
            $t->boolean('is_corner')->default(false);
            $t->decimal('rate_per_sqft', 12, 2);
            $t->string('offer_text', 200)->nullable();
            $t->decimal('offer_rate_per_sqft', 12, 2)->nullable();
            $t->date('offer_valid_till')->nullable();
            $t->boolean('offer_active')->default(false);
            $t->string('status', 20)->default('available');
            $t->json('map_polygon')->nullable();
            $t->decimal('map_x', 6, 3)->nullable();
            $t->decimal('map_y', 6, 3)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'plot_no']);
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('plot_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plot_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->string('reason', 255)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['plot_status_history', 'plots', 'project_documents', 'project_subtasks', 'project_stages', 'estimate_lines', 'project_estimates', 'projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
