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
     * @var int
     */
    private $errorMessage;

    /**
     * Set the error (result) code for the (last) Memcached operation
     *
     * @param int $errorCode
     *
     * @return OperationFailed
     */
    public function setResultCode($errorCode)
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    /**
     * Get the error (result) code for the (last) Memcached operation
     *
     * @return int
     */
    public function getResultCode()
    {
        return $this->errorCode;
    }

    /**
     * Set the error (result) message for the (last) Memcached operation
     *
     * @param string $errorMessage
     *
     * @return OperationFailed
     */
    public function setResultMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * Get the error (result) message for the (last) Memcached operation
     *
     * @return string
     */
    public function getResultMessage()
    {
        return $this->errorMessage;
    }
}
