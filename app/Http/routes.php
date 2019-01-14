<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::any('/', function () {
    return abort('403');
});

//后台模块
Route::group(['namespace'=>'Admin','prefix' => 'admin','middleware' => ['AdminToken','VerifyAuthToken']], function () {
    Route::post('/Common/api', 'CommonController@index');
    Route::options('/Common/api', 'CommonController@index');
    Route::post('/account/api', 'AccountController@index');
    Route::options('/account/api', 'AccountController@index');
    Route::post('/goods/api', 'GoodsController@index');
    Route::options('/goods/api', 'GoodsController@index');
    Route::post('/user/api', 'UserController@index');
    Route::options('/user/api', 'UserController@index');
    Route::post('/order/api', 'OrderController@index');
    Route::options('/order/api', 'OrderController@index');
    Route::post('/offer/api', 'OfferController@index');
    Route::options('/offer/api', 'OfferController@index');
    Route::post('/address/api', 'AddressController@index');
    Route::options('/address/api', 'AddressController@index');
    Route::post('/system/api', 'SystemController@index');
    Route::options('/system/api', 'SystemController@index');
    Route::post('/app/api', 'AppController@index');
    Route::options('/app/api', 'AppController@index');
    Route::post('/app_version/api', 'AppVersionController@index');
    Route::options('/app_version/api', 'AppVersionController@index');
    Route::post('/banner/api', 'BannerController@index');
    Route::options('/banner/api', 'BannerController@index');
});


//前台模块
Route::group(['namespace'=>'Home','prefix' => 'home','middleware' => ['VerifyAuthToken']], function () {
    Route::post('/account/api', 'AccountController@index');
    Route::options('/account/api', 'AccountController@index');
    Route::post('/address/api', 'AddressController@index');
    Route::options('/address/api', 'AddressController@index');
    Route::post('/goods/api', 'GoodsController@index');
    Route::options('/goods/api', 'GoodsController@index');
    Route::post('/offer/api', 'OfferController@index');
    Route::options('/offer/api', 'OfferController@index');
    Route::post('/order/api', 'OrderController@index');
    Route::options('/order/api', 'OrderController@index');
    Route::post('/system/api', 'SystemController@index');
    Route::options('/system/api', 'SystemController@index');
});


//商贸通接口
Route::group(['namespace'=>'Dealers','prefix' => 'dealers','middleware' => ['VerifyAuthToken']], function () {
    Route::options('/contract/api', 'ContractController@index');
});

//商贸通接口
Route::group(['namespace'=>'Dealers','prefix' => 'dealers'], function () {
    Route::post('/contract/api', 'ContractController@index');
});





