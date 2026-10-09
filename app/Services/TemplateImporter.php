<?php

namespace App\Services;

use App\Models\DocumentChecklist;
use App\Models\FacilityMaster;
use App\Models\InstalmentPlan;
use App\Models\NotificationGroup;
use App\Models\Role;
use App\Models\StageMaster;
use App\Models\SubtaskMaster;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\Passwords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Vector7_Tenant_PreConfig_Template.xlsx importer/validator.
 * Maps by sheet name and header name (never by column position).
 * All validations run first; nothing is imported if any error (single DB transaction).
 */
class TemplateImporter
{
    public const SHEETS = [
        'Tenant Profile' => ['Sino', 'Section', 'Field', 'Value'],
        'Additional Settings' => ['Sino', 'Type', 'Item', 'Unit', 'Unit Value', 'Due Working Days'],
        'Notification Groups' => ['Sino', 'Group Name', 'Type', 'Description', 'Active'],
        'Users' => ['Sino', 'Full Name', 'Email', 'Mobile', 'Role', 'Notification Groups', 'Active'],
        'Approval Stages' => ['Sino', 'Area Type', 'Stage No', 'Stage', 'Task ID', 'Short Name', 'Subtask', 'Depends On', 'Responsible / Authority', 'Output / Document', 'Default Duration (Days)', 'Required Doc IDs', 'Active'],
        'Facility Master' => ['Sino', 'Facility ID', 'Category', 'Facility', 'Specification', 'Unit', 'Basic', 'Standard', 'Premium', 'Statutory', 'Village Unit Cost (₹)', 'Town Unit Cost (₹)', 'City Unit Cost (₹)', 'Active'],
        'Document Checklist' => ['Sino', 'Checklist', 'Doc ID', 'Applies To', 'Document / Item', 'Issued By / Source', 'Submission Format', 'Mandatory', 'Condition / Remarks', 'Stage No', 'Stage', 'Village Task ID', 'Town Task ID', 'City Task ID', 'Active'],
    ];

    /** Sheets required for an App-level (masters only) import. */
    public const MASTER_SHEETS = ['Approval Stages', 'Facility Master', 'Document Checklist'];

    public const ROLE_MAP = ['admin' => 'tenant_admin', 'manager' => 'tenant_manager', 'sales' => 'tenant_sales', 'accountant' => 'tenant_account', 'account' => 'tenant_account', 'support' => 'support'];

    public const AREA_TYPES = ['Village', 'Town', 'City'];

    public const STATES = ['Tamil Nadu', 'Kerala', 'Karnataka', 'Andhra Pradesh', 'Telangana', 'Puducherry'];

    public array $data = [];

    /** @var array<int, array{sheet:string,row:int|string,message:string}> */
    public array $errors = [];

    public array $warnings = [];

    public static function fromFile(string $path): self
    {
        $self = new self;
        $self->read($path);

        return $self;
    }

    private function read(string $path): void
    {
        $reader = new Reader;
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            $name = trim($sheet->getName());
            if (! isset(self::SHEETS[$name])) {
                continue;
            }
            $headers = null;
            $rows = [];
            $rowNo = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $rowNo++;
                $cells = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_string($v) ? trim($v) : $v), $row->toArray());
                if ($headers === null) {
                    // header row = first row that contains the sheet's key headers
                    $norm = array_map(fn ($c) => is_string($c) ? $c : (string) $c, $cells);
                    $need = array_slice(self::SHEETS[$name], 0, 3);
                    if (count(array_intersect($need, $norm)) === count($need)) {
                        $headers = $norm;
                    }

                    continue;
                }
                $assoc = [];
                foreach ($headers as $i => $h) {
                    if ($h !== '') {
                        $assoc[$h] = $cells[$i] ?? null;
                    }
                }
                $assoc['_row'] = $rowNo;
                $rows[] = $assoc;
            }
            if ($headers === null) {
                $this->errors[] = ['sheet' => $name, 'row' => '-', 'message' => 'Header row not found. Do not rename column headers.'];
            } else {
                $missing = array_diff(self::SHEETS[$name], $headers);
                if ($missing) {
                    $this->errors[] = ['sheet' => $name, 'row' => '-', 'message' => 'Missing columns: '.implode(', ', $missing)];
                }
            }
            $this->data[$name] = $rows;
        }
        $reader->close();
    }

    private function err(string $sheet, int|string $row, string $msg): void
    {
        $this->errors[] = ['sheet' => $sheet, 'row' => $row, 'message' => $msg];
    }

    private static function yes(mixed $v, bool $default = true): bool
    {
        if ($v === null || $v === '') {
            return $default;
        }

        return in_array(strtolower((string) $v), ['yes', 'y', '1', 'true'], true);
    }

    private static function list(mixed $v): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) $v)), fn ($x) => $x !== '' && strtoupper($x) !== 'N/A'));
    }

    private function rows(string $sheet, string $key): array
    {
        return array_values(array_filter($this->data[$sheet] ?? [], fn ($r) => isset($r[$key]) && $r[$key] !== null && $r[$key] !== ''));
    }

    /** Run every check from the template's Validation sheet (plus referential checks). */
    public function validate(bool $tenantMode = true, ?int $tenantId = null): bool
    {
        $required = $tenantMode ? array_keys(self::SHEETS) : self::MASTER_SHEETS;
        foreach ($required as $s) {
            if (! array_key_exists($s, $this->data)) {
                $this->err($s, '-', "Sheet \"$s\" is missing. Do not rename sheets.");
            }
        }
        if ($this->errors) {
            return false;
        }
        if ($tenantMode) {
            $this->validateProfile();
            $this->validateSettings();
            $this->validateGroups();
            $this->validateUsers($tenantId);
        }
        $this->validateMasters();

        return ! $this->errors;
    }

    public function profile(): array
    {
        $out = [];
        foreach ($this->data['Tenant Profile'] ?? [] as $r) {
            if (! empty($r['Field'])) {
                $out[$r['Field']] = ['value' => is_float($r['Value']) && floor($r['Value']) == $r['Value'] ? (string) (int) $r['Value'] : (string) ($r['Value'] ?? ''), 'row' => $r['_row'], 'required' => self::yes($r['Required'] ?? 'No', false)];
            }
        }

        return $out;
    }

    private function validateProfile(): void
    {
        $s = 'Tenant Profile';
        foreach ($this->profile() as $field => $p) {
            $v = trim($p['value']);
            if ($p['required'] && $v === '') {
                $this->err($s, $p['row'], "$field is required.");

                continue;
            }
            if ($v === '') {
                continue;
            }
            match (true) {
                $field === 'Pin Code' && ! preg_match('/^\d{6}$/', $v) => $this->err($s, $p['row'], 'Pin Code must be exactly 6 digits.'),
                $field === 'Contact Number' && ! preg_match('/^\d{10}$/', $v) => $this->err($s, $p['row'], 'Contact Number must be a 10-digit mobile number (no +91 or spaces).'),
                $field === 'Alternate Contact' && ! preg_match('/^\d{10,12}$/', $v) => $this->err($s, $p['row'], 'Alternate Contact must be a 10-digit mobile or a landline with STD code.'),
                in_array($field, ['Insta', 'Face Book', 'Website', 'Youtube', 'LinkedIn'], true) && ! str_starts_with($v, 'https://') => $this->err($s, $p['row'], "$field must start with https://"),
                $field === 'State' && ! in_array($v, self::STATES, true) => $this->err($s, $p['row'], 'State must be one of: '.implode(', ', self::STATES)),
                $field === 'Name or Company Name' && mb_strlen($v) > 100 => $this->err($s, $p['row'], 'Name must be at most 100 characters.'),
                default => null,
            };
        }
    }

    public function settings(): array
    {
        $app = [];
        $inst = [];
        foreach ($this->rows('Additional Settings', 'Item') as $r) {
            if (strcasecmp((string) $r['Type'], 'Instalment') === 0) {
                $inst[] = $r;
            } else {
                $app[$r['Item']] = $r;
            }
        }

        return [$app, $inst];
    }

    private function validateSettings(): void
    {
        $s = 'Additional Settings';
        [$app, $inst] = $this->settings();
        $sinos = [];
        foreach (array_merge(array_values($app), $inst) as $r) {
            if (in_array($r['Sino'], $sinos, true)) {
                $this->err($s, $r['_row'], 'Sino '.$r['Sino'].' is repeated.');
            }
            $sinos[] = $r['Sino'];
            if ($r['Unit Value'] === null || $r['Unit Value'] === '') {
                $this->err($s, $r['_row'], "{$r['Item']}: Unit Value is required.");
            } elseif (strcasecmp((string) $r['Unit'], 'Percentage') === 0 && (! is_numeric($r['Unit Value']) || $r['Unit Value'] < 0 || $r['Unit Value'] > 100)) {
                $this->err($s, $r['_row'], "{$r['Item']}: percentage must be between 0 and 100.");
            } elseif (in_array($r['Unit'], ['Days', 'Times'], true) && (! is_numeric($r['Unit Value']) || $r['Unit Value'] < 0)) {
                $this->err($s, $r['_row'], "{$r['Item']}: must be a positive number.");
            }
        }
        foreach (['Currency', 'Sellable SQFT %', 'Broker Commission', 'Expense vs Budget Alert', 'Booking Validity', 'Sale Completion Window', 'MRP Multiplier'] as $needed) {
            if (! isset($app[$needed])) {
                $this->err($s, '-', "Setting \"$needed\" is missing.");
            }
        }
        if (! $inst) {
            $this->err($s, '-', 'At least one Instalment row is required.');

            return;
        }
        $total = array_sum(array_map(fn ($r) => (float) $r['Unit Value'], $inst));
        if (abs($total - 100) > 0.001) {
            $this->err($s, '-', "Instalment percentages must total 100 (now $total).");
        }
        $window = (float) ($app['Sale Completion Window']['Unit Value'] ?? 0);
        $last = max(array_map(fn ($r) => (float) ($r['Due Working Days'] ?? 0), $inst));
        if ($last > $window) {
            $this->err($s, '-', "Last instalment due ($last working days) must be ≤ the Sale Completion Window ($window days).");
        }
    }

    private function validateGroups(): void
    {
        $active = array_filter($this->rows('Notification Groups', 'Group Name'), fn ($r) => self::yes($r['Active']));
        if (! $active) {
            $this->err('Notification Groups', '-', 'At least one active notification group is required.');
        }
    }

    private function validateUsers(?int $tenantId): void
    {
        $s = 'Users';
        $rows = array_filter($this->data[$s] ?? [], fn ($r) => ! empty($r['Full Name']) || ! empty($r['Email']) || ! empty($r['Mobile']) || ! empty($r['Role']));
        if (! $rows) {
            $this->warnings[] = 'Users sheet is empty — no extra users will be created (you can add users later in Users & roles).';

            return;
        }
        $emails = [];
        $groups = array_map(fn ($r) => strtolower($r['Group Name']), $this->rows('Notification Groups', 'Group Name'));
        foreach ($rows as $r) {
            $row = $r['_row'];
            if (empty($r['Full Name']) || empty($r['Email']) || empty($r['Mobile']) || empty($r['Role'])) {
                $this->err($s, $row, 'Name, email, mobile and role are all required.');

                continue;
            }
            $email = strtolower((string) $r['Email']);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->err($s, $row, "Invalid email: $email");
            }
            if (in_array($email, $emails, true)) {
                $this->err($s, $row, "Email $email is repeated.");
            }
            $emails[] = $email;
            $existing = User::withoutGlobalScopes()->where('email', $email)->first();
            if ($existing && $existing->tenant_id !== $tenantId) {
                $this->err($s, $row, "Email $email is already used by another account.");
            }
            $mobile = is_float($r['Mobile']) ? (string) (int) $r['Mobile'] : (string) $r['Mobile'];
            if (! preg_match('/^\d{10}$/', $mobile)) {
                $this->err($s, $row, 'Mobile must be 10 digits.');
            }
            if (! isset(self::ROLE_MAP[strtolower((string) $r['Role'])])) {
                $this->err($s, $row, 'Role must be Admin, Manager, Sales or Accountant.');
            }
            foreach (self::list($r['Notification Groups'] ?? '') as $g) {
                if (! in_array(strtolower($g), $groups, true)) {
                    $this->err($s, $row, "Notification group \"$g\" is not in the Notification Groups sheet.");
                }
            }
        }
    }

    private function validateMasters(): void
    {
        // Approval stages
        $s = 'Approval Stages';
        $tasks = $this->rows($s, 'Task ID');
        $ids = [];
        foreach ($tasks as $r) {
            $id = (string) $r['Task ID'];
            if (isset($ids[$id])) {
                $this->err($s, $r['_row'], "Task ID $id is repeated.");
            }
            $ids[$id] = $r;
            if (! in_array($r['Area Type'], self::AREA_TYPES, true)) {
                $this->err($s, $r['_row'], 'Area Type must be Village, Town or City.');
            }
            if (! is_numeric($r['Stage No'])) {
                $this->err($s, $r['_row'], 'Stage No must be a number.');
            }
            if (! is_numeric($r['Default Duration (Days)']) || $r['Default Duration (Days)'] < 0) {
                $this->err($s, $r['_row'], 'Default Duration must be a number ≥ 0.');
            }
            if (empty($r['Short Name']) || empty($r['Subtask']) || empty($r['Stage'])) {
                $this->err($s, $r['_row'], 'Stage, Short Name and Subtask are required.');
            }
        }
        if (! $tasks) {
            $this->err($s, '-', 'No approval tasks found.');
        }
        $graph = [];
        foreach ($tasks as $r) {
            $deps = self::list($r['Depends On'] ?? '');
            foreach ($deps as $d) {
                if (! isset($ids[$d])) {
                    $this->err($s, $r['_row'], "Depends On refers to unknown Task ID $d.");
                } elseif ($ids[$d]['Area Type'] !== $r['Area Type']) {
                    $this->err($s, $r['_row'], "Task {$r['Task ID']} depends on $d from a different area type.");
                }
            }
            $graph[(string) $r['Task ID']] = $deps;
        }
        if ($cycle = self::findCycle($graph)) {
            $this->err($s, '-', 'Dependencies contain a cycle: '.implode(' → ', $cycle));
        }

        // Facility master
        $s = 'Facility Master';
        $fids = [];
        foreach ($this->rows($s, 'Facility ID') as $r) {
            if (isset($fids[$r['Facility ID']])) {
                $this->err($s, $r['_row'], "Facility ID {$r['Facility ID']} is repeated.");
            }
            $fids[$r['Facility ID']] = true;
            foreach (['Basic', 'Standard', 'Premium'] as $tier) {
                if (! in_array((string) $r[$tier], ['Y', 'Opt', '-'], true)) {
                    $this->err($s, $r['_row'], "$tier must be Y, Opt or -.");
                }
            }
            if (self::yes($r['Active'])) {
                foreach (self::AREA_TYPES as $a) {
                    $c = $r["$a Unit Cost (₹)"];
                    if (! is_numeric($c) || $c <= 0) {
                        $this->err($s, $r['_row'], "$a Unit Cost must be greater than 0.");
                    }
                }
            }
        }

        // Document checklist
        $s = 'Document Checklist';
        $dids = [];
        $perChecklist = [];
        foreach ($this->rows($s, 'Doc ID') as $r) {
            if (isset($dids[$r['Doc ID']])) {
                $this->err($s, $r['_row'], "Doc ID {$r['Doc ID']} is repeated.");
            }
            $dids[$r['Doc ID']] = true;
            $perChecklist[$r['Checklist']] = ($perChecklist[$r['Checklist']] ?? 0) + 1;
            if (! in_array($r['Mandatory'], ['Yes', 'No', 'Conditional'], true)) {
                $this->err($s, $r['_row'], 'Mandatory must be Yes, No or Conditional.');
            }
            foreach (self::AREA_TYPES as $a) {
                $t = trim((string) ($r["$a Task ID"] ?? ''));
                if ($t !== '' && strtoupper($t) !== 'N/A' && ! isset($ids[$t])) {
                    $this->err($s, $r['_row'], "$a Task ID $t does not exist in Approval Stages.");
                }
            }
        }
        foreach ($perChecklist as $cl => $n) {
            if ($n < 1) {
                $this->err($s, '-', "Checklist $cl has no items.");
            }
        }
        foreach ($tasks as $r) {
            foreach (self::list($r['Required Doc IDs'] ?? '') as $d) {
                if (! isset($dids[$d])) {
                    $this->err('Approval Stages', $r['_row'], "Required Doc ID $d does not exist in Document Checklist.");
                }
            }
        }
    }

    /** DFS cycle detection; returns the cycle path or null. */
    public static function findCycle(array $graph): ?array
    {
        $state = [];
        $stack = [];
        $visit = function ($n) use (&$visit, &$state, &$stack, $graph) {
            $state[$n] = 1;
            $stack[] = $n;
            foreach ($graph[$n] ?? [] as $m) {
                if (! isset($graph[$m])) {
                    continue;
                }
                if (($state[$m] ?? 0) === 1) {
                    return array_merge(array_slice($stack, array_search($m, $stack, true)), [$m]);
                }
                if (($state[$m] ?? 0) === 0 && ($c = $visit($m))) {
                    return $c;
                }
            }
            array_pop($stack);
            $state[$n] = 2;

            return null;
        };
        foreach (array_keys($graph) as $n) {
            if (($state[$n] ?? 0) === 0 && ($c = $visit($n))) {
                return $c;
            }
        }

        return null;
    }

    /** Import (after validate()). $tenant null = App-level masters. Returns a summary. */
    public function import(?Tenant $tenant): array
    {
        return DB::transaction(function () use ($tenant) {
            $summary = [];
            $tid = $tenant?->id;
            if ($tenant) {
                $summary = array_merge($summary, $this->importTenantData($tenant));
            }
            $summary = array_merge($summary, $this->importMasters($tid));

            return $summary;
        });
    }

    private function importTenantData(Tenant $tenant): array
    {
        $p = $this->profile();
        $v = fn ($k) => ($x = trim($p[$k]['value'] ?? '')) === '' ? null : $x;
        $tenant->update(array_filter([
            'name' => $v('Name or Company Name'),
            'address_line1' => $v('Address Line1'),
            'address_line2' => $v('Address Line2'),
            'village' => $v('Village'),
            'city' => $v('City Or Town'),
            'district' => $v('District'),
            'state' => $v('State'),
            'pin' => $v('Pin Code'),
            'contact' => $v('Contact Number'),
            'alt_contact' => $v('Alternate Contact'),
            'instagram' => $v('Insta'),
            'facebook' => $v('Face Book'),
            'website' => $v('Website'),
            'youtube' => $v('Youtube'),
            'linkedin' => $v('LinkedIn'),
        ], fn ($x) => $x !== null));

        [$app, $inst] = $this->settings();
        $num = fn ($k, $d) => isset($app[$k]) ? (float) $app[$k]['Unit Value'] : $d;
        TenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $tenant->id], [
            'currency' => (string) ($app['Currency']['Unit Value'] ?? 'INR'),
            'sellable_pct' => $num('Sellable SQFT %', 55),
            'broker_commission_pct' => $num('Broker Commission', 0.5),
            'budget_alert_pct' => $num('Expense vs Budget Alert', 80),
            'booking_validity_days' => (int) $num('Booking Validity', 7),
            'sale_window_days' => (int) $num('Sale Completion Window', 15),
            'mrp_multiplier' => $num('MRP Multiplier', 3),
        ]);
        InstalmentPlan::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        foreach (array_values($inst) as $i => $r) {
            InstalmentPlan::create(['tenant_id' => $tenant->id, 'seq' => $i + 1, 'name' => $r['Item'], 'percent' => (float) $r['Unit Value'], 'due_working_days' => (int) ($r['Due Working Days'] ?? 0)]);
        }

        $groups = [];
        foreach ($this->rows('Notification Groups', 'Group Name') as $r) {
            $g = NotificationGroup::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $tenant->id, 'name' => $r['Group Name']], [
                'type' => $r['Type'] ?: 'Email', 'description' => $r['Description'], 'is_active' => self::yes($r['Active']),
            ]);
            $groups[strtolower($g->name)] = $g->id;
        }

        $created = 0;
        foreach ($this->data['Users'] ?? [] as $r) {
            if (empty($r['Email'])) {
                continue;
            }
            $base = self::ROLE_MAP[strtolower((string) $r['Role'])];
            $email = strtolower((string) $r['Email']);
            $user = User::withoutGlobalScopes()->where('email', $email)->first();
            if (! $user) {
                PlanLimiter::ensureUserRole($tenant, $base);
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'role_id' => Role::systemRole($base, $tenant->id)->id,
                    'user_code' => IdGenerator::next($tenant->id, 'user'),
                    'name' => $r['Full Name'],
                    'email' => $email,
                    'mobile' => is_float($r['Mobile']) ? (string) (int) $r['Mobile'] : (string) $r['Mobile'],
                    'password' => Passwords::generate(),
                    'is_active' => self::yes($r['Active']),
                ]);
                $token = Password::broker('users')->createToken($user);
                Notify::send('user_invited', [$user->email], [
                    'name' => $user->name, 'tenant_name' => $tenant->name, 'inviter' => 'Your administrator',
                    'role' => config('permissions.role_labels.'.$base),
                    'link' => route('password.reset', ['token' => $token, 'email' => $user->email]),
                ], $tenant->id);
                $created++;
            }
            $ids = array_filter(array_map(fn ($g) => $groups[strtolower($g)] ?? null, self::list($r['Notification Groups'] ?? '')));
            $user->groups()->syncWithoutDetaching($ids);
        }

        return ['users_created' => $created, 'instalments' => count($inst), 'groups' => count($groups)];
    }

    private function importMasters(?int $tid): array
    {
        $scoped = fn ($m) => $m::withoutGlobalScopes()->where('tenant_id', $tid);
        if ($tid === null) {
            $scoped = fn ($m) => $m::withoutGlobalScopes()->whereNull('tenant_id');
        }
        // Replace the scope's masters (subtasks/dependencies/links cascade).
        $scoped(StageMaster::class)->delete();
        $scoped(FacilityMaster::class)->delete();
        $scoped(DocumentChecklist::class)->delete();

        $docIds = [];
        foreach ($this->rows('Document Checklist', 'Doc ID') as $r) {
            $d = DocumentChecklist::create([
                'tenant_id' => $tid,
                'sino' => (int) $r['Sino'],
                'checklist' => $r['Checklist'],
                'doc_code' => $r['Doc ID'],
                'applies_to' => $r['Applies To'] ?: 'All',
                'name' => Str::limit((string) $r['Document / Item'], 250, ''),
                'issued_by' => $r['Issued By / Source'],
                'submission_format' => $r['Submission Format'],
                'mandatory' => $r['Mandatory'] ?: 'Yes',
                'remarks' => $r['Condition / Remarks'] ? Str::limit((string) $r['Condition / Remarks'], 495, '') : null,
                'stage_no' => is_numeric($r['Stage No']) ? (int) $r['Stage No'] : null,
                'stage_name' => $r['Stage'],
                'village_task_code' => self::taskCode($r['Village Task ID'] ?? null),
                'town_task_code' => self::taskCode($r['Town Task ID'] ?? null),
                'city_task_code' => self::taskCode($r['City Task ID'] ?? null),
                'is_active' => self::yes($r['Active']),
            ]);
            $docIds[$d->doc_code] = $d->id;
        }

        foreach ($this->rows('Facility Master', 'Facility ID') as $r) {
            FacilityMaster::create([
                'tenant_id' => $tid,
                'sino' => (int) $r['Sino'],
                'facility_code' => $r['Facility ID'],
                'category' => $r['Category'],
                'name' => $r['Facility'],
                'specification' => $r['Specification'],
                'unit' => $r['Unit'],
                'tier_basic' => (string) $r['Basic'],
                'tier_standard' => (string) $r['Standard'],
                'tier_premium' => (string) $r['Premium'],
                'is_statutory' => self::yes($r['Statutory'], false),
                'cost_village' => (float) $r['Village Unit Cost (₹)'],
                'cost_town' => (float) $r['Town Unit Cost (₹)'],
                'cost_city' => (float) $r['City Unit Cost (₹)'],
                'is_active' => self::yes($r['Active']),
            ]);
        }

        $stages = [];
        $taskIds = [];
        $tasks = $this->rows('Approval Stages', 'Task ID');
        foreach ($tasks as $r) {
            $key = $r['Area Type'].'|'.(int) $r['Stage No'];
            if (! isset($stages[$key])) {
                $stages[$key] = StageMaster::create(['tenant_id' => $tid, 'area_type' => $r['Area Type'], 'stage_no' => (int) $r['Stage No'], 'name' => $r['Stage']])->id;
            }
            $t = SubtaskMaster::create([
                'tenant_id' => $tid,
                'stage_master_id' => $stages[$key],
                'sino' => (int) $r['Sino'],
                'task_code' => $r['Task ID'],
                'short_name' => Str::limit((string) $r['Short Name'], 58, ''),
                'name' => Str::limit((string) $r['Subtask'], 250, ''),
                'responsible' => $r['Responsible / Authority'] ? Str::limit((string) $r['Responsible / Authority'], 148, '') : null,
                'output' => $r['Output / Document'] ? Str::limit((string) $r['Output / Document'], 148, '') : null,
                'default_duration_days' => (int) $r['Default Duration (Days)'],
                'is_active' => self::yes($r['Active']),
            ]);
            $taskIds[$t->task_code] = $t;
        }
        $links = 0;
        foreach ($tasks as $r) {
            $t = $taskIds[$r['Task ID']];
            $deps = array_filter(array_map(fn ($d) => $taskIds[$d]->id ?? null, self::list($r['Depends On'] ?? '')));
            if ($deps) {
                $t->dependencies()->attach($deps);
            }
            $docs = array_filter(array_map(fn ($d) => $docIds[$d] ?? null, self::list($r['Required Doc IDs'] ?? '')));
            // also every document whose area Task ID points at this task
            foreach (DocumentChecklist::withoutGlobalScopes()->where('tenant_id', $tid)->where(strtolower($r['Area Type']).'_task_code', $t->task_code)->pluck('id') as $id) {
                $docs[] = $id;
            }
            $docs = array_values(array_unique($docs));
            if ($docs) {
                $t->documents()->attach($docs);
                $links += count($docs);
            }
        }

        return ['stages' => count($stages), 'tasks' => count($taskIds), 'facilities' => count($this->rows('Facility Master', 'Facility ID')), 'documents' => count($docIds), 'task_document_links' => $links];
    }

    private static function taskCode(mixed $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' || strtoupper($v) === 'N/A' ? null : $v;
    }
}
