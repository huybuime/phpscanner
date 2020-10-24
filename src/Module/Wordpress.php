<?php

namespace marcocesarato\amwscan\Module;

use GlobIterator;
use marcocesarato\amwscan\Console;
use marcocesarato\amwscan\VerifierInterface;

class Wordpress implements VerifierInterface
{
    protected static $checksums = array();
    protected static $pluginsChecksums = array();
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
                $locale = self::getLocale($path);
                Console::writeLine('Found WordPress ' . $version . ' (' . $locale . ') at "' . $path . '"', 1, 'green');

                $plugins = self::getPlugins($path);
                self::$roots[$path] = array(
                    'path' => $path,
                    'version' => $version,
                    'locale' => $locale,
                    'plugins' => $plugins,
                );
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
            preg_match('/\$wp_version[\s]*=[\s]*[\'"]([0-9.]+)[\'"]/m', $versionContent, $match);
            $version = trim($match[1]);
            if (!empty($version)) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Get locale.
     *
     * @param $root
     *
     * @return string
     */
    public static function getLocale($root)
    {
        $versionFile = $root . self::$DS . 'wp-includes' . self::$DS . 'version.php';
        if (is_file($versionFile)) {
            $versionContent = file_get_contents($versionFile);
            preg_match('/\$wp_local_package[\s]*=[\s]*[\'"]([A-Za-z_-]+)[\'"]/m', $versionContent, $match);
            $locale = trim($match[1]);
            if (!empty($locale)) {
                return $locale;
            }
        }

        return 'en_US';
    }

    /**
     * Get plugins.
     *
     * @param $root
     *
     * @return string[]
     */
    public static function getPlugins($root)
    {
        $plugins = array();
        $files = new GlobIterator($root . self::$DS . 'wp-content' . self::$DS . 'plugins' . self::$DS . '*' . self::$DS . '*.php');
        foreach ($files as $cur) {
            if ($cur->isFile()) {
                $headers = self::getPluginHeaders($cur->getPathname());
                if (!empty($headers['name']) && !empty($headers['version'])) {
                    if (empty($headers['domain'])) {
                        $headers['domain'] = $cur->getBasename('.' . $cur->getExtension());
                    }
                    $headers['path'] = $cur->getPath();
                    $plugins[$cur->getPath()] = $headers;
                    Console::writeLine('Found WordPress Plugin ' . $headers['name'] . ' ' . $headers['version'], 1, 'green');
                }
            }
        }

        return $plugins;
    }

    /**
     * Get file headers.
     *
     * @param $file
     *
     * @return string[]
     */
    public static function getPluginHeaders($file)
    {
        $headers = array('name' => 'Plugin Name', 'version' => 'Version', 'domain' => 'Text Domain');
        $file_data = file_get_contents($file);
        $file_data = str_replace("\r", "\n", $file_data);
        foreach ($headers as $field => $regex) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $file_data, $match) && $match[1]) {
                $headers[$field] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));
            } else {
                $headers[$field] = '';
            }
        }

        return $headers;
    }

    /**
     * Get checksums.
     *
     * @param $version
     * @param string $locale
     * @param array $plugins
     *
     * @return array|false
     */
    public static function getChecksums($version, $locale = 'en_US')
    {
        if (!isset(self::$checksums[$version])) {
            Console::writeLine('Retrieving checksums of Wordpress ' . $version, 1, 'grey');
            $checksums = self::getData('https://api.wordpress.org/core/checksums/1.0/?version=' . $version . '&locale=' . $locale);
            if (!$checksums) {
                return false;
            }
            $versionChecksums = $checksums['checksums'];
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
     * Get plugins checksums.
     *
     * @param $version
     * @param string $locale
     * @param array $plugins
     *
     * @return array|false
     */
    public static function getPluginsChecksums($plugins = array())
    {
        foreach ($plugins as $plugin) {
            if (!isset(self::$pluginsChecksums[$plugin['domain']][$plugin['version']])) {
                Console::writeLine('Retrieving checksums of Wordpress Plugin ' . $plugin['name'] . ' ' . $plugin['version'], 1, 'grey');
                $checksums = self::getData('https://downloads.wordpress.org/plugin-checksums/' . $plugin['domain'] . '/' . $plugin['version'] . '.json');
                if (!$checksums) {
                    self::$pluginsChecksums[$plugin['domain']][$plugin['version']] = array();
                    continue;
                }
                $pluginChecksums = $checksums['files'];
                foreach ($pluginChecksums as $filePath => $checksum) {
                    $path = $plugin['path'] . self::$DS . $filePath;
                    $root = self::getRoot($path);
                    $sanitizePath = str_replace($root, '', $path);
                    $sanitizePath = self::sanitizePath($sanitizePath);
                    self::$pluginsChecksums[$plugin['domain']][$plugin['version']][$sanitizePath] = strtolower($checksum['md5']);
                }
            }
        }

        return self::$pluginsChecksums;
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
            $checksums = self::getChecksums($root['version'], $root['locale']);
            $pluginsChecksums = self::getPluginsChecksums($root['plugins']);
            if (!$checksums) {
                return false;
            }
            // Core
            if (!empty($checksums[$comparePath])) {
                $checksum = md5_file($path);
                $checksum = strtolower($checksum);

                return $checksums[$comparePath] === $checksum;
            }
            // Plugins
            foreach ($root['plugins'] as $plugin) {
                $checksums = $pluginsChecksums[$plugin['domain']][$plugin['version']];
                if (!empty($pluginsChecksums[$plugin['domain']][$plugin['version']]) && !empty($checksums[$comparePath])) {
                    $checksum = md5_file($path);
                    $checksum = strtolower($checksum);

                    return $checksums[$comparePath] === $checksum;
                }
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

    /**
     * HTTP request get data.
     *
     * @param $url
     *
     * @return mixed|null
     */
    protected static function getData($url)
    {
        $headers = get_headers($url);
        if (substr($headers[0], 9, 3) != '200') {
            return null;
        }

        $content = @file_get_contents($url);

        return @json_decode($content, true);
    }
}
