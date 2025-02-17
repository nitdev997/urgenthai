<?php

use Botble\Base\Facades\AdminHelper;
use Botble\Driver\Http\Controllers\DriverController;
use Illuminate\Support\Facades\Route;

AdminHelper::registerRoutes(function () {
    Route::group(['prefix' => 'drivers', 'as' => 'driver.'], function () {
        Route::resource('', DriverController::class)->parameters(['' => 'driver']);
    });
});
