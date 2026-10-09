<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\NotificationGroup;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Notification groups (Management, Sales Team, Accounts …); emails go to group members. */
class GroupController extends Controller
{
    public function index()
    {
        return view('ws.iam.groups', ['groups' => NotificationGroup::with('users')->orderBy('name')->get(), 'users' => User::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $g = NotificationGroup::create(['tenant_id' => app(Tenancy::class)->id(), 'name' => $data['name'], 'description' => $data['description'] ?? null, 'is_active' => true]);
        $g->users()->sync(User::whereIn('id', $data['users'] ?? [])->pluck('id'));

        return back()->with('ok', 'Group created.');
    }

    public function update(Request $request, NotificationGroup $group)
    {
        $data = $this->validated($request, $group);
        $group->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'is_active' => $request->boolean('is_active')]);
        $group->users()->sync(User::whereIn('id', $data['users'] ?? [])->pluck('id'));

        return back()->with('ok', 'Group updated.');
    }

    public function destroy(NotificationGroup $group)
    {
        $group->delete();

        return back()->with('ok', 'Group deleted.');
    }

    private function validated(Request $request, ?NotificationGroup $g = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('notification_groups', 'name')->where('tenant_id', app(Tenancy::class)->id())->ignore($g?->id)],
            'description' => 'nullable|string|max:255',
            'users' => 'array',
            'users.*' => 'integer',
        ]);
    }
}
