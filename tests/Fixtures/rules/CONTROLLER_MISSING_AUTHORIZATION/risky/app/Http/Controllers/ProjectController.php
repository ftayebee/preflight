<?php

namespace App\Http\Controllers;

class ProjectController
{
    public function update($request, $project): void
    {
        $project->update($request->validated());
    }
}
