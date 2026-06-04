<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Store\StoreContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreInquiryRequest;
use App\Models\StoreInquiry;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StoreInquiryController extends Controller
{
    public function __construct(private readonly StoreContext $context) {}

    public function store(StoreInquiryRequest $request): RedirectResponse
    {
        if (! $this->context->hasStore()) {
            throw new NotFoundHttpException('Tienda no resuelta.');
        }

        StoreInquiry::create([
            ...$request->validated(),
            'store_id' => $this->context->store()->id,
            'status' => StoreInquiry::STATUS_NEW,
        ]);

        return back()->with('success', 'Solicitud recibida. Te contactaremos pronto.');
    }
}
