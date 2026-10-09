<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\EmailTemplate;
use App\Models\SocialAccount;
use App\Services\AppSettings;
use App\Services\AuditLogger;
use App\Services\FileStore;
use App\Services\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** App Admin settings: organisation, payment gateway, SMTP, AI, storage driver, social accounts, security. */
class SettingsController extends Controller
{
    public function index(Request $request)
    {
        return view('app.settings.index', [
            'tab' => $request->query('tab', 'organisation'),
            's' => fn (string $k, $d = null) => AppSettings::get($k, $d),
            'masked' => fn (string $k) => AppSettings::masked($k),
            'social' => SocialAccount::whereNull('tenant_id')->get()->keyBy('platform'),
            'aiUsed' => (int) AiUsageLog::where('created_at', '>=', now()->startOfMonth())->sum('credits'),
        ]);
    }

    public function update(Request $request, string $section)
    {
        // Sensitive change: confirm with the admin's own password (no OTP in this release).
        $request->validate(['current_password' => ['required', 'current_password:web']], ['current_password.current_password' => 'Your password is not correct.']);
        $fields = match ($section) {
            'organisation' => ['org.name' => 'required|string|max:100', 'org.address' => 'nullable|string|max:255', 'org.contact' => 'nullable|string|max:20', 'org.support_email' => 'required|email',
                'org.website' => 'nullable|url', 'org.instagram' => 'nullable|url', 'org.facebook' => 'nullable|url', 'org.youtube' => 'nullable|url', 'org.linkedin' => 'nullable|url'],
            'payment' => ['payment.gateway' => 'required|in:none,razorpay,swipe', 'payment.test_mode' => 'nullable|boolean', 'payment.razorpay_key_id' => 'nullable|string|max:100',
                'payment.razorpay_key_secret' => 'nullable|string|max:200', 'payment.razorpay_webhook_secret' => 'nullable|string|max:200',
                'payment.swipe_api_base' => 'nullable|url', 'payment.swipe_api_key' => 'nullable|string|max:300', 'payment.swipe_webhook_secret' => 'nullable|string|max:200'],
            'smtp' => ['smtp.host' => 'nullable|string|max:150', 'smtp.port' => 'nullable|integer|min:1|max:65535', 'smtp.encryption' => 'nullable|in:ssl,tls,none',
                'smtp.username' => 'nullable|string|max:150', 'smtp.password' => 'nullable|string|max:200', 'smtp.from_address' => 'nullable|email', 'smtp.from_name' => 'nullable|string|max:100'],
            'ai' => ['ai.enabled' => 'nullable|boolean', 'ai.api_key' => 'nullable|string|max:300', 'ai.model' => 'required|string|max:60', 'ai.monthly_cap' => 'required|integer|min:0|max:10000000'],
            'storage' => ['storage.driver' => 'required|in:'.implode(',', array_keys(FileStore::DRIVERS)),
                'storage.s3_key' => 'nullable|string|max:150', 'storage.s3_secret' => 'nullable|string|max:200', 'storage.s3_region' => 'nullable|string|max:40', 'storage.s3_bucket' => 'nullable|string|max:100', 'storage.s3_endpoint' => 'nullable|url',
                'storage.gcs_bucket' => 'nullable|string|max:100', 'storage.gcs_credentials' => 'nullable|string|max:10000',
                'storage.azure_account' => 'nullable|string|max:100', 'storage.azure_key' => 'nullable|string|max:200', 'storage.azure_container' => 'nullable|string|max:100',
                'storage.gdrive_folder_id' => 'nullable|string|max:150', 'storage.gdrive_credentials' => 'nullable|string|max:10000'],
            'social' => [],
            'security' => [],
        };
        if ($section === 'social') {
            return $this->social($request);
        }
        $data = $request->validate(collect($fields)->mapWithKeys(fn ($v, $k) => [str_replace('.', '__', $k) => $v])->all());
        foreach (array_keys($fields) as $key) {
            $input = str_replace('.', '__', $key);
            $value = $data[$input] ?? null;
            if (in_array($key, AppSettings::SECRET_KEYS, true) && ($value === null || $value === '')) {
                continue; // blank secret = keep the stored one
            }
            if (str_ends_with($key, 'enabled') || str_ends_with($key, 'test_mode')) {
                $value = $request->boolean($input) ? '1' : '0';
            }
            AppSettings::set($key, $value === null ? null : (string) $value);
        }
        AuditLogger::log('settings_updated', null, null, ['section' => $section, 'keys' => array_keys($fields)]);

        return redirect()->route('app.settings.index', ['tab' => $section])->with('ok', 'Settings saved.');
    }

    private function social(Request $request)
    {
        $data = $request->validate([
            'platform' => 'required|in:instagram,facebook,youtube',
            'account_name' => 'nullable|string|max:120',
            'account_id' => 'nullable|string|max:120',
            'access_token' => 'nullable|string|max:2000',
            'disconnect' => 'nullable|boolean',
        ]);
        $acc = SocialAccount::firstOrNew(['tenant_id' => null, 'platform' => $data['platform']]);
        if ($request->boolean('disconnect')) {
            $acc->fill(['access_token' => null, 'is_connected' => false])->save();
        } else {
            $acc->fill(['account_name' => $data['account_name'], 'account_id' => $data['account_id']]);
            if (! empty($data['access_token'])) {
                $acc->access_token = $data['access_token'];
            }
            $acc->is_connected = (bool) ($acc->access_token && $acc->account_id);
            $acc->connected_at = $acc->is_connected ? now() : null;
            $acc->save();
        }
        AuditLogger::log('social_account_updated', $acc, null, ['platform' => $acc->platform, 'connected' => $acc->is_connected]);

        return redirect()->route('app.settings.index', ['tab' => 'social'])->with('ok', ucfirst($data['platform']).($acc->is_connected ? ' connected.' : ' saved (not connected).'));
    }

    public function testEmail(Request $request)
    {
        $request->validate(['to' => 'required|email']);
        try {
            Notify::send('test_email', [$request->to], [], null, now: true);
        } catch (\Throwable $e) {
            return back()->with('error', 'Sending failed: '.$e->getMessage());
        }

        return back()->with('ok', 'Test email sent to '.$request->to.'. (With MAIL_MAILER=log it is written to storage/logs.)');
    }

    public function testStorage(Request $request)
    {
        $result = FileStore::driver($request->input('driver', AppSettings::get('storage.driver')))->test();

        return redirect()->route('app.settings.index', ['tab' => 'storage'])->with($result === true ? 'ok' : 'error', $result === true ? 'Connection works: a test file was written, read back and deleted.' : 'Connection failed: '.$result);
    }

    public function templates()
    {
        return view('app.settings.templates', ['templates' => EmailTemplate::orderBy('name')->get()]);
    }

    public function updateTemplate(Request $request, EmailTemplate $template)
    {
        $template->update($request->validate(['subject' => 'required|string|max:200', 'body' => 'required|string|max:10000']));

        return back()->with('ok', 'Template saved.');
    }

    public function aiUsage(Request $request)
    {
        return view('app.settings.ai-usage', [
            'logs' => AiUsageLog::latest('id')->paginate(50),
            'byFeature' => AiUsageLog::where('created_at', '>=', now()->startOfMonth())->selectRaw('feature, SUM(credits) c, SUM(input_tokens) i, SUM(output_tokens) o')->groupBy('feature')->get(),
            'cap' => (int) AppSettings::get('ai.monthly_cap'),
        ]);
    }
}
