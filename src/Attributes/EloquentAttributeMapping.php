<?php

namespace UniqKey\Laravel\SCIMServer\Attributes;

class EloquentAttributeMapping extends AttributeMapping
{
    /**
     * @param mixed &$object
     * @return mixed
     */
    public function read(&$object)
    {
        if (null !== $this->read) {
            return ($this->read)($object);
        } else {
            return static::eloquentAttributeToString($object->{$this->eloquentReadAttribute});
        }
    }
}
