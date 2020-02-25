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
//
//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::group(['middleware' => ['api']], function () {
    Route::prefix('admin')->group(function () {

        // Session Routes
        Route::post('/login', 'SessionController@login')->name('admin.session.login');
        Route::get('/logout', 'SessionController@logout')->name('admin.session.logout');


        //Roles Routes
        Route::group(['as' => 'admin.', 'middleware' => ['jwt.auth']],function(){
            Route::resource('roles' ,'RoleController');
            Route::resource('users' ,'UserController');
        });

    });
});