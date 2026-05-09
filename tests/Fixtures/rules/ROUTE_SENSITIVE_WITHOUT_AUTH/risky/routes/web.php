<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin/users', ['middleware' => ['web'], 'uses' => 'AdminUserController@index']);
