<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'api',
    'prefix' => 'api/v1',
    'namespace' => 'Botble\Driver\Http\Controllers\API',
], function () {
    Route::group(['prefix' => 'driver'], function () {
        Route::post('register', 'AuthenticationController@register');
        Route::post('login', 'AuthenticationController@login');
        Route::group([
            'middleware' => 'auth:sanctum'
        ], function () {
            Route::post('logout', 'AuthenticationController@logout');
            Route::post('store-details', 'AuthenticationController@storeDriverDetails');
            Route::get('details', 'AuthenticationController@getDriverDetails');
        });

        Route::group([
            'prefix' => 'password',
        ], function () {
            Route::post('send-code', 'PasswordResetController@sendCode');
            Route::post('verify-code', 'PasswordResetController@verifyCode');
            Route::post('update-password', 'PasswordResetController@updatePassword');
        });

        Route::group(['prefix' => 'orders', 'middleware' => 'auth:sanctum'], function () {
            Route::get('', 'OrderController@index');
            Route::get('/my-orders', 'OrderController@myOrders');
            Route::get('/history', 'OrderController@orderHistory');
            Route::get('/{id}', 'OrderController@orderDetails');
            Route::post('/{id}/accept', 'OrderController@accept');
            Route::post('/{id}/reject', 'OrderController@reject');
            Route::post('/{id}/collect', 'OrderController@collect');
            Route::post('/{id}/deliver', 'OrderController@deliver');
        });

        Route::group(['prefix' => 'notifications', 'middleware' => 'auth:sanctum'], function () {
            Route::get('/', 'NotificationController@index');
        });

        Route::group(['prefix' => 'accounts', 'middleware' => 'auth:sanctum'], function () {
            Route::get('/', 'AccountController@index');
            Route::post('/', 'AccountController@create');
            Route::put('/default/{id}', 'AccountController@setDefault');
            Route::put('/{id}', 'AccountController@update');
            Route::delete('/{id}', 'AccountController@destroy');
        });
    });
});
