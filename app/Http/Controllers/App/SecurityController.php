<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginEvents;
use App\Services\LoginService;
use App\Support\Excel;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Security monitoring for the App team (App Admin / App Support):
 * signed-in sessions with IP and device (end any of them), every sign-in attempt, IP addresses with many failed
 * attempts, and locked accounts (unlock). Passwords are never stored or shown.
 */
class SecurityController extends Controller
{
    public const TABS = ['sessions' => 'Signed-in sessions', 'activity' => 'Sign-in activity', 'ips' => 'Failed attempts by IP', 'locked' => 'Locked accounts'];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'sessions';
        $data = ['tab' => $tab, 'stats' => $this->stats()];

        return app(Tenancy::class)->withoutScope(function () use ($tab, $request, $data) {
            if ($tab === 'activity') {
                $q = $this->activityQuery($request);
                if ($request->query('export') === 'xlsx' && $request->user()->hasPerm('app_security.export')) {
                    return Excel::download('sign-in-activity-'.now()->format('Ymd').'.xlsx', ['When', 'Portal', 'Email', 'Outcome', 'Reason', 'IP', 'Device'],
                        $q->limit(5000)->get()->map(fn ($e) => [$e->created_at->format('d-m-Y H:i:s'), LoginEvent::PORTALS[$e->portal] ?? $e->portal, $e->email,
                            LoginEvent::OUTCOMES[$e->outcome] ?? $e->outcome, $e->reason, $e->ip, LoginEvents::device($e->user_agent)])->all(), 'Sign-in activity');
                }
                $data['events'] = $q->paginate(50)->withQueryString();
            } elseif ($tab === 'ips') {
                $hours = in_array((int) $request->query('hours'), [1, 24, 168], true) ? (int) $request->query('hours') : 24;
                $data['hours'] = $hours;
                $data['ips'] = LoginEvent::whereIn('outcome', ['failed', 'locked'])->where('created_at', '>=', now()->subHours($hours))
                    ->selectRaw('ip, count(*) as attempts, count(distinct email) as emails, max(created_at) as last_at')
                    ->groupBy('ip')->orderByDesc('attempts')->limit(100)->get();
                $data['emails'] = LoginEvent::whereIn('outcome', ['failed', 'locked'])->where('created_at', '>=', now()->subHours($hours))
                    ->selectRaw('email, portal, count(*) as attempts, count(distinct ip) as ips, max(created_at) as last_at')
                    ->groupBy('email', 'portal')->orderByDesc('attempts')->limit(50)->get();
            } elseif ($tab === 'locked') {
                $data['users'] = User::withoutGlobalScopes()->with(['tenant:id,name,code', 'role' => fn ($r) => $r->withoutGlobalScopes()])->where('locked_until', '>', now())->orderBy('locked_until')->get();
                $data['customers'] = Customer::where('locked_until', '>', now())->orderBy('locked_until')->get();
            } else {
                $data['sessions'] = $this->sessions($request);
            }

            return view('app.security.index', $data);
        });
    }

    /** End one signed-in session (the person is signed out on their next click). */
    public function endSession(Request $request, string $id)
    {
        abort_if($id === $request->session()->getId(), 422, 'This is your own session — use Sign out instead.');
        $row = DB::table('sessions')->where('id', $id)->first();
        abort_unless($row, 404);
        $who = LoginEvent::where('session_id', $id)->where('outcome', 'success')->latest('id')->first();
        DB::table('sessions')->where('id', $id)->delete();
        AuditLogger::log('session_ended', null, null, ['user_id' => $row->user_id, 'email' => $who?->email, 'ip' => $row->ip_address]);

        return back()->with('ok', 'Session ended. '.($who?->email ?? 'The user').' has to sign in again.');
    }

    public function unlock(Request $request)
    {
        $data = $request->validate(['type' => 'required|in:user,customer', 'id' => 'required|integer']);
        $account = $data['type'] === 'user' ? User::withoutGlobalScopes()->findOrFail($data['id']) : Customer::findOrFail($data['id']);
        LoginService::unlock($account);

        return back()->with('ok', $account->email.' unlocked.');
    }

    private function stats(): array
    {
        $today = now()->startOfDay();

        return [
            'sessions' => DB::table('sessions')->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->timestamp)
                ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereIn('id', LoginEvent::select('session_id')->whereNotNull('session_id')))->count(),
            'success' => LoginEvent::where('outcome', 'success')->where('created_at', '>=', $today)->count(),
            'failed' => LoginEvent::whereIn('outcome', ['failed', 'locked', 'blocked'])->where('created_at', '>=', $today)->count(),
            'locked' => User::withoutGlobalScopes()->where('locked_until', '>', now())->count() + Customer::where('locked_until', '>', now())->count(),
            'suspicious' => LoginEvent::whereIn('outcome', ['failed', 'locked'])->where('created_at', '>=', now()->subDay())
                ->selectRaw('ip')->groupBy('ip')->havingRaw('count(*) >= 10')->get()->count(),
        ];
    }

    private function activityQuery(Request $request)
    {
        return LoginEvent::with('tenantRel:id,name,code')
            ->when($request->query('outcome'), fn ($q, $o) => $o === 'problem' ? $q->whereIn('outcome', ['failed', 'locked', 'blocked']) : $q->where('outcome', $o))
            ->when($request->query('portal'), fn ($q, $p) => $q->where('portal', $p))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('email', 'like', '%'.strtolower($s).'%')->orWhere('ip', 'like', $s.'%')))
            ->when($request->date('from'), fn ($q, $d) => $q->where('created_at', '>=', $d->startOfDay()))
            ->when($request->date('to'), fn ($q, $d) => $q->where('created_at', '<=', $d->endOfDay()))
            ->latest('id');
    }

    /** Active signed-in sessions: staff by sessions.user_id, buyers by the sign-in record that created the session. */
    private function sessions(Request $request)
    {
        $since = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;
        $rows = DB::table('sessions')->where('last_activity', '>=', $since)->orderByDesc('last_activity')->limit(500)->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);
        $events = LoginEvent::whereIn('session_id', $rows->pluck('id'))->where('outcome', 'success')->latest('id')->get()->unique('session_id')->keyBy('session_id');
        $users = User::withoutGlobalScopes()->with(['tenant:id,name,code', 'role' => fn ($r) => $r->withoutGlobalScopes()])->whereIn('id', $rows->pluck('user_id')->filter())->get()->keyBy('id');
        $customers = Customer::whereIn('id', $events->where('account_type', 'customer')->pluck('account_id'))->get()->keyBy('id');
        $portal = $request->query('portal');

        return $rows->map(function ($r) use ($events, $users, $customers) {
            $e = $events[$r->id] ?? null;
            $u = $r->user_id ? ($users[$r->user_id] ?? null) : null;
            $c = ! $u && $e?->account_type === 'customer' ? ($customers[$e->account_id] ?? null) : null;
            if (! $u && ! $c) {
                return null; // anonymous visitor
            }

            return (object) [
                'id' => $r->id, 'ip' => $r->ip_address, 'device' => LoginEvents::device($r->user_agent), 'last' => \Carbon\Carbon::createFromTimestamp($r->last_activity),
                'since' => $e?->created_at, 'portal' => $e?->portal ?? ($u?->isAppUser() ? 'app' : ($u?->isLister() ? 'lister' : 'promoter')),
                'name' => $u?->name ?? $c?->name, 'email' => $u?->email ?? $c?->email,
                'where' => $u ? ($u->isAppUser() ? 'vector7 App · '.($u->role?->name ?? '') : ($u->tenant?->name.' · '.($u->role?->name ?? ''))) : 'Buyer account',
                'ipChanged' => $e && $e->ip && $e->ip !== $r->ip_address,
            ];
        })->filter()->when($portal, fn ($c) => $c->where('portal', $portal))->values();
    }
}
