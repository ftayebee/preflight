<?php

namespace App\Policies;

class InvoicePolicy
{
    public function view($user, $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }
}
