<?php

namespace UniqKey\Laravel\SCIMServer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Tmilos\ScimFilterParser\Parser;
use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Error\FilterException;
use UniqKey\Laravel\SCIMServer\ResourceType;
use UniqKey\Laravel\SCIMServer\Helper;
use UniqKey\Laravel\SCIMServer\SCIM\ListResponse;
use UniqKey\Laravel\SCIMServer\Contracts\PolicyInterface;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;

class ResourceController extends BaseResourceController
{
    /**
     * @param PolicyInterface $policy
     */
    public function __construct(PolicyInterface $policy)
    {
        $this->policy = $policy;
    }

    /**
     * @param Request $request
     * @param ResourceType $resourceType
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, ResourceType $resourceType)
    {
        return parent::createModel($request, $resourceType);
    }

    /**
     * @param Request $request
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return \Illuminate\Http\Response
     */
    public function replace(Request $request, ResourceType $resourceType, Model $resourceObject)
    {
        return parent::replaceModel($request, $resourceType, $resourceObject);
    }

    /**
     * @param Request $request
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ResourceType $resourceType, Model $resourceObject)
    {
        return parent::updateModel($request, $resourceType, $resourceObject);
    }

    /**
     * @todo Use the attributes parameters ?attributes=userName, excludedAttributes=asdg,asdg; respect "returned" settings "always"
     *
     * @param Request $request
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, ResourceType $resourceType, Model $resourceObject)
    {
        return parent::showModel($request, $resourceType, $resourceObject);
    }

    /**
     * @param Request $request
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request, ResourceType $resourceType, Model $resourceObject)
    {
        return parent::deleteModel($request, $resourceType, $resourceObject);
    }

    /**
     * @todo @ash: Add policy here..
     *
     * @param Request $request
     * @param ResourceType $resourceType
     * @return \Illuminate\Http\JsonResponse
     * @throws SCIMException
     */
    public function index(Request $request, ResourceType $resourceType)
    {
        $class = $resourceType->getClass();

        $filter = $request->input('filter');
        $resourceObjectsBase = $class::when($filter, function ($query) use ($filter, $resourceType) {
            try {
                $parser = new Parser(Mode::FILTER());

                $node = $parser->parse($filter);
                Helper::scimFilterToLaravelQuery($resourceType, $query, $node);
            } catch (FilterException $e) {
                throw (new SCIMException($e->getMessage(), 0, $e))
                    ->setHttpCode(400)
                    ->setScimType('invalidFilter');
            }
        });

        $totalResults = $resourceObjectsBase->count();

        /**
         * The 1-based index of the first query result.
         * A value less than 1 SHALL be interpreted as 1.
         */
        $startIndex = max(1, intVal($request->input('startIndex', 0)));

        /**
         * Non-negative integer. Specifies the desired maximum number of query results per page,
         * e.g., 10. A negative value SHALL be interpreted as "0". A value of "0" indicates
         * that no resource results are to be returned except for "totalResults".
         */
        $count = max(0, intVal($request->input('count', 10)));

        $resourceObjects = $resourceObjectsBase->skip($startIndex - 1)->take($count);
        $resourceObjects = $resourceObjects->with($resourceType->getWithRelations());

        if ($request->input('sortBy')) {
            $sortBy = Helper::getEloquentSortAttribute($resourceType, $request->input('sortBy'));

            if (null !== $sortBy) {
                $direction = $request->input('sortOrder') == 'descending' ? 'desc' : 'asc';

                $resourceObjects = $resourceObjects->orderBy($sortBy, $direction);
            }
        }

        $resourceObjects = $resourceObjects->get();
        $attributes = $excludedAttributes = [];

        return response(new ListResponse(
            $resourceObjects,
            $startIndex,
            $totalResults,
            $attributes,
            $excludedAttributes,
            $resourceType
        ));
    }
}
