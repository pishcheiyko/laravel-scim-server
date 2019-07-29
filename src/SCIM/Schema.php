<?php

namespace UniqKey\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;

class Schema implements Jsonable
{
    const SCHEMA_USER = 'urn:ietf:params:scim:schemas:core:2.0:User';
    const SCHEMA_GROUP = 'urn:ietf:params:scim:schemas:core:2.0:Group';
    const SCHEMA_SERVICE_PROVIDER_CONFIG = 'urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig';
    const SCHEMA_RESOURCE_TYPE = 'urn:ietf:params:scim:schemas:core:2.0:ResourceType';

    const ATTRIBUTES_CORE = [
        'id',
        'externalId',
        'meta',
        'schemas',
    ];

    /**
     * {@inheritdoc}
     */
    public function toJson($options = 0)
    {
        return [];
    }
}
