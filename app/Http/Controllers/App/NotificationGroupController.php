<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\NotificationGroup;
use App\Models\NotificationGroupEvent;
use App\Models\NotificationGroupMember;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationGroupController extends Controller
{
    public function index(): View
    {
        return view('app.settings.notifications.index', ['groups' => NotificationGroup::query()->withCount(['members', 'events'])->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $group = NotificationGroup::create($request->validate(['name' => ['required', 'string', 'max:120']]) + [
            'channel_email' => $request->boolean('channel_email', true),
            'channel_whatsapp' => $request->boolean('channel_whatsapp'),
        ]);

        return $this->done('Group created.', 'app.settings.notifications.show', $group);
    }

    public function show(NotificationGroup $group): View
    {
        return view('app.settings.notifications.show', [
            'group' => $group->load(['members.user', 'events']),
            'users' => User::query()->inCurrentTenant()->where('status', 'active')->whereIn('role', ['admin', 'sales'])->orderBy('name')->get(),
            'eventOptions' => config('vector7.notification_events'),
        ]);
    }

    public function update(Request $request, NotificationGroup $group): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'events' => ['array'],
            'events.*' => [Rule::in(array_keys(config('vector7.notification_events')))],
        ]);

        DB::transaction(function () use ($group, $data, $request) {
            $group->update(['name' => $data['name'], 'channel_email' => $request->boolean('channel_email'), 'channel_whatsapp' => $request->boolean('channel_whatsapp')]);
            $group->events()->delete();
            foreach ($data['events'] ?? [] as $code) {
                $event = new NotificationGroupEvent(['event_code' => $code]);
                $event->notification_group_id = $group->id;
                $event->save();
            }
        });

        return $this->done('Group updated.');
    }

    public function storeMember(Request $request, NotificationGroup $group): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'name' => ['nullable', 'required_without:user_id', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'],
        ]);

        $member = new NotificationGroupMember($data);
        $member->notification_group_id = $group->id;
        $member->save();

        return $this->done('Member added.');
    }

    public function destroyMember(NotificationGroup $group, NotificationGroupMember $member): RedirectResponse
    {
        $member->delete();

        return $this->done('Member removed.');
    }
}
