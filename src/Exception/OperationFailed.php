<?php

namespace MatthiasMullie\Scrapbook\Exception;

/**
 * @author Martin Georgiev <martin.georgiev@gmail.com>
 * @copyright Copyright (c) 2017, Martin Georgiev. All rights reserved
 * @license LICENSE MIT
 */
class OperationFailed extends Exception
{
    /**
     * @var int
     */
    private $errorCode;

    /**
     * @var string
     */
    private $errorMessage;

    /**
     * Set the error (result) code for the (last) Memcached operation.
     *
     * @param int $errorCode
     *
     * @return OperationFailed
     */
    public function setErrorCode($errorCode)
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    /**
     * Get the error (result) code for the (last) Memcached operation.
     *
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Set the error (result) message for the (last) Memcached operation.
     *
     * @param string $errorMessage
     *
     * @return OperationFailed
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * Get the error (result) message for the (last) Memcached operation.
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }
}
