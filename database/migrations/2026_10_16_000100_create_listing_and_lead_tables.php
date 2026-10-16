<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature Update 01 (spec section 17): listings, direct layout launch, media, features, contacts,
 * leads CRM, AI search logs, shortlists, saved searches and chatbots.
 * Additive only — safe to run on an existing database without losing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 17.1 Direct layout launch + location/trust fields on projects
        Schema::table('projects', function (Blueprint $t) {
            $t->string('launch_mode', 10)->default('full')->after('status');
            $t->string('approval_authority', 30)->nullable()->after('approval_type');
            $t->string('approval_number', 60)->nullable()->after('approval_authority');
            $t->string('village', 100)->nullable()->after('location');
            $t->decimal('lat', 10, 7)->nullable()->after('map_url');
            $t->decimal('lng', 10, 7)->nullable()->after('lat');
            $t->string('youtube_url', 255)->nullable()->after('lng');
            $t->foreignId('brochure_file_id')->nullable()->after('youtube_url');
            $t->index(['status', 'launch_mode']);
        });

        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('listing_code', 40);
            $t->string('type', 20);
            $t->string('purpose', 10)->default('sale');
            $t->string('title', 150);
            $t->string('slug', 200)->unique();
            $t->text('description')->nullable();
            $t->string('address_line1', 150)->nullable();
            $t->string('area_name', 100)->nullable();
            $t->string('city', 100);
            $t->string('district', 100);
            $t->string('state', 100)->default('Tamil Nadu');
            $t->string('pin', 6)->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->string('patta_number', 60)->nullable();
            $t->string('survey_number', 60)->nullable();
            $t->string('approval_number', 60)->nullable();
            $t->decimal('price', 16, 2)->nullable();
            $t->string('offer_text', 200)->nullable();
            $t->decimal('offer_price', 16, 2)->nullable();
            $t->date('offer_valid_till')->nullable();
            $t->boolean('negotiable')->default(false);
            $t->decimal('area_value', 14, 2)->nullable();
            $t->string('area_unit', 10)->default('sqft');
            $t->decimal('area_sqft', 14, 2)->nullable(); // normalised for search
            $t->unsignedTinyInteger('bhk')->nullable();
            $t->string('facing', 12)->nullable();
            $t->json('type_details')->nullable();
            $t->text('features_text')->nullable(); // feature names, for FULLTEXT search
            $t->string('status', 20)->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->boolean('featured')->default(false);
            $t->boolean('hidden_by_admin')->default(false);
            $t->string('hidden_reason', 500)->nullable();
            $t->string('youtube_url', 255)->nullable();
            $t->foreignId('brochure_file_id')->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->softDeletes();
            $t->timestamps();
            $t->unique(['tenant_id', 'listing_code']);
            $t->index(['status', 'type', 'purpose']);
            $t->index(['district', 'city']);
            $t->index('price');
            $t->index('area_sqft');
            $t->index('bhk');
        });

        Schema::create('listing_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('file_id');
            $t->foreignId('thumb_file_id')->nullable();
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->string('caption', 150)->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('is_cover')->default(false);
            $t->string('ai_warning', 300)->nullable();
            $t->timestamps();
            $t->index(['listing_id', 'sort']);
            $t->index(['project_id', 'sort']);
        });

        Schema::create('feature_masters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('listing_type', 20); // plot, flat, farm_land, commercial, villa, house, layout, all
            $t->string('group', 40);
            $t->string('name', 80);
            $t->string('icon', 30)->default('check');
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
            $t->index(['listing_type', 'is_active']);
        });

        Schema::create('listing_features', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('feature_master_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('custom_text', 80)->nullable(); // custom highlight (max 10 per owner)
            $t->timestamps();
        });

        Schema::create('listing_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('owner_type', 10); // listing | project
            $t->unsignedBigInteger('owner_id');
            $t->string('name', 100);
            $t->string('mobile', 15);
            $t->string('whatsapp', 15)->nullable();
            $t->string('email')->nullable();
            $t->boolean('allow_call')->default(true);
            $t->boolean('allow_whatsapp')->default(true);
            $t->boolean('allow_email')->default(true);
            $t->timestamps();
            $t->unique(['owner_type', 'owner_id']);
        });

        Schema::create('listing_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason', 20);
            $t->string('details', 1000)->nullable();
            $t->string('ip_hash', 64)->nullable();
            $t->string('status', 20)->default('open');
            $t->foreignId('resolved_by')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('customer_shortlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['customer_id', 'listing_id', 'project_id'], 'shortlist_unique');
        });

        Schema::create('saved_searches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('query_text', 500)->nullable();
            $t->json('filters');
            $t->boolean('alerts')->default(true);
            $t->timestamp('last_notified_at')->nullable();
            $t->timestamps();
        });

        Schema::create('saved_search_matches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $t->string('item_key', 30); // L:12 or P:345
            $t->timestamp('notified_at')->useCurrent();
            $t->unique(['saved_search_id', 'item_key']);
        });

        Schema::create('ai_search_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('ip_hash', 64)->nullable();
            $t->string('query', 500);
            $t->json('filters')->nullable();
            $t->json('relaxed')->nullable();
            $t->unsignedInteger('result_count')->default(0);
            $t->boolean('used_ai')->default(false);
            $t->boolean('cached')->default(false);
            $t->timestamp('created_at')->useCurrent();
        });

        // 17.6 Leads
        Schema::create('lead_stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 40);
            $t->string('key', 30);
            $t->unsignedTinyInteger('sort')->default(0);
            $t->boolean('is_won')->default(false);
            $t->boolean('is_lost')->default(false);
            $t->string('color', 10)->default('gray');
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
        });

        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('lead_code', 40);
            $t->string('name', 100);
            $t->string('mobile', 15)->nullable();
            $t->string('email')->nullable();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('source', 30);
            $t->decimal('budget', 16, 2)->nullable();
            $t->string('preferred_location', 100)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('lead_stage_id')->nullable()->constrained()->nullOnDelete();
            $t->string('priority', 10)->default('warm');
            $t->timestamp('next_follow_up_at')->nullable();
            $t->json('tags')->nullable();
            $t->boolean('consent')->default(false);
            $t->string('lost_reason', 30)->nullable();
            $t->string('lost_notes', 500)->nullable();
            $t->timestamp('won_at')->nullable();
            $t->timestamp('lost_at')->nullable();
            $t->timestamp('first_contacted_at')->nullable();
            $t->timestamp('last_activity_at')->nullable();
            $t->foreignId('enquiry_id')->nullable(); // migrated from enquiries
            $t->foreignId('created_by')->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->softDeletes();
            $t->timestamps();
            $t->unique(['tenant_id', 'lead_code']);
            $t->index(['tenant_id', 'mobile']);
            $t->index(['tenant_id', 'email']);
            $t->index(['tenant_id', 'lead_stage_id']);
            $t->index(['tenant_id', 'assigned_to']);
            $t->index(['tenant_id', 'next_follow_up_at']);
        });

        Schema::create('lead_interests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('plot_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('lead_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->string('type', 30);
            $t->text('body')->nullable();
            $t->json('meta')->nullable();
            $t->string('actor_type', 10)->default('system');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('actor_name', 100)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['lead_id', 'created_at']);
        });

        Schema::create('lead_followups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20)->default('follow_up'); // follow_up | site_visit
            $t->timestamp('due_at');
            $t->string('notes', 500)->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('done_at')->nullable();
            $t->timestamp('reminded_at')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'done_at', 'due_at']);
        });

        Schema::create('whatsapp_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 60);
            $t->text('body');
            $t->timestamps();
        });

        // 17.9 / 17.10 Chatbots
        Schema::create('chat_conversations', function (Blueprint $t) {
            $t->id();
            $t->string('scope', 10); // market | workspace
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('visitor_token', 64)->nullable()->index();
            $t->string('title', 150)->nullable();
            $t->string('language', 5)->nullable();
            $t->unsignedInteger('message_count')->default(0);
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
            $t->index(['scope', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $t->string('role', 10); // user | assistant
            $t->text('content');
            $t->json('meta')->nullable(); // cards, tables, pending actions
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('testimonials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 100);
            $t->string('place', 100)->nullable();
            $t->text('body');
            $t->unsignedTinyInteger('rating')->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('moderated_by')->nullable();
            $t->timestamps();
        });

        // 17.11 Tenant settings
        Schema::table('tenant_settings', function (Blueprint $t) {
            $t->string('contact_name', 100)->nullable();
            $t->string('contact_mobile', 15)->nullable();
            $t->string('contact_whatsapp', 15)->nullable();
            $t->string('contact_email')->nullable();
            $t->boolean('allow_call')->default(true);
            $t->boolean('allow_whatsapp')->default(true);
            $t->boolean('allow_email')->default(true);
            $t->boolean('lead_auto_assign')->default(false);
            $t->unsignedBigInteger('lead_last_assigned_user_id')->nullable();
            $t->boolean('image_watermark')->default(true);
            $t->boolean('workspace_bot_enabled')->default(true);
        });

        // FULLTEXT search (MySQL 8 / MariaDB 10.6+, InnoDB)
        DB::statement('ALTER TABLE listings ADD FULLTEXT listings_fulltext (title, description, features_text)');
        DB::statement('ALTER TABLE projects ADD FULLTEXT projects_fulltext (name, promo_text)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE projects DROP INDEX projects_fulltext');
        Schema::table('tenant_settings', function (Blueprint $t) {
            $t->dropColumn(['contact_name', 'contact_mobile', 'contact_whatsapp', 'contact_email', 'allow_call', 'allow_whatsapp', 'allow_email',
                'lead_auto_assign', 'lead_last_assigned_user_id', 'image_watermark', 'workspace_bot_enabled']);
        });
        foreach (['testimonials', 'chat_messages', 'chat_conversations', 'whatsapp_templates', 'lead_followups', 'lead_activities', 'lead_interests', 'leads',
            'lead_stages', 'ai_search_logs', 'saved_search_matches', 'saved_searches', 'customer_shortlists', 'listing_reports', 'listing_contacts',
            'listing_features', 'feature_masters', 'listing_images', 'listings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('projects', function (Blueprint $t) {
            $t->dropIndex(['status', 'launch_mode']);
            $t->dropColumn(['launch_mode', 'approval_authority', 'approval_number', 'village', 'lat', 'lng', 'youtube_url', 'brochure_file_id']);
        });
    }
};
