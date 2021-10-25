<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'App is running!!',
    ]);
});

Route::get('/check', function (\Illuminate\Http\Request $request) {
    return view('check');
});

Route::get('/ip', function (\Illuminate\Http\Request $request) {
    $ip = \Modules\Tax\Facades\GeoIp::clientIp();
    return response()->json([
        "ip" => $ip
    ]);
    //return view('check');
});
