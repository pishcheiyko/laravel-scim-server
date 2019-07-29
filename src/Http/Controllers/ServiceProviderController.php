<?php

namespace UniqKey\Laravel\SCIMServer\Http\Controllers;

use UniqKey\Laravel\SCIMServer\SCIM\Schema;
use UniqKey\Laravel\SCIMServer\SCIMRoutes;
use Illuminate\Support\Carbon;

class ServiceProviderController extends BaseController
{
    public function index()
    {
        return [
            'schemas' => [Schema::SCHEMA_SERVICE_PROVIDER_CONFIG],
            'patch' => [
                'supported' => true,
            ],
            'bulk' => [
                'supported' => false,
            ],
            'filter' => [
                'supported' => true,
                'maxResults' => 100,
            ],
            'changePassword' => [
                'supported' => true,
            ],
            'sort' => [
                'supported' => true,
            ],
            'etag' => [
                'supported' => true,
            ],
            'authenticationSchemes' => [ // 'oauth', 'oauth2', 'oauthbearertoken', 'httpbasic', and 'httpdigest'
                [
                    'name' => 'OAuth Bearer Token',
                    'description' => 'Authentication scheme using the OAuth Bearer Token Standard',
                    'specUri' => 'http://www.rfc-editor.org/info/rfc6750',
                    'documentationUri' => 'http://example.com/help/oauth.html',
                    'type' => 'oauthbearertoken',
                    'primary' => true,
                ],
                [
                    'name' => 'HTTP Basic',
                    'description' => 'Authentication scheme using the HTTP Basic Standard',
                    'specUri' => 'http://www.rfc-editor.org/info/rfc2617',
                    'documentationUri' => 'http://example.com/help/httpBasic.html',
                    'type' => 'httpbasic',
                ],
            ],
            'meta' => [
                'location' => resolve(SCIMRoutes::class)->route('scim.serviceproviderconfig'),
                'resourceType' => 'ServiceProviderConfig',
                'created' => Carbon::createFromTimestampUTC(filectime(__FILE__))->format('c'),
                'lastModified' => Carbon::createFromTimestampUTC(filemtime(__FILE__))->format('c'),
                'version' => sprintf('W/"%s"', sha1(filemtime(__FILE__))),
            ],
        ];
    }
}
