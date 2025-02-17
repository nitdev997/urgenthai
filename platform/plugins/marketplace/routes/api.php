<?php

use Botble\Driver\Http\Controllers\API\OrderController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'api',
    'prefix' => 'api/v1',
    'namespace' => 'Botble\Marketplace\Http\Controllers\API'
], function () {
    Route::group(['prefix' => 'vendor'], function () {
        Route::post('register', 'VendorController@register');
        Route::post('login', 'VendorController@login');

        Route::group(['middleware' => 'auth:sanctum'], function () {
            Route::post('logout', 'VendorController@logout');
            Route::get('profile', 'VendorController@profile');
            Route::post('update', 'VendorController@update');
        });

        // Password
        Route::group(['prefix' => 'password'], function () {
            Route::post('send-code', 'VendorController@sendCode');
            Route::post('verify-code', 'VendorController@verifyCode');
            Route::post('update-password', 'VendorController@updatePassword');
        });

        // Products
        Route::group(['prefix' => 'products', 'middleware' => 'auth:sanctum'], function () {
            Route::get('', 'ProductController@index');
            Route::post('create', 'ProductController@store');
            Route::get('{id}', 'ProductController@view');
            Route::post('update/{id}', 'ProductController@update');
            Route::delete('delete/{id}', 'ProductController@destroy');
        });

        // Orders
        Route::group(['prefix' => 'orders', 'middleware' => 'auth:sanctum'], function () {
            Route::get('/pending', 'OrderController@pending');
            Route::get('/ongoing', 'OrderController@ongoing');
            Route::get('/history', 'OrderController@orderHistory');
            Route::get('/{id}', 'OrderController@orderDetails');
            Route::post('/accept/{id}', 'OrderController@acceptOrder');
            Route::post('/reject/{id}', 'OrderController@rejectOrder');
        });

        // Notifications
        Route::group(['prefix' => 'notifications', 'middleware' => 'auth:sanctum'], function () {
            Route::get('', 'NotificationController@index');
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
