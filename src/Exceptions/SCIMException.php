<?php

namespace ArieTimmerman\Laravel\SCIMServer\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Support\Renderable;
use ArieTimmerman\Laravel\SCIMServer\SCIM\Error as ScimError;

class SCIMException extends Exception
{
    /** @var string */
    protected $scimType = 'invalidValue';
    /** @var int */
    protected $httpCode = 404;
    /** @var array */
    protected $errors = [];

    /**
     * @param string $scimType
     * @return $this
     */
    public function setScimType(string $scimType)
    {
        $this->scimType = $scimType;
        return $this;
    }

    /**
     * @param int $code
     * @return $this
     */
    public function setHttpCode(int $code)
    {
        $this->httpCode = $code;
        return $this;
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
     * Report the exception.
     */
    public function report()
    {
        Log::debug(sprintf(
            'Validation failed. Errors: %s\n\nMessage: %s\n\nBody: %s',
            json_encode($this->errors, JSON_PRETTY_PRINT),
            $this->getMessage(),
            request()->getContent()
        ));
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function render($request)
    {
        $scimError = new ScimError($this->getMessage(), $this->httpCode, $this->scimType);
        $scimError->setErrors($this->errors);

        return response($scimError, $this->httpCode);
    }
}
