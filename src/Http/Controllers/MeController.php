<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\Contracts\PolicyInterface;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class MeController extends BaseResourceController
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
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $resourceType = ResourceType::user();

        return parent::createModel($request, $resourceType);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function replace(Request $request)
    {
        $resourceType = ResourceType::user();
        $class = $resourceType->getClass();
        $subject = $request->user();
        $resourceObject = $class::find($subject->getUserId());

        return parent::replaceModel($request, $resourceType, $resourceObject);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $resourceType = ResourceType::user();
        $class = $resourceType->getClass();
        $subject = $request->user();
        $resourceObject = $class::find($subject->getUserId());

        return parent::updateModel($request, $resourceType, $resourceObject);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $resourceType = ResourceType::user();
        $class = $resourceType->getClass();
        $subject = $request->user();

        $resourceObject = $class::find($subject->getUserId());

        if (null === $resourceObject) {
            throw new SCIMException('This is not a registered user');
        }

        return parent::showModel($request, $resourceType, $resourceObject);
    }
}
