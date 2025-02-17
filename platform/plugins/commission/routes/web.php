<?php

use Botble\Base\Facades\AdminHelper;
use Botble\Commission\Http\Controllers\CommissionController;
use Illuminate\Support\Facades\Route;

AdminHelper::registerRoutes(function () {
    Route::group(['prefix' => 'commissions', 'as' => 'commission.'], function () {
        Route::resource('', CommissionController::class)->parameters(['' => 'commission']);
    });
});
