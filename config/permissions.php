<?php

/*
|--------------------------------------------------------------------------
| Permission matrix: module × action per role
|--------------------------------------------------------------------------
| Keys are "module.action". Actions: view, create, update, delete, export, approve.
| Enforced by the `perm:` route middleware (every workspace route) and by
| policies / the TenantScope on every query.
| Tenant Admin can build custom roles only from the tenant modules below.
*/

$all = ['view', 'create', 'update', 'delete', 'export', 'approve'];
$crud = ['view', 'create', 'update', 'delete', 'export'];
$cru = ['view', 'create', 'update', 'export'];
$ro = ['view', 'export'];

$grant = function (array $map): array {
    $out = [];
    foreach ($map as $module => $actions) {
        foreach ($actions as $a) {
            $out[] = "$module.$a";
        }
    }

    return $out;
};

$appModules = [
    'app_dashboard' => 'Dashboards',
    'plans' => 'Subscriptions / Plans',
    'subscriptions' => 'Tenant subscriptions & usage',
    'app_iam' => 'App IAM',
    'customers' => 'Customers',
    'app_projects' => 'Projects (all tenants)',
    'app_sales' => 'Booking & Sales (all tenants)',
    'services' => 'Services',
    'marketing' => 'Marketing (AI)',
    'app_enquiries' => 'Enquiries',
    'helpdesk' => 'Help Desk',
    'app_settings' => 'Settings & integrations',
    'prerequisites' => 'Prerequisite masters',
    'seo' => 'SEO settings',
    'audit' => 'Audit log',
];

$tenantModules = [
    'dashboard' => 'Dashboard',
    'tenant_iam' => 'Users & roles',
    'tenant_settings' => 'Tenant settings',
    'masters' => 'Masters (stages, facilities, documents)',
    'projects' => 'Projects',
    'estimates' => 'Estimates & budget',
    'tracking' => 'Project tracking',
    'launch' => 'Project launch & plots',
    'bookings' => 'Bookings',
    'sales' => 'Sales & payments',
    'refunds' => 'Refunds',
    'registration' => 'Registration',
    'tenant_customers' => 'Customers',
    'accounts' => 'Accounts',
    'reports' => 'Reports',
    'promos' => 'Social posts & promo codes',
    'enquiries' => 'Enquiries',
    'tickets' => 'Help desk tickets',
];

return [
    'actions' => $all,

    'app_modules' => $appModules,
    'tenant_modules' => $tenantModules,

    // Plan "enabled modules" checklist maps to these tenant modules (always-on modules are not listed).
    'plan_modules' => [
        'masters' => 'Masters',
        'estimates' => 'Estimates & budget',
        'tracking' => 'Project tracking',
        'launch' => 'Launch & marketplace',
        'bookings' => 'Booking & sales',
        'registration' => 'Registration',
        'accounts' => 'Accounts',
        'reports' => 'Reports',
        'promos' => 'Social posts & promo codes',
        'ai' => 'AI features',
    ],

    'role_labels' => [
        'app_admin' => 'App Admin',
        'app_manager' => 'App Manager',
        'tenant_admin' => 'Tenant Admin',
        'tenant_manager' => 'Tenant Manager',
        'tenant_sales' => 'Tenant Sales',
        'tenant_account' => 'Tenant Account',
        'support' => 'Support',
    ],

    'roles' => [
        'app_admin' => $grant(array_fill_keys(array_keys($appModules), $all)),

        // Every App module except IAM and Settings
        'app_manager' => $grant(array_fill_keys(array_diff(array_keys($appModules), ['app_iam', 'app_settings', 'audit']), $all)),

        'tenant_admin' => $grant(array_fill_keys(array_keys($tenantModules), $all)),

        'tenant_manager' => $grant([
            'dashboard' => ['view'],
            'masters' => $crud,
            'projects' => $cru,
            'estimates' => $crud,
            'tracking' => $cru,
            'launch' => $crud,
            'bookings' => $ro,
            'sales' => $ro,
            'refunds' => ['view'],
            'registration' => ['view'],
            'tenant_customers' => $ro,
            'accounts' => $ro,
            'reports' => $ro,
            'promos' => $crud,
            'enquiries' => $ro,
            'tickets' => ['view', 'create'],
        ]),

        'tenant_sales' => $grant([
            'dashboard' => ['view'],
            'projects' => ['view'],
            'launch' => ['view'],
            'bookings' => $crud,
            'sales' => $cru,
            'refunds' => ['view', 'create'],
            'registration' => $cru,
            'tenant_customers' => ['view', 'create', 'export'],
            'accounts' => ['view', 'create'],
            'enquiries' => $cru,
            'tickets' => ['view', 'create'],
        ]),

        'tenant_account' => $grant([
            'dashboard' => ['view'],
            'projects' => ['view'],
            'sales' => ['view', 'update', 'export'],
            'refunds' => ['view'],
            'accounts' => $crud,
            'reports' => $ro,
            'tickets' => ['view', 'create'],
        ]),

        'support' => $grant([
            'dashboard' => ['view'],
            'tenant_iam' => ['view', 'create', 'update'],
            'projects' => ['view'],
            'launch' => ['view'],
            'bookings' => ['view'],
            'tenant_customers' => ['view'],
            'enquiries' => $cru,
            'tickets' => $cru,
        ]),
    ],
];
