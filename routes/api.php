<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\NotificationController; // ⬅️ tambahkan ini
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RecurringTransactionController;

// ====================
// AUTH (public)
// ====================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ====================
// PROTECTED
// ====================
Route::middleware('auth:sanctum')->group(function () {

    // ----- Auth -----
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user',    [AuthController::class, 'me']);

    // ----- Banks -----
    Route::get('/banks',         [BankController::class, 'index']);
    Route::post('/banks',        [BankController::class, 'store']);
    Route::get('/banks/{id}',    [BankController::class, 'show']);
    Route::put('/banks/{id}',    [BankController::class, 'update']);
    Route::delete('/banks/{id}', [BankController::class, 'destroy']);

    // ----- Types -----
    Route::get('/types',         [TypeController::class, 'index']);
    Route::post('/types',        [TypeController::class, 'store']);
    Route::get('/types/{id}',    [TypeController::class, 'show']);
    Route::put('/types/{id}',    [TypeController::class, 'update']);
    Route::delete('/types/{id}', [TypeController::class, 'destroy']);

    // ----- Assets -----
    Route::get('/assets',         [AssetController::class, 'index']);
    Route::post('/assets',        [AssetController::class, 'store']);
    Route::get('/assets/{id}',    [AssetController::class, 'show']);
    Route::put('/assets/{id}',    [AssetController::class, 'update']);
    Route::delete('/assets/{id}', [AssetController::class, 'destroy']);

    // ----- Categories -----
    Route::get('/categories',         [CategoryController::class, 'index']);
    Route::post('/categories',        [CategoryController::class, 'store']);
    Route::get('/categories/{id}',    [CategoryController::class, 'show']);
    Route::put('/categories/{id}',    [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // ----- Transactions -----
    Route::get('/transactions',         [TransactionController::class, 'index']);
    Route::post('/transactions',        [TransactionController::class, 'store']);
    Route::get('/transactions/{id}',    [TransactionController::class, 'show']);
    Route::put('/transactions/{id}',    [TransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);

    // ----- Investments -----
    Route::get('/investments',            [InvestmentController::class, 'index']);
    Route::post('/investments',           [InvestmentController::class, 'store']);   // BUY
    Route::get('/investments/{id}',       [InvestmentController::class, 'show']);
    Route::put('/investments/{id}',       [InvestmentController::class, 'update']);  // optional
    Route::delete('/investments/{id}',    [InvestmentController::class, 'destroy']);
    Route::post('/investments/{id}/sell', [InvestmentController::class, 'sell']);    // SELL

    // ----- Budgets -----
    Route::get('/budgets',         [BudgetController::class, 'index']);
    Route::post('/budgets',        [BudgetController::class, 'store']);
    Route::get('/budgets/{id}',    [BudgetController::class, 'show']);
    Route::put('/budgets/{id}',    [BudgetController::class, 'update']);
    Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);

    // ----- Notifications -----
    Route::get('/notifications',                [NotificationController::class, 'index']);      // list (bisa pakai ?status=unread|all)
    Route::get('/notifications/unread-count',   [NotificationController::class, 'unreadCount']); 
    Route::post('/notifications/{id}/read',     [NotificationController::class, 'markRead']);   // set read_at now
    Route::post('/notifications/read-all',      [NotificationController::class, 'markAllRead']); 
    Route::delete('/notifications/{id}',        [NotificationController::class, 'destroy']);    // soft delete

        // ----- Profile -----
    Route::get('/profile/me',          [ProfileController::class, 'me']);
    Route::put('/profile',             [ProfileController::class, 'updateProfile']);
    Route::put('/profile/password',    [ProfileController::class, 'updatePassword']);
    Route::put('/profile/currency',    [ProfileController::class, 'updateCurrency']);
    Route::post('/profile/picture',    [ProfileController::class, 'updateProfilePicture']);

    // ----- Dashboard -----
    Route::get('/dashboard/all',       [DashboardController::class, 'indexAll']);
    Route::get('/dashboard/summary',   [DashboardController::class, 'summary']);
    Route::get('/dashboard/cashflow',  [DashboardController::class, 'cashflow']);
    Route::get('/dashboard/allocation', [DashboardController::class, 'allocation']);
    Route::get('/dashboard/smart-insight', [DashboardController::class, 'smartInsight']);
    Route::get('/dashboard/smart-suggestions', [DashboardController::class, 'smartSuggestions']);

    // ----- Reports (tidak pakai prefix) -----
    Route::get('/reports/monthly-summary', [ReportController::class, 'monthlySummary']);
    Route::get('/reports/category-allocation', [ReportController::class, 'categoryAllocation']);
    Route::get('/reports/overview', [ReportController::class, 'overview']);

        // ----- Recurring Transactions -----
    Route::get('/recurring-transactions', [RecurringTransactionController::class, 'index']);
    Route::post('/recurring-transactions', [RecurringTransactionController::class, 'store']);
    Route::get('/recurring-transactions/{id}', [RecurringTransactionController::class, 'show']);
    Route::put('/recurring-transactions/{id}', [RecurringTransactionController::class, 'update']);
    Route::delete('/recurring-transactions/{id}', [RecurringTransactionController::class, 'destroy']);

});
