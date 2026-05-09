<?php

use Illuminate\Support\Facades\Route;

Route::get('/users/{user}/delete', ['middleware' => ['auth'], 'uses' => 'UserController@destroy']);
