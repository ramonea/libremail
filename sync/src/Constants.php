<?php

namespace App;

class Constants
{
    /**
     * Iterates over an array and defines constants.
     *
     * @param array $constants
     */
    public static function process($constants)
    {
        foreach ($constants as $constant => $value) {
            define($constant, $value);
        }
    }
}
