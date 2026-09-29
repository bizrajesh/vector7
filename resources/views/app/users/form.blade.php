<x-layouts.app :title="$user->exists ? 'Edit user' : 'Add user'">
    <x-page-header :title="$user->exists ? 'Edit '.$user->name : 'Add user'" :back="route('app.users.index')" />
    <form method="POST" action="{{ $user->exists ? route('app.users.update', $user) : route('app.users.store') }}" class="card-pad grid max-w-2xl gap-4 sm:grid-cols-2">
        @csrf @if ($user->exists) @method('PUT') @endif
        <x-field name="name" label="Full name" :value="$user->name" required class="sm:col-span-2" />
        <x-field name="email" type="email" label="Email" :value="$user->email" required />
        <x-field name="phone" type="tel" label="Mobile" :value="$user->phone" inputmode="tel" />
        <x-select name="role" label="Role" :options="collect(config('vector7.tenant_roles'))->mapWithKeys(fn ($r) => [$r => config('vector7.roles.'.$r)])" :value="$user->role?->value ?? 'sales'" required />
        @if ($user->exists)
            <x-select name="status" label="Status" :options="['active' => 'Active', 'disabled' => 'Disabled']" :value="$user->status" required />
        @endif
        <div data-show-when="role=shareholder" class="sm:col-span-2">
            <x-select name="shareholder_id" label="Linked shareholder" :options="$options['shareholders']->pluck('name', 'id')" :value="$user->shareholder_id" placeholder="Choose shareholder" />
            <p class="help">Add shareholders under Manage Shares first.</p>
        </div>
        <div data-show-when="role=customer" class="sm:col-span-2">
            <x-select name="customer_id" label="Linked customer" :options="$options['customers']->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->phone])" :value="$user->customer_id" placeholder="Choose customer" />
        </div>
        @unless ($user->exists)
            <p class="text-sm text-ink-muted sm:col-span-2">The user receives an email to set their own password. Admins never see or choose passwords.</p>
        @endunless
        <div class="sm:col-span-2"><button type="submit" class="btn-primary">Save user</button></div>
    </form>
</x-layouts.app>
