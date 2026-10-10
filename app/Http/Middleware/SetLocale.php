<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the UI language for the request: the signed-in user's preference, else the
 * `locale` cookie, else Bahasa Melayu.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        Locale::apply(Locale::forRequest($request));

        return $next($request);
    }
}
