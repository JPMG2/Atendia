<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\PaymentReceiptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel Admin (/admin)
|--------------------------------------------------------------------------
|
| Prefijo /admin, names admin.*, protegido por permiso access-admin-panel
| (ver bootstrap/app.php). El super-admin pasa por Gate::before. Esta área
| crece por su cuenta, separada del panel cliente.
|
*/

// Home: what is waiting for her right now, with the rows behind each number.
Route::livewire('/', 'admin.home.index')->name('dashboard');

// Businesses: the ledger of everyone the platform serves. /admin/adopcion
// measures the funnel; this one administers.
Route::livewire('/negocios', 'admin.businesses.index')->name('businesses')->middleware('permission:businesses.view');

// Catalogs, the system masters: a master-detail hub. Each master's CRUD is
// wired when its turn comes.
Route::livewire('/catalogs', 'catalog.manager')->name('catalogs')->middleware('permission:catalogs.manage');

// Company: AtendIa's own data, a single row.
Route::livewire('/company', 'configuration.company')->name('company')->middleware('permission:company.manage');

// Integrations: the health of everything the platform consumes.
Route::livewire('/integrations', 'configuration.integrations')->name('integrations')->middleware('permission:integrations.view');

// Testimonials: the moderation desk before an owner's word hits the landing.
Route::livewire('/testimonios', 'admin.testimonials.index')->name('testimonials')->middleware('permission:testimonials.moderate');

// Payments: receipts waiting to be credited or rejected, and the latest ones.
Route::livewire('/pagos', 'admin.payments.index')->name('payments')->middleware('permission:payments.view');
Route::get('/pagos/{payment}/comprobante', PaymentReceiptController::class)->name('payments.receipt')->middleware('permission:payments.view');

// AI: which model answers each task, and what each model costs. Under Cobros
// because a model change is a cost change before it is anything else.
Route::livewire('/ia', 'admin.ai.index')->name('ai')->middleware('permission:ai.manage');

// AI spend: what each business consumed in a month, and what it cost her.
Route::livewire('/consumo-ia', 'admin.ai-usage.index')->name('ai-usage')->middleware('permission:ai.view');

// Moderation: what the content filter caught, and the switch to lift a suspension.
Route::livewire('/moderacion', 'admin.moderation.index')->name('moderation')->middleware('permission:moderation.view');

// Support: what the businesses report from their own panel, by arrival.
Route::livewire('/soporte', 'admin.support.index')->name('support')->middleware('permission:support.view');

// Access: who can sign in and who actually does. Opened to answer "no puedo
// entrar", so a closed account and an unverified address are listed, not hidden.
Route::livewire('/usuarios', 'admin.users.index')->name('users')->middleware('permission:users.view');

// Roles: which doors each job opens. The matrix is the only place where a
// new role is born, so the panel grows without touching a seeder.
Route::livewire('/roles', 'admin.roles.index')->name('roles')->middleware('permission:roles.manage');

// Audit: who did what. Opened on people, because most of the trail is the
// assistant filing suggestions and that buries what somebody actually did.
Route::livewire('/auditoria', 'admin.audit.index')->name('audit')->middleware('permission:audit.view');

// Platform settings: the behaviour knobs she may turn without a deploy. The
// row overrides `config/atendia.php`, which stays as the written default.
Route::livewire('/ajustes', 'admin.settings.index')->name('settings')->middleware('permission:settings.manage');

// System logs: the latest entries, built to be copied into a help chat.
Route::livewire('/logs', 'configuration.logs')->name('logs')->middleware('permission:logs.view');

// Proof of life for the WebSocket. It goes when the real chat exists.
Route::livewire('/ws-demo', 'ws-demo')->name('ws-demo');

// Adoption: where each business stalled on the way to being answered by its
// own assistant. The one screen that measures product, not code.
Route::livewire('/adopcion', 'admin.adoption.index')->name('adoption')->middleware('permission:adoption.view');

// The desk of what went wrong: threads left unanswered, handoffs nobody took,
// customers who left annoyed and jobs that died. Sorted by severity.
Route::livewire('/incidencias', 'admin.incidents.index')->name('incidents')->middleware('permission:incidents.view');

// The radar: the people who reach the platform, and the ones who talk to more
// than one business — the only view no single business can have.
Route::livewire('/radar', 'admin.contacts.index')->name('contacts')->middleware('permission:contacts.view');

// How the two assistants are being marked: the one answering customers and
// the one answering her. Same permission as the rest of the AI screens.
Route::livewire('/calidad-ia', 'admin.ai-quality.index')->name('ai-quality')->middleware('permission:ai.view');

// The collection queue, worth first: who to ring today, how much is hanging
// off it and since when.
Route::livewire('/cobranza', 'admin.collections.index')->name('collections')->middleware('permission:payments.view');
