<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MyController;

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
    return view('home');
});

// Route::get('predict',[MyController::class,'index']);
Route::post('result',[MyController::class,'index']);
Route::get('result',[MyController::class,'index']);
Route::post('result1',[MyController::class,'result']);
Route::get('result1',[MyController::class,'result']);
