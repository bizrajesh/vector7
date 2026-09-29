<?php

use App\Http\Controllers\App;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Platform;
use App\Http\Controllers\Portal;
use App\Http\Controllers\Site;
use App\Http\Controllers\Webhook\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public, indexable pages (SEO standard applies)
|--------------------------------------------------------------------------
*/
Route::get('/', [Site\PageController::class, 'home'])->name('home');
Route::get('/features', [Site\PageController::class, 'features'])->name('features');
Route::get('/pricing', [Site\PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [Site\PageController::class, 'about'])->name('about');
Route::get('/contact', [Site\PageController::class, 'contact'])->name('contact');
Route::get('/privacy', [Site\PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [Site\PageController::class, 'terms'])->name('terms');
// Public projects: view layouts and live plot availability (indexable)
Route::get('/projects', [Site\ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{tenant}/{code}', [Site\ProjectController::class, 'show'])
    ->where(['tenant' => '[a-z0-9\-]+', 'code' => '[A-Za-z0-9_\-]+'])->name('projects.show');
Route::post('/projects/{tenant}/{code}/callback', [Site\CallbackController::class, 'store'])
    ->where(['tenant' => '[a-z0-9\-]+', 'code' => '[A-Za-z0-9_\-]+'])->middleware('throttle:public-forms')->name('projects.callback');

// Online booking hold (noindex): form → email one-time code → customer portal. Purchases only via sales.
Route::middleware('noindex')->group(function () {
    Route::get('/book/{tenant}/{code}/{plot}', [Site\OnlineBookingController::class, 'create'])
        ->where(['tenant' => '[a-z0-9\-]+', 'code' => '[A-Za-z0-9_\-]+', 'plot' => '[A-Za-z0-9\-\/]+'])->name('booking.create');
    Route::post('/book/{tenant}/{code}/{plot}', [Site\OnlineBookingController::class, 'store'])
        ->where(['tenant' => '[a-z0-9\-]+', 'code' => '[A-Za-z0-9_\-]+', 'plot' => '[A-Za-z0-9\-\/]+'])->middleware('throttle:otp')->name('booking.store');
    Route::get('/book/verify', [Site\OnlineBookingController::class, 'verifyForm'])->name('booking.verify');
    Route::post('/book/verify', [Site\OnlineBookingController::class, 'verify'])->middleware('throttle:otp-verify')->name('booking.verify.submit');
    Route::post('/book/resend', [Site\OnlineBookingController::class, 'resend'])->middleware('throttle:otp')->name('booking.resend');

    Route::get('/track', [Site\TrackController::class, 'show'])->name('track');
    Route::post('/track', [Site\TrackController::class, 'send'])->middleware('throttle:otp');
    Route::get('/track/verify', [Site\TrackController::class, 'verifyForm'])->name('track.verify');
    Route::post('/track/verify', [Site\TrackController::class, 'verify'])->middleware('throttle:otp-verify')->name('track.verify.submit');
});

Route::get('/robots.txt', [Site\SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [Site\SeoController::class, 'sitemap'])->name('sitemap');

Route::post('/webhooks/razorpay', RazorpayWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.razorpay');

/*
|--------------------------------------------------------------------------
| Authentication (noindex)
|--------------------------------------------------------------------------
*/
Route::middleware(['guest', 'noindex'])->group(function () {
    Route::get('/login', [Auth\LoginController::class, 'show'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [Auth\RegisterController::class, 'show'])->name('register');
    Route::post('/register', [Auth\RegisterController::class, 'store'])->middleware('throttle:register');
    Route::get('/forgot-password', [Auth\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [Auth\PasswordResetController::class, 'email'])->middleware('throttle:password')->name('password.email');
    Route::get('/reset-password/{token}', [Auth\PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [Auth\PasswordResetController::class, 'update'])->middleware('throttle:password')->name('password.update');
});

Route::middleware(['auth', 'noindex'])->group(function () {
    Route::post('/logout', [Auth\LoginController::class, 'destroy'])->name('logout');
    Route::get('/email/verify', [Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [Auth\EmailVerificationController::class, 'send'])->middleware('throttle:3,1')->name('verification.send');
    Route::post('/impersonation/stop', [Platform\TenantController::class, 'stopImpersonating'])->name('impersonation.stop');
});

/*
|--------------------------------------------------------------------------
| Super Admin console
|--------------------------------------------------------------------------
*/
Route::prefix('platform')->name('platform.')->middleware(['auth', 'active', 'super', 'noindex'])->group(function () {
    Route::get('/', Platform\DashboardController::class)->name('dashboard');
    Route::resource('plans', Platform\PlanController::class)->except(['show', 'destroy']);
    Route::get('tenants', [Platform\TenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/{tenant}', [Platform\TenantController::class, 'show'])->name('tenants.show');
    Route::middleware('throttle:writes')->group(function () {
        Route::post('tenants/{tenant}/status', [Platform\TenantController::class, 'status'])->name('tenants.status');
        Route::post('tenants/{tenant}/trial', [Platform\TenantController::class, 'extendTrial'])->name('tenants.trial');
        Route::post('tenants/{tenant}/plan', [Platform\TenantController::class, 'changePlan'])->name('tenants.plan');
        Route::post('tenants/{tenant}/impersonate', [Platform\TenantController::class, 'impersonate'])->name('tenants.impersonate');
    });
});

/*
|--------------------------------------------------------------------------
| Tenant portal — Admin and Sales
|--------------------------------------------------------------------------
*/
Route::prefix('app')->name('app.')->middleware(['auth', 'active', 'role:admin,sales', 'subscribed', 'noindex'])->group(function () {
    Route::get('/', App\DashboardController::class)->name('dashboard');

    // Billing & users (Admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('billing', [App\BillingController::class, 'show'])->name('billing');
        Route::post('billing/pay', [App\BillingController::class, 'pay'])->middleware('throttle:writes')->name('billing.pay');
    });
    Route::middleware('permission:users.manage')->group(function () {
        Route::resource('users', App\UserController::class)->except(['show', 'destroy']);
    });

    // Settings (Admin)
    Route::prefix('settings')->name('settings.')->middleware('permission:settings.manage')->group(function () {
        Route::get('/', [App\SettingsController::class, 'edit'])->name('edit');
        Route::put('/', [App\SettingsController::class, 'update'])->name('update');

        Route::resource('stage-groups', App\StageGroupController::class)->except(['edit', 'create']);
        Route::post('stage-groups/{stage_group}/stages', [App\StageGroupController::class, 'storeStage'])->name('stage-groups.stages.store');
        Route::put('stage-groups/{stage_group}/stages/{stage}', [App\StageGroupController::class, 'updateStage'])->scopeBindings()->name('stage-groups.stages.update');
        Route::delete('stage-groups/{stage_group}/stages/{stage}', [App\StageGroupController::class, 'destroyStage'])->scopeBindings()->name('stage-groups.stages.destroy');
        Route::post('stage-groups/{stage_group}/stages/{stage}/tasks', [App\StageGroupController::class, 'storeTask'])->scopeBindings()->name('stage-groups.tasks.store');
        Route::delete('stage-groups/{stage_group}/tasks/{task}', [App\StageGroupController::class, 'destroyTask'])->name('stage-groups.tasks.destroy');

        Route::get('masters/{type}', [App\MasterDataController::class, 'index'])->name('masters.index');
        Route::post('masters/{type}', [App\MasterDataController::class, 'store'])->name('masters.store');
        Route::put('masters/{type}/{id}', [App\MasterDataController::class, 'update'])->whereNumber('id')->name('masters.update');
        Route::delete('masters/{type}/{id}', [App\MasterDataController::class, 'destroy'])->whereNumber('id')->name('masters.destroy');

        Route::get('checklists', [App\ChecklistTemplateController::class, 'index'])->name('checklists.index');
        Route::post('checklists', [App\ChecklistTemplateController::class, 'store'])->name('checklists.store');
        Route::get('checklists/{checklist}', [App\ChecklistTemplateController::class, 'show'])->name('checklists.show');
        Route::post('checklists/{checklist}/items', [App\ChecklistTemplateController::class, 'storeItem'])->name('checklists.items.store');
        Route::delete('checklists/{checklist}/items/{item}', [App\ChecklistTemplateController::class, 'destroyItem'])->scopeBindings()->name('checklists.items.destroy');

        Route::get('notifications', [App\NotificationGroupController::class, 'index'])->name('notifications.index');
        Route::post('notifications', [App\NotificationGroupController::class, 'store'])->name('notifications.store');
        Route::get('notifications/{group}', [App\NotificationGroupController::class, 'show'])->name('notifications.show');
        Route::put('notifications/{group}', [App\NotificationGroupController::class, 'update'])->name('notifications.update');
        Route::post('notifications/{group}/members', [App\NotificationGroupController::class, 'storeMember'])->name('notifications.members.store');
        Route::delete('notifications/{group}/members/{member}', [App\NotificationGroupController::class, 'destroyMember'])->scopeBindings()->name('notifications.members.destroy');
    });

    // Layout projects
    Route::get('layouts', [App\LayoutController::class, 'index'])->middleware('permission:layouts.view')->name('layouts.index');
    Route::middleware('permission:layouts.manage')->group(function () {
        Route::get('layouts/create', [App\LayoutController::class, 'create'])->name('layouts.create');
        Route::post('layouts', [App\LayoutController::class, 'store'])->name('layouts.store');
        Route::get('layouts/{layout}/edit', [App\LayoutController::class, 'edit'])->name('layouts.edit');
        Route::put('layouts/{layout}', [App\LayoutController::class, 'update'])->name('layouts.update');
        Route::post('layouts/{layout}/owners', [App\LayoutController::class, 'storeOwner'])->name('layouts.owners.store');
        Route::delete('layouts/{layout}/owners/{owner}', [App\LayoutController::class, 'destroyOwner'])->scopeBindings()->name('layouts.owners.destroy');
        Route::post('layouts/{layout}/surveys', [App\LayoutController::class, 'storeSurvey'])->name('layouts.surveys.store');
        Route::delete('layouts/{layout}/surveys/{survey}', [App\LayoutController::class, 'destroySurvey'])->scopeBindings()->name('layouts.surveys.destroy');
        Route::post('layouts/{layout}/documents', [App\LayoutController::class, 'storeDocument'])->name('layouts.documents.store');
        Route::delete('layouts/{layout}/documents/{document}', [App\LayoutController::class, 'destroyDocument'])->scopeBindings()->name('layouts.documents.destroy');
        Route::post('layouts/{layout}/facilities', [App\LayoutController::class, 'storeFacility'])->name('layouts.facilities.store');
        Route::delete('layouts/{layout}/facilities/{facility}', [App\LayoutController::class, 'destroyFacility'])->scopeBindings()->name('layouts.facilities.destroy');
        Route::post('layouts/{layout}/stage-group', [App\LayoutController::class, 'applyStageGroup'])->name('layouts.stage-group');
        Route::post('layouts/{layout}/publish', [App\LayoutController::class, 'publish'])->name('layouts.publish');
        Route::post('layouts/{layout}/notification-groups', [App\LayoutController::class, 'syncNotificationGroups'])->name('layouts.notification-groups');
        Route::post('layouts/{layout}/submit', [App\LayoutController::class, 'submit'])->name('layouts.submit');
        Route::post('layouts/{layout}/launch', [App\LayoutController::class, 'launch'])->name('layouts.launch');
        Route::post('layouts/{layout}/close', [App\LayoutController::class, 'close'])->name('layouts.close');

        Route::put('stages/{stage}', [App\ProjectStageController::class, 'update'])->name('stages.update');
        Route::post('stages/{stage}/dependencies', [App\ProjectStageController::class, 'dependencies'])->name('stages.dependencies');
        Route::post('stages/{stage}/start', [App\ProjectStageController::class, 'start'])->name('stages.start');
        Route::post('stages/{stage}/complete', [App\ProjectStageController::class, 'complete'])->name('stages.complete');
        Route::post('stages/{stage}/skip', [App\ProjectStageController::class, 'skip'])->name('stages.skip');
        Route::post('tasks/{task}/toggle', [App\ProjectStageController::class, 'toggleTask'])->name('tasks.toggle');
        Route::post('stages/{stage}/expenses', [App\ProjectStageController::class, 'storeExpense'])->middleware('permission:expenses.record')->name('stages.expenses');

        Route::get('layouts/{layout}/plots/import', [App\PlotController::class, 'importForm'])->name('plots.import');
        Route::post('layouts/{layout}/plots/import', [App\PlotController::class, 'importPreview'])->name('plots.import.preview');
        Route::post('layouts/{layout}/plots/import/commit', [App\PlotController::class, 'importCommit'])->name('plots.import.commit');
        Route::get('plots/template.csv', [App\PlotController::class, 'template'])->name('plots.template');
        Route::post('layouts/{layout}/plots', [App\PlotController::class, 'store'])->name('plots.store');
        Route::put('plots/{plot}', [App\PlotController::class, 'update'])->name('plots.update');
        Route::post('plots/{plot}/reserve', [App\PlotController::class, 'toggleReserve'])->name('plots.reserve');
    });
    Route::get('layouts/{layout}', [App\LayoutController::class, 'show'])->middleware('permission:layouts.view')->name('layouts.show');
    Route::get('layouts/{layout}/documents/{document}', [App\LayoutController::class, 'downloadDocument'])->middleware('permission:layouts.manage')->scopeBindings()->name('layouts.documents.download');

    // Plots, bookings, sales, registration
    Route::middleware('permission:plots.view')->group(function () {
        Route::get('layouts/{layout}/plots', [App\PlotController::class, 'index'])->name('plots.index');
        Route::get('plots/{plot}', [App\PlotController::class, 'show'])->name('plots.show');
    });
    Route::middleware(['permission:bookings.create', 'throttle:writes'])->group(function () {
        Route::get('plots/{plot}/book', [App\BookingController::class, 'create'])->withoutMiddleware('throttle:writes')->name('bookings.create');
        Route::post('plots/{plot}/book', [App\BookingController::class, 'store'])->name('bookings.store');
        Route::post('bookings/{booking}/cancel', [App\BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('bookings/{booking}/confirm', [App\BookingController::class, 'confirm'])->middleware('permission:payments.record')->name('bookings.confirm');
    });
    Route::middleware(['permission:sales.create'])->group(function () {
        Route::get('plots/{plot}/sell', [App\SaleController::class, 'create'])->name('sales.create');
        Route::post('plots/{plot}/sell', [App\SaleController::class, 'store'])->middleware('throttle:writes')->name('sales.store');
        Route::get('sales', [App\SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}', [App\SaleController::class, 'show'])->name('sales.show');
        Route::post('sales/{sale}/cancel', [App\SaleController::class, 'cancel'])->middleware('role:admin')->name('sales.cancel');
    });
    Route::middleware('permission:payments.record')->group(function () {
        Route::post('sales/{sale}/payments', [App\SaleController::class, 'storePayment'])->middleware('throttle:writes')->name('sales.payments.store');
        Route::get('payments/{payment}/receipt', [App\SaleController::class, 'receipt'])->name('payments.receipt');
    });
    Route::middleware('permission:registrations.manage')->group(function () {
        Route::post('plots/{plot}/registration', [App\RegistrationController::class, 'store'])->name('registrations.store');
        Route::get('registrations/{registration}', [App\RegistrationController::class, 'show'])->name('registrations.show');
        Route::put('registrations/{registration}/items/{item}', [App\RegistrationController::class, 'updateItem'])->scopeBindings()->name('registrations.items.update');
        Route::get('registrations/{registration}/items/{item}/file', [App\RegistrationController::class, 'itemFile'])->scopeBindings()->name('registrations.items.file');
        Route::post('registrations/{registration}/complete', [App\RegistrationController::class, 'complete'])->name('registrations.complete');
        Route::get('registrations/{registration}/details', [App\RegistrationController::class, 'details'])->name('registrations.details');
        Route::get('registrations/{registration}/ack', [App\RegistrationController::class, 'ack'])->name('registrations.ack');
        Route::get('registrations/{registration}/deed', [App\RegistrationController::class, 'deed'])->name('registrations.deed');
    });

    Route::middleware('permission:requests.manage')->group(function () {
        Route::get('requests', [App\RequestController::class, 'index'])->name('requests.index');
        Route::put('requests/{purchaseRequest}', [App\RequestController::class, 'update'])->name('requests.update');
    });

    Route::middleware('permission:customers.view')->group(function () {
        Route::get('customers', [App\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [App\CustomerController::class, 'show'])->name('customers.show');
    });
    Route::post('customers', [App\CustomerController::class, 'store'])->middleware('permission:customers.manage')->name('customers.store');

    // Shares (Admin only; nobody can buy, sell or transfer)
    Route::prefix('shares')->name('shares.')->middleware('permission:shares.view')->group(function () {
        Route::get('/', [App\ShareController::class, 'index'])->name('index');
        Route::get('pools/{pool}', [App\ShareController::class, 'show'])->name('show');
        Route::get('shareholders', [App\ShareController::class, 'shareholders'])->name('shareholders');
        Route::middleware(['permission:shares.allocate', 'throttle:writes'])->group(function () {
            Route::post('shareholders', [App\ShareController::class, 'storeShareholder'])->name('shareholders.store');
            Route::put('shareholders/{shareholder}', [App\ShareController::class, 'updateShareholder'])->name('shareholders.update');
            Route::post('layouts/{layout}/pool', [App\ShareController::class, 'createPool'])->name('pools.store');
            Route::post('pools/{pool}/allocate', [App\ShareController::class, 'allocate'])->name('allocate');
            Route::post('issuances/{issuance}/reverse', [App\ShareController::class, 'reverse'])->name('reverse');
        });
        Route::post('pools/{pool}/payouts', [App\ShareController::class, 'payout'])->middleware(['permission:shares.payout', 'throttle:writes'])->name('payouts.store');
    });

    // Accounting & analytics
    Route::get('ledger', [App\LedgerController::class, 'index'])->name('ledger.index');
    Route::get('ledger/export', [App\LedgerController::class, 'export'])->name('ledger.export');
    Route::middleware(['permission:ledger.manage', 'throttle:writes'])->group(function () {
        Route::post('ledger', [App\LedgerController::class, 'store'])->name('ledger.store');
        Route::post('ledger/{entry}/reverse', [App\LedgerController::class, 'reverse'])->name('ledger.reverse');
    });
    Route::get('analytics', App\AnalyticsController::class)->name('analytics');
});

/*
|--------------------------------------------------------------------------
| Shareholder and customer portals (read-only, own records only)
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->name('portal.')->middleware(['auth', 'active', 'noindex'])->group(function () {
    Route::get('shareholder', [Portal\ShareholderPortalController::class, 'home'])->middleware('role:shareholder')->name('shareholder');
    Route::get('shareholder/allocations', [Portal\ShareholderPortalController::class, 'allocations'])->middleware('role:shareholder')->name('shareholder.allocations');
    Route::get('customer', [Portal\CustomerPortalController::class, 'home'])->middleware('role:customer')->name('customer');
    Route::post('customer/purchase-request', [Portal\CustomerPortalController::class, 'requestPurchase'])->middleware(['role:customer', 'throttle:public-forms'])->name('customer.purchase');
    Route::get('customer/receipts/{payment}', [Portal\CustomerPortalController::class, 'receipt'])->middleware('role:customer')->name('customer.receipt');
});

// Billing page for suspended/cancelled tenants (outside the "subscribed" middleware).
Route::get('/app/account/billing', [App\BillingController::class, 'show'])->middleware(['auth', 'active', 'role:admin', 'noindex'])->name('billing.show');
