<?php

namespace App\Http\Controllers;

class ProjectController
{
    public function update($request, $project): void
    {
        $this->authorize('update', $project);
    }
}
