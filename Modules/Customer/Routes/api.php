<?php

use Modules\Customer\Http\Controllers\CustomerAccountController;
use Modules\Customer\Http\Controllers\CustomerAddressAccountController;
use Modules\Customer\Http\Controllers\SessionController;
use Modules\Customer\Http\Controllers\RegistrationController;
use Modules\Customer\Http\Controllers\ResetPasswordController;
use Modules\Customer\Http\Controllers\ForgotPasswordController;

Route::group(["middleware" => ["api"]], function () {
    // CUSTOMER ROUTES
    Route::group(["prefix" => "customers", "as" => "customers."],function () {
        Route::post("/register", [RegistrationController::class, "register"])->name("register");
        // Session Routes
        Route::post("/login", [SessionController::class, "login"])->name("session.login");
        Route::get("/logout", [SessionController::class, "logout"])->middleware("jwt.verify")->name("session.logout");
        Route::post("/forget-password", [ForgotPasswordController::class, "store"])->name("forget-password.store");
        Route::post("/reset-password", [ResetPasswordController::class, "store"])->name("reset-password.store");
        Route::get("/reset-password/{token}", [ResetPasswordController::class, "create"])->name("reset-password.create");
    });

    Route::group(["middleware" => ["customer"], "prefix" => "customers", "as" => "customers."], function () {
        // CUSTOMER PROFILE
        Route::group(["prefix" => "accounts", "as" => "account."], function () {
            Route::get("/", [CustomerAccountController::class, "show"])->name("show");
            Route::put("/", [CustomerAccountController::class, "update"])->name("update");
            Route::post("image", [CustomerAccountController::class, "uploadProfileImage"])->name("image.update");
            Route::delete("image", [CustomerAccountController::class, "deleteProfileImage"])->name("image.delete");    
        });
        
        // CUSTOMER ADDRESS
        Route::group(["prefix" => "addresses", "as" => "address."], function () {
            Route::get("/", [CustomerAddressAccountController::class, "show"])->name("show");
            Route::post("/", [CustomerAddressAccountController::class, "create"])->name("create");
            Route::put("/{id}", [CustomerAddressAccountController::class, "update"])->name("update");
            Route::delete("/{id}", [CustomerAddressAccountController::class, "delete"])->name("delete");
        });
    });

    // ADMIN CUSTOMERS ROUTES
    Route::group(["prefix" => "admin", "as" => "admin.", "middleware" => ["language", "admin"]],function () {
        // Customer Group Routes
        Route::resource("groups", CustomerGroupController::class)->except(["create", "edit"]);

        // Customer Routes
        Route::put("/customers/{customer_id}/status", [\Modules\Customer\Http\Controllers\CustomerController::class, "updateStatus"])->name("customers.status");
        Route::resource("customers", CustomerController::class)->except(["create", "edit"]);
        Route::get("customers/{customer_id}/view", [\Modules\Customer\Http\Controllers\CustomerController::class, "view"])->name("customers.view");


        // Customer Address Routes
        Route::group(["prefix" => "customers/{customers}", "as" => "customers."], function() {
            Route::resource("addresses", AddressController::class)->except(["create", "edit"]);
        });
    });


    // Customer Coupon Routes
    Route::group(["prefix" => "customers/coupon", "as" => "customers.coupons."],function () {
        Route::get("/publicly-available", [\Modules\Customer\Http\Controllers\CustomerCouponController::class, "publiclyAvailableCoupons"])->name("publicly_available");
        Route::get("/{id}", [\Modules\Customer\Http\Controllers\CustomerCouponController::class, "show"])->name("show");
    });
});
