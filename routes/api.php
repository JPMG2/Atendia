<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AdminAdoptionController;
use App\Http\Controllers\Api\V1\AdminAlertsController;
use App\Http\Controllers\Api\V1\AdminAskController;
use App\Http\Controllers\Api\V1\AdminBusinessesController;
use App\Http\Controllers\Api\V1\AdminCatalogEditController;
use App\Http\Controllers\Api\V1\AdminCatalogsController;
use App\Http\Controllers\Api\V1\AdminCollectionsController;
use App\Http\Controllers\Api\V1\AdminCompanyController;
use App\Http\Controllers\Api\V1\AdminCompanyEditController;
use App\Http\Controllers\Api\V1\AdminComplianceController;
use App\Http\Controllers\Api\V1\AdminContactsController;
use App\Http\Controllers\Api\V1\AdminHomeController;
use App\Http\Controllers\Api\V1\AdminIncidentsController;
use App\Http\Controllers\Api\V1\AdminMenuController;
use App\Http\Controllers\Api\V1\AdminPaymentsController;
use App\Http\Controllers\Api\V1\AdminSessionController;
use App\Http\Controllers\Api\V1\AdminSupportController;
use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AskController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\BroadcastAuthController;
use App\Http\Controllers\Api\V1\BusinessCardController;
use App\Http\Controllers\Api\V1\BusinessClosuresController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\BusinessHoursController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DeviceChallengeController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DigestController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\WhatsAppStatusController;
use App\Http\Controllers\Webhooks\EvolutionWebhookController;
use App\Http\Middleware\EnsureAppAccess;
use App\Http\Middleware\ExpireIdleStaffToken;
use App\Http\Middleware\RejectRevokedDevice;
use App\Http\Middleware\SetTenantDatabaseContext;
use App\Http\Middleware\StaffTokenGate;
use App\Http\Middleware\VerifyEvolutionWebhook;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Canal de la app móvil. Autenticación con tokens Bearer de Sanctum.
| Todo va versionado bajo /api/v1 para poder evolucionar sin romper
| clientes viejos. El límite general 'api' aplica a todo el grupo; las
| rutas de auth suman 'throttle:auth' (estricto, anti fuerza bruta).
|
*/

// Evolution delivers here over the internal Docker network. No throttle: a
// busy WhatsApp must never have messages dropped by a rate limiter, and the
// shared-secret middleware is the lock.
Route::post('webhooks/evolution', EvolutionWebhookController::class)
    ->middleware(VerifyEvolutionWebhook::class);

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    // Public.
    Route::get('brand', BrandController::class);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    // The second half of a sign-in from an install the account never used.
    Route::prefix('login/challenge')->middleware('throttle:app-challenge')->group(function (): void {
        Route::post('/', [DeviceChallengeController::class, 'verify']);
        Route::post('recovery', [DeviceChallengeController::class, 'recovery']);
        Route::post('resend', [DeviceChallengeController::class, 'resend']);
    });

    // Behind a bearer token. Authentication comes first, so the business
    // (Postgres row security) and the device check can read who is calling.
    Route::middleware(['auth:sanctum', ExpireIdleStaffToken::class, SetTenantDatabaseContext::class, RejectRevokedDevice::class])->group(function (): void {
        // Signing out stays open to everyone: a lapsed plan must not trap a token.
        Route::post('logout', [AuthController::class, 'logout']);

        // Closing the account stays open too: leaving is a right a lapsed plan does not suspend.
        // A staff token never closes an account: it would take the admin's own with it.
        Route::delete('account', [AccountController::class, 'close'])->middleware(['throttle:app-account', StaffTokenGate::class.':never']);

        // The platform's own people. The lock is on the server: a staff token (born only from the second
        // step) AND the area permission, then the key of each screen (`can:`: spatie's middleware reads the web guard).
        Route::prefix('admin')->middleware([StaffTokenGate::class.':only', 'can:access-admin-panel'])->group(function (): void {
            Route::get('me', AdminSessionController::class);
            Route::get('menu', AdminMenuController::class);
            Route::get('home', AdminHomeController::class);
            Route::get('ask', [AdminAskController::class, 'show'])->middleware('can:ai.ask');
            Route::post('ask', [AdminAskController::class, 'ask'])->middleware('can:ai.ask');
            Route::post('ask/draft', [AdminAskController::class, 'decide'])->middleware('can:ai.ask');
            Route::get('alerts', AdminAlertsController::class)->middleware('can:alerts.view');
            // Both keys, as the web: its button lives inside the board, behind `alerts.view`.
            Route::post('alerts/{event}/acknowledge', [AdminAlertsController::class, 'acknowledge'])->where('event', '[0-9]{1,18}')->middleware(['can:alerts.view', 'can:alerts.acknowledge']);
            Route::get('adoption', AdminAdoptionController::class)->middleware('can:adoption.view');
            Route::get('incidents', AdminIncidentsController::class)->middleware('can:incidents.view');
            Route::get('contacts', AdminContactsController::class)->middleware('can:contacts.view');
            Route::get('compliance', [AdminComplianceController::class, 'index'])->middleware('can:compliance.view');
            Route::get('compliance/{business}', [AdminComplianceController::class, 'show'])->where('business', '[0-9]{1,18}')->middleware('can:compliance.view');
            Route::get('businesses', [AdminBusinessesController::class, 'index'])->middleware('can:businesses.view');
            Route::get('businesses/{business}', [AdminBusinessesController::class, 'show'])->where('business', '[0-9]{1,18}')->middleware('can:businesses.view');
            Route::get('payments', AdminPaymentsController::class)->middleware('can:payments.view');
            Route::get('collections', AdminCollectionsController::class)->middleware('can:payments.view');
            Route::get('company', AdminCompanyController::class)->middleware('can:company.manage');
            // Editing the company: the web form's two steps over the same piece, and a network removed at once, as the web does.
            Route::get('company/places', [AdminCompanyEditController::class, 'places'])->middleware('can:company.manage');
            Route::get('company/{step}', [AdminCompanyEditController::class, 'sheet'])->whereIn('step', ['main', 'commercial'])->middleware('can:company.manage');
            Route::put('company/{step}', [AdminCompanyEditController::class, 'update'])->whereIn('step', ['main', 'commercial'])->middleware('can:company.manage');
            Route::delete('company/social/{link}', [AdminCompanyEditController::class, 'removeSocial'])->where('link', '[0-9]{1,18}')->middleware('can:company.manage');
            Route::get('catalogs', [AdminCatalogsController::class, 'index'])->middleware('can:catalogs.manage');
            Route::get('catalogs/{master}', [AdminCatalogsController::class, 'show'])->where('master', '[a-z][a-z-]{0,60}')->middleware('can:catalogs.manage');
            Route::prefix('catalogs/{master}')->where(['master' => '[a-z][a-z-]{0,60}', 'id' => '[0-9]{1,18}'])->middleware('can:catalogs.manage')->group(function (): void {
                Route::get('form/{id?}', [AdminCatalogEditController::class, 'sheet']);
                Route::post('/', [AdminCatalogEditController::class, 'store']);
                Route::put('{id}', [AdminCatalogEditController::class, 'update']);
                Route::get('{id}/removal', [AdminCatalogEditController::class, 'removal']);
                Route::delete('{id}', [AdminCatalogEditController::class, 'destroy']);
                Route::post('{id}/deactivate', [AdminCatalogEditController::class, 'deactivate']);
                Route::post('{id}/notice', [AdminCatalogEditController::class, 'notice']);
            });
            Route::get('support', [AdminSupportController::class, 'index'])->middleware('can:support.view');
            Route::get('support/help', [AdminSupportController::class, 'help'])->middleware('can:support.view');
            Route::get('support/{ticket}', [AdminSupportController::class, 'show'])->where('ticket', '[0-9]{1,18}')->middleware('can:support.view');
            Route::get('support/saved-replies/{reply}', [AdminSupportController::class, 'savedReply'])->where('reply', '[0-9]{1,18}')->middleware('can:support.view');
            Route::post('support/{ticket}/reply', [AdminSupportController::class, 'reply'])->where('ticket', '[0-9]{1,18}')->middleware('can:support.view');
            Route::post('support/{ticket}/resolve', [AdminSupportController::class, 'resolve'])->where('ticket', '[0-9]{1,18}')->middleware('can:support.view');
        });

        // The one door both kinds of token share: it tells the app which side it is on (`staff`).
        Route::get('me', [AuthController::class, 'me'])->middleware(EnsureAppAccess::class);

        // Everything below is a customer's own: the staff token is for `admin/*` and nothing else, even for
        // an account that also holds a business (the plan check alone would let that one in).
        Route::middleware([EnsureAppAccess::class, StaffTokenGate::class.':never'])->group(function (): void {
            Route::get('menu', MenuController::class);
            Route::post('broadcasting/auth', BroadcastAuthController::class);

            Route::get('digests', DigestController::class)->middleware('can:manage-business');
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/read', [NotificationController::class, 'read']);
            Route::post('notifications/read-all', [NotificationController::class, 'readAll']);

            Route::put('account/push-token', [PushTokenController::class, 'update']);
            Route::delete('account/push-token', [PushTokenController::class, 'destroy']);

            Route::get('account', [AccountController::class, 'show']);
            Route::post('account/password', [AccountController::class, 'password'])->middleware('throttle:app-account');
            Route::get('account/devices', [DeviceController::class, 'index']);
            Route::delete('account/devices/{device}', [DeviceController::class, 'destroy'])->where('device', '[0-9]{1,18}');
            Route::post('account/devices/revoke-others', [DeviceController::class, 'revokeOthers'])->middleware('throttle:app-account');
            Route::get('conversations', [ConversationController::class, 'index']);
            Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->where('conversation', '[0-9]{1,18}');
            Route::get('whatsapp', WhatsAppStatusController::class)->middleware('can:manage-business');
            Route::get('plan', PlanController::class)->middleware('can:manage-business');
            Route::get('ask', [AskController::class, 'show'])->middleware('can:manage-business');
            Route::post('ask', [AskController::class, 'ask'])->middleware('can:manage-business');
            Route::post('ask/rate', [AskController::class, 'rate'])->middleware('can:manage-business');
            Route::get('business', BusinessController::class)->middleware('can:manage-business');
            Route::get('business/{card}', [BusinessCardController::class, 'show'])->where('card', 'identity|location|appointments|contact|billing|social')->middleware('can:manage-business');
            Route::put('business/{card}', [BusinessCardController::class, 'update'])->where('card', 'identity|location|appointments|contact|billing|social')->middleware('can:manage-business');
            Route::post('business/identity/logo', [BusinessCardController::class, 'logo'])->middleware('can:manage-business');
            Route::get('business/hours', [BusinessHoursController::class, 'show'])->middleware('can:manage-business');
            Route::put('business/hours', [BusinessHoursController::class, 'update'])->middleware('can:manage-business');
            Route::get('business/closures', [BusinessClosuresController::class, 'show'])->middleware('can:manage-business');
            Route::post('business/closures', [BusinessClosuresController::class, 'store'])->middleware('can:manage-business');
            Route::post('business/closures/holidays', [BusinessClosuresController::class, 'importHolidays'])->middleware('can:manage-business');
            Route::post('business/closures/{closure}/tell', [BusinessClosuresController::class, 'tell'])->where('closure', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::delete('business/closures/{closure}', [BusinessClosuresController::class, 'destroy'])->where('closure', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::get('team', [TeamController::class, 'show'])->middleware('can:manage-business');
            Route::post('team/invitations', [TeamController::class, 'invite'])->middleware('can:manage-business');
            Route::post('team/invitations/{invitation}/resend', [TeamController::class, 'resend'])->where('invitation', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::delete('team/invitations/{invitation}', [TeamController::class, 'cancel'])->where('invitation', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::put('team/members/{member}', [TeamController::class, 'update'])->where('member', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::delete('team/members/{member}', [TeamController::class, 'remove'])->where('member', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::get('support', [SupportController::class, 'show']);
            // Each report tells the team by mail and WhatsApp: the account limiter keeps a stuck button from flooding it.
            Route::post('support', [SupportController::class, 'store'])->middleware('throttle:app-account');
            Route::get('agenda', [AgendaController::class, 'show'])->middleware('can:manage-business');
            Route::get('agenda/hours', [AgendaController::class, 'hours'])->middleware('can:manage-business');
            Route::get('agenda/sheet', [AgendaController::class, 'sheet'])->middleware('can:manage-business');
            Route::post('agenda/bookings', [AgendaController::class, 'book'])->middleware('can:manage-business');
            Route::put('agenda/bookings/{booking}', [AgendaController::class, 'move'])->where('booking', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::post('agenda/bookings/{booking}/cancel', [AgendaController::class, 'cancel'])->where('booking', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::post('agenda/bookings/{booking}/done', [AgendaController::class, 'done'])->where('booking', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::get('products', [ProductController::class, 'index'])->middleware('can:manage-business');
            Route::get('products/new', [ProductController::class, 'blank'])->middleware('can:manage-business');
            Route::get('products/types/{type}', [ProductController::class, 'type'])->where('type', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::post('products', [ProductController::class, 'store'])->middleware('can:manage-business');
            Route::get('products/{product}', [ProductController::class, 'show'])->where('product', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::put('products/{product}', [ProductController::class, 'update'])->where('product', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->where('product', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::post('products/{product}/toggle', [ProductController::class, 'toggle'])->where('product', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::post('products/{product}/restore', [ProductController::class, 'restore'])->where('product', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::get('services', [ServiceController::class, 'index'])->middleware('can:manage-business');
            Route::get('services/new', [ServiceController::class, 'blank'])->middleware('can:manage-business');
            Route::get('services/types/{type}', [ServiceController::class, 'type'])->where('type', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::post('services', [ServiceController::class, 'store'])->middleware('can:manage-business');
            Route::post('services/categories', [ServiceController::class, 'storeCategory'])->middleware('can:manage-business');
            Route::put('services/categories/{category}/position', [ServiceController::class, 'moveCategory'])->where('category', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::get('services/{service}', [ServiceController::class, 'show'])->where('service', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::put('services/{service}', [ServiceController::class, 'update'])->where('service', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::delete('services/{service}', [ServiceController::class, 'destroy'])->where('service', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::post('services/{service}/toggle', [ServiceController::class, 'toggle'])->where('service', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::post('services/{service}/restore', [ServiceController::class, 'restore'])->where('service', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::get('customers', [CustomerController::class, 'index']);
            Route::get('customers/{customer}', [CustomerController::class, 'show'])->where('customer', '[0-9]{1,18}');
            Route::put('customers/{customer}', [CustomerController::class, 'update'])->where('customer', '[0-9]{1,18}');
            Route::post('customers/{customer}/opt-in', [CustomerController::class, 'optIn'])->where('customer', '[0-9]{1,18}');
            Route::post('customers/{customer}/merge', [CustomerController::class, 'merge'])->where('customer', '[0-9]{1,18}');
            Route::post('team/departments', [TeamController::class, 'storeDepartment'])->middleware('can:manage-business');
            Route::put('team/departments/{department}', [TeamController::class, 'updateDepartment'])->where('department', '[0-9]{1,18}')->middleware('can:manage-business');
            Route::delete('team/departments/{department}', [TeamController::class, 'removeDepartment'])->where('department', '[0-9]{1,18}')->middleware('can:manage-business');
        });
    });
});
