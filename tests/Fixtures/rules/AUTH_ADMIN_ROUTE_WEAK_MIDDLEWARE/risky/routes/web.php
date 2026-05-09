<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin/payments', ['middleware' => ['auth'], 'uses' => 'PaymentAdminController@index']);
