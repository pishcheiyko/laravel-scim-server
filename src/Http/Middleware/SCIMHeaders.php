<?php

namespace ArieTimmerman\Laravel\SCIMServer\Middleware\Http;

use Closure;
use Illuminate\Http\Request;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class SCIMHeaders
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws SCIMException
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->method() != 'GET'
        &&  $request->header('content-type') != 'application/scim+json'
        &&  $request->header('content-type') != 'application/json'
        &&  strlen($request->getContent()) > 0) {
            throw new SCIMException('The content-type header should be set to "application/scim+json"');
        }

        $response = $next($request);

        return $response->header('Content-Type', 'application/scim+json');
    }
}
