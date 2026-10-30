<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards GET routes that change the signed-in user's state: they must be opened
 * as a page, not loaded as an embedded image, script or frame by other content.
 */
class RequireNavigation
{
    public function handle(Request $request, Closure $next): Response
    {
        $destination = $request->headers->get('Sec-Fetch-Dest');
        abort_if($destination !== null && $destination !== 'document', 403);

        return $next($request);
    }
}
