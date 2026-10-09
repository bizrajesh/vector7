<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Broker;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Holiday;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\PromoCode;
use App\Models\Registration;
use App\Models\RegistrationParty;
use App\Models\RegistrationWitness;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Service;
use App\Models\SocialPost;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FileStore;
use App\Services\IdGenerator;
use App\Services\PlotImporter;
use App\Services\ProjectService;
use App\Services\SalesService;
use App\Services\SeoService;
use App\Services\TenantProvisioner;
use App\Services\TicketService;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Demo tenant "Demo Promoters, Thanjavur" with a user for every role, a launched Village project
 * (estimate, 100% progress, 40 plots in mixed statuses), an in-progress project, bookings, sales,
 * payments, registrations, expenses, enquiries and tickets — so every dashboard shows data.
 *
 * Demo logins (password Demo@2026 for all): admin@demo.vector7.in, manager@…, sales@…, accounts@…, support@…
 * Demo buyer: buyer1@demo.vector7.in / Demo@2026
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo@2026';

    private Tenant $tenant;

    private array $users = [];

    public function run(): void
    {
        if (Tenant::where('slug', 'demo-promoters')->exists()) {
            $this->command?->info('Demo tenant already exists — skipped.');

            return;
        }
        Mail::fake(); // no emails while seeding demo data
        $realNow = Carbon::now();

        try {
            Carbon::setTestNow($realNow->copy()->subMonths(7)->setTime(10, 0));
            $this->tenantAndUsers();
            app(Tenancy::class)->run($this->tenant, function () use ($realNow) {
                $launched = $this->launchedProject($realNow);
                $this->inProgressProject($realNow);
                $this->salesActivity($launched, $realNow);
                Carbon::setTestNow($realNow);
                $this->crm($launched);
            });
        } finally {
            Carbon::setTestNow();
        }
        app(Tenancy::class)->withoutScope(fn () => SeoService::buildAll());
        $this->command?->info('Demo tenant created. Sign in at /workspace/login with admin@demo.vector7.in / '.self::PASSWORD);
    }

    private function tenantAndUsers(): void
    {
        $plan = Plan::where('slug', 'professional')->first() ?? Plan::orderBy('sort')->first();
        [$tenant, $admin] = TenantProvisioner::create([
            'name' => 'Demo Promoters', 'email' => 'admin@demo.vector7.in', 'mobile' => '9840012345',
            'password' => self::PASSWORD, 'city' => 'Thanjavur', 'district' => 'Thanjavur', 'admin_name' => 'Arun Kumar (Admin)',
        ]);
        $tenant->update([
            'slug' => 'demo-promoters', 'address_line1' => '12, South Main Street', 'address_line2' => 'Near Big Temple', 'village' => 'Vallam',
            'pin' => '613001', 'contact' => '9840012345', 'support_email' => 'support@demo.vector7.in', 'website' => 'https://www.example.com',
            'onboarding_step' => 9,
        ]);
        Subscription::where('tenant_id', $tenant->id)->update(['plan_id' => $plan->id, 'status' => 'active', 'starts_on' => today(), 'ends_on' => today()->addYear()->addMonths(7)]);
        TenantProvisioner::copyAppMasters($tenant);
        $this->tenant = $tenant->fresh();
        $this->users['admin'] = $admin;

        app(Tenancy::class)->run($this->tenant, function () {
            foreach ([['manager', 'tenant_manager', 'Meena Rajan (Manager)', ['Management']], ['sales', 'tenant_sales', 'Suresh Babu (Sales)', ['Sales Team']],
                ['accounts', 'tenant_account', 'Lakshmi Priya (Accounts)', ['Accounts']], ['support', 'support', 'Karthik S (Support)', []]] as [$key, $role, $name, $groups]) {
                $u = User::create([
                    'tenant_id' => $this->tenant->id, 'role_id' => Role::systemRole($role, $this->tenant->id)->id, 'user_code' => IdGenerator::next($this->tenant->id, 'user'),
                    'name' => $name, 'email' => "$key@demo.vector7.in", 'mobile' => '98400'.random_int(10000, 99999), 'password' => self::PASSWORD,
                ]);
                foreach ($groups as $g) {
                    \App\Models\NotificationGroup::where('name', $g)->first()?->users()->attach($u->id);
                }
                $this->users[$key] = $u;
            }
            foreach (['2026-01-15' => 'Pongal', '2026-01-26' => 'Republic Day', '2026-08-15' => 'Independence Day', '2026-10-20' => 'Deepavali', '2026-12-25' => 'Christmas'] as $d => $n) {
                Holiday::firstOrCreate(['tenant_id' => $this->tenant->id, 'date' => $d], ['name' => $n]);
            }
        });
    }

    private function newProject(array $attrs): Project
    {
        $acres = $attrs['size_acres'];

        return Project::create($attrs + [
            'tenant_id' => $this->tenant->id, 'project_code' => IdGenerator::next($this->tenant->id, 'project'), 'approval_type' => 'Village',
            'district' => 'Thanjavur', 'state' => 'Tamil Nadu', 'email' => 'sales@demo.vector7.in', 'contact' => '9840012345', 'owner_name' => 'R. Subramanian',
            'land_classification' => 'Dry (Punjai)', 'guideline_rate' => 350, 'guideline_value' => round($acres * Project::SQFT_PER_ACRE * 350, 2),
            'market_rate' => 650, 'market_value' => round($acres * Project::SQFT_PER_ACRE * 650, 2), 'manager_id' => $this->users['manager']->id,
            'created_by' => $this->users['admin']->id, 'status' => 'draft',
        ]);
    }

    private function estimate(Project $p, string $tier): void
    {
        $sqft = $p->totalSqft();
        $qty = ['sq m' => round($sqft * 0.0929 * 0.30), 'running m' => round(sqrt($sqft * 0.0929) * 4), 'nos' => 12, 'no' => 12, 'lump sum' => 1, 'sq ft' => round($sqft * 0.10), 'kL' => 50, 'set' => 1];
        $facility = [];
        foreach (ProjectService::facilitiesFor($tier) as $f) {
            $facility[$f->id] = ['include' => 1, 'qty' => $qty[$f->unit] ?? 10, 'cost' => $f->costFor($p->approval_type)];
        }
        $stage = [];
        foreach (ProjectService::stageMasters($p) as $s) {
            $stage[$s->stage_no] = ['include' => 1, 'name' => $s->name, 'cost' => [1 => 60000, 2 => 25000, 3 => 180000, 4 => 90000, 5 => 75000, 6 => 40000, 7 => 30000, 8 => 450000, 9 => 120000, 10 => 0, 11 => 25000, 12 => 85000, 13 => 150000][$s->stage_no] ?? 50000];
        }
        ProjectService::saveEstimate($p, ['tier' => $tier, 'facility' => $facility, 'stage' => $stage,
            'other' => [['description' => 'Site office, signage and launch event', 'qty' => 1, 'cost' => 250000]]], $this->tenant);
        ProjectService::decide($p->fresh(), 'go', 'Clear title, dry land, good road access. Go.', $this->users['admin']->id);
    }

    /** Complete sub-tasks in dependency order (documents marked as received) up to $limit tasks, spreading actual dates. */
    private function completeTasks(Project $p, int $limit, Carbon $from): void
    {
        $done = 0;
        $tasks = ProjectSubtask::where('project_id', $p->id)->orderBy('planned_start')->orderBy('id')->get();
        foreach ($tasks as $t) {
            if ($done >= $limit) {
                break;
            }
            $cost = round((float) $t->budget * (0.85 + mt_rand(0, 25) / 100), 2);
            $t->update(['status' => 'done', 'actual_start' => $t->planned_start, 'actual_end' => $t->planned_end, 'actual_cost' => $cost, 'notes' => 'Completed']);
            $done++;
        }
        $next = $tasks->firstWhere('status', 'not_started');
        if ($next && $done < $tasks->count()) {
            $next->update(['status' => 'in_progress', 'actual_start' => $next->planned_start]);
        }
        $blocked = $tasks->where('status', 'not_started')->skip(1)->first();
        if ($blocked && $limit < $tasks->count()) {
            $blocked->update(['status' => 'blocked', 'notes' => 'Waiting for WRD NOC inspection date']);
        }
        ProjectService::recalc($p->fresh());
    }

    private function launchedProject(Carbon $realNow): Project
    {
        $p = $this->newProject([
            'name' => 'Vallam Green Meadows', 'location' => 'Vallam', 'address' => 'Thanjavur–Pudukkottai Road, Vallam', 'pin' => '613403', 'size_acres' => 2.2,
            'survey_numbers' => '112/1, 112/2, 113', 'patta_numbers' => '845, 846',
        ]);
        $this->estimate($p, 'Standard');
        ProjectService::start($p->fresh(), today()->previousWeekday());
        $this->completeTasks($p->fresh(), 10_000, today());
        $p = $p->fresh();
        $p->update(['status' => 'ready_to_launch']);

        // Layout picture drawn with GD + 40 plots over it.
        [$png, $w, $h, $polys] = $this->drawLayout();
        $file = FileStore::storeContents($png, 'vallam-green-meadows-layout.png', 'image/png', 'layout', $this->tenant->id, $p);
        $p->update([
            'layout_file_id' => $file->id, 'layout_width' => $w, 'layout_height' => $h, 'is_featured' => true,
            'promo_text' => "Vallam Green Meadows is a DTCP-approved residential layout on the Thanjavur–Pudukkottai Road, 10 minutes from the Big Temple.\n\n"
                ."• 40 plots from 1,080 to 1,650 sq ft, East and North facing\n• 30 ft and 23 ft black-top roads, storm-water drains and street lights\n• Children's park and OSR land handed over to the panchayat\n• Bank loans available from leading banks",
            'facilities' => ['BT roads', 'Storm-water drains', 'Street lights', 'Overhead water tank', 'Children\'s park', 'Compound wall & arch', 'EB connection'],
            'map_url' => 'https://maps.google.com/?q=Vallam,Thanjavur',
        ]);
        $rows = [];
        $offerTill = $realNow->copy()->addDays(20)->format('d-m-Y');
        foreach ($polys as $i => [$poly, $row, $col]) {
            $n = $i + 1;
            $corner = in_array($col, [0, 9], true);
            $width = $corner ? 36 : 30;
            $length = in_array($row, [0, 3], true) ? 45 : 40;
            $size = $width * $length;
            $facing = $row < 2 ? ($row === 0 ? 'North' : 'South') : ($row === 2 ? 'North' : 'South');
            $rate = 1450 + ($corner ? 150 : 0) + ($row === 2 ? 50 : 0);
            $offer = in_array($n, [3, 4, 5, 14, 15, 23, 24, 33], true);
            $rows[] = [
                'plot_no' => (string) $n, 'patta_number' => '845/'.$n, 'size_sqft' => (string) $size, 'length_ft' => (string) $length, 'width_ft' => (string) $width,
                'facing' => $facing, 'east_boundary' => $col === 9 ? '23 ft road' : 'Plot '.($n + 1), 'west_boundary' => $col === 0 ? '23 ft road' : 'Plot '.($n - 1),
                'north_boundary' => $row === 0 ? 'Patta land S.No. 111' : ($row === 2 ? '30 ft road' : 'Plot '.($n - 10)),
                'south_boundary' => $row === 3 ? 'OSR / park' : ($row === 1 ? '30 ft road' : 'Plot '.($n + 10)),
                'road_width_ft' => in_array($row, [1, 2], true) ? '30' : '23', 'corner_plot' => $corner ? 'Y' : 'N', 'rate_per_sqft' => (string) $rate,
                'offer' => $offer ? 'Deepavali offer – ₹100/sq ft off' : '', 'offer_rate_per_sqft' => $offer ? (string) ($rate - 100) : '', 'offer_valid_till' => $offer ? $offerTill : '',
                'status' => in_array($n, [20, 40], true) ? 'Blocked' : 'Available', 'map_polygon' => $poly,
            ];
        }
        [$errors, , $clean] = PlotImporter::validate($p, $rows);
        if ($errors) {
            throw new \RuntimeException('Demo plot import failed: '.json_encode($errors));
        }
        PlotImporter::import($p, $clean, $this->users['manager']->id);
        $p->update(['launched_at' => now()]);
        ProjectService::setStatus($p->fresh(), 'launched', 'Published on the vector7 marketplace.');
        if ($p->estimate) {
            ProjectService::recalcEstimate($p->estimate()->with('lines')->first(), $p->fresh());
        }

        return $p->fresh();
    }

    private function inProgressProject(Carbon $realNow): void
    {
        $p = $this->newProject([
            'name' => 'Kumbakonam Temple View Nagar', 'location' => 'Kumbakonam', 'district' => 'Thanjavur', 'address' => 'Ayikulam Road, Kumbakonam', 'pin' => '612001',
            'size_acres' => 4.5, 'survey_numbers' => '204/3, 205', 'patta_numbers' => '1120',
        ]);
        Carbon::setTestNow($realNow->copy()->subMonths(4));
        $this->estimate($p, 'Basic');
        ProjectService::start($p->fresh(), today()->previousWeekday());
        Carbon::setTestNow($realNow->copy()->subDays(3));
        $this->completeTasks($p->fresh(), 22, today());

        // A draft project for the pipeline.
        $this->newProject(['name' => 'Orathanadu Garden City', 'location' => 'Orathanadu', 'size_acres' => 2.75, 'survey_numbers' => '77/2', 'patta_numbers' => '310']);
    }

    /** Draw a simple layout plan (4 rows × 10 plots, roads and a park) and return PNG + plot polygons in %. */
    private function drawLayout(): array
    {
        $W = 1200;
        $H = 820;
        $im = imagecreatetruecolor($W, $H);
        $bg = imagecolorallocate($im, 246, 244, 236);
        $road = imagecolorallocate($im, 200, 200, 196);
        $line = imagecolorallocate($im, 11, 27, 51);
        $park = imagecolorallocate($im, 176, 218, 214);
        $text = imagecolorallocate($im, 11, 27, 51);
        imagefill($im, 0, 0, $bg);
        imagefilledrectangle($im, 0, 0, 40, $H, $road);
        imagefilledrectangle($im, $W - 40, 0, $W, $H, $road);
        imagefilledrectangle($im, 40, 370, $W - 40, 430, $road);
        imagefilledrectangle($im, 40, 730, $W - 40, $H, $park);
        imagestring($im, 5, 520, 392, '30 FT ROAD', $text);
        imagestring($im, 5, 480, 765, 'OSR / CHILDREN\'S PARK', $text);
        imagestring($im, 4, 50, 12, 'VALLAM GREEN MEADOWS - LAYOUT PLAN (NOT TO SCALE)', $text);
        $rowsY = [[40, 205], [205, 370], [430, 580], [580, 730]];
        $colW = ($W - 80) / 10;
        $polys = [];
        foreach ($rowsY as $r => [$y1, $y2]) {
            for ($c = 0; $c < 10; $c++) {
                $x1 = (int) round(40 + $c * $colW);
                $x2 = (int) round(40 + ($c + 1) * $colW);
                imagerectangle($im, $x1, $y1, $x2, $y2, $line);
                $n = $r * 10 + $c + 1;
                imagestring($im, 5, (int) (($x1 + $x2) / 2 - 8), (int) (($y1 + $y2) / 2 - 8), (string) $n, $text);
                $polys[] = [[[round($x1 / $W * 100, 3), round($y1 / $H * 100, 3)], [round($x2 / $W * 100, 3), round($y1 / $H * 100, 3)],
                    [round($x2 / $W * 100, 3), round($y2 / $H * 100, 3)], [round($x1 / $W * 100, 3), round($y2 / $H * 100, 3)]], $r, $c];
            }
        }
        ob_start();
        imagepng($im, null, 6);
        $png = ob_get_clean();
        imagedestroy($im);

        return [$png, $W, $H, $polys];
    }

    private function customer(int $i, string $name, string $city): Customer
    {
        return Customer::firstOrCreate(['email' => "buyer$i@demo.vector7.in"], [
            'name' => $name, 'mobile' => '9'.str_pad((string) (876500000 + $i * 1117), 9, '0', STR_PAD_LEFT), 'password' => self::PASSWORD,
            'city' => $city, 'district' => 'Thanjavur', 'state' => 'Tamil Nadu', 'created_at' => now(),
        ]);
    }

    private function pay(Sale $sale, float $amount, string $mode = 'bank_transfer'): void
    {
        SalesService::recordPayment($sale->fresh(), ['amount' => $amount, 'mode' => $mode, 'reference_no' => 'UTR'.random_int(100000, 999999), 'paid_on' => today()->toDateString()], $this->users['sales']->id);
    }

    private function salesActivity(Project $p, Carbon $realNow): void
    {
        $sales = $this->users['sales']->id;
        $plot = fn (string $no) => Plot::where('project_id', $p->id)->where('plot_no', $no)->first();
        $names = [[1, 'Ramesh Krishnan', 'Thanjavur'], [2, 'Priya Venkatesh', 'Kumbakonam'], [3, 'Mohamed Ismail', 'Thanjavur'], [4, 'Anitha Selvam', 'Trichy'],
            [5, 'Vignesh Murugan', 'Chennai'], [6, 'Deepa Raghavan', 'Thanjavur'], [7, 'Senthil Kumar', 'Pattukkottai'], [8, 'Kavitha Natarajan', 'Bengaluru'],
            [9, 'Balaji Sundaram', 'Thanjavur'], [10, 'Revathi Ganesan', 'Madurai'], [11, 'Arjun Prakash', 'Thanjavur'], [12, 'Sangeetha Mani', 'Chennai'],
            [13, 'Rajkumar Pillai', 'Mannargudi'], [14, 'Nithya Anand', 'Thanjavur'], [15, 'Gopinath R', 'Kumbakonam']];
        $c = [];
        foreach ($names as [$i, $n, $city]) {
            $c[$i] = $this->customer($i, $n, $city);
        }
        $broker = Broker::create(['tenant_id' => $this->tenant->id, 'name' => 'Sri Lakshmi Realtors', 'mobile' => '9443012345', 'commission_pct' => 0.5]);

        // Fully paid sales spread over the last 6 months → ROR, and 4 of them go through registration.
        $full = [[1, '1', 175], [2, '2', 150], [3, '11', 120], [4, '12', 95], [5, '21', 70], [6, '31', 45], [7, '10', 30]];
        $ror = [];
        foreach ($full as $k => [$ci, $no, $daysAgo]) {
            Carbon::setTestNow($realNow->copy()->subDays($daysAgo)->setTime(11, 0));
            $pl = $plot($no);
            if ($k % 2 === 0) {
                SalesService::book($pl, $c[$ci], 'marketplace', null, '127.0.0.1');
                Carbon::setTestNow(now()->addDays(2));
            }
            $price = SalesService::pricing($pl->fresh(), today(), null)['net_price'];
            $sale = SalesService::initiateSale($pl->fresh(), $c[$ci], ['amount' => round($price * 0.30), 'mode' => 'upi', 'reference_no' => 'UPI'.random_int(10000, 99999), 'paid_on' => today()->toDateString()], $k === 2 ? $broker->id : null, null, '127.0.0.1', $sales);
            Carbon::setTestNow(now()->addDays(6));
            $this->pay($sale, round($price * 0.60));
            Carbon::setTestNow(now()->addDays(5));
            $this->pay($sale, (float) $sale->fresh()->due_amount, 'cheque');
            $ror[] = $sale->fresh();
        }

        // Registration: 2 sold, 1 ROR-Completed, 1 ROR-Init; the rest stay ROR.
        foreach (array_slice($ror, 0, 4) as $k => $sale) {
            Carbon::setTestNow($realNow->copy()->subDays(max(5, 160 - $k * 40)));
            $reg = Registration::create([
                'tenant_id' => $this->tenant->id, 'project_id' => $sale->project_id, 'plot_id' => $sale->plot_id, 'sale_id' => $sale->id, 'customer_id' => $sale->customer_id,
                'status' => 'draft', 'registration_date' => today()->addDays(3), 'sro_id' => \App\Http\Controllers\Tenant\RegistrationController::pickSro($p)?->id,
                'pr_numbers' => 'PR-'.random_int(1000, 9999), 'document_writer' => 'M. Ganesan, Document Writer', 'disclaimer_accepted_at' => now(), 'created_by' => $sales,
                'checklist' => collect(\App\Http\Controllers\Tenant\RegistrationController::checklistItems())->map(fn ($i) => ['done' => true] + $i)->all(),
            ]);
            RegistrationParty::create(['tenant_id' => $this->tenant->id, 'registration_id' => $reg->id, 'party_type' => 'seller', 'name' => 'R. Subramanian', 'relation_name' => 'S/o Ramasamy', 'age' => 58, 'address' => '4, East Street, Vallam', 'mobile' => '9840012345', 'pan_encrypted' => Crypt::encryptString('ABCPS1234K')]);
            RegistrationParty::create(['tenant_id' => $this->tenant->id, 'registration_id' => $reg->id, 'party_type' => 'buyer', 'name' => $sale->customer->name, 'relation_name' => 'S/o / D/o —', 'age' => 40, 'address' => $sale->customer->city, 'mobile' => $sale->customer->mobile, 'pan_encrypted' => Crypt::encryptString('BQRPK'.random_int(1000, 9999).'L')]);
            foreach ([['V. Murali', 'S/o Veerappan'], ['K. Saravanan', 'S/o Kandasamy']] as [$wn, $rel]) {
                RegistrationWitness::create(['tenant_id' => $this->tenant->id, 'registration_id' => $reg->id, 'name' => $wn, 'relation_name' => $rel, 'age' => 45, 'address' => 'Thanjavur']);
            }
            $plotModel = Plot::find($sale->plot_id);
            Carbon::setTestNow(now()->addDays(1));
            $reg->update(['status' => 'ror_init', 'submitted_at' => now()]);
            $plotModel->moveTo('ror_init', 'Submitted to document writer');
            if ($k <= 2) {
                Carbon::setTestNow(now()->addDays(3));
                $reg->update(['status' => 'ror_completed', 'completed_at' => now(), 'registered_doc_no' => (2026 - 0).'/'.random_int(1000, 9999), 'registered_doc_date' => today()]);
                $plotModel->moveTo('ror_completed', 'Registered');
            }
            if ($k <= 1) {
                Carbon::setTestNow(now()->addDays(2));
                $reg->update(['status' => 'sold', 'sold_at' => now(), 'physical_file_no' => 'VGM-F-'.str_pad((string) ($k + 1), 3, '0', STR_PAD_LEFT)]);
                $plotModel->moveTo('sold', 'Physical file handed over');
                $sale->update(['status' => 'completed', 'active_plot_lock' => null]);
            }
        }

        // Sales in progress (Sale Init) — one past its window with an overdue instalment.
        foreach ([[8, '22', 24, 0.30], [9, '13', 9, 0.30], [10, '32', 6, 0.50], [11, '33', 3, 0.30]] as [$ci, $no, $daysAgo, $pct]) {
            Carbon::setTestNow($realNow->copy()->subDays($daysAgo)->setTime(12, 0));
            $pl = $plot($no);
            $price = SalesService::pricing($pl, today(), null)['net_price'];
            SalesService::initiateSale($pl, $c[$ci], ['amount' => round($price * $pct), 'mode' => 'bank_transfer', 'reference_no' => 'NEFT'.random_int(10000, 99999), 'paid_on' => today()->toDateString()], null, null, '127.0.0.1', $sales);
        }

        // Active bookings (one expiring tomorrow) and one expired booking.
        $promo = PromoCode::create(['tenant_id' => $this->tenant->id, 'code' => 'DIWALI26', 'discount_type' => 'flat', 'value' => 25000, 'valid_from' => $realNow->copy()->subDays(30), 'valid_to' => $realNow->copy()->addDays(30), 'max_uses' => 20, 'project_ids' => [$p->id], 'created_by' => $this->users['manager']->id]);
        foreach ([[12, '4', 6, null], [13, '14', 3, 'DIWALI26'], [14, '23', 1, null], [15, '5', 0, null]] as [$ci, $no, $daysAgo, $code]) {
            Carbon::setTestNow($realNow->copy()->subWeekdays($daysAgo)->setTime(15, 0));
            SalesService::book($plot($no), $c[$ci], $ci % 2 ? 'marketplace' : 'workspace', $code, '127.0.0.1', $ci % 2 ? null : $sales);
        }
        Carbon::setTestNow($realNow->copy()->subDays(20));
        $old = SalesService::book($plot('24'), $c[2], 'marketplace', null, '127.0.0.1');
        SalesService::releaseBooking($old, 'expired', 'First instalment not paid within the booking validity');

        // Expenses against the launched and in-progress projects over 7 months.
        Carbon::setTestNow($realNow);
        $cats = ExpenseCategory::pluck('id', 'name');
        $inProgress = Project::where('status', 'in_progress')->first();
        $stages = fn (Project $pr) => $pr->stages()->pluck('id', 'stage_no');
        foreach ([[$p, 'Approval fees', 'DTCP scrutiny and development charges', 'DTCP Thanjavur', 485000, 200, 8],
            [$p, 'Survey & legal', 'Title opinion and boundary survey', 'Adv. N. Rajagopal', 62000, 210, 1],
            [$p, 'Civil works', 'BT road laying — phase 1', 'Sri Murugan Constructions', 1240000, 120, 10],
            [$p, 'Civil works', 'Storm-water drains', 'Sri Murugan Constructions', 640000, 95, 10],
            [$p, 'Electrical', 'Street lights and EB line extension', 'TANGEDCO / Sakthi Electricals', 410000, 80, 10],
            [$p, 'Marketing', 'Launch event, hoardings and digital ads', 'Thanjai Ads', 185000, 40, 13],
            [$inProgress, 'Survey & legal', 'EC 30 years and legal opinion', 'Adv. N. Rajagopal', 48000, 100, 1],
            [$inProgress, 'Approval fees', 'CLU conversion charges', 'DTCP Thanjavur', 320000, 45, 3],
            [$inProgress, 'Survey & legal', 'Topographic survey', 'Geo Surveys', 85000, 20, 4],
            [null, 'Office & admin', 'Office rent — October', 'Owner', 35000, 5, null]] as [$pr, $cat, $desc, $vendor, $amt, $daysAgo, $stageNo]) {
            Expense::create([
                'tenant_id' => $this->tenant->id, 'transaction_no' => IdGenerator::next($this->tenant->id, 'expense'), 'project_id' => $pr?->id,
                'project_stage_id' => $pr && $stageNo ? ($stages($pr)[$stageNo] ?? null) : null, 'expense_category_id' => $cats[$cat] ?? null,
                'vendor' => $vendor, 'description' => $desc, 'amount' => $amt, 'spent_on' => $realNow->copy()->subDays($daysAgo), 'mode' => 'bank_transfer',
                'reference_no' => 'NEFT'.random_int(100000, 999999), 'created_by' => $this->users['accounts']->id,
            ]);
        }
        ProjectService::recalc($p->fresh());
        if ($inProgress) {
            ProjectService::recalc($inProgress->fresh());
        }
    }

    private function crm(Project $p): void
    {
        $avail = Plot::where('project_id', $p->id)->where('status', 'available')->take(3)->get();
        foreach ([['Hari Prasad', 'hari@example.com', '9876501234', 'Is a bank loan available for plot 6? I would like to visit on Sunday.', 'new', 0],
            ['Shalini M', 'shalini@example.com', '9876502345', 'Please share the DTCP approval copy and the final price for a corner plot.', 'contacted', 1],
            ['Venkat Raman', 'venkat@example.com', '9876503456', 'Looking for an east-facing plot under ₹18 lakh.', 'new', 2]] as $i => [$n, $e, $m, $msg, $st, $pi]) {
            Enquiry::create(['tenant_id' => $this->tenant->id, 'project_id' => $p->id, 'plot_id' => $avail[$pi]->id ?? null, 'name' => $n, 'email' => $e, 'mobile' => $m,
                'message' => $msg, 'status' => $st, 'assigned_to' => $st === 'contacted' ? $this->users['sales']->id : null, 'follow_up_on' => today()->addDays($i - 1)]);
        }
        // App-level records are created outside the tenant context (the tenant trait would stamp the tenant on them).
        app(Tenancy::class)->run(null, fn () => Enquiry::create(['tenant_id' => null, 'assigned_team' => 'app', 'name' => 'Sathish K', 'email' => 'sathish@example.com', 'mobile' => '9876504567',
            'message' => 'I am a layout promoter in Trichy. How do I list my project on vector7?', 'source' => 'support', 'status' => 'new']));

        $ticket = TicketService::open($this->users['admin'], ['category' => 'technical', 'priority' => 'high', 'subject' => 'Plot map not showing on mobile', 'body' => 'The plot map on Vallam Green Meadows is cut off on my phone. Please check.'], null, $this->tenant->id);
        TicketService::addMessage($ticket, User::withoutGlobalScopes()->whereNull('tenant_id')->first(), 'Thanks — we are checking. Could you tell us your phone model?', notify: false);
        $buyer = Customer::where('email', 'buyer1@demo.vector7.in')->first();
        app(Tenancy::class)->run(null, fn () => TicketService::open($buyer, ['category' => 'booking', 'priority' => 'medium', 'subject' => 'Copy of receipt', 'body' => 'Please resend my last payment receipt.']));
        if ($svc = Service::where('available_to', '!=', 'customer')->first()) {
            TicketService::requestService($svc, $this->users['manager'], 'Need a title opinion for 4.5 acres at Kumbakonam (S.No. 204/3, 205).', $this->tenant->id);
        }

        foreach (['instagram', 'facebook'] as $platform) {
            SocialPost::create(\App\Services\SocialService::write($p->load('plots'), $platform, PromoCode::where('code', 'DIWALI26')->first(), $this->tenant)
                + ['tenant_id' => $this->tenant->id, 'project_id' => $p->id, 'platform' => $platform, 'status' => 'draft', 'created_by' => $this->users['manager']->id]);
        }
    }
}
