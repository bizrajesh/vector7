<x-layouts.account title="Profile">
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('account.profile.update') }}" class="card card-pad grid gap-3 sm:grid-cols-2">
            @csrf @method('PUT')
            <h2 class="section-title sm:col-span-2">Your details</h2>
            <x-field name="name" label="Name" :value="$c->name" required class="sm:col-span-2" />
            <x-field name="email" type="email" label="Email" :value="$c->email" required />
            <x-field name="mobile" label="Mobile" :value="$c->mobile" required inputmode="numeric" maxlength="10" />
            <x-field name="address" label="Address" :value="$c->address" class="sm:col-span-2" />
            <x-field name="city" label="City" :value="$c->city" />
            <x-field name="district" label="District" :value="$c->district" />
            <x-field name="state" label="State" :value="$c->state" />
            <x-field name="pin" label="PIN" :value="$c->pin" inputmode="numeric" maxlength="6" />
            <div class="sm:col-span-2"><button class="btn-primary">Save</button></div>
        </form>
        <form method="POST" action="{{ route('account.password') }}" class="card card-pad space-y-3">
            @csrf @method('PUT')
            <h2 class="section-title">Change password</h2>
            <x-password name="current_password" label="Current password" :policy="false" />
            <x-password label="New password" autocomplete="new-password" />
            <x-password name="password_confirmation" label="Confirm new password" :policy="false" autocomplete="new-password" />
            <button class="btn-primary">Change password</button>
        </form>
    </div>
</x-layouts.account>
