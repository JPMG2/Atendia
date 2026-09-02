<?php

declare(strict_types=1);

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

Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');

// Catalogs, the system masters: a master-detail hub. Each master's CRUD is
// wired when its turn comes.
Route::livewire('/catalogs', 'catalog.manager')->name('catalogs');

// Company: AtendIa's own data, a single row.
Route::livewire('/company', 'configuration.company')->name('company');

// Integrations: the health of everything the platform consumes.
Route::livewire('/integrations', 'configuration.integrations')->name('integrations');

// System logs: the latest entries, built to be copied into a help chat.
Route::livewire('/logs', 'configuration.logs')->name('logs');

// Proof of life for the WebSocket. It goes when the real chat exists.
Route::livewire('/ws-demo', 'ws-demo')->name('ws-demo');
