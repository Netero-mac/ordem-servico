<?php

use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\ServiceOrderPdfController;
use App\Http\Controllers\ServiceOrderPhotoController;
use App\Livewire\Customers;
use App\Livewire\Dashboard;
use App\Livewire\ServiceOrders;
use App\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// Acompanhamento público da OS (sem login). O token tem 64 caracteres alfanuméricos.
Route::prefix('acompanhar/{token}')
    ->where(['token' => '[A-Za-z0-9]{64}'])
    ->controller(PublicTrackingController::class)
    ->group(function () {
        Route::get('/', 'show')->middleware('throttle:public-tracking')->name('tracking.show');
        Route::post('decisao', 'decide')->middleware('throttle:public-decision')->name('tracking.decide');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('clientes', Customers\Index::class)->name('customers.index');
    Route::livewire('clientes/novo', Customers\Edit::class)->name('customers.create');
    Route::livewire('clientes/{customer}', Customers\Show::class)->name('customers.show');
    Route::livewire('clientes/{customer}/editar', Customers\Edit::class)->name('customers.edit');

    Route::livewire('usuarios', Users\Index::class)->name('users.index');

    Route::livewire('os', ServiceOrders\Index::class)->name('service-orders.index');
    Route::livewire('os/nova', ServiceOrders\Create::class)->name('service-orders.create');
    Route::livewire('os/{serviceOrder}', ServiceOrders\Show::class)->name('service-orders.show');
    Route::get('os/{serviceOrder}/comprovante-entrada.pdf', [ServiceOrderPdfController::class, 'entry'])->name('service-orders.pdf.entry');
    Route::get('os/{serviceOrder}/comprovante-entrega.pdf', [ServiceOrderPdfController::class, 'delivery'])->name('service-orders.pdf.delivery');
    Route::get('os/{serviceOrder}/fotos/{photo}', ServiceOrderPhotoController::class)->name('service-orders.photos.show');
});

require __DIR__.'/settings.php';
