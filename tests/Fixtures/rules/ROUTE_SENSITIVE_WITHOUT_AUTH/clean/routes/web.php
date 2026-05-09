<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin/users', ['middleware' => ['auth'], 'uses' => 'AdminUserController@index']);
