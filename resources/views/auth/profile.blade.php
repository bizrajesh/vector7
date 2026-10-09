<x-layouts.workspace title="My profile">
    <x-page-header title="My profile" :subtitle="$user->email.' · '.$user->role->name" />
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('profile.update') }}" class="card card-pad space-y-4">
            @csrf @method('PUT')
            <h2 class="section-title">Details</h2>
            <x-field name="name" label="Name" :value="$user->name" required />
            <x-field name="mobile" label="Mobile" :value="$user->mobile" inputmode="numeric" maxlength="10" />
            @if ($user->user_code)<p class="text-sm text-muted">User ID: <span class="font-semibold text-navy">{{ $user->user_code }}</span></p>@endif
            <button class="btn-primary">Save</button>
        </form>
        <form method="POST" action="{{ route('profile.password') }}" class="card card-pad space-y-4">
            @csrf @method('PUT')
            <h2 class="section-title">Change password</h2>
            <x-password name="current_password" label="Current password" :policy="false" />
            <x-password label="New password" autocomplete="new-password" />
            <x-password name="password_confirmation" label="Confirm new password" :policy="false" autocomplete="new-password" />
            <button class="btn-primary">Change password</button>
        </form>
    </div>
</x-layouts.workspace>
