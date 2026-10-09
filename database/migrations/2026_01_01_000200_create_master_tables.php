<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // App-level masters have tenant_id = NULL; tenant copies carry their tenant_id.
        Schema::create('stage_masters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('area_type', 10);
            $t->unsignedTinyInteger('stage_no');
            $t->string('name', 120);
            $t->decimal('default_cost', 14, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'area_type', 'stage_no']);
        });

        Schema::create('subtask_masters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('stage_master_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('sino')->default(0);
            $t->string('task_code', 12);
            $t->string('short_name', 60);
            $t->string('name', 255);
            $t->string('responsible', 150)->nullable();
            $t->string('output', 150)->nullable();
            $t->unsignedSmallInteger('default_duration_days')->default(1);
            $t->decimal('default_cost', 14, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'task_code']);
        });

        Schema::create('subtask_dependencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subtask_master_id')->constrained()->cascadeOnDelete();
            $t->foreignId('depends_on_id')->constrained('subtask_masters')->cascadeOnDelete();
            $t->unique(['subtask_master_id', 'depends_on_id']);
        });

        Schema::create('facility_masters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('sino')->default(0);
            $t->string('facility_code', 12);
            $t->string('category', 60);
            $t->string('name', 150);
            $t->string('specification', 255)->nullable();
            $t->string('unit', 30);
            $t->string('tier_basic', 3)->default('-');
            $t->string('tier_standard', 3)->default('-');
            $t->string('tier_premium', 3)->default('-');
            $t->boolean('is_statutory')->default(false);
            $t->decimal('cost_village', 14, 2)->default(0);
            $t->decimal('cost_town', 14, 2)->default(0);
            $t->decimal('cost_city', 14, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'facility_code']);
        });

        Schema::create('document_checklists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('sino')->default(0);
            $t->string('checklist', 60);
            $t->string('doc_code', 12);
            $t->string('applies_to', 30)->default('All');
            $t->string('name', 255);
            $t->string('issued_by', 150)->nullable();
            $t->string('submission_format', 100)->nullable();
            $t->string('mandatory', 12)->default('Yes');
            $t->string('remarks', 500)->nullable();
            $t->unsignedTinyInteger('stage_no')->nullable();
            $t->string('stage_name', 120)->nullable();
            $t->string('village_task_code', 12)->nullable();
            $t->string('town_task_code', 12)->nullable();
            $t->string('city_task_code', 12)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'doc_code']);
        });

        Schema::create('subtask_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subtask_master_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_checklist_id')->constrained()->cascadeOnDelete();
            $t->unique(['subtask_master_id', 'document_checklist_id'], 'subtask_doc_unique');
        });

        Schema::create('sros', function (Blueprint $t) {
            $t->id();
            $t->string('state', 60)->default('Tamil Nadu');
            $t->string('district', 100)->index();
            $t->string('taluk', 100)->nullable();
            $t->string('name', 150);
            $t->string('address', 255)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['sros', 'subtask_documents', 'document_checklists', 'facility_masters', 'subtask_dependencies', 'subtask_masters', 'stage_masters'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
