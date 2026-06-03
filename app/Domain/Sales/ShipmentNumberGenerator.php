<?php

namespace App\Domain\Sales;

use App\Models\Shipment;
use App\Models\Website;

class ShipmentNumberGenerator
{
    public function next(Website $website): string
    {
        $sequence = Shipment::where('website_id', $website->id)->count() + 1001;

        return strtoupper($website->code).'-ENV-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
