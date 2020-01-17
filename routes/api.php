<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function(){
    Route::post('login', 'Api\AuthController@login');
    Route::get('logout', 'Api\AuthController@logout');
    Route::post('login-by-social', 'Api\AuthController@loginBySocial');
    Route::post('send-password-code', 'Api\AuthController@sendPasswordCode');
    Route::post('password-verification', 'Api\AuthController@passwordVerification');
    Route::post('update-personal-info', 'Api\AuthController@updatePersonalInfo');
    Route::post('rooms/add-admin', 'Api\RoomController@addAdmin');
    Route::post('rooms/add-invite', 'Api\RoomController@addInvite');
    Route::post('rooms/remove-invite', 'Api\RoomController@removeInvite');
    Route::post('register', 'Api\AuthController@register');
    Route::post('rooms/search', 'Api\RoomController@search');
    Route::post('playlist/search', 'Api\PlaylistController@search');
    Route::post('rooms/remove-admin', 'Api\RoomController@removeAdmin');
    Route::post('rooms/{id}', 'Api\RoomController@update');
    Route::resource('rooms', 'Api\RoomController');
    Route::get('playlist/{id}', 'Api\PlaylistController@show');
    Route::post('playlist/{id}', 'Api\PlaylistController@update');
    Route::resource('playlist', 'Api\PlaylistController');
    Route::get('user', 'Api\UserController@getInfo');
    Route::post('add-social-provider', 'Api\UserController@addProviderInfo');
    Route::post('remove-social-provider', 'Api\UserController@destroySocialAccount');
    Route::post('user', 'Api\UserController@getInfo');
    Route::post('remove-song', 'Api\SongController@destroy');
    Route::post('action', 'Api\SongController@update');
    Route::resource('song', 'Api\SongController');
});