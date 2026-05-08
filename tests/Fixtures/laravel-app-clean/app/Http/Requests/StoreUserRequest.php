<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-user');
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }
}
