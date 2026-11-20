<?php

use App\Livewire\Customers;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('clientes', Customers\Index::class)->name('customers.index');
    Route::livewire('clientes/novo', Customers\Edit::class)->name('customers.create');
    Route::livewire('clientes/{customer}', Customers\Show::class)->name('customers.show');
    Route::livewire('clientes/{customer}/editar', Customers\Edit::class)->name('customers.edit');
});

require __DIR__.'/settings.php';
