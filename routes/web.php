<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountContactsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VenuesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/venues', [VenuesController::class, 'index'])->name('venues');
Route::get('/venues/{type}', [VenuesController::class, 'type'])->name('venues.type');

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::get('/account', [AccountController::class, 'index'])->name('account')->middleware('auth');
Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile')->middleware('auth');
Route::get('/account/contacts', [AccountController::class, 'contacts'])->name('account.contacts')->middleware('auth');
Route::get('/account/roles/{assignment}', [AccountController::class, 'role'])->name('account.roles.show')->middleware('auth');

Route::post('/account/contacts', [AccountContactsController::class, 'store'])->name('account.contacts.store')->middleware('auth');
Route::patch('/account/contacts/{contact}', [AccountContactsController::class, 'update'])->name('account.contacts.update')->middleware('auth');
Route::post('/account/contacts/{contact}/confirm-request', [AccountContactsController::class, 'requestConfirm'])
    ->name('account.contacts.confirm.request')
    ->middleware('auth');
Route::post('/account/contacts/{contact}/confirm-verify', [AccountContactsController::class, 'verifyConfirm'])
    ->name('account.contacts.confirm.verify')
    ->middleware('auth');
Route::delete('/account/contacts/{contact}', [AccountContactsController::class, 'destroy'])->name('account.contacts.destroy')->middleware('auth');
Route::patch('/account/emails/{contact}', [AccountContactsController::class, 'updateEmail'])->name('account.emails.update')->middleware('auth');
Route::delete('/account/emails/{contact}', [AccountContactsController::class, 'destroyEmail'])->name('account.emails.destroy')->middleware('auth');


Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
