<?php

namespace UniqKey\Laravel\SCIMServer\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use UniqKey\Laravel\SCIMServer\ResourceType;
use UniqKey\Laravel\SCIMServer\SCIMHelper;
use UniqKey\Laravel\SCIMServer\SCIM\Schema;
use UniqKey\Laravel\SCIMServer\Contracts\PolicyInterface;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;
use UniqKey\Laravel\SCIMServer\Events\Get;
use UniqKey\Laravel\SCIMServer\Events\Create;
use UniqKey\Laravel\SCIMServer\Events\Replace;
use UniqKey\Laravel\SCIMServer\Events\Update;
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
        $this->failIfGettingIsDisallowed($resourceType, $resourceObject);

        $this->fireGetEvent($resourceObject);

        $response = resolve(SCIMHelper::class)->objectToSCIMResponse(
            $resourceObject,
            $resourceType
        );
        return $response;
    }

    /**
     * @param Request $request
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return Response
     */
    protected function deleteModel(
        Request $request,
        ResourceType $resourceType,
        Model $resourceObject
    ): Response {
        $this->failIfDeletingIsDisallowed($resourceType, $resourceObject);

        $resourceObject->delete();

        $this->fireDeleteEvent($resourceObject);

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
                ->setHttpCode(400);
        }

        $helper = resolve(SCIMHelper::class);

        $flattened = $helper->flatten($input, $input['schemas']);
        $flattened = $this->validateScim($resourceType, $flattened);

        $this->failIfCreatingIsDisallowed($resourceType, $flattened);

        $class = $resourceType->getClass();

        /** @var Model */
        $resourceObject = new $class();

        $allAttributeConfigs = [];

        foreach ($flattened as $scimAttribute => $value) {
            $attributeConfig = $helper->getAttributeConfigOrFail(
                $resourceType,
                $scimAttribute
            );
            $attributeConfig->add($value, $resourceObject);
            $allAttributeConfigs[] = $attributeConfig;
        }

        $resourceObject = $this->saveNewModel($resourceObject, $allAttributeConfigs, $flattened);

        $response = $helper->objectToSCIMCreateResponse($resourceObject, $resourceType);
        return $response;
    }

    /**
     * @param Model $resourceObject
     * @param array $allAttributeConfigs
     * @param array $flattened
     * @return Model
     * @throws SCIMException
     */
    protected function saveNewModel(
        Model $resourceObject,
        array $allAttributeConfigs,
        array $flattened
    ): Model {
        try {
            $resourceObject->save();
        } catch (QueryException $e) {
            $class = get_class($resourceObject);
            throw (new SCIMException("Could not save new {$class} instance", 0, $e))
                ->setHttpCode(500);
        }

        foreach ($allAttributeConfigs as &$attributeConfig) {
            $fullKey = $attributeConfig->getFullKey();
            $attributeConfig->writeAfter($flattened[$fullKey], $resourceObject);
        }

        $this->fireCreateEvent($resourceObject);

        return $resourceObject;
    }

    /**
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
        $helper = resolve(SCIMHelper::class);

        $originalRaw = $helper->objectToSCIMArray($resourceObject, $resourceType);
        $original = $helper->flatten($originalRaw, [$resourceType->getSchema()]);

        // TODO: get flattend from $resourceObject
        $flattened = $helper->flatten($request->input(), [$resourceType->getSchema()]);
        $flattened = $this->validateScim($resourceType, $flattened, $resourceObject);

        $updated = [];

        foreach ($flattened as $key => $value) {
            if (!isset($original[$key])
            ||  json_encode($original[$key]) != json_encode($flattened[$key])) {
                $updated[$key] = $flattened[$key];
            }
        }

        $this->failIfReplacingIsDisallowed($resourceType, $resourceObject, $updated);

        // Keep an array of written values
        $uses = [];

        // Write all values
        foreach ($flattened as $scimAttribute => $value) {
            $attributeConfig = $helper->getAttributeConfigOrFail($resourceType, $scimAttribute);

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
                // @ash TODO: why ArieTimmerman marks the following line as commented ???
                // $attributeConfig->remove($resourceObject);
            }
        }

        $resourceObject->save();

        $this->fireReplaceEvent($resourceObject, $originalRaw);

        $response = $helper->objectToSCIMResponse($resourceObject, $resourceType);
        return $response;
    }

    /**
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

        if ($input['schemas'] !== [Schema::SCHEMA_PATCH_OP]) {
            throw (new SCIMException(sprintf(
                'Invalid schema "%s". MUST be "%s"',
                json_encode($input['schemas']),
                Schema::SCHEMA_PATCH_OP
            )))->setHttpCode(400);
        }

        if (isset($input[Schema::SCHEMA_PATCH_OP . ':Operations'])) {
            $input['Operations'] = $input[Schema::SCHEMA_PATCH_OP . ':Operations'];
            unset($input[Schema::SCHEMA_PATCH_OP . ':Operations']);
        }

        $helper = resolve(SCIMHelper::class);

        $oldObject = $helper->objectToSCIMArray($resourceObject, $resourceType);

        foreach ($input['Operations'] as $operation) {
            switch (strtolower($operation['op'])) {
                case 'add':
                    if (isset($operation['path'])) {
                        $attributeConfig = $helper->getAttributeConfigOrFail(
                            $resourceType,
                            $operation['path']
                        );

                        if (is_array($operation['value'])) {
                            foreach ($operation['value'] as $value) {
                                $attributeConfig->add($value, $resourceObject);
                            }
                        } else {
                            $attributeConfig->add($operation['value'], $resourceObject);
                        }
                    } else {
                        foreach ($operation['value'] as $key => $value) {
                            $attributeConfig = $helper->getAttributeConfigOrFail(
                                $resourceType,
                                $key
                            );

                            if (is_array($value)) {
                                foreach ($value as $v) {
                                    $attributeConfig->add($v, $resourceObject);
                                }
                            } else {
                                $attributeConfig->add($value, $resourceObject);
                            }
                        }
                    }
                    break;

                case 'remove':
                    if (isset($operation['path'])) {
                        $attributeConfig = $helper->getAttributeConfigOrFail(
                            $resourceType,
                            $operation['path']
                        );
                        $attributeConfig->remove($operation['value'] ?? null, $resourceObject);
                    } else {
                        throw (new SCIMException('You MUST provide a "Path"'))
                            ->setHttpCode(400);
                    }
                    break;

                case 'replace':
                    if (isset($operation['path'])) {
                        $attributeConfig = $helper->getAttributeConfigOrFail(
                            $resourceType,
                            $operation['path']
                        );
                        $attributeConfig->replace($operation['value'], $resourceObject);
                    } else {
                        foreach ($operation['value'] as $key => $value) {
                            $attributeConfig = $helper->getAttributeConfigOrFail(
                                $resourceType,
                                $key
                            );
                            $attributeConfig->replace($value, $resourceObject);
                        }
                    }
                    break;

                default:
                    throw (new SCIMException("Operation \"{$operation['op']}\" is not supported."))
                        ->setHttpCode(400);
                    break;
            }
        }

        // TODO: prevent something from getten written before ...
        // $dirty = $resourceObject->getDirty();

        $newObject = $helper->flatten(
            $helper->objectToSCIMArray($resourceObject, $resourceType),
            [$resourceType->getSchema()]
        );
        $flattened = $this->validateScim($resourceType, $newObject, $resourceObject);

        $this->failIfUpdatingIsDisallowed($resourceType, $resourceObject, $flattened);

        $resourceObject->save();

        $this->fireUpdateEvent($resourceObject, $oldObject);

        $response = $helper->objectToSCIMResponse($resourceObject, $resourceType);
        return $response;
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

    /**
     * @param Model $resourceObject
     * @param array $extra
     */
    protected function fireGetEvent(Model $resourceObject, array $extra = [])
    {
        event(new Get($resourceObject, array_merge($extra, [
            'origin' => static::class,
        ])));
    }

    /**
     * @param Model $resourceObject
     * @param array $extra
     */
    protected function fireDeleteEvent(Model $resourceObject, array $extra = [])
    {
        event(new Delete($resourceObject, array_merge($extra, [
            'origin' => static::class,
        ])));
    }

    /**
     * @param Model $resourceObject
     * @param array $extra
     */
    protected function fireCreateEvent(Model $resourceObject, array $extra = [])
    {
        event(new Create($resourceObject, array_merge($extra, [
            'origin' => static::class,
        ])));
    }

    /**
     * @param Model $resourceObject
     * @param array $previous
     * @param array $extra
     */
    protected function fireReplaceEvent(
        Model $resourceObject,
        array $previous,
        array $extra = []
    ) {
        event(new Replace($resourceObject, array_merge($extra, [
            'origin' => static::class,
            'previous' => $previous,
        ])));
    }

    /**
     * @param Model $resourceObject
     * @param array $previous
     * @param array $extra
     */
    protected function fireUpdateEvent(
        Model $resourceObject,
        array $previous,
        array $extra = []
    ) {
        event(new Update($resourceObject, array_merge($extra, [
            'origin' => static::class,
            'previous' => $previous,
        ])));
    }

    /**
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @throws SCIMException
     */
    protected function failIfGettingIsDisallowed(
        ResourceType $resourceType,
        Model $resourceObject
    ) {
        if (false === $this->policy->isGettingAllowed($resourceType, $resourceObject)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }
    }

    /**
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @throws SCIMException
     */
    protected function failIfDeletingIsDisallowed(
        ResourceType $resourceType,
        Model $resourceObject
    ) {
        if (false === $this->policy->isDeletingAllowed($resourceType, $resourceObject)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }
    }

    /**
     * @param ResourceType $resourceType
     * @param array $flattened
     * @throws SCIMException
     */
    protected function failIfCreatingIsDisallowed(
        ResourceType $resourceType,
        array $flattened
    ) {
        if (false === $this->policy->isCreatingAllowed($resourceType, $flattened)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }
    }

    /**
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @param array $flattened
     * @throws SCIMException
     */
    protected function failIfReplacingIsDisallowed(
        ResourceType $resourceType,
        Model $resourceObject,
        array $flattened
    ) {
        if (false === $this->policy->isReplacingAllowed($resourceType, $resourceObject, $flattened)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }
    }

    /**
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @param array $flattened
     * @throws SCIMException
     */
    protected function failIfUpdatingIsDisallowed(
        ResourceType $resourceType,
        Model $resourceObject,
        array $flattened
    ) {
        if (false === $this->policy->isUpdatingAllowed($resourceType, $resourceObject, $flattened)) {
            throw (new SCIMException('This is not allowed'))
                ->setHttpCode(403);
        }
    }
}
