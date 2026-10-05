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
Route::livewire('/negocios', 'admin.businesses.index')->name('businesses');

// Catalogs, the system masters: a master-detail hub. Each master's CRUD is
// wired when its turn comes.
Route::livewire('/catalogs', 'catalog.manager')->name('catalogs');

// Company: AtendIa's own data, a single row.
Route::livewire('/company', 'configuration.company')->name('company');

// Integrations: the health of everything the platform consumes.
Route::livewire('/integrations', 'configuration.integrations')->name('integrations');

// Testimonials: the moderation desk before an owner's word hits the landing.
Route::livewire('/testimonios', 'admin.testimonials.index')->name('testimonials');

// Payments: receipts waiting to be credited or rejected, and the latest ones.
Route::livewire('/pagos', 'admin.payments.index')->name('payments');
Route::get('/pagos/{payment}/comprobante', PaymentReceiptController::class)->name('payments.receipt');

// AI: which model answers each task, and what each model costs. Under Cobros
// because a model change is a cost change before it is anything else.
Route::livewire('/ia', 'admin.ai.index')->name('ai');

// AI spend: what each business consumed in a month, and what it cost her.
Route::livewire('/consumo-ia', 'admin.ai-usage.index')->name('ai-usage');

// Moderation: what the content filter caught, and the switch to lift a suspension.
Route::livewire('/moderacion', 'admin.moderation.index')->name('moderation');

// Support: what the businesses report from their own panel, by arrival.
Route::livewire('/soporte', 'admin.support.index')->name('support');

// System logs: the latest entries, built to be copied into a help chat.
Route::livewire('/logs', 'configuration.logs')->name('logs');

// Proof of life for the WebSocket. It goes when the real chat exists.
Route::livewire('/ws-demo', 'ws-demo')->name('ws-demo');

// Adoption: where each business stalled on the way to being answered by its
// own assistant. The one screen that measures product, not code.
Route::livewire('/adopcion', 'admin.adoption.index')->name('adoption');
