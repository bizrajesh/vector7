<?php

/*
|--------------------------------------------------------------------------
| Vector7 application configuration
|--------------------------------------------------------------------------
| Roles, the permission matrix (section 3 of the prompt), default tenant
| settings (section 4.1) and seed data for new tenants.
*/

return [

    'support_email' => env('XAXIS_SUPPORT_EMAIL', 'support@example.com'),

    'require_email_verification' => (bool) env('XAXIS_REQUIRE_EMAIL_VERIFICATION', true),

    'roles' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'sales' => 'Sales',
        'shareholder' => 'Shareholder',
        'customer' => 'Customer',
    ],

    // Roles an Admin can assign to users inside their tenant.
    'tenant_roles' => ['admin', 'sales', 'shareholder', 'customer'],

    /*
    | Permission matrix. Deny by default: a permission not listed for a role
    | is refused. Record-level ownership (shareholder/customer "own only") is
    | enforced in the portal controllers by scoping queries to the user's link.
    */
    'permissions' => [
        'admin' => [
            'dashboard.view', 'users.manage', 'settings.manage',
            'layouts.view', 'layouts.manage', 'stages.execute', 'expenses.record',
            'plots.view', 'plots.manage', 'customers.view', 'customers.manage',
            'bookings.create', 'sales.create', 'payments.record', 'registrations.manage', 'requests.manage',
            'shares.view', 'shares.allocate', 'shares.payout',
            'ledger.view', 'ledger.manage', 'analytics.view',
        ],
        'sales' => [
            'dashboard.view', 'layouts.view', 'plots.view', 'customers.view', 'customers.manage',
            'bookings.create', 'sales.create', 'payments.record', 'registrations.manage', 'requests.manage',
            'ledger.view.sales', 'analytics.view.sales',
        ],
        'shareholder' => ['portal.shareholder'],
        'customer' => ['portal.customer'],
        'super_admin' => ['platform.manage'],
    ],

    'tenant_defaults' => [
        'sellable_pct' => 55,
        'broker_commission_pct' => 0.5,
        'budget_alert_pct' => 80,
        'booking_validity_days' => 15,
        'online_hold_hours' => 48,
        'sale_window_working_days' => 15,
        'instalments' => [
            ['pct' => 30, 'due_working_day' => 0],
            ['pct' => 60, 'due_working_day' => 7],
            ['pct' => 10, 'due_working_day' => 15],
        ],
        'share_face_value' => 1000,
        'share_recognition' => 'sold', // 'sold' or 'ror'
        'storage' => 'local',
        'drive_folder_id' => null,
    ],

    'seed' => [
        'facilities' => [
            ['Park', 'sqft', true], ['Bore', 'nos', false], ['Overhead Tank', 'nos', false],
            ['Road', 'sqft', true], ['Electricity', 'lumpsum', false], ['Rain Water / Storm Drainage', 'rft', false],
            ['Walk Track', 'rft', false], ['Greens', 'sqft', true], ['Children Play Area', 'sqft', true],
            ['Common Area', 'sqft', true], ['Sign Boards', 'nos', false], ['Entry Arch', 'nos', false],
            ['Fencing Walls', 'rft', false],
        ],
        'registration_checklist' => [
            ['Property location', false, false], ['Survey No', false, false], ['Plot Number', false, false],
            ['Four boundary details', false, false], ['Geo-tagged photo of the property', true, false],
            ['Patta', true, false], ['Chitta', true, false], ['EC from 1989', true, false],
            ['Buyer Aadhaar', true, false], ['Buyer PAN', true, false],
            ['Seller Aadhaar', true, false], ['Seller PAN', true, false],
            ['Two witnesses with Aadhaar', true, false], ['Registration date', false, true],
            ['Verified by / date', false, true],
        ],
        'ledger_categories' => [
            ['Partner contribution', 'in'], ['Plot sales', 'in'], ['Land purchase', 'out'],
            ['Approvals & NOC fees', 'out'], ['Survey & legal', 'out'], ['Civil works', 'out'],
            ['Broker commission', 'out'], ['Refunds', 'out'], ['Shareholder payouts', 'out'], ['Other', 'out'],
        ],
    ],

    'notification_events' => [
        'stage.started' => 'Stage started', 'stage.completed' => 'Stage completed', 'stage.overdue' => 'Stage overdue',
        'budget.threshold' => 'Budget threshold crossed', 'booking.created' => 'Booking created',
        'booking.expiring' => 'Booking expiring', 'booking.expired' => 'Booking expired',
        'instalment.due' => 'Instalment due', 'instalment.overdue' => 'Instalment overdue',
        'plot.ror' => 'Plot ready for registration', 'registration.completed' => 'Registration completed',
        'investment.recorded' => 'Investment recorded',
        'booking.online' => 'Online booking hold (awaiting advance)',
        'purchase.requested' => 'Purchase / call-back request',
    ],

    'uploads' => [
        'max_kb' => 10240,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],
];
