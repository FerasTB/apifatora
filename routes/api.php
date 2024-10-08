<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\User\ThirdPartyController;
use App\Http\Controllers\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/token/check', function (Request $request) {
    return response()->json(['message' => 'Token is valid.'], 200);
});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum', 'checkRole:admin'])->prefix('admin')->group(function () {
    Route::post('/users', [AdminController::class, 'addUser']); // Add new user or third-party app
    Route::get('/users', [AdminController::class, 'listUsers']); // View all users or third-party apps
    Route::get('/users/{user}', [AdminController::class, 'listUser']); // View all users or third-party apps
    Route::put('/users/{userId}', [AdminController::class, 'updateUser']); // Update user
    Route::delete('/users/{userId}', [AdminController::class, 'deleteUser']); // Delete user

    Route::post('/bills', [AdminController::class, 'addBill']); // Add bill to user
    Route::put('/bill/{bill}', [AdminController::class, 'editBill']); // edit bill to user
    Route::delete('/bill/{bill}', [AdminController::class, 'deleteBill']); // edit bill to user
    Route::get('/bills', [AdminController::class, 'listBills']); // View all bills
    Route::get('/bill/{bill}', [AdminController::class, 'getBill']); // View bill
    Route::get('/users/{user}/bills', [AdminController::class, 'listUserBills']); // View all bills

    Route::post('/invoices', [AdminController::class, 'addInvoice']); // Add invoice to user
    Route::post('/invoice/{user}/{bill}', [AdminController::class, 'createInvoiceForBill']); // Add invoice to user
    Route::get('/invoices', [AdminController::class, 'listInvoices']); // View all invoices
    Route::get('/users/{user}/invoices', [AdminController::class, 'listUserInvoices']); // View all invoices
    Route::get('/invoice/{invoice}', [AdminController::class, 'getInvoice']); // View all invoices

    Route::get('/refunds', [AdminController::class, 'listRefunds']); // View all refunds
    Route::post('/refunds/{refundId}/confirm', [AdminController::class, 'confirmRefund']); // Confirm refund

    Route::get('/transactions', [AdminController::class, 'listTransactions']); // View all transactions
    Route::get('/users/{user}/transactions', [AdminController::class, 'listUserTransactions']); // View all transactions
    Route::post('/{user}/transactions', [AdminController::class, 'increaseUserBalance']); // View all transactions

    Route::get('/summary', [AdminController::class, 'getSummary']); // View all transactions
});

Route::middleware('auth:sanctum')->group(function () {
    // User profile routes
    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);

    // User financial routes
    Route::post('/user/deposit', [UserController::class, 'depositFunds']);
    Route::post('/user/withdraw', [UserController::class, 'withdrawFunds']);

    // User data routes
    Route::get('/user/bills', [UserController::class, 'viewBills']);
    Route::get('/user/invoices', [UserController::class, 'viewInvoices']);
    Route::get('/user/transactions', [UserController::class, 'viewTransactions']);
    Route::get('/user/refunds', [UserController::class, 'viewRefunds']);

    // Refund request
    Route::post('/user/refunds', [UserController::class, 'requestRefund']);
});

Route::middleware(['auth:sanctum', 'checkRole:third_party_app'])->prefix('third-party')->group(function () {
    // Initiate a transaction
    Route::post('/transactions', [ThirdPartyController::class, 'initiateTransaction']);

    // View transactions
    Route::get('/transactions', [ThirdPartyController::class, 'viewTransactions']);

    // Request a refund
    Route::post('/refunds', [ThirdPartyController::class, 'requestRefund']);

    // Account details
    Route::get('/account', [ThirdPartyController::class, 'accountDetails']);
    Route::put('/account', [ThirdPartyController::class, 'updateAccount']);
});
