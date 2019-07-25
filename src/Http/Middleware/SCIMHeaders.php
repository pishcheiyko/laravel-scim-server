<?php

namespace UniqKey\Laravel\SCIMServer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;

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
            throw (new SCIMException('The content-type header should be set to "application/scim+json"'))
                ->setHttpCode(400);
        }

        $response = $next($request);

        return $response->header('Content-Type', 'application/scim+json');
    }
}
