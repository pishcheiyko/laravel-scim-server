<?php

namespace UniqKey\Laravel\SCIMServer\Contracts;

use Illuminate\Database\Eloquent\Model;

interface EventInterface
{
    /**
     * @return Model
     */
    public function getModel(): Model;

    /**
     * @return array
     */
    public function getExtra(): array;

    /**
     * @return string
     */
    public function getOrigin(): string;
}
