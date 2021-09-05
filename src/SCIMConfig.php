<?php

namespace UniqKey\Laravel\SCIMServer;

use UniqKey\Laravel\SCIMServer\SCIM\Schema;
use UniqKey\Laravel\SCIMServer\SCIMRoutes;
use UniqKey\Laravel\SCIMServer\SCIMHelper;
use UniqKey\Laravel\SCIMServer\Attributes\AttributeMapping;

class SCIMConfig
{
    /**
     * @param string $name
     * @return array|null
     */
    public function getConfigForResource(string $name): ?array
    {
        if ($name == 'Users') {
            return $this->getUserConfig();
        } else {
            $result = $this->getConfig();
            return isset($result[$name]) ? $result[$name] : null;
        }
    }

    /**
     * @return array
     */
    public function getUserConfig(): array
    {
        return [
            // Set to 'null' to make use of auth.providers.users.model (App\User::class)
            'class' => resolve(SCIMHelper::class)->getAuthUserClass(),
            'validations' => [
                Schema::SCHEMA_USER . ':userName' => 'required',
                Schema::SCHEMA_USER . ':password' => 'nullable',
                Schema::SCHEMA_USER . ':active' => 'nullable|boolean',
                Schema::SCHEMA_USER . ':emails' => 'array',
                Schema::SCHEMA_USER . ':emails.*.value' => 'email',
                Schema::SCHEMA_USER . ':phoneNumbers' => 'nullable|array',
                Schema::SCHEMA_USER . ':phoneNumbers.*.value' => 'required|string',
                Schema::SCHEMA_USER . ':roles' => 'nullable|array',
                Schema::SCHEMA_USER . ':roles.*.value' => 'required',
                Schema::SCHEMA_USER . ':title' => 'nullable|string',
            ],
            'singular' => 'User',
            'schema' => Schema::SCHEMA_USER,
            //eager loading
            'withRelations' => [],
            'map_unmapped' => true,
            'unmapped_namespace' => 'urn:ietf:params:scim:schemas:laravel:unmapped',
            'description' => 'User Account',
            // Map a SCIM attribute to an attribute of the object.
            'mapping' => [
                'id' => AttributeMapping::eloquent('id')->disableWrite(),
                'externalId' => null,
                'meta' => [
                    'created' => AttributeMapping::eloquent('created_at')->disableWrite(),
                    'lastModified' => AttributeMapping::eloquent('updated_at')->disableWrite(),
                    'location' => (new AttributeMapping())->setRead(function ($object) {
                        return resolve(SCIMRoutes::class)->route('scim.resource', [
                            'resourceType' => 'Users',
                            'resourceObject' => $object->id,
                        ]);
                    })->disableWrite(),
                    'resourceType' => AttributeMapping::constant('User'),
                ],
                'schemas' => AttributeMapping::constant([
                    Schema::SCHEMA_USER,
                    'example:name:space',
                ])->ignoreWrite(),
                'example:name:space' => [
                    'cityPrefix' => AttributeMapping::eloquent('cityPrefix'),
                ],
                Schema::SCHEMA_USER => [
                    'userName' => AttributeMapping::eloquent('email'),
                    'name' => [
                        'formatted' => AttributeMapping::eloquent('name'),
                        'familyName' => null,
                        'givenName' => null,
                        'middleName' => null,
                        'honorificPrefix' => null,
                        'honorificSuffix' => null,
                    ],
                    'displayName' => AttributeMapping::eloquent('name'),
                    'nickName' => null,
                    'profileUrl' => null,
                    'title' => AttributeMapping::eloquent('role'),
                    'userType' => null,
                    'preferredLanguage' => null, // Section 5.3.5 of [RFC7231]
                    'locale' => null, // see RFC5646
                    'timezone' => null, // see RFC6557
                    'active' => AttributeMapping::eloquent('active'),
                    'password' => AttributeMapping::eloquent('password')->disableRead(),
                    // Multi-Valued Attributes
                    'phoneNumbers' => [[
                        'value' => AttributeMapping::eloquent('phone'),
                        'display' => null,
                        'type' => AttributeMapping::constant('mobile')->ignoreWrite(),
                        'primary' => AttributeMapping::constant(true)->ignoreWrite(),
                    ],[
                        'value' => AttributeMapping::eloquent('phone'),
                        'display' => null,
                        'type' => AttributeMapping::constant('work')->isWriteSupported(),
                        'primary' => AttributeMapping::constant(false)->ignoreWrite(),
                    ]],
                    'ims' => [[
                        'value' => null,
                        'display' => null,
                        'type' => null,
                        'primary' => null,
                    ]], // Instant messaging addresses for the User
                    'photos' => [[
                        'value' => null,
                        'display' => null,
                        'type' => null,
                        'primary' => null,
                    ]],
                    'addresses' => [[
                        'formatted' => null,
                        'streetAddress' => null,
                        'locality' => null,
                        'region' => null,
                        'postalCode' => null,
                        'country' => null,
                        'type' => null,
                    ]],
                    'groups' => [[
                        'value' => null,
                        '$ref' => null,
                        'display' => null,
                        'type' => null,
                    ]],
                    'entitlements' => null,
                    'roles' => null,
                    'x509Certificates' => null,
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'Users' => $this->getUserConfig(),
        ];
    }
}
