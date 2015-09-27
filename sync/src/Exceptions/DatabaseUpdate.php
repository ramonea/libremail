<?php

namespace App\Exceptions;

class DatabaseUpdate extends \Exception
{
    public $code = EXC_DB_UPDATE;
    public $message = "There was a problem updating this %s.";

    function __construct( $type, $errors = [] )
    {
        $this->message = sprintf( $this->message, $type );

        if ( count( $errors ) ) {
            $this->message .= " ". implode( PHP_EOL, $errors );
        }
    }
}