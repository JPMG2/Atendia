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

// Catálogos (maestros del sistema). Hub maestro-detalle; el CRUD de cada
// maestro se cablea en su momento. La opción ya vive en el menú admin.
Route::livewire('/catalogs', 'catalog.manager')->name('catalogs');
