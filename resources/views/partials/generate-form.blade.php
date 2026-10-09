{{-- Generate-password action with options (used on every IAM screen). --}}
<details class="relative" data-dropdown>
    <summary class="btn-light btn-sm list-none cursor-pointer"><x-icon name="key" class="h-4 w-4" /> Generate password</summary>
    <form method="POST" action="{{ $action }}" class="absolute right-0 z-20 mt-2 w-72 space-y-3 rounded-xl bg-white p-4 text-left shadow-lift ring-1 ring-navy-50">
        @csrf
        <p class="text-sm font-semibold">Create a new 12-character password for {{ $name }}?</p>
        <input type="hidden" name="must_change" value="0">
        <x-checkbox name="must_change" label="Ask user to change password at next login" :checked="true" />
        <x-checkbox name="email_user" label="Email it to the user" />
        <button class="btn-primary btn-sm w-full">Generate</button>
    </form>
</details>
