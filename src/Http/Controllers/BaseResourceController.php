<?php

namespace UniqKey\Laravel\SCIMServer\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use UniqKey\Laravel\SCIMServer\ResourceType;
use UniqKey\Laravel\SCIMServer\Helper;
use UniqKey\Laravel\SCIMServer\Contracts\PolicyInterface;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;
use UniqKey\Laravel\SCIMServer\Events\Get;
use UniqKey\Laravel\SCIMServer\Events\Create;
use UniqKey\Laravel\SCIMServer\Events\Replace;
use UniqKey\Laravel\SCIMServer\Events\Patch;
use UniqKey\Laravel\SCIMServer\Events\Delete;

/**
 * Base class
 */
class BaseResourceController extends BaseController
{
    /**
     * Assign an instance in the child class constructor.
     *
     * @var PolicyInterface
     */
    protected $policy;

    /**
     * @param Request $request
     * @return Response
     */
    public function notImplemented(Request $request)
    {
        return response(null, 501);
    }

    /**
     * @param Request $request
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return Response
     * @throws SCIMException
     */
    protected function showModel(
        Request $request,
        ResourceType $resourceType,
        Model $resourceObject
    ): Response {
        $flattened = Helper::flatten(Helper::objectToSCIMArray($resourceObject, $resourceType), $resourceType->getSchema());
        $flattened = $this->validateScim($resourceType, $flattened, $resourceObject);

        if (false === $this->policy->isAllowed($request, PolicyInterface::OPERATION_GET, $flattened, $resourceType, $resourceObject)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }

        event(new Get($resourceObject, [
            'origin' => static::class,
        ]));

        return Helper::objectToSCIMResponse($resourceObject, $resourceType);
    }

    /**
     * @param Request $request
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return Response
     * @throws SCIMException
     */
    protected function deleteModel(
        Request $request,
        ResourceType $resourceType,
        Model $resourceObject
    ): Response {
        $flattened = Helper::flatten(Helper::objectToSCIMArray($resourceObject, $resourceType), $resourceType->getSchema());
        $flattened = $this->validateScim($resourceType, $flattened, $resourceObject);

        if (false === $this->policy->isAllowed($request, PolicyInterface::OPERATION_DELETE, $flattened, $resourceType)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }

        $resourceObject->delete();

        event(new Delete($resourceObject, [
            'origin' => static::class,
        ]));

        return response(null, 204);
    }

    /**
     * @param Request $request
     * @param ResourceType $resourceType
     * @return Response
     * @throws SCIMException
     */
    protected function createModel(
        Request $request,
        ResourceType $resourceType
    ): Response {
        $input = $request->input();

        if (false === isset($input['schemas'])
        ||  false === is_array($input['schemas'])) {
            throw (new SCIMException('Missing a valid schemas-attribute.'))
                ->setHttpCode(500);
        }

        $flattened = Helper::flatten($input, $input['schemas']);
        $flattened = $this->validateScim($resourceType, $flattened);

        if (false === $this->policy->isAllowed($request, PolicyInterface::OPERATION_POST, $flattened, $resourceType)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }

        $class = $resourceType->getClass();
        
        /** @var Model */
        $resourceObject = new $class();
        
        $allAttributeConfigs = [];
        
        foreach ($flattened as $scimAttribute => $value) {
            $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $scimAttribute);
            $attributeConfig->add($value, $resourceObject);
            $allAttributeConfigs[] = $attributeConfig;
        }

        try {
            $resourceObject->save();
        } catch (QueryException $e) {
            throw (new SCIMException("Could not save new {$class} instance", 0, $e))
                ->setHttpCode(500);
        }
        
        foreach ($allAttributeConfigs as &$attributeConfig) {
            $attributeConfig->writeAfter($flattened[$attributeConfig->getFullKey()], $resourceObject);
        }

        event(new Create($resourceObject, [
            'origin' => static::class,
        ]));
        
        return Helper::objectToSCIMCreateResponse($resourceObject, $resourceType);
    }

    /**
     * @todo See 'TODO:' below
     *
     * @param Request $request
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return Response
     * @throws SCIMException
     */
    protected function replaceModel(
        Request $request,
        ResourceType $resourceType,
        Model $resourceObject
    ): Response {
        $originalRaw = Helper::objectToSCIMArray($resourceObject, $resourceType);
        $original = Helper::flatten($originalRaw, $resourceType->getSchema());

        // TODO: get flattend from $resourceObject
        $flattened = Helper::flatten($request->input(), $resourceType->getSchema());
        $flattened = $this->validateScim($resourceType, $flattened, $resourceObject);

        $updated = [];

        foreach ($flattened as $key => $value) {
            if (!isset($original[$key])
            ||  json_encode($original[$key]) != json_encode($flattened[$key])) {
                $updated[$key] = $flattened[$key];
            }
        }

        if (false === $this->policy->isAllowed($request, PolicyInterface::OPERATION_PUT, $updated, $resourceType, $resourceObject)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }

        // Keep an array of written values
        $uses = [];
        
        // Write all values
        foreach ($flattened as $scimAttribute => $value) {
            $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $scimAttribute);

            if ($attributeConfig->isWriteSupported()) {
                $attributeConfig->replace($value, $resourceObject);
            }
            
            $uses[] = $attributeConfig;
        }
        
        // Find values that have not been written in order to empty these.
        $allAttributeConfigs = $resourceType->getAllAttributeConfigs();
                
        foreach ($uses as $use) {
            foreach ($allAttributeConfigs as $key => $option) {
                if ($use->getFullKey() == $option->getFullKey()) {
                    unset($allAttributeConfigs[$key]);
                }
            }
        }

        foreach ($allAttributeConfigs as $attributeConfig) {
            // Do not write write-only attributes (such as passwords)
            if ($attributeConfig->isReadSupported() && $attributeConfig->isWriteSupported()) {
                // @ash TODO: why ArieTimmerman marks this line as commented ???
                //   $attributeConfig->remove($resourceObject);
            }
        }

        $resourceObject->save();

        event(new Replace($resourceObject, [
            'origin' => static::class,
            'previous' => $originalRaw,
        ]));

        return Helper::objectToSCIMResponse($resourceObject, $resourceType);
    }

    /**
     * @todo See 'TODO:' below
     *
     * @param Request $request
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return Response
     * @throws SCIMException
     */
    protected function updateModel(
        Request $request,
        ResourceType $resourceType,
        Model $resourceObject
    ): Response {
        $input = $request->input();
        
        if ($input['schemas'] !== ["urn:ietf:params:scim:api:messages:2.0:PatchOp"]) {
            throw (new SCIMException(sprintf('Invalid schema "%s". MUST be "urn:ietf:params:scim:api:messages:2.0:PatchOp"', json_encode($input['schemas']))))
                ->setHttpCode(404);
        }
                
        if (isset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'])) {
            $input['Operations'] = $input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'];
            unset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations']);
        }
        
        $oldObject = Helper::objectToSCIMArray($resourceObject, $resourceType);
        
        foreach ($input['Operations'] as $operation) {
            switch (strtolower($operation['op'])) {
                case "add":
                    if (isset($operation['path'])) {
                        $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $operation['path']);
                        
                        foreach ($operation['value'] as $value) {
                            $attributeConfig->add($value, $resourceObject);
                        }
                    } else {
                        foreach ($operation['value'] as $key => $value) {
                            $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $key);
                            
                            foreach ($value as $v) {
                                $attributeConfig->add($v, $resourceObject);
                            }
                        }
                    }
                    break;
                
                case "remove":
                    if (isset($operation['path'])) {
                        $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $operation['path']);
                        $attributeConfig->remove($operation['value'] ?? null, $resourceObject);
                    } else {
                        throw new SCIMException('You MUST provide a "Path"');
                    }
                    break;
                    
                case "replace":
                    if (isset($operation['path'])) {
                        $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $operation['path']);
                        $attributeConfig->replace($operation['value'], $resourceObject);
                    } else {
                        foreach ($operation['value'] as $key => $value) {
                            $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $key);
                            $attributeConfig->replace($value, $resourceObject);
                        }
                    }
                    break;
                    
                default:
                    throw new SCIMException(sprintf('Operation "%s" is not supported', $operation['op']));
                    break;
            }
        }

        $dirty = $resourceObject->getDirty();

        // TODO: prevent something from getten written before ...
        $newObject = Helper::flatten(Helper::objectToSCIMArray($resourceObject, $resourceType), $resourceType->getSchema());
        $flattened = $this->validateScim($resourceType, $newObject, $resourceObject);

        if (false === $this->policy->isAllowed($request, PolicyInterface::OPERATION_PATCH, $flattened, $resourceType, $resourceObject)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }
        
        $resourceObject->save();

        event(new Patch($resourceObject, [
            'origin' => static::class,
            'previous' => $oldObject,
        ]));
        
        return Helper::objectToSCIMResponse($resourceObject, $resourceType);
    }

    /**
     * @param ResourceType $resourceType
     * @param array $flattened
     * @param Model|null $resourceObject
     * @return array
     * @throws SCIMException
     */
    protected function validateScim(
        ResourceType $resourceType,
        array $flattened,
        Model $resourceObject = null
    ): array {
        $validations = $resourceType->getValidations();
        $simpleValidations = [];
        $forValidation = [];

        /**
         * Dots have a different meaning in SCIM and in Laravel's validation logic
         */
        foreach ($flattened as $key => $value) {
            $newKey = preg_replace('/([^*])\.([^*])/', '${1}___${2}', $key);

            $forValidation[$newKey] = $value;
        }

        foreach ($validations as $key => $value) {
            $newKey = preg_replace('/([^*])\.([^*])/', '${1}___${2}', $key);

            if (false === is_string($value)) {
                $simpleValidations[$newKey] = $value;
            } elseif (null !== $resourceObject) {
                $simpleValidations[$newKey] = preg_replace('/,\[OBJECT_ID\]/', ",{$resourceObject->id}", $value);
            } else {
                $simpleValidations[$newKey] = str_replace(',[OBJECT_ID]', '', $value);
            }
        }

        $validator = Validator::make($forValidation, $simpleValidations);

        if ($validator->fails()) {
            $e = $validator->errors();
            $e = $this->replaceKeys($e->toArray());

            throw (new SCIMException('Invalid data!'))
                ->setHttpCode(400)
                ->setScimType('invalidSyntax')
                ->setErrors($e);
        }

        $validTemp = $validator->valid();
        $valid = [];

        $keys = collect($simpleValidations)->keys()->map(function ($rule) {
            return explode('.', $rule)[0];
        })->unique()->toArray();

        foreach ($keys as $key) {
            if (array_key_exists($key, $validTemp)) {
                $valid[$key] = $validTemp[$key];
            }
        }
        
        $result = [];

        foreach ($valid as $key => $value) {
            $newKey = str_replace(['___'], ['.'], $key);

            $result[$newKey] = $value;
        }

        return $result;
    }

    /**
     * @param array $input
     * @return array
     */
    protected function replaceKeys(array $input): array
    {
        $return = [];

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $value = $this->replaceKeys($value);
            }

            if (strpos($key, '_') > 0) {
                $newKey = str_replace('___', '.', $key);

                $return[$newKey] = $value;
            } else {
                $return[$key] = $value;
            }
        }

        return $return;
    }
}
