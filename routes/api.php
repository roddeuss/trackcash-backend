<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;

// ====================
// AUTH
// ====================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ====================
// PROTECTED ROUTES
// ====================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // ====================
    // BANKS
    // ====================
    Route::get('/banks', [BankController::class, 'index']);
    Route::post('/banks', [BankController::class, 'store']);
    Route::get('/banks/{id}', [BankController::class, 'show']);
    Route::put('/banks/{id}', [BankController::class, 'update']);
    Route::delete('/banks/{id}', [BankController::class, 'destroy']);

    // ====================
    // ASSETS
    // ====================
    Route::get('/assets', [AssetController::class, 'index']);
    Route::post('/assets', [AssetController::class, 'store']);
    Route::get('/assets/{id}', [AssetController::class, 'show']);
    Route::put('/assets/{id}', [AssetController::class, 'update']);
    Route::delete('/assets/{id}', [AssetController::class, 'destroy']);

    // ====================
    // CATEGORIES
    // ====================
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // ====================
    // TRANSACTIONS
    // ====================
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);

    // ====================
    // INVESTMENTS
    // ====================
    Route::get('/investments', [InvestmentController::class, 'index']);
    Route::post('/investments', [InvestmentController::class, 'store']);
    Route::get('/investments/{id}', [InvestmentController::class, 'show']);
    Route::put('/investments/{id}', [InvestmentController::class, 'update']);
    Route::delete('/investments/{id}', [InvestmentController::class, 'destroy']);

    // ====================
    // DASHBOARD
    // ====================
    // Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
});
