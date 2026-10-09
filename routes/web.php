<?php

use App\Http\Controllers\App;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Customer;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Market;
use App\Http\Controllers\Tenant;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketplace
|--------------------------------------------------------------------------
*/
Route::get('/', [Market\HomeController::class, 'home'])->name('home');
Route::get('/projects', [Market\ProjectController::class, 'index'])->name('market.projects');
Route::get('/projects/{project}/plots/{plotNo}', [Market\ProjectController::class, 'plot'])->name('market.plot')->where('plotNo', '[A-Za-z0-9\-]+');
Route::get('/projects/{project}/layout', [Market\ProjectController::class, 'layout'])->name('market.layout');
Route::get('/projects/{project}/gallery/{file}', [Market\ProjectController::class, 'gallery'])->name('market.gallery');
Route::get('/projects/{location}/{project}', [Market\ProjectController::class, 'show'])->name('market.project');
Route::get('/projects/{project}', [Market\ProjectController::class, 'legacy'])->name('market.project.legacy');
Route::get('/services', [Market\PageController::class, 'services'])->name('market.services');
Route::get('/services/{slug}', [Market\PageController::class, 'service'])->name('market.service');
Route::get('/about', [Market\PageController::class, 'about'])->name('market.about');
Route::get('/support', [Market\PageController::class, 'support'])->name('market.support');
Route::post('/support', [Market\PageController::class, 'contact'])->middleware('throttle:enquiry')->name('market.contact');
Route::get('/for-promoters', [Market\PageController::class, 'pricing'])->name('market.pricing');
Route::get('/privacy', [Market\PageController::class, 'privacy'])->name('market.privacy');
Route::get('/terms', [Market\PageController::class, 'terms'])->name('market.terms');
Route::post('/enquiry', [Market\EnquiryController::class, 'store'])->middleware('throttle:enquiry')->name('market.enquiry');
Route::match(['get', 'post'], '/find-a-plot', [Market\RequirementController::class, 'search'])->middleware('throttle:ai')->name('market.requirement');
Route::get('/sitemap.xml', [Market\SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [Market\SeoController::class, 'robots'])->name('robots');
Route::get('/tenant-logo/{tenant}', [FileController::class, 'tenantLogo'])->name('files.public-logo');
Route::post('/webhooks/payment/{gateway}', [WebhookController::class, 'payment'])->name('webhooks.payment');

/*
|--------------------------------------------------------------------------
| Customer accounts (guard: customer) — /login
|--------------------------------------------------------------------------
*/
Route::middleware('guest:customer')->group(function () {
    Route::get('/login', [Auth\CustomerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Auth\CustomerAuthController::class, 'login'])->middleware('throttle:login')->name('customer.login');
    Route::get('/register', [Auth\CustomerAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [Auth\CustomerAuthController::class, 'register'])->middleware('throttle:register')->name('customer.register');
    Route::get('/forgot-password', [Auth\CustomerAuthController::class, 'showForgot'])->name('customer.password.request');
    Route::post('/forgot-password', [Auth\CustomerAuthController::class, 'sendReset'])->middleware('throttle:forgot')->name('customer.password.email');
    Route::get('/reset-password/{token}', [Auth\CustomerAuthController::class, 'showReset'])->name('customer.password.reset');
    Route::post('/reset-password', [Auth\CustomerAuthController::class, 'reset'])->middleware('throttle:forgot')->name('customer.password.update');
});
Route::post('/logout', [Auth\CustomerAuthController::class, 'logout'])->name('customer.logout');

Route::middleware(['auth:customer', 'auth.session', 'customer'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', [Customer\AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/bookings', [Customer\AccountController::class, 'bookings'])->name('bookings');
    Route::get('/purchases', [Customer\AccountController::class, 'purchases'])->name('purchases');
    Route::get('/payments', [Customer\AccountController::class, 'payments'])->name('payments');
    Route::get('/receipts/{payment}', [Customer\AccountController::class, 'receipt'])->name('receipt');
    Route::get('/documents', [Customer\AccountController::class, 'documents'])->name('documents');
    Route::get('/files/{file}', [FileController::class, 'customerFile'])->name('file');
    Route::get('/enquiries', [Customer\AccountController::class, 'enquiries'])->name('enquiries');
    Route::get('/tickets', [Customer\TicketController::class, 'index'])->name('tickets');
    Route::post('/tickets', [Customer\TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [Customer\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [Customer\TicketController::class, 'reply'])->name('tickets.reply');
    Route::post('/services/{service}/request', [Customer\TicketController::class, 'requestService'])->name('services.request');
    Route::get('/requirements', [Customer\AccountController::class, 'requirements'])->name('requirements');
    Route::post('/requirements', [Market\RequirementController::class, 'save'])->name('requirements.save');
    Route::delete('/requirements/{requirement}', [Market\RequirementController::class, 'destroy'])->name('requirements.destroy');
    Route::get('/profile', [Customer\AccountController::class, 'profile'])->name('profile');
    Route::put('/profile', [Customer\AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('/password', [Customer\AccountController::class, 'updatePassword'])->name('password');
    Route::get('/book/{plot}', [Customer\BookingController::class, 'create'])->name('book');
    Route::post('/book/{plot}', [Customer\BookingController::class, 'store'])->name('book.store');
});
Route::middleware(['auth:customer', 'auth.session'])->group(function () {
    Route::get('/account/change-password', [Auth\CustomerAuthController::class, 'showForceChange'])->name('account.force-password');
    Route::post('/account/change-password', [Auth\CustomerAuthController::class, 'forceChange'])->name('account.force-password.update');
});

/*
|--------------------------------------------------------------------------
| Staff login (App users and tenant users) — /workspace/login
|--------------------------------------------------------------------------
*/
Route::prefix('workspace')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [Auth\StaffAuthController::class, 'showLogin'])->name('staff.login');
        Route::post('/login', [Auth\StaffAuthController::class, 'login'])->middleware('throttle:login')->name('staff.login.post');
        Route::get('/create', [Auth\SignupController::class, 'show'])->name('signup');
        Route::post('/create', [Auth\SignupController::class, 'store'])->middleware('throttle:register')->name('signup.store');
        Route::get('/forgot-password', [Auth\StaffAuthController::class, 'showForgot'])->name('password.request');
        Route::post('/forgot-password', [Auth\StaffAuthController::class, 'sendReset'])->middleware('throttle:forgot')->name('password.email');
        Route::get('/reset-password/{token}', [Auth\StaffAuthController::class, 'showReset'])->name('password.reset');
        Route::post('/reset-password', [Auth\StaffAuthController::class, 'reset'])->middleware('throttle:forgot')->name('password.update');
    });
    Route::post('/logout', [Auth\StaffAuthController::class, 'logout'])->name('staff.logout');
    Route::middleware(['auth:web', 'auth.session'])->group(function () {
        Route::get('/change-password', [Auth\StaffAuthController::class, 'showForceChange'])->name('staff.force-password');
        Route::post('/change-password', [Auth\StaffAuthController::class, 'forceChange'])->name('staff.force-password.update');
    });
});

Route::middleware(['auth:web', 'auth.session', 'staff'])->group(function () {
    Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');
    Route::get('/profile', [Auth\ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [Auth\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Auth\ProfileController::class, 'password'])->name('profile.password');
});

/*
|--------------------------------------------------------------------------
| Tenant workspace — /workspace/...
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web', 'auth.session', 'staff', 'tenant'])->prefix('workspace')->name('ws.')->group(function () {
    Route::get('/', [Tenant\DashboardController::class, 'index'])->middleware('perm:dashboard.view')->name('dashboard');
    Route::get('/notifications', [Tenant\DashboardController::class, 'notifications'])->name('notifications');

    // Setup wizard
    Route::get('/setup', [Tenant\SetupController::class, 'show'])->middleware('perm:tenant_settings.update')->name('setup');
    Route::post('/setup/profile', [Tenant\SetupController::class, 'profile'])->middleware('perm:tenant_settings.update')->name('setup.profile');
    Route::post('/setup/template', [Tenant\SetupController::class, 'template'])->middleware('perm:tenant_settings.update')->name('setup.template');
    Route::post('/setup/defaults', [Tenant\SetupController::class, 'defaults'])->middleware('perm:tenant_settings.update')->name('setup.defaults');
    Route::post('/setup/finish', [Tenant\SetupController::class, 'finish'])->middleware('perm:tenant_settings.update')->name('setup.finish');
    Route::get('/template/download', [Tenant\SetupController::class, 'download'])->middleware('perm:tenant_settings.view')->name('template.download');
    Route::get('/template/export', [Tenant\SetupController::class, 'export'])->middleware('perm:masters.export')->name('template.export');

    // IAM
    Route::get('/users', [Tenant\IamController::class, 'index'])->middleware('perm:tenant_iam.view')->name('iam.index');
    Route::get('/users/create', [Tenant\IamController::class, 'create'])->middleware('perm:tenant_iam.create')->name('iam.create');
    Route::post('/users', [Tenant\IamController::class, 'store'])->middleware('perm:tenant_iam.create')->name('iam.store');
    Route::get('/users/{user}/edit', [Tenant\IamController::class, 'edit'])->middleware('perm:tenant_iam.update')->name('iam.edit');
    Route::put('/users/{user}', [Tenant\IamController::class, 'update'])->middleware('perm:tenant_iam.update')->name('iam.update');
    Route::delete('/users/{user}', [Tenant\IamController::class, 'destroy'])->middleware('perm:tenant_iam.delete')->name('iam.destroy');
    Route::post('/users/{user}/generate-password', [Tenant\IamController::class, 'generatePassword'])->middleware(['perm:tenant_iam.update', 'throttle:genpass'])->name('iam.generate');
    Route::post('/users/{user}/reset-link', [Tenant\IamController::class, 'resetLink'])->middleware('perm:tenant_iam.update')->name('iam.reset-link');
    Route::post('/users/{user}/unlock', [Tenant\IamController::class, 'unlock'])->middleware('perm:tenant_iam.update')->name('iam.unlock');
    Route::post('/users/bulk-passwords', [Tenant\IamController::class, 'bulkPasswords'])->middleware(['perm:tenant_iam.update', 'throttle:genpass'])->name('iam.bulk');
    Route::post('/users/import', [Tenant\IamController::class, 'import'])->middleware('perm:tenant_iam.create')->name('iam.import');
    Route::get('/roles', [Tenant\RoleController::class, 'index'])->middleware('perm:tenant_iam.view')->name('roles.index');
    Route::post('/roles', [Tenant\RoleController::class, 'store'])->middleware('perm:tenant_iam.create')->name('roles.store');
    Route::put('/roles/{role}', [Tenant\RoleController::class, 'update'])->middleware('perm:tenant_iam.update')->name('roles.update');
    Route::delete('/roles/{role}', [Tenant\RoleController::class, 'destroy'])->middleware('perm:tenant_iam.delete')->name('roles.destroy');
    Route::get('/groups', [Tenant\GroupController::class, 'index'])->middleware('perm:tenant_iam.view')->name('groups.index');
    Route::post('/groups', [Tenant\GroupController::class, 'store'])->middleware('perm:tenant_iam.create')->name('groups.store');
    Route::put('/groups/{group}', [Tenant\GroupController::class, 'update'])->middleware('perm:tenant_iam.update')->name('groups.update');
    Route::delete('/groups/{group}', [Tenant\GroupController::class, 'destroy'])->middleware('perm:tenant_iam.delete')->name('groups.destroy');

    // Settings
    Route::get('/settings', [Tenant\SettingsController::class, 'index'])->middleware('perm:tenant_settings.view')->name('settings.index');
    Route::put('/settings/organisation', [Tenant\SettingsController::class, 'organisation'])->middleware('perm:tenant_settings.update')->name('settings.organisation');
    Route::put('/settings/sales', [Tenant\SettingsController::class, 'sales'])->middleware('perm:tenant_settings.update')->name('settings.sales');
    Route::put('/settings/disclaimers', [Tenant\SettingsController::class, 'disclaimers'])->middleware('perm:tenant_settings.update')->name('settings.disclaimers');
    Route::put('/settings/ids', [Tenant\SettingsController::class, 'ids'])->middleware('perm:tenant_settings.update')->name('settings.ids');
    Route::post('/settings/holidays', [Tenant\SettingsController::class, 'addHoliday'])->middleware('perm:tenant_settings.update')->name('settings.holidays.store');
    Route::delete('/settings/holidays/{holiday}', [Tenant\SettingsController::class, 'deleteHoliday'])->middleware('perm:tenant_settings.update')->name('settings.holidays.destroy');
    Route::get('/settings/plan', [Tenant\PlanController::class, 'show'])->middleware('perm:tenant_settings.view')->name('settings.plan');
    Route::post('/settings/plan/{plan}', [Tenant\PlanController::class, 'upgrade'])->middleware('perm:tenant_settings.update')->name('settings.plan.upgrade');
    Route::get('/settings/invoices/{invoice}', [Tenant\PlanController::class, 'invoice'])->middleware('perm:tenant_settings.view')->name('settings.invoice');
    Route::post('/settings/invoices/{invoice}/verify', [Tenant\PlanController::class, 'verify'])->middleware('perm:tenant_settings.update')->name('settings.invoice.verify');
    Route::get('/brokers', [Tenant\BrokerController::class, 'index'])->middleware('perm:sales.view')->name('brokers.index');
    Route::post('/brokers', [Tenant\BrokerController::class, 'store'])->middleware('perm:sales.create')->name('brokers.store');
    Route::put('/brokers/{broker}', [Tenant\BrokerController::class, 'update'])->middleware('perm:sales.update')->name('brokers.update');

    // Masters (tenant copies)
    Route::get('/masters/{type?}', [Tenant\MasterController::class, 'index'])->middleware(['perm:masters.view', 'module:masters'])->name('masters.index')->where('type', 'stages|facilities|documents|sros');
    Route::post('/masters/{type}', [Tenant\MasterController::class, 'store'])->middleware(['perm:masters.create', 'module:masters'])->name('masters.store');
    Route::put('/masters/{type}/{id}', [Tenant\MasterController::class, 'update'])->middleware(['perm:masters.update', 'module:masters'])->name('masters.update');
    Route::delete('/masters/{type}/{id}', [Tenant\MasterController::class, 'destroy'])->middleware(['perm:masters.delete', 'module:masters'])->name('masters.destroy');
    Route::post('/masters-import', [Tenant\SetupController::class, 'template'])->middleware(['perm:masters.create', 'module:masters'])->name('masters.import');

    // Projects
    Route::get('/projects', [Tenant\ProjectController::class, 'index'])->middleware('perm:projects.view')->name('projects.index');
    Route::get('/projects/create', [Tenant\ProjectController::class, 'create'])->middleware('perm:projects.create')->name('projects.create');
    Route::post('/projects', [Tenant\ProjectController::class, 'store'])->middleware('perm:projects.create')->name('projects.store');
    Route::get('/projects/{project}', [Tenant\ProjectController::class, 'show'])->middleware('perm:projects.view')->name('projects.show');
    Route::get('/projects/{project}/edit', [Tenant\ProjectController::class, 'edit'])->middleware('perm:projects.update')->name('projects.edit');
    Route::put('/projects/{project}', [Tenant\ProjectController::class, 'update'])->middleware('perm:projects.update')->name('projects.update');
    Route::delete('/projects/{project}', [Tenant\ProjectController::class, 'destroy'])->middleware('perm:projects.delete')->name('projects.destroy');
    Route::get('/projects/{project}/estimate', [Tenant\EstimateController::class, 'edit'])->middleware(['perm:estimates.view', 'module:estimates'])->name('estimate.edit');
    Route::post('/projects/{project}/estimate', [Tenant\EstimateController::class, 'save'])->middleware(['perm:estimates.update', 'module:estimates'])->name('estimate.save');
    Route::get('/projects/{project}/estimate/export/{format}', [Tenant\EstimateController::class, 'export'])->middleware(['perm:estimates.export', 'module:estimates'])->name('estimate.export');
    Route::post('/projects/{project}/decision', [Tenant\ProjectController::class, 'decision'])->middleware('perm:projects.approve')->name('projects.decision');
    Route::post('/projects/{project}/start', [Tenant\ProjectController::class, 'start'])->middleware('perm:projects.update')->name('projects.start');
    Route::get('/projects/{project}/tracking', [Tenant\TrackingController::class, 'show'])->middleware(['perm:tracking.view', 'module:tracking'])->name('tracking.show');
    Route::put('/projects/{project}/subtasks/{subtask}', [Tenant\TrackingController::class, 'update'])->middleware(['perm:tracking.update', 'module:tracking'])->name('tracking.update');
    Route::post('/projects/{project}/documents/{document}', [Tenant\TrackingController::class, 'upload'])->middleware(['perm:tracking.update', 'module:tracking'])->name('tracking.upload');
    Route::post('/projects/{project}/ready', [Tenant\TrackingController::class, 'ready'])->middleware(['perm:tracking.update', 'module:tracking'])->name('tracking.ready');

    // Launch & plots (Admin / Manager write; Sales & Support read-only)
    Route::middleware('module:launch')->group(function () {
        Route::get('/launch', [Tenant\LaunchController::class, 'index'])->middleware('perm:launch.view')->name('launch.index');
        Route::get('/launch/sample.csv', [Tenant\LaunchController::class, 'sample'])->middleware('perm:launch.view')->name('launch.sample');
        Route::get('/launch/{project}', [Tenant\LaunchController::class, 'show'])->middleware('perm:launch.view')->name('launch.show');
        Route::post('/launch/{project}/layout', [Tenant\LaunchController::class, 'layout'])->middleware('perm:launch.update')->name('launch.layout');
        Route::post('/launch/{project}/details', [Tenant\LaunchController::class, 'details'])->middleware('perm:launch.update')->name('launch.details');
        Route::post('/launch/{project}/ai-text', [Tenant\LaunchController::class, 'aiText'])->middleware(['perm:launch.update', 'throttle:ai'])->name('launch.ai-text');
        Route::post('/launch/{project}/gallery', [Tenant\LaunchController::class, 'gallery'])->middleware('perm:launch.update')->name('launch.gallery');
        Route::delete('/launch/{project}/gallery/{file}', [Tenant\LaunchController::class, 'removeImage'])->middleware('perm:launch.update')->name('launch.gallery.remove');
        Route::post('/launch/{project}/import', [Tenant\PlotImportController::class, 'preview'])->middleware('perm:launch.create')->name('launch.import');
        Route::post('/launch/{project}/extract', [Tenant\PlotImportController::class, 'extract'])->middleware(['perm:launch.create', 'throttle:ai'])->name('launch.extract');
        Route::post('/launch/{project}/import/confirm', [Tenant\PlotImportController::class, 'confirm'])->middleware('perm:launch.create')->name('launch.import.confirm');
        Route::post('/launch/{project}/go-live', [Tenant\LaunchController::class, 'goLive'])->middleware('perm:launch.approve')->name('launch.go-live');
        Route::get('/launch/{project}/plots/export', [Tenant\PlotController::class, 'export'])->middleware('perm:launch.export')->name('plots.export');
        Route::get('/launch/{project}/plots/{plot}/edit', [Tenant\PlotController::class, 'edit'])->middleware('perm:launch.update')->name('plots.edit');
        Route::put('/launch/{project}/plots/{plot}', [Tenant\PlotController::class, 'update'])->middleware('perm:launch.update')->name('plots.update');
        Route::delete('/launch/{project}/plots/{plot}', [Tenant\PlotController::class, 'destroy'])->middleware('perm:launch.delete')->name('plots.destroy');
        Route::post('/launch/{project}/plots/{plot}/pin', [Tenant\PlotController::class, 'pin'])->middleware('perm:launch.update')->name('plots.pin');
    });

    // Bookings & sales
    Route::middleware('module:bookings')->group(function () {
        Route::get('/bookings', [Tenant\BookingController::class, 'index'])->middleware('perm:bookings.view')->name('bookings.index');
        Route::get('/bookings/create', [Tenant\BookingController::class, 'create'])->middleware('perm:bookings.create')->name('bookings.create');
        Route::post('/bookings', [Tenant\BookingController::class, 'store'])->middleware('perm:bookings.create')->name('bookings.store');
        Route::get('/bookings/{booking}', [Tenant\BookingController::class, 'show'])->middleware('perm:bookings.view')->name('bookings.show');
        Route::post('/bookings/{booking}/cancel', [Tenant\BookingController::class, 'cancel'])->middleware('perm:bookings.delete')->name('bookings.cancel');
        Route::get('/sales', [Tenant\SaleController::class, 'index'])->middleware('perm:sales.view')->name('sales.index');
        Route::get('/sales/create', [Tenant\SaleController::class, 'create'])->middleware('perm:sales.create')->name('sales.create');
        Route::post('/sales', [Tenant\SaleController::class, 'store'])->middleware('perm:sales.create')->name('sales.store');
        Route::get('/sales/{sale}', [Tenant\SaleController::class, 'show'])->middleware('perm:sales.view')->name('sales.show');
        Route::post('/sales/{sale}/payments', [Tenant\PaymentController::class, 'store'])->middleware('perm:accounts.create')->name('payments.store');
        Route::get('/payments/{payment}/receipt', [Tenant\PaymentController::class, 'receipt'])->middleware('perm:sales.view')->name('payments.receipt');
        Route::post('/sales/{sale}/refund', [Tenant\RefundController::class, 'store'])->middleware('perm:refunds.create')->name('refunds.store');
        Route::get('/refunds', [Tenant\RefundController::class, 'index'])->middleware('perm:refunds.view')->name('refunds.index');
        Route::post('/refunds/{refund}/decide', [Tenant\RefundController::class, 'decide'])->middleware('perm:refunds.approve')->name('refunds.decide');
        Route::get('/refunds/{refund}/note', [Tenant\RefundController::class, 'note'])->middleware('perm:refunds.view')->name('refunds.note');
        Route::get('/api/plots', [Tenant\BookingController::class, 'plots'])->middleware('perm:bookings.view')->name('api.plots');
        Route::get('/api/customers', [Tenant\BookingController::class, 'customers'])->middleware('perm:bookings.view')->name('api.customers');
    });

    // Registration
    Route::middleware('module:registration')->group(function () {
        Route::get('/registrations', [Tenant\RegistrationController::class, 'index'])->middleware('perm:registration.view')->name('registrations.index');
        Route::get('/registrations/init/{sale}', [Tenant\RegistrationController::class, 'create'])->middleware('perm:registration.create')->name('registrations.create');
        Route::post('/registrations/init/{sale}', [Tenant\RegistrationController::class, 'store'])->middleware('perm:registration.create')->name('registrations.store');
        Route::get('/registrations/{registration}', [Tenant\RegistrationController::class, 'show'])->middleware('perm:registration.view')->name('registrations.show');
        Route::put('/registrations/{registration}', [Tenant\RegistrationController::class, 'update'])->middleware('perm:registration.update')->name('registrations.update');
        Route::post('/registrations/{registration}/files', [Tenant\RegistrationController::class, 'upload'])->middleware('perm:registration.update')->name('registrations.upload');
        Route::post('/registrations/{registration}/submit', [Tenant\RegistrationController::class, 'submit'])->middleware('perm:registration.update')->name('registrations.submit');
        Route::post('/registrations/{registration}/complete', [Tenant\RegistrationController::class, 'complete'])->middleware('perm:registration.update')->name('registrations.complete');
        Route::post('/registrations/{registration}/sold', [Tenant\RegistrationController::class, 'sold'])->middleware('perm:registration.update')->name('registrations.sold');
        Route::get('/registrations/{registration}/pack', [Tenant\RegistrationController::class, 'pack'])->middleware('perm:registration.view')->name('registrations.pack');
        Route::get('/registrations/{registration}/acknowledgement', [Tenant\RegistrationController::class, 'acknowledgement'])->middleware('perm:registration.view')->name('registrations.ack');
    });

    // Customers (read-only, associated only)
    Route::get('/customers', [Tenant\CustomerController::class, 'index'])->middleware('perm:tenant_customers.view')->name('customers.index');
    Route::get('/customers/{customer}', [Tenant\CustomerController::class, 'show'])->middleware('perm:tenant_customers.view')->name('customers.show');

    // Accounts
    Route::middleware('module:accounts')->group(function () {
        Route::get('/accounts', [Tenant\AccountsController::class, 'index'])->middleware('perm:accounts.view')->name('accounts.index');
        Route::get('/accounts/receipts', [Tenant\AccountsController::class, 'receipts'])->middleware('perm:accounts.view')->name('accounts.receipts');
        Route::get('/accounts/expenses', [Tenant\ExpenseController::class, 'index'])->middleware('perm:accounts.view')->name('expenses.index');
        Route::post('/accounts/expenses', [Tenant\ExpenseController::class, 'store'])->middleware('perm:accounts.create')->name('expenses.store');
        Route::put('/accounts/expenses/{expense}', [Tenant\ExpenseController::class, 'update'])->middleware('perm:accounts.update')->name('expenses.update');
        Route::delete('/accounts/expenses/{expense}', [Tenant\ExpenseController::class, 'destroy'])->middleware('perm:accounts.delete')->name('expenses.destroy');
        Route::post('/accounts/categories', [Tenant\ExpenseController::class, 'category'])->middleware('perm:accounts.create')->name('expenses.category');
        Route::get('/accounts/budget', [Tenant\AccountsController::class, 'budget'])->middleware('perm:accounts.view')->name('accounts.budget');
        Route::get('/accounts/day-book', [Tenant\AccountsController::class, 'dayBook'])->middleware('perm:accounts.view')->name('accounts.daybook');
        Route::get('/accounts/receivables', [Tenant\AccountsController::class, 'receivables'])->middleware('perm:accounts.view')->name('accounts.receivables');
    });

    // Reports
    Route::get('/reports', [Tenant\ReportController::class, 'index'])->middleware(['perm:reports.view', 'module:reports'])->name('reports.index');
    Route::get('/reports/{report}', [Tenant\ReportController::class, 'show'])->middleware(['perm:reports.view', 'module:reports'])->name('reports.show');

    // Social posts & promo codes
    Route::middleware('module:promos')->group(function () {
        Route::get('/promotions', [Tenant\PromoController::class, 'index'])->middleware('perm:promos.view')->name('promos.index');
        Route::post('/promotions/post', [Tenant\PromoController::class, 'generate'])->middleware(['perm:promos.create', 'throttle:ai'])->name('promos.generate');
        Route::put('/promotions/post/{post}', [Tenant\PromoController::class, 'updatePost'])->middleware('perm:promos.update')->name('promos.post.update');
        Route::delete('/promotions/post/{post}', [Tenant\PromoController::class, 'destroyPost'])->middleware('perm:promos.delete')->name('promos.post.destroy');
        Route::post('/promotions/codes', [Tenant\PromoController::class, 'storeCode'])->middleware('perm:promos.create')->name('promos.codes.store');
        Route::put('/promotions/codes/{code}', [Tenant\PromoController::class, 'updateCode'])->middleware('perm:promos.update')->name('promos.codes.update');
    });

    // Enquiries & tickets
    Route::get('/enquiries', [Tenant\EnquiryController::class, 'index'])->middleware('perm:enquiries.view')->name('enquiries.index');
    Route::put('/enquiries/{enquiry}', [Tenant\EnquiryController::class, 'update'])->middleware('perm:enquiries.update')->name('enquiries.update');
    Route::get('/tickets', [Tenant\TicketController::class, 'index'])->middleware('perm:tickets.view')->name('tickets.index');
    Route::post('/tickets', [Tenant\TicketController::class, 'store'])->middleware('perm:tickets.create')->name('tickets.store');
    Route::get('/tickets/{ticket}', [Tenant\TicketController::class, 'show'])->middleware('perm:tickets.view')->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [Tenant\TicketController::class, 'reply'])->middleware('perm:tickets.create')->name('tickets.reply');
    Route::get('/services', [Tenant\TicketController::class, 'services'])->middleware('perm:tickets.view')->name('services.index');
    Route::post('/services/{service}/request', [Tenant\TicketController::class, 'requestService'])->middleware('perm:tickets.create')->name('services.request');
});

/*
|--------------------------------------------------------------------------
| App workspace — /app/...
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web', 'auth.session', 'staff', 'appuser'])->prefix('app')->name('app.')->group(function () {
    Route::get('/', [App\DashboardController::class, 'index'])->middleware('perm:app_dashboard.view')->name('dashboard');

    Route::get('/plans', [App\PlanController::class, 'index'])->middleware('perm:plans.view')->name('plans.index');
    Route::get('/plans/create', [App\PlanController::class, 'create'])->middleware('perm:plans.create')->name('plans.create');
    Route::post('/plans', [App\PlanController::class, 'store'])->middleware('perm:plans.create')->name('plans.store');
    Route::get('/plans/{plan}/edit', [App\PlanController::class, 'edit'])->middleware('perm:plans.update')->name('plans.edit');
    Route::put('/plans/{plan}', [App\PlanController::class, 'update'])->middleware('perm:plans.update')->name('plans.update');
    Route::delete('/plans/{plan}', [App\PlanController::class, 'destroy'])->middleware('perm:plans.delete')->name('plans.destroy');

    Route::get('/tenants', [App\SubscriptionController::class, 'index'])->middleware('perm:subscriptions.view')->name('subscriptions.index');
    Route::get('/tenants/{tenant}', [App\SubscriptionController::class, 'show'])->middleware('perm:subscriptions.view')->name('subscriptions.show');
    Route::put('/tenants/{tenant}/subscription', [App\SubscriptionController::class, 'update'])->middleware('perm:subscriptions.update')->name('subscriptions.update');
    Route::post('/tenants/{tenant}/status', [App\SubscriptionController::class, 'status'])->middleware('perm:subscriptions.update')->name('subscriptions.status');
    Route::post('/invoices/{invoice}/paid', [App\SubscriptionController::class, 'markPaid'])->middleware('perm:subscriptions.approve')->name('invoices.paid');
    Route::get('/invoices/{invoice}', [App\SubscriptionController::class, 'invoice'])->middleware('perm:subscriptions.view')->name('invoices.show');

    Route::get('/users', [App\IamController::class, 'index'])->middleware('perm:app_iam.view')->name('iam.index');
    Route::post('/users', [App\IamController::class, 'store'])->middleware('perm:app_iam.create')->name('iam.store');
    Route::put('/users/{user}', [App\IamController::class, 'update'])->middleware('perm:app_iam.update')->name('iam.update');
    Route::post('/users/{user}/generate-password', [App\IamController::class, 'generatePassword'])->middleware(['perm:app_iam.update', 'throttle:genpass'])->name('iam.generate');
    Route::post('/users/{user}/reset-link', [App\IamController::class, 'resetLink'])->middleware('perm:app_iam.update')->name('iam.reset-link');
    Route::post('/users/{user}/unlock', [App\IamController::class, 'unlock'])->middleware('perm:app_iam.update')->name('iam.unlock');
    Route::post('/users/bulk-passwords', [App\IamController::class, 'bulkPasswords'])->middleware(['perm:app_iam.update', 'throttle:genpass'])->name('iam.bulk');
    Route::get('/tenant-users', [App\IamController::class, 'tenantUsers'])->middleware('perm:app_iam.view')->name('iam.tenant-users');
    Route::get('/audit', [App\AuditController::class, 'index'])->middleware('perm:audit.view')->name('audit.index');

    Route::get('/customers', [App\CustomerController::class, 'index'])->middleware('perm:customers.view')->name('customers.index');
    Route::get('/customers/{customer}', [App\CustomerController::class, 'show'])->middleware('perm:customers.view')->name('customers.show');
    Route::put('/customers/{customer}', [App\CustomerController::class, 'update'])->middleware('perm:customers.update')->name('customers.update');
    Route::post('/customers/{customer}/toggle', [App\CustomerController::class, 'toggle'])->middleware('perm:customers.update')->name('customers.toggle');
    Route::post('/customers/{customer}/generate-password', [App\CustomerController::class, 'generatePassword'])->middleware(['perm:customers.update', 'throttle:genpass'])->name('customers.generate');
    Route::post('/customers/{customer}/reset-link', [App\CustomerController::class, 'resetLink'])->middleware('perm:customers.update')->name('customers.reset-link');
    Route::post('/customers/{customer}/unlock', [App\CustomerController::class, 'unlock'])->middleware('perm:customers.update')->name('customers.unlock');
    Route::post('/customers/bulk-passwords', [App\CustomerController::class, 'bulkPasswords'])->middleware(['perm:customers.update', 'throttle:genpass'])->name('customers.bulk');

    Route::get('/projects', [App\ReadOnlyController::class, 'projects'])->middleware('perm:app_projects.view')->name('projects.index');
    Route::get('/projects/{project}', [App\ReadOnlyController::class, 'project'])->middleware('perm:app_projects.view')->name('projects.show');
    Route::get('/sales', [App\ReadOnlyController::class, 'sales'])->middleware('perm:app_sales.view')->name('sales.index');

    Route::get('/services', [App\ServiceController::class, 'index'])->middleware('perm:services.view')->name('services.index');
    Route::post('/services', [App\ServiceController::class, 'store'])->middleware('perm:services.create')->name('services.store');
    Route::put('/services/{service}', [App\ServiceController::class, 'update'])->middleware('perm:services.update')->name('services.update');
    Route::delete('/services/{service}', [App\ServiceController::class, 'destroy'])->middleware('perm:services.delete')->name('services.destroy');

    Route::get('/enquiries', [App\EnquiryController::class, 'index'])->middleware('perm:app_enquiries.view')->name('enquiries.index');
    Route::put('/enquiries/{enquiry}', [App\EnquiryController::class, 'update'])->middleware('perm:app_enquiries.update')->name('enquiries.update');

    Route::get('/tickets', [App\TicketController::class, 'index'])->middleware('perm:helpdesk.view')->name('tickets.index');
    Route::get('/tickets/{ticket}', [App\TicketController::class, 'show'])->middleware('perm:helpdesk.view')->name('tickets.show');
    Route::put('/tickets/{ticket}', [App\TicketController::class, 'update'])->middleware('perm:helpdesk.update')->name('tickets.update');
    Route::post('/tickets/{ticket}/reply', [App\TicketController::class, 'reply'])->middleware('perm:helpdesk.update')->name('tickets.reply');

    Route::get('/marketing', [App\MarketingController::class, 'index'])->middleware('perm:marketing.view')->name('marketing.index');
    Route::post('/marketing/generate', [App\MarketingController::class, 'generate'])->middleware(['perm:marketing.create', 'throttle:ai'])->name('marketing.generate');
    Route::put('/marketing/{post}', [App\MarketingController::class, 'update'])->middleware('perm:marketing.update')->name('marketing.update');
    Route::post('/marketing/{post}/publish', [App\MarketingController::class, 'publish'])->middleware('perm:marketing.update')->name('marketing.publish');
    Route::delete('/marketing/{post}', [App\MarketingController::class, 'destroy'])->middleware('perm:marketing.delete')->name('marketing.destroy');

    Route::get('/prerequisites/{type?}', [App\MasterController::class, 'index'])->middleware('perm:prerequisites.view')->name('masters.index')->where('type', 'stages|facilities|documents|sros');
    Route::post('/prerequisites/{type}', [App\MasterController::class, 'store'])->middleware('perm:prerequisites.create')->name('masters.store');
    Route::put('/prerequisites/{type}/{id}', [App\MasterController::class, 'update'])->middleware('perm:prerequisites.update')->name('masters.update');
    Route::delete('/prerequisites/{type}/{id}', [App\MasterController::class, 'destroy'])->middleware('perm:prerequisites.delete')->name('masters.destroy');
    Route::post('/prerequisites-import', [App\MasterController::class, 'import'])->middleware('perm:prerequisites.create')->name('masters.import');
    Route::get('/prerequisites-export', [App\MasterController::class, 'export'])->middleware('perm:prerequisites.export')->name('masters.export');

    Route::get('/seo', [App\SeoController::class, 'index'])->middleware('perm:seo.view')->name('seo.index');
    Route::put('/seo', [App\SeoController::class, 'update'])->middleware('perm:seo.update')->name('seo.update');
    Route::post('/seo/build', [App\SeoController::class, 'build'])->middleware('perm:seo.update')->name('seo.build');
    Route::put('/seo/meta/{meta}', [App\SeoController::class, 'meta'])->middleware('perm:seo.update')->name('seo.meta');

    Route::get('/settings', [App\SettingsController::class, 'index'])->middleware('perm:app_settings.view')->name('settings.index');
    Route::put('/settings/{section}', [App\SettingsController::class, 'update'])->middleware('perm:app_settings.update')->name('settings.update')->where('section', 'organisation|payment|smtp|ai|storage|social|security');
    Route::post('/settings/test-email', [App\SettingsController::class, 'testEmail'])->middleware('perm:app_settings.update')->name('settings.test-email');
    Route::post('/settings/test-storage', [App\SettingsController::class, 'testStorage'])->middleware('perm:app_settings.update')->name('settings.test-storage');
    Route::get('/settings/templates', [App\SettingsController::class, 'templates'])->middleware('perm:app_settings.view')->name('settings.templates');
    Route::put('/settings/templates/{template}', [App\SettingsController::class, 'updateTemplate'])->middleware('perm:app_settings.update')->name('settings.templates.update');
    Route::get('/settings/ai-usage', [App\SettingsController::class, 'aiUsage'])->middleware('perm:app_settings.view')->name('settings.ai-usage');
});
