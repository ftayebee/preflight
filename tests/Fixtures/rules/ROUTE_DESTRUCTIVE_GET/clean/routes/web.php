<?php

use Illuminate\Support\Facades\Route;

Route::delete('/users/{user}', ['middleware' => ['auth'], 'uses' => 'UserController@destroy']);
