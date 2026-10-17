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
    'app_listings' => 'Listings moderation',
    'app_chats' => 'Chatbot conversations',
    'app_security' => 'Security monitoring (sessions, sign-ins)',
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
    'listings' => 'Property listings',
    'quick_launch' => 'Quick launch layout',
    'leads' => 'Leads & enquiries',
    'workspace_bot' => 'Workspace assistant (AI)',
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
        'leads' => 'Leads CRM',
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
        'app_support' => 'App Support',
        'property_lister' => 'Property Lister',
    ],

    'roles' => [
        'app_admin' => $grant(array_fill_keys(array_keys($appModules), $all)),

        // Every App module except IAM and Settings (chatbot conversation logs are App Admin / App Support only)
        'app_manager' => $grant(array_fill_keys(array_diff(array_keys($appModules), ['app_iam', 'app_settings', 'audit', 'app_chats', 'app_security']), $all)),

        // App Support: service requests, enquiries, help desk, chatbot conversations and security monitoring
        'app_support' => $grant([
            'app_dashboard' => ['view'],
            'services' => ['view', 'export'],
            'app_enquiries' => ['view', 'create', 'update', 'export'],
            'helpdesk' => ['view', 'create', 'update', 'export'],
            'app_chats' => ['view', 'export'],
            'app_security' => ['view', 'update', 'export'],
            'customers' => ['view'],
            'app_listings' => ['view'],
        ]),

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
            'listings' => $crud,
            'quick_launch' => $cru,
            'leads' => $crud,
            'workspace_bot' => ['view'],
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
            'listings' => ['view'],
            'quick_launch' => ['view'],
            'leads' => $cru,
            'workspace_bot' => ['view'],
            'tickets' => ['view', 'create'],
        ]),

        'tenant_account' => $grant([
            'dashboard' => ['view'],
            'projects' => ['view'],
            'sales' => ['view', 'update', 'export'],
            'refunds' => ['view'],
            'accounts' => $crud,
            'reports' => $ro,
            'workspace_bot' => ['view'],
            'tickets' => ['view', 'create'],
        ]),

        // Independent owner / agent who only lists properties (own workspace, own plans). Not a promoter role.
        'property_lister' => $grant([
            'dashboard' => ['view'],
            'tenant_settings' => ['view', 'update'],
            'listings' => $crud,
            'leads' => $cru,
            'tickets' => ['view', 'create'],
            'workspace_bot' => ['view'],
        ]),

        'support' => $grant([
            'dashboard' => ['view'],
            'tenant_iam' => ['view', 'create', 'update'],
            'projects' => ['view'],
            'launch' => ['view'],
            'bookings' => ['view'],
            'tenant_customers' => ['view'],
            'listings' => ['view'],
            'quick_launch' => ['view'],
            'leads' => ['view', 'create', 'update'],
            'workspace_bot' => ['view'],
            'tickets' => $cru,
        ]),
    ],
];
