<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest / public routes
|--------------------------------------------------------------------------
*/

// Registration step 1: send a signed verification link to the email address.
Route::post('/verification', Controllers\EmailVerificationController::class)->middleware('throttle:emails');
// Registration step 2: validate the signed link the user clicked.
Route::get('/verify', Controllers\ValidateSignatureController::class);
// Registration step 3: create the account (requires a verified signature from step 2).
Route::post('/account-setup', [Controllers\AccountSetupController::class, 'store']);

// Session based login / logout (Sanctum SPA authentication).
Route::post('/auth', [Controllers\AuthController::class, 'store']);
Route::delete('/auth', [Controllers\AuthController::class, 'destroy']);

// Verify an existing user's email (or a changed email) from the signed link.
Route::post('/auth/verification', [Controllers\Auth\VerificationController::class, 'store']);

Route::post('/password/forgot', [Controllers\Auth\ForgotPasswordController::class, 'store'])->middleware('throttle:emails');
Route::post('/password/reset', [Controllers\Auth\ResetPasswordController::class, 'store']);

// App-wide UI settings (theme colours, app name, ...). Public so the sign-in screens are themed too.
Route::get('/settings', [Controllers\SettingController::class, 'show']);

// Read by the customer website (desivideshidiner.com.au).
Route::prefix('public')->middleware('throttle:public')->group(function () {
    Route::get('/menu', Controllers\PublicApi\MenuController::class);
    Route::get('/store', Controllers\PublicApi\StoreController::class);
    Route::post('/orders', [Controllers\PublicApi\OrderController::class, 'store'])->middleware('throttle:orders');
    Route::get('/orders/{token}', [Controllers\PublicApi\OrderController::class, 'show']);

    // Customer accounts (session cookie, "customer" guard).
    Route::post('/customer/register', [Controllers\Customer\AuthController::class, 'register'])->middleware('throttle:customer-auth');
    Route::post('/customer/login', [Controllers\Customer\AuthController::class, 'login'])->middleware('throttle:customer-auth');
    Route::post('/customer/logout', [Controllers\Customer\AuthController::class, 'logout']);
    Route::post('/customer/password/forgot', [Controllers\Customer\PasswordController::class, 'forgot'])->middleware('throttle:emails');
    Route::post('/customer/password/reset', [Controllers\Customer\PasswordController::class, 'reset'])->middleware('throttle:customer-auth');

    Route::middleware('auth:customer')->group(function () {
        Route::get('/customer', [Controllers\Customer\AccountController::class, 'show']);
        Route::put('/customer', [Controllers\Customer\AccountController::class, 'update']);
        Route::get('/customer/orders', [Controllers\Customer\AccountController::class, 'orders']);
    });
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Available before the 2FA login challenge has been completed.
    Route::get('/user', Controllers\Auth\UserController::class);
    Route::post('/two-factor/sms', Controllers\TwoFactor\SmsController::class)->middleware('throttle:two-factor-send');
    Route::post('/two-factor/email', Controllers\TwoFactor\EmailController::class)->middleware('throttle:two-factor-send');
    Route::post('/two-factor/verify', Controllers\TwoFactor\VerifyController::class)->middleware('throttle:two-factor-verify');

    // Everything below requires the 2FA challenge to be passed (when the user has 2FA enabled).
    Route::middleware(['2fa'])->group(function () {
        Route::put('/two-factor', Controllers\TwoFactor\UpdateController::class);
        Route::put('/accept-terms', Controllers\AcceptTermsController::class);
        Route::post('/auth/verification/resend', [Controllers\Auth\ResendVerificationController::class, 'store'])->middleware('throttle:two-factor-send');

        // Users
        Route::get('/users', [Controllers\UserController::class, 'index']);
        Route::post('/users', [Controllers\UserController::class, 'store']);
        Route::get('/users/{user}', [Controllers\UserController::class, 'show']);
        Route::put('/users/{user}', [Controllers\UserController::class, 'update']);
        Route::delete('/users/{user}', [Controllers\UserController::class, 'destroy']);
        Route::put('/users/{user}/password', [Controllers\User\PasswordController::class, 'update']);
        Route::put('/users/{user}/disable-two-factor', Controllers\User\DisableTwoFactorController::class);
        Route::get('/users/{user}/permissions', [Controllers\User\PermissionController::class, 'show']);
        Route::put('/users/{user}/permissions', [Controllers\User\PermissionController::class, 'update']);
        Route::delete('/users/{user}/permissions', [Controllers\User\PermissionController::class, 'destroy']);

        // Roles (read-only: roles are defined in code and synced with `php artisan abilities:refresh`)
        Route::get('/roles', [Controllers\RoleController::class, 'index']);
        Route::get('/roles/{role}', [Controllers\RoleController::class, 'show']);

        // Settings (requires the "manage settings" ability)
        Route::put('/settings', [Controllers\SettingController::class, 'update']);
        Route::delete('/settings', [Controllers\SettingController::class, 'destroy']);
        Route::get('/ordering-settings', [Controllers\OrderingSettingController::class, 'show']);
        Route::put('/ordering-settings', [Controllers\OrderingSettingController::class, 'update']);

        // Orders ("read orders" to view, "manage orders" to move them along)
        Route::get('/orders', [Controllers\Orders\OrderController::class, 'index']);
        Route::get('/orders/{order}', [Controllers\Orders\OrderController::class, 'show']);
        Route::put('/orders/{order}/status', [Controllers\Orders\OrderController::class, 'updateStatus']);
        Route::put('/orders/{order}/payment', [Controllers\Orders\OrderController::class, 'updatePayment']);

        // Menu ("read menu" to view, "manage menu" to change)
        Route::get('/menu/categories', [Controllers\Menu\CategoryController::class, 'index']);
        Route::post('/menu/categories', [Controllers\Menu\CategoryController::class, 'store']);
        Route::put('/menu/categories/sort', [Controllers\Menu\CategoryController::class, 'sort']);
        Route::put('/menu/categories/{category}', [Controllers\Menu\CategoryController::class, 'update']);
        Route::delete('/menu/categories/{category}', [Controllers\Menu\CategoryController::class, 'destroy']);
        Route::put('/menu/categories/{category}/items/sort', [Controllers\Menu\ItemController::class, 'sort']);
        Route::post('/menu/items', [Controllers\Menu\ItemController::class, 'store']);
        Route::put('/menu/items/{item}', [Controllers\Menu\ItemController::class, 'update']);
        Route::delete('/menu/items/{item}', [Controllers\Menu\ItemController::class, 'destroy']);
    });
});
