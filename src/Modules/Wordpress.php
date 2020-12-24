<?php

namespace marcocesarato\amwscan\Modules;

use GlobIterator;
use marcocesarato\amwscan\Cache;
use marcocesarato\amwscan\Console;
use marcocesarato\amwscan\Interfaces\VerifierInterface;

class Wordpress implements VerifierInterface
{
    protected static $roots = [];
    protected static $ttl = -1;
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
                self::$roots[$path] = [
                    'path' => $path,
                    'version' => $version,
                    'locale' => $locale,
                    'plugins' => $plugins,
                ];
                self::getChecksums($version, $locale);
                self::getPluginsChecksums($plugins);
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
            if (!empty($match[1])) {
                return $match[1];
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
        $plugins = [];
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
        $headers = ['name' => 'Plugin Name', 'version' => 'Version', 'domain' => 'Text Domain'];
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
        $cache = Cache::getInstance();
        $key = 'wordpress_' . $locale . '-' . $version;
        $checksums = $cache->get($key);

        if (is_null($checksums)) {
            Console::writeLine('Retrieving checksums of Wordpress ' . $version, 1, 'grey');

            $checksums = [];
            $dataChecksums = self::getData('https://api.wordpress.org/core/checksums/1.0/?version=' . $version . '&locale=' . $locale);
            if (!$dataChecksums) {
                $cache->set($key, false, self::$ttl);

                return false;
            }
            $versionChecksums = $dataChecksums['checksums'];

            // Sanitize paths and checksum
            foreach ($versionChecksums as $filePath => $checksum) {
                $sanitizePath = self::sanitizePath($filePath);
                $checksums[$sanitizePath] = strtolower($checksum);
            }
            $cache->set($key, $checksums, self::$ttl);
        }

        return $checksums;
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
    public static function getPluginsChecksums($plugins = [])
    {
        $cache = Cache::getInstance();
        $pluginsChecksums = [];
        foreach ($plugins as $plugin) {
            $key = 'wordpress-plugin_' . $plugin['domain'] . '-' . $plugin['version'];
            $checksums = $cache->get($key);

            if (!is_null($checksums)) {
                $pluginsChecksums[$plugin['domain']][$plugin['version']] = $checksums;
                continue;
            }

            Console::writeLine('Retrieving checksums of Wordpress Plugin ' . $plugin['name'] . ' ' . $plugin['version'], 1, 'grey');
            $dataChecksums = self::getData('https://downloads.wordpress.org/plugin-checksums/' . $plugin['domain'] . '/' . $plugin['version'] . '.json');
            if (!$dataChecksums) {
                $cache->set($key, [], self::$ttl);
                $pluginsChecksums[$plugin['domain']][$plugin['version']] = [];
                continue;
            }
            $pluginChecksums = $dataChecksums['files'];

            $checksums = [];
            foreach ($pluginChecksums as $filePath => $checksum) {
                $path = $plugin['path'] . self::$DS . $filePath;
                $root = self::getRoot($path);
                $sanitizePath = str_replace($root['path'], '', $path);
                $sanitizePath = self::sanitizePath($sanitizePath);
                $checksums[$sanitizePath] = strtolower($checksum['md5']);
            }
            $cache->set($key, $checksums, self::$ttl);
            $pluginsChecksums[$plugin['domain']][$plugin['version']] = $checksums;
        }

        return $pluginsChecksums;
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
            $pluginRoot = self::getPluginRoot($root, $path);
            $pluginsChecksums = self::getPluginsChecksums($root['plugins']);
            if (!empty($root['plugins'][$pluginRoot])) {
                $plugin = $root['plugins'][$pluginRoot];
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
     * Get root from child plugin file.
     *
     * @param $root
     * @param $path
     *
     * @return mixed|null
     */
    protected static function getPluginRoot($root, $path)
    {
        $pluginsPaths = array_keys($root['plugins']);
        foreach ($pluginsPaths as $key => $pluginPath) {
            if (strpos($path, $pluginPath) === 0) {
                return $pluginPath;
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

        return strtolower($sanitized);
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
