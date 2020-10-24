<?php

namespace marcocesarato\amwscan\Module;

use marcocesarato\amwscan\Console;
use marcocesarato\amwscan\VerifierInterface;

class Wordpress implements VerifierInterface
{
    protected static $checksums = array();
    protected static $roots = array();
    protected static $DS = DIRECTORY_SEPARATOR;

    /**
     * Initialize path.
     *
     * @param $path
     */
    public static function init($path)
    {
        if (self::isRoot($path)) {
            $version = self::getVersion($path);
            if ($version && !empty($version) && !isset(self::$roots[$path])) {
                Console::writeLine('Found WordPress ' . $version . ' at "' . $path . '"', 1, 'green');
                self::$roots[$path] = array(
                    'path' => $path,
                    'version' => $version,
                );
                self::getChecksums($version);
            }
        }
    }

    /**
     * Detect root.
     *
     * @param $path
     *
     * @return bool
     */
    public static function isRoot($path)
    {
        return
            is_dir($path) &&
            is_dir($path . self::$DS . 'wp-admin') &&
            is_dir($path . self::$DS . 'wp-content') &&
            is_dir($path . self::$DS . 'wp-includes') &&
            is_file($path . self::$DS . 'wp-config.php') &&
            is_file($path . self::$DS . 'wp-includes' . self::$DS . 'version.php')
        ;
    }

    /**
     * Get version.
     *
     * @param $root
     *
     * @return string|null
     */
    public static function getVersion($root)
    {
        $versionFile = $root . self::$DS . 'wp-includes' . self::$DS . 'version.php';
        if (is_file($versionFile)) {
            $versionContent = file_get_contents($versionFile);
            preg_match('/\$wp_version[\s]*=[\s]*[\'"]([0-9.]+)[\'"]/', $versionContent, $match);
            $version = trim($match[1]);
            if (!empty($version)) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Get checksums.
     *
     * @param $version
     *
     * @return array
     */
    public static function getChecksums($version)
    {
        if (empty(self::$checksums[$version])) {
            $checksums = file_get_contents('https://api.wordpress.org/core/checksums/1.0/?version=' . $version);
            $checksums = json_decode($checksums, true);
            $versionChecksums = $checksums['checksums'][$version];
            self::$checksums[$version] = array();
            // Sanitize paths and checksum
            foreach ($versionChecksums as $filePath => $checksum) {
                $sanitizePath = self::sanitizePath($filePath);
                self::$checksums[$version][$sanitizePath] = strtolower($checksum);
            }
        }

        return self::$checksums[$version];
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
        if (!is_file($path)) {
            return false;
        }

        $root = self::getRoot($path);
        if (!empty($root)) {
            $comparePath = str_replace($root['path'], '', $path);
            $comparePath = self::sanitizePath($comparePath);
            $checksums = self::getChecksums($root['version']);
            if (!$checksums) {
                return false;
            }
            if (!empty($checksums[$comparePath])) {
                $checksum = md5_file($path);
                $checksum = strtolower($checksum);

                return $checksums[$comparePath] === $checksum;
            }
        }

        return false;
    }

    /**
     * Get root from child file.
     *
     * @param $path
     *
     * @return array
     */
    public static function getRoot($path)
    {
        foreach (self::$roots as $key => $root) {
            if (strpos($path, $root['path']) === 0) {
                return $root;
            }
        }

        return null;
    }

    /**
     * Sanitize path to be compared.
     *
     * @param $path
     *
     * @return string
     */
    public static function sanitizePath($path)
    {
        $sanitized = preg_replace('#[\\\\/]+#', self::$DS, $path);
        $sanitized = trim($sanitized);
        $sanitized = trim($sanitized, self::$DS);
        $sanitized = strtolower($sanitized);

        return $sanitized;
    }
}