<x-layouts.workspace title="Settings">
    <x-page-header title="Settings" subtitle="App Admin only. Secrets are stored encrypted and never shown again — leave a secret blank to keep it.">
        <a href="{{ route('app.settings.templates') }}" class="btn-light"><x-icon name="mail" class="h-4 w-4" /> Email templates</a>
        <a href="{{ route('app.settings.ai-usage') }}" class="btn-light"><x-icon name="sparkles" class="h-4 w-4" /> AI usage log</a>
    </x-page-header>
    @php($tabs = ['organisation' => 'Organisation', 'payment' => 'Payment gateway', 'smtp' => 'Email (SMTP)', 'ai' => 'AI (Claude)', 'storage' => 'Document storage', 'social' => 'Social accounts', 'security' => 'Security'])
    <nav class="tabs mb-6">@foreach ($tabs as $k => $l)<a href="{{ route('app.settings.index', ['tab' => $k]) }}" class="tab {{ $tab === $k ? 'active' : '' }}">{{ $l }}</a>@endforeach</nav>
    @php($pw = '<div class="sm:col-span-2 lg:col-span-3 rounded-xl bg-page p-4"><label class="label" for="cp">Confirm with your password</label><input id="cp" type="password" name="current_password" class="input max-w-xs" required autocomplete="current-password">'.($errors->has('current_password') ? '<p class="error">'.e($errors->first('current_password')).'</p>' : '').'</div>')

    @if ($tab === 'organisation')
        <form method="POST" action="{{ route('app.settings.update', 'organisation') }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf @method('PUT')
            <p class="text-sm text-muted sm:col-span-2 lg:col-span-3">Used in the marketplace footer, emails and PDFs.</p>
            <x-field name="org__name" label="Organisation name" :value="$s('org.name')" required />
            <x-field name="org__support_email" type="email" label="Support email" :value="$s('org.support_email')" required />
            <x-field name="org__contact" label="Contact number" :value="$s('org.contact')" />
            <x-field name="org__address" label="Address" :value="$s('org.address')" class="sm:col-span-2 lg:col-span-3" />
            <x-field name="org__website" type="url" label="Website" :value="$s('org.website')" />
            <x-field name="org__instagram" type="url" label="Instagram" :value="$s('org.instagram')" />
            <x-field name="org__facebook" type="url" label="Facebook" :value="$s('org.facebook')" />
            <x-field name="org__youtube" type="url" label="YouTube" :value="$s('org.youtube')" />
            <x-field name="org__linkedin" type="url" label="LinkedIn" :value="$s('org.linkedin')" />
            {!! $pw !!}
            <div><button class="btn-primary">Save</button></div>
        </form>
    @elseif ($tab === 'payment')
        <form method="POST" action="{{ route('app.settings.update', 'payment') }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf @method('PUT')
            <p class="text-sm text-muted sm:col-span-2 lg:col-span-3">For tenant subscription payments only (customers pay offline in this release). Payments use the gateway's hosted payment-link page. Webhook URL: <code class="rounded bg-page px-1">{{ url('/webhooks/payment/razorpay') }}</code> or <code class="rounded bg-page px-1">{{ url('/webhooks/payment/swipe') }}</code>.</p>
            <x-select name="payment__gateway" label="Gateway" :options="['none' => 'None — mark invoices paid manually', 'razorpay' => 'Razorpay', 'swipe' => 'Swipe']" :value="$s('payment.gateway')" />
            <div class="flex items-end"><input type="hidden" name="payment__test_mode" value="0"><x-checkbox name="payment__test_mode" label="Test mode" :checked="$s('payment.test_mode') === '1'" /></div>
            <div></div>
            <x-field name="payment__razorpay_key_id" label="Razorpay key ID" :value="$s('payment.razorpay_key_id')" />
            <x-field name="payment__razorpay_key_secret" type="password" label="Razorpay key secret" :placeholder="$masked('payment.razorpay_key_secret') ?: 'Not set'" autocomplete="off" />
            <x-field name="payment__razorpay_webhook_secret" type="password" label="Razorpay webhook secret" :placeholder="$masked('payment.razorpay_webhook_secret') ?: 'Not set'" autocomplete="off" />
            <x-field name="payment__swipe_api_base" type="url" label="Swipe API base URL" :value="$s('payment.swipe_api_base')" placeholder="https://app.getswipe.in/api/partner/v2" />
            <x-field name="payment__swipe_api_key" type="password" label="Swipe API key" :placeholder="$masked('payment.swipe_api_key') ?: 'Not set'" autocomplete="off" />
            <x-field name="payment__swipe_webhook_secret" type="password" label="Swipe webhook secret" :placeholder="$masked('payment.swipe_webhook_secret') ?: 'Not set'" autocomplete="off" />
            {!! $pw !!}
            <div><button class="btn-primary">Save</button></div>
        </form>
    @elseif ($tab === 'smtp')
        <div class="grid gap-6 lg:grid-cols-3">
            <form method="POST" action="{{ route('app.settings.update', 'smtp') }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:col-span-2">
                @csrf @method('PUT')
                <p class="text-sm text-muted sm:col-span-2">Hostinger mail: host smtp.hostinger.com, port 465, encryption SSL, your full mailbox address as username. Leave host blank to use the .env mailer (log on Laragon).</p>
                <x-field name="smtp__host" label="SMTP host" :value="$s('smtp.host')" />
                <x-field name="smtp__port" type="number" label="Port" :value="$s('smtp.port', 465)" />
                <x-select name="smtp__encryption" label="Encryption" :options="['ssl' => 'SSL', 'tls' => 'TLS (STARTTLS)', 'none' => 'None']" :value="$s('smtp.encryption', 'ssl')" />
                <x-field name="smtp__username" label="Username" :value="$s('smtp.username')" autocomplete="off" />
                <x-field name="smtp__password" type="password" label="Password" :placeholder="$masked('smtp.password') ?: 'Not set'" autocomplete="off" />
                <x-field name="smtp__from_address" type="email" label="From address" :value="$s('smtp.from_address')" />
                <x-field name="smtp__from_name" label="From name" :value="$s('smtp.from_name', 'vector7')" />
                <div class="sm:col-span-2 rounded-xl bg-page p-4"><label class="label" for="cp2">Confirm with your password</label><input id="cp2" type="password" name="current_password" class="input max-w-xs" required autocomplete="current-password"></div>
                <div><button class="btn-primary">Save</button></div>
            </form>
            <form method="POST" action="{{ route('app.settings.test-email') }}" class="card card-pad space-y-3">@csrf
                <h2 class="section-title">Send test email</h2>
                <x-field name="to" type="email" label="Send to" :value="auth()->user()->email" required />
                <button class="btn-light">Send test email</button>
            </form>
        </div>
    @elseif ($tab === 'ai')
        <form method="POST" action="{{ route('app.settings.update', 'ai') }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf @method('PUT')
            <p class="text-sm text-muted sm:col-span-2 lg:col-span-3">Anthropic Claude powers plot extraction from PDFs and photos, promotion text, social posts, SEO descriptions and plain-language plot search. Used this month: <strong class="text-navy">{{ number_format($aiUsed) }}</strong> of {{ number_format((int) $s('ai.monthly_cap')) }} credits.</p>
            <div class="flex items-end"><input type="hidden" name="ai__enabled" value="0"><x-checkbox name="ai__enabled" label="AI features enabled" :checked="$s('ai.enabled') === '1'" /></div>
            <x-field name="ai__api_key" type="password" label="Anthropic API key" :placeholder="$masked('ai.api_key') ?: 'Not set'" autocomplete="off" />
            <x-field name="ai__model" label="Model" :value="$s('ai.model')" required hint="Default claude-sonnet-5-5" />
            <x-field name="ai__monthly_cap" type="number" label="Monthly usage cap (credits, all tenants)" :value="$s('ai.monthly_cap')" required />
            {!! $pw !!}
            <div><button class="btn-primary">Save</button></div>
        </form>
    @elseif ($tab === 'storage')
        <form method="POST" action="{{ route('app.settings.update', 'storage') }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf @method('PUT')
            <p class="text-sm text-muted sm:col-span-2 lg:col-span-3">Where uploaded documents, layouts, photos and receipts are kept. Existing files stay with the driver they were saved on.</p>
            <x-select name="storage__driver" label="Storage driver" :options="\App\Services\FileStore::DRIVERS" :value="$s('storage.driver')" />
            <div class="sm:col-span-2"></div>
            <h3 class="font-bold sm:col-span-2 lg:col-span-3">AWS S3</h3>
            <x-field name="storage__s3_key" label="Access key ID" :value="$s('storage.s3_key')" autocomplete="off" />
            <x-field name="storage__s3_secret" type="password" label="Secret access key" :placeholder="$masked('storage.s3_secret') ?: 'Not set'" autocomplete="off" />
            <x-field name="storage__s3_region" label="Region" :value="$s('storage.s3_region', 'ap-south-1')" />
            <x-field name="storage__s3_bucket" label="Bucket" :value="$s('storage.s3_bucket')" />
            <x-field name="storage__s3_endpoint" type="url" label="Custom endpoint (optional)" :value="$s('storage.s3_endpoint')" />
            <div></div>
            <h3 class="font-bold sm:col-span-2 lg:col-span-3">Google Cloud Storage</h3>
            <x-field name="storage__gcs_bucket" label="Bucket" :value="$s('storage.gcs_bucket')" />
            <x-textarea name="storage__gcs_credentials" label="Service-account JSON key" rows="3" class="sm:col-span-2" :placeholder="$masked('storage.gcs_credentials') ? 'Stored — paste a new key to replace' : 'Paste the JSON key'" />
            <h3 class="font-bold sm:col-span-2 lg:col-span-3">Azure Blob</h3>
            <x-field name="storage__azure_account" label="Account name" :value="$s('storage.azure_account')" />
            <x-field name="storage__azure_key" type="password" label="Account key" :placeholder="$masked('storage.azure_key') ?: 'Not set'" autocomplete="off" />
            <x-field name="storage__azure_container" label="Container" :value="$s('storage.azure_container')" />
            <h3 class="font-bold sm:col-span-2 lg:col-span-3">Google Drive</h3>
            <x-field name="storage__gdrive_folder_id" label="Folder ID" :value="$s('storage.gdrive_folder_id')" hint="Share this folder with the service-account email" />
            <x-textarea name="storage__gdrive_credentials" label="Service-account JSON key" rows="3" class="sm:col-span-2" :placeholder="$masked('storage.gdrive_credentials') ? 'Stored — paste a new key to replace' : 'Paste the JSON key'" />
            {!! $pw !!}
            <div class="flex gap-2"><button class="btn-primary">Save</button></div>
        </form>
        <form method="POST" action="{{ route('app.settings.test-storage') }}" class="card card-pad mt-4 flex flex-wrap items-end gap-3">@csrf
            <x-select name="driver" label="Test connection for" :options="\App\Services\FileStore::DRIVERS" :value="$s('storage.driver')" />
            <button class="btn-light">Test connection</button>
        </form>
    @elseif ($tab === 'social')
        <div class="grid gap-4 lg:grid-cols-3">
            @foreach (['instagram' => 'Instagram (Business account via Meta Graph API)', 'facebook' => 'Facebook Page (Meta Graph API)', 'youtube' => 'YouTube (Data API)'] as $p => $label)
                @php($acc = $social[$p] ?? null)
                <form method="POST" action="{{ route('app.settings.update', 'social') }}" class="card card-pad space-y-3">
                    @csrf @method('PUT')
                    <input type="hidden" name="platform" value="{{ $p }}">
                    <div class="flex items-center justify-between"><h2 class="section-title">{{ ucfirst($p) }}</h2><span class="{{ $acc?->is_connected ? 'badge-teal' : 'badge-gray' }}">{{ $acc?->is_connected ? 'Connected' : 'Not connected' }}</span></div>
                    <p class="text-xs text-muted">{{ $label }}</p>
                    <x-field name="account_name" label="Account / page name" :value="$acc?->account_name" id="{{ $p }}an" />
                    <x-field name="account_id" :label="$p === 'youtube' ? 'Channel ID' : ($p === 'instagram' ? 'Instagram business account ID' : 'Page ID')" :value="$acc?->account_id" id="{{ $p }}ai" />
                    <x-field name="access_token" type="password" :label="$p === 'youtube' ? 'API key / OAuth token' : 'Long-lived access token'" placeholder="{{ $acc?->access_token ? 'Stored — paste to replace' : 'Not set' }}" id="{{ $p }}at" autocomplete="off" />
                    <div class="rounded-xl bg-page p-3"><label class="label" for="{{ $p }}cp">Confirm with your password</label><input id="{{ $p }}cp" type="password" name="current_password" class="input" required autocomplete="current-password"></div>
                    <div class="flex gap-2"><button class="btn-primary btn-sm">Save</button>
                        @if ($acc?->is_connected)<button class="btn-ghost btn-sm" name="disconnect" value="1">Disconnect</button>@endif</div>
                </form>
            @endforeach
        </div>
    @else
        <div class="card card-pad space-y-4">
            <h2 class="section-title">Login & password policy</h2>
            <ul class="list-inside list-disc text-sm text-muted">
                <li>Email + password login for App users, tenant users and customers.</li>
                <li>Passwords: 8–32 characters; letters, numbers and symbols; no spaces at the start or end.</li>
                <li>Accounts lock for 15 minutes after 5 wrong passwords (unlock from IAM).</li>
                <li>Sensitive actions (refund approval, settings changes) ask for the user's password again.</li>
            </ul>
            <div class="flex items-center justify-between rounded-xl bg-page p-4 opacity-70">
                <div><p class="font-semibold">Two-factor authentication</p><p class="text-sm text-muted">Coming later — switched off in this release.</p></div>
                <label class="inline-flex cursor-not-allowed items-center gap-2 text-sm"><input type="checkbox" disabled class="checkbox"> Off</label>
            </div>
        </div>
    @endif
</x-layouts.workspace>
