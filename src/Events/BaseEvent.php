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
    /** @var string */
    protected $origin;
    /** @var array */
    protected $extra = [];

    /**
     * @param Model $model
     * @param string $origin
     * @param array $extra
     */
    public function __construct(Model $model, string $origin, array $extra = [])
    {
        $this->model = $model;
        $this->origin = $origin;
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
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtra(): array
    {
        return $this->extra;
    }
}
