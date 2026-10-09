<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Custom roles: Tenant Admin combines existing tenant permissions under a new name. */
class RoleController extends Controller
{
    public function index()
    {
        return view('ws.iam.roles', ['roles' => Role::withCount('users')->orderBy('is_system', 'desc')->orderBy('name')->get(), 'catalog' => config('permissions.tenant_modules')]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isTenantAdmin(), 403);
        $data = $this->validated($request);
        Role::create([
            'tenant_id' => app(Tenancy::class)->id(),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'base_role' => $data['base_role'],
            'permissions' => $this->clean($data['permissions'] ?? []),
            'is_system' => false,
        ]);

        return back()->with('ok', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        abort_unless($request->user()->isTenantAdmin(), 403);
        abort_if($role->is_system, 403, 'System roles cannot be changed.');
        $data = $this->validated($request);
        $role->update(['name' => $data['name'], 'base_role' => $data['base_role'], 'permissions' => $this->clean($data['permissions'] ?? [])]);

        return back()->with('ok', 'Role updated.');
    }

    public function destroy(Request $request, Role $role)
    {
        abort_unless($request->user()->isTenantAdmin(), 403);
        abort_if($role->is_system, 403, 'System roles cannot be deleted.');
        if ($role->users()->exists()) {
            return back()->with('error', 'Move the users of this role to another role first.');
        }
        $role->delete();

        return back()->with('ok', 'Role deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:60',
            'base_role' => 'required|in:tenant_manager,tenant_sales,tenant_account,support',
            'permissions' => 'array',
            'permissions.*' => 'string',
        ]);
    }

    /** Only existing tenant permissions may be combined. */
    private function clean(array $keys): array
    {
        return array_values(array_intersect($keys, array_keys(Role::tenantPermissionCatalog())));
    }
}
