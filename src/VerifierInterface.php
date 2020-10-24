<?php

namespace marcocesarato\amwscan;

interface VerifierInterface
{
    /**
     * Initialize path.
     *
     * @param $path
     *
     * @return mixed
     */
    public static function init($path);

    /**
     * Is verified file.
     *
     * @param $path
     *
     * @return mixed
     */
    public static function isVerified($path);
}
