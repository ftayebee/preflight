<?php

namespace App\Policies;

class InvoicePolicy
{
    public function view($user, $invoice): bool
    {
        return true;
    }
}
