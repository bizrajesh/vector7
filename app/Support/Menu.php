<?php

namespace App\Support;

use App\Models\User;
use App\Services\PlanLimiter;

/** Sidebar navigation, filtered by the signed-in user's permissions and plan modules. */
class Menu
{
    public static function tenant(User $user): array
    {
        $groups = [
            'Overview' => [
                ['Dashboard', 'ws.dashboard', 'home', 'dashboard.view', null],
            ],
            'Projects' => [
                ['Projects', 'ws.projects.index', 'folder', 'projects.view', null],
                ['Launch & plots', 'ws.launch.index', 'rocket', 'launch.view', 'launch'],
            ],
            'Sales' => [
                ['Bookings', 'ws.bookings.index', 'calendar', 'bookings.view', 'bookings'],
                ['Sales & payments', 'ws.sales.index', 'cart', 'sales.view', 'bookings'],
                ['Refunds', 'ws.refunds.index', 'refresh', 'refunds.view', 'bookings'],
                ['Registration', 'ws.registrations.index', 'clipboard', 'registration.view', 'registration'],
                ['Customers', 'ws.customers.index', 'users', 'tenant_customers.view', null],
                ['Enquiries', 'ws.enquiries.index', 'chat', 'enquiries.view', null],
            ],
            'Finance' => [
                ['Accounts', 'ws.accounts.index', 'rupee', 'accounts.view', 'accounts'],
                ['Reports', 'ws.reports.index', 'chart', 'reports.view', 'reports'],
            ],
            'Growth' => [
                ['Posts & promo codes', 'ws.promos.index', 'megaphone', 'promos.view', 'promos'],
                ['Help desk', 'ws.tickets.index', 'lifebuoy', 'tickets.view', null],
            ],
            'Setup' => [
                ['Masters', 'ws.masters.index', 'layers', 'masters.view', 'masters'],
                ['Users & roles', 'ws.iam.index', 'shield', 'tenant_iam.view', null],
                ['Settings', 'ws.settings.index', 'cog', 'tenant_settings.view', null],
            ],
        ];
        $tenant = $user->tenant;

        return self::filter($groups, $user, fn ($module) => $module === null || ($tenant && PlanLimiter::moduleEnabled($tenant, $module)));
    }

    public static function app(User $user): array
    {
        $groups = [
            'Overview' => [
                ['Dashboards', 'app.dashboard', 'home', 'app_dashboard.view', null],
            ],
            'Business' => [
                ['Tenants & usage', 'app.subscriptions.index', 'building', 'subscriptions.view', null],
                ['Plans', 'app.plans.index', 'tag', 'plans.view', null],
                ['Customers', 'app.customers.index', 'users', 'customers.view', null],
                ['Projects', 'app.projects.index', 'folder', 'app_projects.view', null],
                ['Bookings & sales', 'app.sales.index', 'cart', 'app_sales.view', null],
            ],
            'Engage' => [
                ['Services', 'app.services.index', 'briefcase', 'services.view', null],
                ['Enquiries', 'app.enquiries.index', 'chat', 'app_enquiries.view', null],
                ['Help desk', 'app.tickets.index', 'lifebuoy', 'helpdesk.view', null],
                ['Marketing', 'app.marketing.index', 'megaphone', 'marketing.view', null],
            ],
            'Platform' => [
                ['Prerequisites', 'app.masters.index', 'layers', 'prerequisites.view', null],
                ['SEO', 'app.seo.index', 'globe', 'seo.view', null],
                ['IAM', 'app.iam.index', 'shield', 'app_iam.view', null],
                ['Settings', 'app.settings.index', 'cog', 'app_settings.view', null],
                ['Audit log', 'app.audit.index', 'database', 'audit.view', null],
            ],
        ];

        return self::filter($groups, $user, fn () => true);
    }

    private static function filter(array $groups, User $user, callable $moduleOk): array
    {
        $out = [];
        foreach ($groups as $group => $items) {
            $visible = array_values(array_filter($items, fn ($i) => $user->hasPerm($i[3]) && $moduleOk($i[4])));
            if ($visible) {
                $out[$group] = array_map(fn ($i) => ['label' => $i[0], 'route' => $i[1], 'icon' => $i[2]], $visible);
            }
        }

        return $out;
    }
}
