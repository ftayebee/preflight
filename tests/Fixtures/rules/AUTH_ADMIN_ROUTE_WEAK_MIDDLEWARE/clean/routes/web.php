<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin/payments', ['middleware' => ['auth', 'permission:payments.view'], 'uses' => 'PaymentAdminController@index']);
