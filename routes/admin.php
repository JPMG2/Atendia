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

// Compañía: los datos de Atendia (un único registro). Por ahora es solo la
// maqueta del formulario, sin cablear.
Route::livewire('/company', 'configuration.company')->name('company');

// Prueba de vida del WebSocket. Se borra cuando exista el chat real.
Route::livewire('/ws-demo', 'ws-demo')->name('ws-demo');
