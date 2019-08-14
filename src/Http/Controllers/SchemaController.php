<?php

namespace UniqKey\Laravel\SCIMServer\Http\Controllers;

use UniqKey\Laravel\SCIMServer\SCIMRoutes;
use Tmilos\ScimSchema\Builder\SchemaBuilderV2;
use UniqKey\Laravel\SCIMServer\SCIM\ListResponse;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;
use UniqKey\Laravel\SCIMServer\SCIMConfig;

class SchemaController extends BaseController
{
    /** @var \Illuminate\Support\Collection|null */
    protected $schemas = null;

    /**
     * @return \Illuminate\Support\Collection
     * @throws SCIMException
     */
    public function getSchemas()
    {
        if (null !== $this->schemas) {
            return $this->schemas;
        }

        $schemas = [];
        $config = $this->getScimConfig();

        foreach ($config as $key => $value) {
            $schema = (new SchemaBuilderV2())->get($value['schema']);

            if ($schema == null) {
                throw (new SCIMException('Schema not found'))
                    ->setHttpCode(404);
            }

            $schema->getMeta()->setLocation(
                resolve(SCIMRoutes::class)->route('scim.schema', [
                    'id' =>  $schema->getId(),
                ])
            );

            $schemas[] = $schema->serializeObject();
        }

        $this->schemas = collect($schemas);

        return $this->schemas;
    }

    /**
     * @return array
     */
    protected function getScimConfig(): array
    {
        return array_filter(resolve(SCIMConfig::class)->getConfig(), function ($key) {
            return in_array($key, ['Users', 'Groups',]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param string $id
     * @return array
     * @throws SCIMException
     */
    public function show(string $id)
    {
        $schemas = $this->getSchemas();

        $result = $schemas->first(function ($value, $key) use ($id) {
            return $value['id'] == $id;
        });

        if (null === $result) {
            throw (new SCIMException("Resource \"{$id}\" not found"))
                ->setHttpCode(404);
        }

        return $result;
    }

    /**
     * @return ListResponse
     */
    public function index()
    {
        return new ListResponse($this->getSchemas(), 1, $this->getSchemas()->count());
    }
}
