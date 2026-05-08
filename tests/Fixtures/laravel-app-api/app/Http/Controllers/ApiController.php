<?php

namespace App\Http\Controllers;

class ApiController
{
    public function ping()
    {
        return ['ok' => true];
    }
}
