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
        $input = $request->input();
        $time = time();
        file_put_contents(storage_path('income_request'.$time.'.txt'), json_encode($input, $request->method()));
        if (false === $request->isMethod('get')
        &&  false === $this->isValidContentType($request)
        &&  strlen($request->getContent()) > 0) {
            throw (new SCIMException('The content-type header should be set to "application/scim+json".'))
                ->setHttpCode(400);
        }

        $response = $next($request);

        return $response->header('Content-Type', 'application/scim+json');
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function isValidContentType(Request $request): bool
    {
        if (false === $request->hasHeader('content-type')) {
            return false;
        }

        $header = $request->header('content-type', '');
        $chunks = explode(';', $header);
        $contentType = $chunks[0];

        return 'application/scim+json' == $contentType
            || 'application/json' == $contentType;
    }
}
