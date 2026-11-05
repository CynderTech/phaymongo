<?php

namespace Paymongo\Phaymongo;

use Exception;
use Throwable;

class PaymongoException extends Exception {
    protected $paymongo_errors = [];

    public function __construct($errors, int $status_code = 0, ?Throwable $previous = null)
    {
        parent::__construct('A payment error occured. Please try again.', $status_code, $previous);

        $this->paymongo_errors = $errors;
    }

    /**
     * @return array The raw error objects returned by Paymongo
     */
    public function getErrors() {
        return $this->paymongo_errors;
    }

    public function format_errors() {
        return array_map(function ($error) {
            return is_array($error) && isset($error['detail']) ? $error['detail'] : '';
        }, $this->paymongo_errors);
    }
}
