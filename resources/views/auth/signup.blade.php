<x-layouts.auth title="Create your promoter workspace" :wide="true">
    <p class="eyebrow">For layout promoters</p>
    <h1 class="mt-1 text-2xl font-extrabold">Create your workspace</h1>
    <p class="mt-1 text-sm text-muted">Start free on the trial plan. Your workspace opens straight after sign-up.</p>
    <form method="POST" action="{{ route('signup.store') }}" class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2" novalidate>
        @csrf
        <fieldset class="sm:col-span-2">
            <legend class="label">Workspace type <span class="text-red-700" aria-hidden="true">*</span></legend>
            <div class="mt-1 flex gap-3">
                @foreach (['organisation' => 'Organisation', 'individual' => 'Individual'] as $v => $l)
                    <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-navy-100 px-4 py-3 text-sm font-semibold has-[:checked]:border-teal has-[:checked]:bg-teal-50">
                        <input type="radio" name="type" value="{{ $v }}" class="text-teal-700 focus:ring-teal" @checked(old('type', 'organisation') === $v)> {{ $l }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <x-field class="sm:col-span-2" name="name" label="Name or company name" required maxlength="100" placeholder="Sri Murugan Promoters" />
        <x-field name="admin_name" label="Your name" maxlength="100" hint="Shown on your user profile" />
        <x-field name="city" label="City / town" placeholder="Thanjavur" />
        <x-field name="email" type="email" label="Email" required autocomplete="email" hint="You will sign in with this email" />
        <x-field name="mobile" type="tel" label="Mobile" required inputmode="numeric" maxlength="10" />
        <x-password label="Password" autocomplete="new-password" />
        <x-password name="password_confirmation" label="Confirm password" :policy="false" autocomplete="new-password" />
        <x-checkbox class="sm:col-span-2" name="terms" label="I accept the vector7 Terms of Service and Privacy Policy" />
        <div class="sm:col-span-2"><button class="btn-primary w-full btn-pill py-3">Create workspace</button></div>
    </form>
    <x-slot:below>Already have a workspace? <a href="{{ route('staff.login') }}" class="font-semibold">Sign in</a></x-slot:below>
</x-layouts.auth>
