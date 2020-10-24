<?php

namespace marcocesarato\amwscan;

use marcocesarato\amwscan\Module\Wordpress;

class Modules
{
    /**
     * Check path.
     *
     * @param $path
     */
    public static function init($path)
    {
        Wordpress::init($path);
    }

    /**
     * Is verified file.
     *
     * @param $path
     *
     * @return bool
     */
    public static function isVerified($path)
    {
        return Wordpress::isVerified($path);
    }
}
