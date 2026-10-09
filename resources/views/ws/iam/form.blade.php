<x-layouts.workspace :title="$user->exists ? 'Edit user' : 'Add user'">
    <x-page-header :title="$user->exists ? 'Edit '.$user->name : 'Add user'" :back="route('ws.iam.index')" />
    <form method="POST" action="{{ $user->exists ? route('ws.iam.update', $user) : route('ws.iam.store') }}" class="card card-pad grid max-w-3xl gap-4 sm:grid-cols-2">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <x-field name="name" label="Full name" :value="$user->name" required />
        <x-field name="email" type="email" label="Email" :value="$user->email" required />
        <x-field name="mobile" label="Mobile" :value="$user->mobile" inputmode="numeric" maxlength="10" />
        <x-select name="role_id" label="Role" :options="$roles->pluck('name', 'id')" :value="$user->role_id" required placeholder="Choose a role" />
        <fieldset class="sm:col-span-2">
            <legend class="label">Notification groups</legend>
            <div class="mt-1 flex flex-wrap gap-4">
                @foreach ($groups as $g)
                    <x-checkbox name="groups[]" :value="$g->id" :label="$g->name" :checked="in_array($g->id, old('groups', $user->exists ? $user->groups->pluck('id')->all() : []))" />
                @endforeach
            </div>
        </fieldset>
        <input type="hidden" name="is_active" value="0">
        <x-checkbox name="is_active" label="Active (can sign in)" :checked="$user->is_active ?? true" class="sm:col-span-2" />
        @unless ($user->exists)
            <fieldset class="sm:col-span-2 rounded-xl bg-page p-4">
                <legend class="label px-1">How will the user get a password?</legend>
                <label class="mt-2 flex items-center gap-2 text-sm"><input type="radio" name="password_mode" value="link" class="text-teal-700 focus:ring-teal" @checked(old('password_mode', 'link') === 'link')> Email a set-password link (valid 60 minutes)</label>
                <label class="mt-2 flex items-center gap-2 text-sm"><input type="radio" name="password_mode" value="generate" class="text-teal-700 focus:ring-teal" @checked(old('password_mode') === 'generate')> Generate a password now and show it once</label>
                <div class="mt-3 space-y-2 pl-6" data-show-when="password_mode=generate">
                    <input type="hidden" name="must_change" value="0">
                    <x-checkbox name="must_change" label="Ask user to change password at next login" :checked="true" />
                    <x-checkbox name="email_user" label="Also email it to the user" />
                </div>
            </fieldset>
        @endunless
        <div class="sm:col-span-2 flex gap-2"><button class="btn-primary">{{ $user->exists ? 'Save changes' : 'Create user' }}</button><a href="{{ route('ws.iam.index') }}" class="btn-ghost">Cancel</a></div>
    </form>
</x-layouts.workspace>
