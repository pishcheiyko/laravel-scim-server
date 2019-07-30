<?php

namespace UniqKey\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;

class Error implements Jsonable
{
    /** @var string */
    protected $detail;
    /** @var int */
    protected $status;
    /** @var string */
    protected $scimType;
    /** @var array */
    protected $errors = [];

    /**
     * @param string $detail
     * @param int $status
     * @param string $scimType
     */
    public function __construct(
        string $detail,
        int $status = 404,
        string $scimType = 'invalidValue'
    ) {
        $this->detail = $detail;
        $this->status = $status;
        $this->scimType = $scimType;
    }

    /**
     * @param array $errors
     * @return $this
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toJson($options = 0)
    {
        return json_encode(array_filter([
            'schemas' => [Schema::SCHEMA_ERROR],
            'detail' => $this->detail,
            'status' => $this->status,
            'scimType' => ($this->status == 400 ? $this->scimType : null),
            // not defined in SCIM 2.0
            'errors' => $this->errors,
        ]), $options);
    }
}
