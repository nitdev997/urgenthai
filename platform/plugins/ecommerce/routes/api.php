<?php

use Botble\Ecommerce\Http\Controllers\API\PaymentController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'api',
    'prefix' => 'api/v1',
    'namespace' => 'Botble\Ecommerce\Http\Controllers\API',
], function () {
    Route::get('product-categories', 'ProductCategoryController@index');
    Route::get('/products/category/{categoryId}', 'ProductCategoryController@getByCategoryId');
    Route::get('/products/{productId}', 'ProductCategoryController@getProductDetail');
    Route::get('/categories-with-products', 'ProductCategoryController@getCategoriesWithProducts');

    Route::group(['prefix' => 'auth'], function () {
        Route::post('send-otp', 'AuthenticationController@sendOTP');
        Route::post('verify-otp', 'AuthenticationController@verifyOTP');
    });

    Route::group([
        'prefix' => 'profile',
        'middleware' => 'auth:sanctum'
    ], function () {
        Route::get('/', 'UserController@getProfile');
        Route::post('/', 'UserController@updateProfile');
        Route::get('/addresses', 'UserController@getCustomerAllAddresses');
    });

    Route::group([
        'prefix' => 'address',
        'middleware' => 'auth:sanctum'
    ], function () {
        Route::get('/edit', 'AddressController@editCustomerAddress');
        Route::post('create', 'AddressController@createCustomerAddress');
        Route::post('/update', 'AddressController@updateCustomerAddress');
        Route::delete('/delete/{id}', 'AddressController@deleteCustomerAddress');
    });

    Route::post('/login', 'AuthenticationController@login');

    Route::group([
        'prefix' => 'cart',
        'middleware' => 'auth:sanctum'
    ], function () {
        Route::get('/', 'CartController@getCartItem');
        Route::post('add', 'CartController@store');
        Route::post('remove-item', 'CartController@remove');
        //Route::post('generate-cart-token','CartController@generateCartToken');
    });

    Route::group([
        'prefix' => 'order',
        'middleware' => 'auth:sanctum'
    ], function () {
        Route::get('my-orders', 'OrderPlaceController@myOrders');
        Route::get('my-orders/{id}', 'OrderPlaceController@orderDetails');
        Route::post('/checkout', 'OrderPlaceController@checkout');
        Route::post('/re-order/{id}', 'OrderPlaceController@reOrder');
        Route::post('/favorite/{id}', 'OrderPlaceController@makeFavorite');
    });

    Route::group([
        'prefix' => 'search',
        'middleware' => 'auth:sanctum'
    ], function () {
        Route::get('onhome', 'SearchController@searchOnHome');
    });

    Route::group([
        'prefix' => 'notifications',
        'middleware' => 'auth:sanctum'
    ], function () {
        Route::get('/', 'NotificationController@index');
    });

    Route::group([
        'prefix' => 'rating',
        'middleware' => 'auth:sanctum'
    ], function () {
        Route::post('/', 'RatingController@rateProduct');
        Route::post('/driver', 'RatingController@rateDriver');
    });

    Route::group([
        'prefix' => 'payments',
        'middleware' => 'auth:sanctum'
    ], function () {
        Route::post('/create-order', [PaymentController::class, 'createOrder']);
        Route::post('/verify-payment', [PaymentController::class, 'verifyPayment']);
    });
});
