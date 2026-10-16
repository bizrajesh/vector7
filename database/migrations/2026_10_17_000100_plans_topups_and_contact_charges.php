<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plans v2 (no data is removed):
 *  - plan limits for plots and users (all roles); plans renamed Starter / Growth / Enterprise
 *  - top-up packs designed by the App team, tenant top-ups (bought or granted), billed on subscription invoices
 *  - paid contact view for buyers (contact_unlocks), money goes to the App owner
 *  - call-back requests: preferred contact mode and time on leads
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topup_packs', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40);             // storage_mb | projects | plots | users | listings
            $t->string('name', 80);
            $t->unsignedInteger('units');       // e.g. 5120 (MB), 1 (user), 100 (plots)
            $t->decimal('price', 12, 2);        // per month, excl. GST
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('tenant_topups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('topup_pack_id')->nullable()->constrained()->nullOnDelete();
            $t->string('key', 40);
            $t->unsignedInteger('quantity')->default(1);
            $t->unsignedInteger('units');       // total units added (pack units × quantity)
            $t->unsignedSmallInteger('months')->default(1);
            $t->string('source', 20)->default('purchase'); // purchase | grant
            $t->string('status', 20)->default('pending');  // pending | active | cancelled
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status', 'key']);
        });

        Schema::table('subscription_invoices', function (Blueprint $t) {
            $t->string('kind', 20)->default('plan')->after('plan_id');
            $t->foreignId('tenant_topup_id')->nullable()->after('kind')->constrained()->nullOnDelete();
            $t->string('description', 255)->nullable()->after('tenant_topup_id');
        });
        Schema::table('subscription_invoices', function (Blueprint $t) {
            $t->foreignId('plan_id')->nullable()->change();
        });

        Schema::create('contact_unlocks', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete(); // owner of the property (reporting only)
            $t->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title', 200)->nullable();
            $t->decimal('amount', 10, 2);
            $t->decimal('tax', 10, 2)->default(0);
            $t->decimal('total', 10, 2);
            $t->string('status', 20)->default('due'); // due | paid | cancelled
            $t->string('method', 20)->nullable();
            $t->string('gateway_order_id')->nullable()->index();
            $t->string('gateway_payment_id')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['customer_id', 'status']);
            $t->index(['status', 'paid_at']);
        });

        Schema::table('leads', function (Blueprint $t) {
            $t->string('preferred_mode', 20)->nullable()->after('preferred_location');  // call | whatsapp | email
            $t->string('preferred_time', 40)->nullable()->after('preferred_mode');      // slot label, e.g. "Evening (4–8 pm)"
        });

        $this->seedData();
    }

    private function seedData(): void
    {
        $now = now();
        // Professional → Growth (only the name / slug the original seed used; edited plans are left alone).
        if (! DB::table('plans')->where('slug', 'growth')->exists()) {
            DB::table('plans')->where('slug', 'professional')->update(['name' => 'Growth', 'slug' => 'growth', 'updated_at' => $now]);
        }
        $defaults = [
            'starter' => ['plots' => 200, 'users' => 5],
            'growth' => ['plots' => 2000, 'users' => 20],
            'enterprise' => ['plots' => -1, 'users' => -1],
        ];
        foreach (DB::table('plans')->get() as $plan) {
            $roleLimits = DB::table('plan_limits')->where('plan_id', $plan->id)->where('key', 'like', 'users\_%')->pluck('value');
            $users = $roleLimits->contains(fn ($v) => (int) $v < 0) ? -1 : (int) $roleLimits->sum();
            $limits = $defaults[$plan->slug] ?? ['plots' => 500, 'users' => $users ?: 5];
            foreach ($limits as $key => $value) {
                DB::table('plan_limits')->insertOrIgnore(['plan_id' => $plan->id, 'key' => $key, 'value' => $value]);
            }
            // One total user limit now; per-role caps stay available but default to "no cap".
            DB::table('plan_limits')->where('plan_id', $plan->id)->where('key', 'like', 'users\_%')->update(['value' => -1]);
        }
        // Starter: 5 free active listings by default (only when still at the original seed value).
        DB::table('plan_limits')->whereIn('plan_id', DB::table('plans')->where('slug', 'starter')->pluck('id'))
            ->where('key', 'listings')->where('value', 10)->update(['value' => 5]);

        $packs = [
            ['users', '1 extra user', 1, 199],
            ['projects', '1 extra project', 1, 499],
            ['plots', '100 extra plots', 100, 299],
            ['storage_mb', '5 GB extra storage', 5120, 249],
            ['listings', '5 extra active listings', 5, 199],
        ];
        if (! DB::table('topup_packs')->exists()) {
            foreach ($packs as $i => [$key, $name, $units, $price]) {
                DB::table('topup_packs')->insert(['key' => $key, 'name' => $name, 'units' => $units, 'price' => $price, 'is_active' => true, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['preferred_mode', 'preferred_time']));
        Schema::dropIfExists('contact_unlocks');
        Schema::table('subscription_invoices', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tenant_topup_id');
            $t->dropColumn(['kind', 'description']);
        });
        Schema::dropIfExists('tenant_topups');
        Schema::dropIfExists('topup_packs');
        DB::table('plan_limits')->whereIn('key', ['plots', 'users'])->delete();
    }
};
