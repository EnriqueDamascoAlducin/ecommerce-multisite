<?php

namespace App\Domain\Sales;

use App\Models\Invoice;
use App\Models\Website;

class InvoiceNumberGenerator
{
    public function next(Website $website): string
    {
        $sequence = Invoice::where('website_id', $website->id)->count() + 1001;

        return strtoupper($website->code).'-INV-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
