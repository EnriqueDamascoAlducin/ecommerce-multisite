<?php

namespace App\Http\Middleware;

use App\Domain\Store\StoreResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el sitio actual (website/store) para las peticiones del storefront
 * y lo deja disponible vía StoreContext (singleton).
 */
class ResolveStore
{
    public function __construct(private readonly StoreResolver $resolver) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->resolver->resolve($request);

        return $next($request);
    }
}
