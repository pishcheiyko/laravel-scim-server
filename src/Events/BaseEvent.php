<?php

namespace UniqKey\Laravel\SCIMServer\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use UniqKey\Laravel\SCIMServer\Contracts\EventInterface;

/**
 * Base class
 */
class BaseEvent implements EventInterface
{
    use SerializesModels;

    /** @var Model */
    protected $model;
    /** @var array */
    protected $extra = [];

    /**
     * @param Model $model
     * @param array $extra
     */
    public function __construct(Model $model, array $extra = [])
    {
        $this->model = $model;
        $this->extra = $extra;
    }

    /**
     * {@inheritdoc}
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtra(): array
    {
        return $this->extra;
    }
}
