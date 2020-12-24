<?php

/**
 * PHP Antimalware Scanner.
 *
 * @author Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright Copyright (c) 2020
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see https://github.com/marcocesarato/PHP-Antimalware-Scanner
 */

namespace marcocesarato\amwscan;

use ArrayIterator;
use ArrayObject;
use CallbackFilterIterator;
use Exception;
use LimitIterator;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class Application.
 */
class Scanner
{
    /**
     * App name.
     *
     * @var string
     */
    public static $name = 'amwscan';

    /**
     * Version.
     *
     * @var string
     */
    public static $version = '0.7.3.133';

    /**
     * Root path.
     *
     * @var string
     */
    public static $root = './';

    /**
     * Quarantine path.
     *
     * @var string
     */
    public static $pathQuarantine = '/scanner-quarantine/';

    /**
     * Backup path.
     *
     * @var string
     */
    public static $pathBackups = '/scanner-backups/';

    /**
     * Logs Path.
     *
     * @var string
     */
    public static $pathLogs = '/scanner.log';

    /**
     * Infected logs path.
     *s.
     *
     * @var string
     */
    public static $pathReport = '/scanner-report.log';

    /**
     * Whitelist path.
     *
     * @var string
     */
    public static $pathWhitelist = '/scanner-whitelist.json';

    /**
     * Path to scan.
     *
     * @var string
     */
    public static $pathScan = './';

    /**
     * Max filesize.
     *
     * @var int
     */
    public static $maxFilesize = -1;

    /**
     * File extensions to scan.
     *
     * @var array
     */
    public static $extensions = [
        'htaccess',
        'php',
        'php3',
        'ph3',
        'php4',
        'ph4',
        'php5',
        'ph5',
        'php7',
        'ph7',
        'php8',
        'ph8',
        'phtm',
        'phtml',
        'ico',
    ];
    /**
     * Arguments.
     *
     * @var Argv
     */
    public static $argv = [];

    /**
     * Whitelist.
     *
     * @var array
     */
    public static $whitelist = [];

    /**
     * Functions.
     *
     * @var array
     */
    public static $functions = [];

    /**
     * Exploits.
     *
     * @var array
     */
    public static $exploits = [];

    /**
     * Settings.
     *
     * @var array
     */
    public static $settings = [];

    /**
     * Report.
     *
     * @var array
     */
    protected static $report = [
        'scanned' => 0,
        'detected' => 0,
        'removed' => [],
        'ignored' => [],
        'edited' => [],
        'quarantine' => [],
        'whitelist' => [],
    ];

    /**
     * Ignore paths.
     *
     * @var array
     */
    public static $ignorePaths = [];

    /**
     * Filter paths.
     *
     * @var array
     */
    public static $filterPaths = [];

    /**
     * Prompt.
     *
     * @var string
     */
    public static $prompt;

    /**
     * Interrupt.
     *
     * @var bool
     */
    public $interrupt = false;

    /**
     * @var string
     */
    public $lastError;

    protected static $inited = false;

    /**
     * Application constructor.
     */
    public function __construct()
    {
        if (!self::$inited) {
            if (function_exists('gc_enable') && (function_exists('gc_enable') && !gc_enabled())) {
                gc_enable();
            }

            if (self::$root === './') {
                self::$root = self::currentDirectory();
            }

            if (self::$pathScan === './') {
                self::$pathScan = self::currentDirectory();
            }
            self::$pathQuarantine = self::$root . self::$pathQuarantine;
            self::$pathLogs = self::$root . self::$pathLogs;
            self::$pathWhitelist = self::$root . self::$pathWhitelist;
            self::$pathReport = self::$root . self::$pathReport;

            Definitions::optimizeSig(Definitions::$SIGNATURES);

            if (!self::isCli()) {
                self::setSilentMode(true);
            }

            self::$inited = true;
        }
    }

    /**
     * Initialize.
     */
    private function init()
    {
        // Load whitelist
        if (file_exists(self::$pathWhitelist)) {
            self::$whitelist = file_get_contents(self::$pathWhitelist);
            self::$whitelist = @json_decode(self::$whitelist, true);
            if (!is_array(self::$whitelist)) {
                self::$whitelist = [];
            }
        }
    }

    /**
     * Run application.
     *
     * @param null $args
     */
    public function run($args = null)
    {
        $this->interrupt = false;

        try {
            // Print header
            Console::header();
            // Initialize arguments
            $this->arguments($args);
            // Initialize
            $this->init();
            // Initialize modes
            $this->modes();

            // Start scanning
            Console::displayLine('Start scanning...');

            Console::writeLine('Scan date: ' . date('d-m-Y H:i:s'));
            Console::writeLine('Scanning ' . self::$pathScan, 2);

            // Mapping files
            Console::writeLine('Mapping and retrieving checksums, please wait...', 2);
            $iterator = $this->mapping();

            // Counting files
            $filesCount = iterator_count($iterator);
            Console::writeLine('Found ' . $filesCount . ' files to check', 2);
            Console::writeLine('Checking files...', 2);
            Console::progress(0, $filesCount);

            if ($this->interrupt) {
                return false;
            }

            // Scan all files
            $this->scan($iterator);

            // Scan finished
            Console::writeBreak(2);
            Console::write('Scan finished!', 'green');
            Console::writeBreak(3);

            // Print summary
            $this->summary();

            return self::getReport();
        } catch (Exception $e) {
            $this->interrupt = true;
            $this->setLastError($e->getMessage());
            Console::writeBreak();
            Console::writeLine($e->getMessage(), 1, 'red');
        }
    }

    /**
     * Initialize application arguments.
     *
     * @param null $args
     */
    private function arguments($args = null)
    {
        // Define Arguments
        self::$argv = new Argv();
        self::$argv->addFlag('agile', ['alias' => '-a', 'default' => false]);
        self::$argv->addFlag('help', ['alias' => '-h', 'default' => false]);
        self::$argv->addFlag('log', ['alias' => '-l', 'default' => null, 'has_value' => true]);
        self::$argv->addFlag('backup', ['alias' => '-b', 'default' => false]);
        self::$argv->addFlag('offset', ['default' => null, 'has_value' => true]);
        self::$argv->addFlag('limit', ['default' => null, 'has_value' => true]);
        self::$argv->addFlag('report', ['alias' => '-r', 'default' => false]);
        self::$argv->addFlag('version', ['alias' => '-v', 'default' => false]);
        self::$argv->addFlag('update', ['alias' => '-u', 'default' => false]);
        self::$argv->addFlag('only-signatures', ['alias' => '-s', 'default' => false]);
        self::$argv->addFlag('only-exploits', ['alias' => '-e', 'default' => false]);
        self::$argv->addFlag('only-functions', ['alias' => '-f', 'default' => false]);
        self::$argv->addFlag('definitions-list', ['default' => false]);
        self::$argv->addFlag('definitions-exploits', ['default' => false]);
        self::$argv->addFlag('definitions-functions', ['default' => false]);
        self::$argv->addFlag('exploits', ['default' => false, 'has_value' => true]);
        self::$argv->addFlag('functions', ['default' => false, 'has_value' => true]);
        self::$argv->addFlag('whitelist-only-path', ['default' => false]);
        self::$argv->addFlag('max-filesize', ['default' => -1, 'has_value' => true]);
        self::$argv->addFlag('silent', ['default' => false]);
        self::$argv->addFlag('ignore-paths', ['alias' => '--ignore-path', 'default' => null, 'has_value' => true]);
        self::$argv->addFlag('filter-paths', ['alias' => '--filter-path', 'default' => null, 'has_value' => true]);
        self::$argv->addFlag('auto-clean', ['default' => false]);
        self::$argv->addFlag('auto-clean-line', ['default' => false]);
        self::$argv->addFlag('auto-delete', ['default' => false]);
        self::$argv->addFlag('auto-quarantine', ['default' => false]);
        self::$argv->addFlag('auto-skip', ['default' => false]);
        self::$argv->addFlag('auto-whitelist', ['default' => false]);
        self::$argv->addFlag('auto-prompt', ['default' => null, 'has_value' => true]);
        self::$argv->addFlag('path-whitelist', ['default' => false, 'has_value' => true]);
        self::$argv->addFlag('path-backups', ['default' => false, 'has_value' => true]);
        self::$argv->addFlag('path-quarantine', ['default' => false, 'has_value' => true]);
        self::$argv->addFlag('path-logs', ['default' => false, 'has_value' => true]);
        self::$argv->addFlag('path-report', ['default' => false, 'has_value' => true]);
        self::$argv->addFlag('disable-colors', ['alias' => ['--no-colors', '--no-color'], 'default' => false]);
        self::$argv->addFlag('disable-cache', ['alias' => '--no-cache', 'default' => false]);
        self::$argv->addArgument('path', ['var_args' => true, 'default' => '']);
        self::$argv->parse($args);

        // Version
        if (isset(self::$argv['version']) && self::$argv['version']) {
            $this->interrupt();
        }

        // Help
        if (isset(self::$argv['help']) && self::$argv['help']) {
            Console::helper();
            $this->interrupt();
        }

        // List exploits
        if (isset(self::$argv['definitions-list']) && self::$argv['definitions-list']) {
            Console::helplist();
            $this->interrupt();
        }

        // List exploits
        if (isset(self::$argv['definitions-exploits']) && self::$argv['definitions-exploits']) {
            Console::helplist('exploits');
        }

        // List functions
        if (isset(self::$argv['definitions-functions']) && self::$argv['definitions-functions']) {
            Console::helplist('functions');
        }

        // Update
        if (isset(self::$argv['update']) && self::$argv['update']) {
            $this->update();
            $this->interrupt();
        }

        // Report mode
        self::setReportMode((isset(self::$argv['report']) && self::$argv['report']) || !self::isCli());

        // Silent
        self::setSilentMode(isset(self::$argv['silent']) && self::$argv['silent']);

        // Backups
        if ((isset(self::$argv['backup']) && self::$argv['backup'])) {
            self::enableBackups();
        }

        // Colors
        if (isset(self::$argv['disable-colors']) && self::$argv['disable-colors']) {
            self::setColors(false);
        } elseif (function_exists('ncurses_has_colors')) {
            self::setColors(ncurses_has_colors());
        }

        // Cache
        self::setCache(!(isset(self::$argv['disable-cache']) && self::$argv['disable-cache']));

        // Max filesize
        if (isset(self::$argv['max-filesize']) && is_numeric(self::$argv['max-filesize'])) {
            self::setMaxFilesize(self::$argv['max-filesize']);
        }

        // Write logs
        if (isset(self::$argv['log']) && !empty(self::$argv['log'])) {
            self::enableLogs();
            if (is_string(self::$argv['log'])) {
                self::setPathLogs(self::$argv['log']);
            }
        }

        // Offset
        self::setOffset(0);
        if (isset(self::$argv['offset']) && is_numeric(self::$argv['offset'])) {
            self::setOffset((int)self::$argv['offset']);
        }

        // Limit
        if (isset(self::$argv['limit']) && is_numeric(self::$argv['limit'])) {
            self::setLimit((int)self::$argv['limit']);
        }

        // Path quarantine
        if (isset(self::$argv['path-quarantine']) && !empty(self::$argv['path-quarantine'])) {
            self::setPathQuarantine(self::$argv['path-quarantine']);
        }

        // Path backups
        if (isset(self::$argv['path-backups']) && !empty(self::$argv['path-backups'])) {
            self::setPathQuarantine(self::$argv['path-backups']);
        }

        // Path Whitelist
        if (isset(self::$argv['path-whitelist']) && !empty(self::$argv['path-whitelist'])) {
            self::setPathWhitelist(self::$argv['path-whitelist']);
        }

        // Path report
        if (isset(self::$argv['path-report']) && !empty(self::$argv['path-report'])) {
            self::setPathReport(self::$argv['path-report']);
        }

        // Path logs
        if (isset(self::$argv['path-logs']) && !empty(self::$argv['path-logs'])) {
            self::setPathLogs(self::$argv['path-logs']);
        }

        // Ignore paths
        if (isset(self::$argv['ignore-paths']) && !empty(self::$argv['ignore-paths'])) {
            $paths = explode(',', self::$argv['ignore-paths']);
            $ignorePaths = [];
            foreach ($paths as $path) {
                $path = trim($path);
                $ignorePaths[] = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
            }
            self::setIgnorePaths($ignorePaths);
        }

        // Filter paths
        if (isset(self::$argv['filter-paths']) && !empty(self::$argv['filter-paths'])) {
            $paths = explode(',', self::$argv['filter-paths']);
            $filterPaths = [];
            foreach ($paths as $path) {
                $path = trim($path);
                $filterPaths[] = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
            }
            self::setFilterPaths($filterPaths);
        }

        // Check on whitelist only file path and not line number
        if (isset(self::$argv['whitelist-only-path']) && self::$argv['whitelist-only-path']) {
            self::setOnlyPathWhitelistMode(true);
        }

        // Check Filter exploits
        if (isset(self::$argv['exploits']) && self::$argv['exploits'] && is_string(self::$argv['exploits'])) {
            $exploits = [];
            $filtered = str_replace(["\n", "\r", "\t", ' '], '', self::$argv['exploits']);
            $filtered = @explode(',', $filtered);
            if (!empty($filtered) && count($filtered) > 0) {
                foreach (Definitions::$EXPLOITS as $key => $value) {
                    if (in_array($key, $filtered)) {
                        $exploits[$key] = $value;
                    }
                }
                if (!empty($exploits) && count($exploits) > 0) {
                    Console::writeLine('Exploit to search: ' . implode(', ', array_keys($exploits)));
                } else {
                    $exploits = [];
                }
            }
            self::setExploits($exploits);
        }

        // Check if exploit mode is enabled
        if (isset(self::$argv['only-exploits']) && self::$argv['only-exploits']) {
            self::setOnlyExploitsMode(true);
        }

        // Check functions to search
        if (isset(self::$argv['functions']) && self::$argv['functions'] && is_string(self::$argv['functions'])) {
            $functions = str_replace(["\n", "\r", "\t", ' '], '', self::$argv['functions']);
            $functions = @explode(',', $functions);
            if (!empty($functions) && count($functions) > 0) {
                Console::writeLine('Functions to search: ' . implode(', ', $functions));
            } else {
                $functions = [];
            }
            self::setFunctions($functions);
        }

        // Check if functions mode is enabled
        if (isset(self::$argv['only-functions']) && self::$argv['only-functions']) {
            self::setOnlyFunctionsMode(true);
        }

        // Check if only signatures mode is enabled
        if (isset(self::$argv['only-signatures'])) {
            self::setOnlySignaturesMode(true);
        }

        // Check if agile scan is enabled
        if (isset(self::$argv['agile']) && self::$argv['agile']) {
            self::setAgileMode(true);
            self::$exploits = Definitions::$EXPLOITS;
            self::$exploits['execution'] = '/\b(eval|assert|passthru|exec|include|system|pcntl_exec|shell_exec|`|array_map|ob_start|call_user_func(_array)?)\s*\(\s*(base64_decode|php:\/\/input|str_rot13|gz(inflate|uncompress)|getenv|pack|\\?\$_(GET|REQUEST|POST|COOKIE|SERVER)).*?(?=\))\)/';
            self::$exploits['concat_vars_with_spaces'] = '/(\$([a-zA-Z0-9]+)[\s\r\n]*\.[\s\r\n]*){8}/';  // concatenation of more than 8 words, with spaces
            self::$exploits['concat_vars_array'] = '/(\$([a-zA-Z0-9]+)(\{|\[)([0-9]+)(\}|\])[\s\r\n]*\.[\s\r\n]*){8}.*?(?=\})\}/i'; // concatenation of more than 8 words, with spaces
            unset(self::$exploits['nano'], self::$exploits['double_var2'], self::$exploits['base64_long']);
        }

        // Prompt
        if (isset(self::$argv['auto-clean']) && self::$argv['auto-clean']) {
            self::setAutoClean(true);
        }

        if (isset(self::$argv['auto-clean-line']) && self::$argv['auto-clean-line']) {
            self::setAutoCleanLine(true);
        }

        if (isset(self::$argv['auto-delete']) && self::$argv['auto-delete']) {
            self::setAutoDelete(true);
        }

        if (isset(self::$argv['auto-quarantine']) && self::$argv['auto-quarantine']) {
            self::setAutoQuarantine(true);
        }

        if (isset(self::$argv['auto-whitelist']) && self::$argv['auto-whitelist']) {
            self::setAutoWhitelist(true);
        }

        if (isset(self::$argv['auto-skip']) && self::$argv['auto-skip']) {
            self::setAutoSkip(true);
        }

        if (isset(self::$argv['auto-prompt']) && !empty(self::$argv['auto-prompt'])) {
            self::setPrompt(self::$argv['auto-prompt']);
        }

        // Check if logs and scan at the same time
        if (self::isLogEnabled() && self::isReportMode()) {
            self::disableLogs();
        }

        // Check for path or functions as first argument
        $arg = self::$argv->arg(0);
        if (!empty($arg)) {
            $path = trim($arg);
            if (file_exists(realpath($path))) {
                self::setPathScan(realpath($path));
            }
        }
    }

    /**
     * Init application modes.
     */
    private function modes()
    {
        if (self::isOnlyFunctionsMode() && self::isOnlyExploitsMode() && self::isOnlySignaturesMode()) {
            $error = 'Can\'t be set flags --only-signatures, --only-functions and --only-exploits together!';
            Console::writeLine($error, 2);
            $this->interrupt();
            $this->setLastError($error);
        }

        if (self::isOnlyFunctionsMode() && self::isOnlySignaturesMode()) {
            $error = 'Can\'t be set both flags --only-signatures and --only-functions together!';
            Console::writeLine($error, 2);
            $this->interrupt();
            $this->setLastError($error);
        }

        if (self::isOnlySignaturesMode() && self::isOnlyExploitsMode()) {
            $error = 'Can\'t be set both flags --only-signatures and --only-exploits together!';
            Console::writeLine($error, 2);
            $this->interrupt();
            $this->setLastError($error);
        }

        if (self::isOnlyFunctionsMode() && self::isOnlyExploitsMode()) {
            $error = 'Can\'t be set both flags --only-functions and --only-exploits together!';
            Console::writeLine($error, 2);
            $this->interrupt();
            $this->setLastError($error);
        }

        // Malware Definitions
        if (self::isOnlyFunctionsMode() || (!self::isOnlyExploitsMode() && empty(self::$functions))) {
            // Functions to search
            self::setFunctions(Definitions::$FUNCTIONS);
        } elseif (!self::isOnlyExploitsMode() && !self::isAgileMode() && empty(self::$functions)) {
            Console::writeLine('No functions to search');
        }

        if (self::$argv['max-filesize'] > 0) {
            Console::writeLine('Max filesize: ' . self::getMaxFilesize() . ' bytes', 2);
        }

        // Exploits to search
        if (!self::isOnlyFunctionsMode() && empty(self::$exploits)) {
            self::setExploits(Definitions::$EXPLOITS);
        }

        if (self::isAgileMode()) {
            Console::writeLine('Agile mode enabled');
        }

        if (self::isReportMode()) {
            Console::writeLine('Report scan mode enabled');
        }

        if (self::isOnlyFunctionsMode()) {
            Console::writeLine('Only function mode enabled');
            self::setExploits([]);
        }

        if (self::isOnlyExploitsMode() && !self::isAgileMode()) {
            Console::writeLine('Only exploit mode enabled');
            self::setFunctions([]);
        }

        if (self::isOnlySignaturesMode()) {
            Console::writeLine('Only signatures mode enabled');
            self::setExploits([]);
            self::setFunctions([]);
        }
    }

    /**
     * Map files.
     *
     * @return ArrayIterator
     */
    public function mapping()
    {
        // Mapping files
        if (is_dir(self::$pathScan)) {
            $directory = new RecursiveDirectoryIterator(self::$pathScan);
            $files = new RecursiveIteratorIterator($directory);
            $filtered = new CallbackFilterIterator($files, function ($cur) {
                $ignore = false;
                $wildcard = '.*?'; // '[^\\\\\\/]*'
                // Ignore
                foreach (self::$ignorePaths as $ignorePath) {
                    $ignorePath = preg_quote($ignorePath, ';');
                    $ignorePath = str_replace('\*', $wildcard, $ignorePath);
                    if (preg_match(';' . $ignorePath . ';i', $cur->getPath())) {
                        $ignore = true;
                    }
                }
                // Filter
                foreach (self::$filterPaths as $filterPath) {
                    $filterPath = preg_quote($filterPath, ';');
                    $filterPath = str_replace('\*', $wildcard, $filterPath);
                    if (!preg_match(';' . $filterPath . ';i', $cur->getPath())) {
                        $ignore = true;
                    }
                }

                if (!$ignore &&
                    $cur->isDir()) {
                    Modules::init($cur->getPath());

                    return false;
                }

                return
                    !$ignore &&
                    $cur->isFile() &&
                    in_array($cur->getExtension(), self::getExtensions(), true);
            });
            $mapping = [];

            $mapped = 0;
            $count = iterator_count($filtered);

            Console::writeBreak(1);
            Console::writeLine('Verifying files checksum...', 2);

            foreach ($filtered as $cur) {
                Console::progress($mapped++, $count);
                if ($cur->isFile() && !Modules::isVerified($cur->getPathname())) {
                    $mapping[] = $cur;
                }
                Console::progress($mapped, $count);
            }
            $iterator = new \ArrayObject($mapping);

            Console::writeBreak(1);

            return $iterator->getIterator();
        }

        $file = new SplFileInfo(self::$pathScan);
        $obj = new ArrayObject([$file]);

        return $obj->getIterator();
    }

    /**
     * Detect infected favicon.
     *
     * @param $file
     *
     * @return bool
     */
    public static function isInfectedFavicon($file)
    {
        // Case favicon_[random chars].ico
        $fileName = $file->getFilename();
        $fileExtension = $file->getExtension();

        return ((strpos($fileName, 'favicon_') === 0) && ($fileExtension === 'ico') && (strlen($fileName) > 12)) || preg_match('/^\.[\w]+\.ico/i', trim($fileName));
    }

    /**
     * Scan file.
     *
     * @param $info
     *
     * @return array
     */
    public function scanFile($info)
    {
        $filePath = $info->getPathname();

        $isFavicon = self::isInfectedFavicon($info);
        $patternFound = [];

        $mimeType = 'text/php';
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
        }

        if (0 === stripos($mimeType, 'text')) {
            $deobfuscator = new Deobfuscator();
            $contentRaw = file_get_contents($filePath);
            $contentClean = php_strip_whitespace($filePath);
            $contentDeobfuscated = $deobfuscator->deobfuscate($contentRaw);
            $contentDecoded = $deobfuscator->decode($contentDeobfuscated);

            $contents = [
                $contentRaw, // Original content
                $contentClean, // Cleaned content
                $contentDeobfuscated, // Deobfuscated content
                $contentDecoded, // Decoded content
            ];

            /**
             * Scan exploits.
             */
            $lastMatch = null;
            foreach (self::$exploits as $key => $pattern) {
                $checkExploit = function ($match) use ($contentRaw, $pattern, $key, &$patternFound) {
                    $matchDescription = null;
                    $lineNumber = null;
                    $lastMatch = $match[0];
                    $matchDescription = $key . "\n => " . $lastMatch;
                    if (!empty($lastMatch) && @preg_match('/' . preg_quote($lastMatch, '/') . '/i', $contentRaw, $lineMatch, PREG_OFFSET_CAPTURE)) {
                        $lineNumber = count(explode("\n", substr($contentRaw, 0, $lineMatch[0][1])));
                        $matchDescription = $key . ' [line ' . $lineNumber . "]\n => " . $lastMatch;
                    }
                    if (!empty($matchDescription)) {
                        $patternFound[$matchDescription] = [
                            'key' => $key,
                            'line' => $lineNumber,
                            'pattern' => $pattern,
                            'match' => $lastMatch,
                        ];
                    }
                };
                // Check exploits
                foreach ($contents as $content) {
                    if (@preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $checkExploit($match);
                        }
                    }
                }
            }

            /**
             * Scan functions.
             */
            $lastMatch = null;
            foreach (self::$functions as $funcRaw) {
                $func = preg_quote(trim($funcRaw), '/');

                // Basic search
                $regexPattern = "/(?:^|[\s\r\n]+|[^a-zA-Z0-9_>]+)(" . $func . "[\s\r\n]*\((?<=\().*?(?=\))\))/si";
                $checkFunction = function ($match) use ($contentRaw, $funcRaw, $regexPattern, &$patternFound) {
                    $lastMatch = explode($funcRaw, $match[0]);
                    $lastMatch = $funcRaw . $lastMatch[1];
                    $matchDescription = $funcRaw . "\n => " . $lastMatch;
                    if (!empty($lastMatch) && @preg_match('/' . preg_quote($lastMatch, '/') . '/', $contentRaw, $lineMatch, PREG_OFFSET_CAPTURE)) {
                        $lineNumber = count(explode("\n", substr($contentRaw, 0, $lineMatch[0][1])));
                        $matchDescription = $funcRaw . ' [line ' . $lineNumber . "]\n => " . $lastMatch;
                    }
                    if (!empty($matchDescription)) {
                        $patternFound[$matchDescription] = [
                            'key' => $funcRaw,
                            'line' => $lineNumber,
                            'pattern' => $regexPattern,
                            'match' => $lastMatch,
                        ];
                    }
                };
                // Check functions
                foreach ($contents as $content) {
                    if (@preg_match_all($regexPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $checkFunction($match);
                        }
                    }
                }

                // Check of base64
                $regexPatternBase64 = '/' . base64_encode($funcRaw) . '/s';
                $checkBase64Function = function ($match) use ($contentRaw, $funcRaw, $regexPatternBase64, &$patternFound) {
                    $lastMatch = explode($funcRaw, $match[0]);
                    $lastMatch = $funcRaw . $lastMatch[1];
                    $matchDescription = $funcRaw . "_base64\n => " . $lastMatch;

                    if (!empty($lastMatch) && @preg_match('/' . preg_quote($lastMatch, '/') . '/', $contentRaw, $lineMatch, PREG_OFFSET_CAPTURE)) {
                        $lineNumber = count(explode("\n", substr($contentRaw, 0, $lineMatch[0][1])));
                        $matchDescription = $funcRaw . '_base64 [line ' . $lineNumber . "]\n => " . $lastMatch;
                    }
                    if (!empty($matchDescription)) {
                        $patternFound[$matchDescription] = [
                            'key' => $funcRaw . '_base64',
                            'line' => $lineNumber,
                            'pattern' => $regexPatternBase64,
                            'match' => $lastMatch,
                        ];
                    }
                };
                // Check base64 functions
                foreach ($contents as $content) {
                    if (@preg_match_all($regexPatternBase64, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $checkBase64Function($match);
                        }
                    }
                }

                /*$field = bin2hex($pattern);
                $field = chunk_split($field, 2, '\x');
                $field = '\x' . substr($field, 0, -2);
                $regex_pattern = "/(" . preg_quote($field) . ")/i";
                if (@preg_match($regex_pattern, $contents, $match, PREG_OFFSET_CAPTURE)) {
                    $found = true;
                    $lineNumber = count(explode("\n", substr($fc, 0, $match[0][1])));
                    $pattern_found[$pattern . " [line " . $lineNumber . "]"] = $regex_pattern;
                }*/
            }

            /**
             * Check definitions.
             */
            foreach (Definitions::$SIGNATURES as $key => $pattern) {
                $regexPattern = '#' . $pattern . '#smiS';
                $checkDefinitions = function ($match) use ($contentRaw, $key, $regexPattern, &$patternFound) {
                    $lastMatch = $match[0];
                    if (!empty($lastMatch) && @preg_match('/' . preg_quote($lastMatch, '/') . '/', $contentRaw, $lineMatch, PREG_OFFSET_CAPTURE)) {
                        $lineNumber = count(explode("\n", substr($contentRaw, 0, $lineMatch[0][1])));
                        $matchDescription = 'Sign ' . $key . ' [line ' . $lineNumber . "]\n => " . $lastMatch;
                    }
                    if (!empty($matchDescription)) {
                        $patternFound[$matchDescription] = [
                            'key' => $key,
                            'line' => $lineNumber,
                            'pattern' => $regexPattern,
                            'match' => $lastMatch,
                        ];
                    }
                };
                // Check definitions
                foreach ($contents as $content) {
                    if (@preg_match_all($regexPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $checkDefinitions($match);
                        }
                    }
                }
            }
        }

        if ($isFavicon) {
            $patternFound['infected_icon'] = [
                'key' => 'infected_icon',
                'line' => '',
                'pattern' => '',
                'match' => '',
            ];
        }

        return $patternFound;
    }

    /**
     * Run index.php.
     *
     * @param $iterator
     */
    private function scan($iterator)
    {
        $filesCount = iterator_count($iterator);
        $limit = !empty(self::$settings['limit']) ? self::$settings['limit'] : null;
        if (!empty(self::$settings['offset'])) {
            self::$report['scanned'] = self::$settings['offset'];
            $iterator = new LimitIterator($iterator, self::$settings['offset'], $limit);
        } elseif (!empty($limit)) {
            $iterator = new LimitIterator($iterator, 0, $limit);
        }

        // Scanning
        foreach ($iterator as $info) {
            Console::progress(self::$report['scanned'], $filesCount);

            $filePath = $info->getPathname();
            $fileExtension = $info->getExtension();
            $fileSize = filesize($filePath);

            $isFavicon = self::isInfectedFavicon($info);

            if ((
                in_array($fileExtension, self::$extensions) &&
                (self::$maxFilesize < 1 || $fileSize <= self::$maxFilesize) &&
                (!file_exists(self::$pathQuarantine) || strpos(realpath($filePath), realpath(self::$pathQuarantine)) === false)
                   /*&& (strpos($fileName, '-') === FALSE)*/
            ) ||
               $isFavicon) {
                $patternFound = $this->scanFile($info);

                // Check whitelist
                $inWhitelist = 0;
                foreach (self::$whitelist as $item) {
                    foreach ($patternFound as $key => $pattern) {
                        $lineNumber = $pattern['line'];
                        $exploit = $pattern['key'];
                        $match = $pattern['match'];

                        if (strpos($filePath, $item['file']) !== false &&
                            $match === $item['match'] &&
                            $exploit === $item['exploit'] &&
                           (self::isOnlyPathWhitelistMode() || (!self::isOnlyPathWhitelistMode() && $lineNumber == $item['line']))) {
                            $inWhitelist++;
                        }
                    }
                }

                // Scan finished

                self::$report['scanned']++;
                usleep(10);

                if (realpath($filePath) !== realpath(__FILE__) && ($isFavicon || !empty($patternFound)) && ($inWhitelist === 0 || $inWhitelist != count($patternFound))) {
                    self::$report['detected']++;
                    if (self::isReportMode()) {
                        // Scan mode only
                        self::$report['ignored'][] = 'File: ' . $filePath . PHP_EOL .
                                                   'Exploits:' . PHP_EOL .
                                                   ' => ' . implode(PHP_EOL . ' => ', array_keys($patternFound));
                        continue;
                    }

                    // Scan with code check
                    $inLoop = true;
                    $lastCommand = '0';
                    Console::newLine(2);
                    Console::writeBreak();
                    Console::writeLine('PROBABLE MALWARE FOUND!', 1, 'red');

                    while ($inLoop) {
                        $fileContent = file_get_contents($filePath);
                        $previewLines = explode(Console::eol(1), trim($fileContent));
                        $preview = implode(Console::eol(1), array_slice($previewLines, 0, 1000));
                        if (!in_array($lastCommand, ['4', '5', '7'])) {
                            Console::displayLine("$filePath", 2, 'yellow');

                            $title = Console::title(' PREVIEW ', '=');
                            Console::display($title, 'white', 'red');
                            Console::newLine(2);

                            Console::code($preview, $patternFound);
                            if (count($previewLines) > 1000) {
                                Console::newLine(2);
                                Console::display('  [ ' . (count($previewLines) - 1000) . ' rows more ]');
                            }
                            Console::newLine(2);

                            $title = Console::title('', '=');
                            Console::display($title, 'white', 'red');
                        }
                        Console::newLine(2);
                        Console::writeLine('Checksum: ' . md5_file($filePath), 1, 'yellow');
                        Console::writeLine('File path: ' . $filePath, 1, 'yellow');
                        Console::writeLine('Exploits found: ' . Console::eol(1) . implode(Console::eol(1), array_keys($patternFound)), 2, 'red');
                        Console::displayLine('OPTIONS:', 2);

                        $confirmation = self::$prompt;
                        if (self::$prompt === null) {
                            $confirmation = Console::choice('What is your choice? ', [
                                1 => 'Delete file',
                                2 => 'Move to quarantine',
                                3 => 'Dry run evil code fixer',
                                4 => 'Dry run evil line code fixer',
                                5 => 'Open with vim',
                                6 => 'Open with nano',
                                7 => 'Add to whitelist',
                                8 => 'Show source',
                                '-' => 'Ignore',
                            ]);
                        }
                        Console::newLine();

                        $lastCommand = $confirmation;
                        unset($previewLines, $preview);

                        if (in_array($confirmation, ['1', 'delete'])) {
                            // Remove file
                            Console::writeLine('File path: ' . $filePath, 1, 'yellow');
                            $confirm2 = 'y';
                            if (self::$prompt === null) {
                                $confirm2 = Console::read('Want delete this file [y|N]? ', 'purple');
                            }
                            Console::newLine();
                            if ($confirm2 === 'y') {
                                Actions::deleteFile($filePath);
                                self::$report['removed'][] = $filePath;
                                Console::writeLine("File '$filePath' removed!", 2, 'green');
                                $inLoop = false;
                            }
                        } elseif (in_array($confirmation, ['2', 'quarantine'])) {
                            // Move to quarantine
                            $quarantine = Actions::moveToQuarantine($filePath);
                            self::$report['quarantine'][] = $quarantine;
                            Console::writeLine("File '$filePath' moved to quarantine!", 2, 'green');
                            $inLoop = false;
                        } elseif (in_array($confirmation, ['3', 'clean']) && count($patternFound) > 0) {
                            // Remove evil code
                            $fileContent = Actions::cleanEvilCode($fileContent, $patternFound);
                            Console::newLine();

                            $title = Console::title(' SANITIZED ', '=');
                            Console::display($title, 'black', 'green');
                            Console::newLine(2);
                            Console::code($fileContent);
                            Console::newLine(2);

                            $title = Console::title('', '=');
                            Console::display($title, 'black', 'green');
                            Console::newLine(2);
                            Console::displayLine('File sanitized, now you must verify if has been fixed correctly.', 2, 'yellow');
                            $confirm2 = 'y';
                            if (self::$prompt === null) {
                                $confirm2 = Console::read('Confirm and save [y|N]? ', 'purple');
                            }
                            Console::newLine();
                            if ($confirm2 === 'y') {
                                Console::writeLine("File '$filePath' sanitized!", 2, 'green');
                                Actions::putContents($filePath, $fileContent);
                                self::$report['removed'][] = $filePath;
                                $inLoop = false;
                            } else {
                                self::$report['ignored'][] = $filePath;
                            }
                        } elseif (in_array($confirmation, ['4', 'clean-line']) && count($patternFound) > 0) {
                            // Remove evil line code
                            $fileContent = Actions::cleanEvilCodeLine($fileContent, $patternFound);

                            Console::newLine();

                            $title = Console::title(' SANITIZED ', '=');
                            Console::display($title, 'black', 'green');
                            Console::newLine(2);
                            Console::code($fileContent);
                            Console::newLine(2);

                            $title = Console::title('', '=');
                            Console::display($title, 'black', 'green');
                            Console::newLine(2);
                            Console::displayLine('File sanitized, now you must verify if has been fixed correctly.', 2, 'yellow');
                            $confirm2 = 'y';
                            if (self::$prompt === null) {
                                $confirm2 = Console::read('Confirm and save [y|N]? ', 'purple');
                            }
                            Console::newLine();
                            if ($confirm2 === 'y') {
                                Console::writeLine("File '$filePath' sanitized!", 2, 'green');
                                Actions::putContent($filePath, $fileContent);
                                self::$report['removed'][] = $filePath;
                                $inLoop = false;
                            } else {
                                self::$report['ignored'][] = $filePath;
                            }
                        } elseif (in_array($confirmation, ['5', 'vim'])) {
                            // Open with vim
                            Actions::openWithVim($filePath);
                            self::$report['edited'][] = $filePath;
                            Console::writeLine("File '$filePath' edited with vim!", 2, 'green');
                            self::$report['removed'][] = $filePath;
                        } elseif (in_array($confirmation, ['6', 'nano'])) {
                            // Open with nano
                            Actions::openWithNano($filePath);
                            self::$report['edited'][] = $filePath;
                            Console::writeLine("File '$filePath' edited with nano!", 2, 'green');
                            self::$report['removed'][] = $filePath;
                        } elseif (in_array($confirmation, ['7', 'whitelist'])) {
                            // Add to whitelist
                            if (Actions::addToWhitelist($filePath, $patternFound)) {
                                self::$report['whitelist'][] = $filePath;
                                Console::writeLine("Exploits of file '$filePath' added to whitelist!", 2, 'green');
                                $inLoop = false;
                            } else {
                                Console::writeLine("Exploits of file '$filePath' failed adding file to whitelist! Check write permission of '" . self::$pathWhitelist . "' file!", 2, 'red');
                            }
                        } elseif (in_array($confirmation, ['8', 'show'])) {
                            // Show source code
                            Console::newLine();
                            Console::displayLine("$filePath", 2, 'yellow');

                            $title = Console::title(' SOURCE ', '=');
                            Console::display($title, 'white', 'red');
                            Console::newLine(2);

                            Console::code($fileContent, $patternFound);
                            Console::newLine(2);

                            $title = Console::title('', '=');
                            Console::display($title, 'white', 'red');
                            Console::newLine(2);
                        } else {
                            // Skip
                            Console::writeLine("File '$filePath' skipped!", 2, 'green');
                            self::$report['ignored'][] = $filePath;
                            $inLoop = false;
                        }

                        Console::writeBreak();
                    }
                    unset($fileContent);
                }
            }
        }
    }

    /**
     * Print summary.
     */
    private function summary()
    {
        if (!empty(self::$settings['offset'])) {
            self::$report['scanned'] -= self::$settings['offset'];
        }

        // Statistics
        Console::displayTitle('SUMMARY', 'black', 'cyan');
        Console::writeBreak();
        Console::writeLine('Files scanned: ' . self::$report['scanned']);
        if (!self::isReportMode()) {
            self::$report['ignored'] = array_unique(self::$report['ignored']);
            self::$report['edited'] = array_unique(self::$report['edited']);
            Console::writeLine('Files edited: ' . count(self::$report['edited']));
            Console::writeLine('Files quarantined: ' . count(self::$report['quarantine']));
            Console::writeLine('Files whitelisted: ' . count(self::$report['whitelist']));
            Console::writeLine('Files ignored: ' . count(self::$report['ignored']), 2);
        }
        Console::writeLine('Malware detected: ' . self::$report['detected']);
        if (!self::$settings['report']) {
            Console::writeLine('Malware removed: ' . count(self::$report['removed']));
        }

        if (self::isReportMode()) {
            Console::writeLine(Console::eol(1) . "Files infected: '" . self::$pathReport . "'", 1, 'red');
            file_put_contents(self::$pathReport, 'Log date: ' . date('d-m-Y H:i:s') . Console::eol(1) . implode(Console::eol(2), self::$report['ignored']));
            Console::writeBreak(2);
        } else {
            if (count(self::$report['removed']) > 0) {
                Console::writeBreak();
                Console::writeLine('Files removed:', 1, 'red');
                foreach (self::$report['removed'] as $un) {
                    Console::writeLine($un);
                }
            }
            if (count(self::$report['edited']) > 0) {
                Console::writeBreak();
                Console::writeLine('Files edited:', 1, 'green');
                foreach (self::$report['edited'] as $un) {
                    Console::writeLine($un);
                }
            }
            if (count(self::$report['quarantine']) > 0) {
                Console::writeBreak();
                Console::writeLine('Files quarantined:', 1, 'yellow');
                foreach (self::$report['ignored'] as $un) {
                    Console::writeLine($un);
                }
            }
            if (count(self::$report['whitelist']) > 0) {
                Console::writeBreak();
                Console::writeLine('Files whitelisted:', 1, 'cyan');
                foreach (self::$report['whitelist'] as $un) {
                    Console::writeLine($un);
                }
            }
            if (count(self::$report['ignored']) > 0) {
                Console::writeBreak();
                Console::writeLine('Files ignored:', 1, 'cyan');
                foreach (self::$report['ignored'] as $un) {
                    Console::writeLine($un);
                }
            }
            Console::writeBreak(2);
        }
    }

    /**
     * Update to last version.
     */
    public function update()
    {
        if (!self::isCli()) {
            return;
        }

        Console::writeLine('Checking update...');
        $version = file_get_contents('https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/version');
        if (!empty($version)) {
            if (version_compare(self::$version, $version, '<')) {
                Console::write('New version');
                Console::write(' ' . $version . ' ');
                Console::writeLine('of the scanner available!', 2);
                $confirm = Console::read('You sure you want update the index.php to the last version [y|N]? ', 'purple');
                Console::writeBreak();
                if (strtolower($confirm) === 'y') {
                    $newVersion = file_get_contents('https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner');
                    file_put_contents(self::currentFilename(), $newVersion);
                    Console::write('Updated to last version');
                    Console::write(' (' . self::$version . ' => ' . $version . ') ');
                    Console::writeLine('with SUCCESS!', 2);
                } else {
                    Console::writeLine('Updated SKIPPED!', 2);
                }
            } else {
                Console::writeLine('You have the last version of the index.php yet!', 2);
            }
        } else {
            Console::writeLine('Update FAILED!', 2, 'red');
        }
    }

    /**
     * Convert to Bytes.
     *
     * @param string $from
     *
     * @return int|null
     */
    private static function convertToBytes($from)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $number = substr($from, 0, -2);
        $suffix = strtoupper(substr($from, -2));

        if (is_numeric($suffix[0])) {
            return preg_replace('/[^\d]/', '', $from);
        }
        $pow = array_flip($units)[$suffix] ?: null;
        if ($pow === null) {
            return null;
        }

        return $number * pow(1024, $pow);
    }

    /**
     * Return real current path.
     *
     * @return string|string[]|null
     */
    public static function currentDirectory()
    {
        return dirname(self::currentFilename());
    }

    /**
     * Return real current filename.
     *
     * @return string|string[]|null
     */
    public static function currentFilename()
    {
        if (method_exists('Phar', 'running')) {
            return Phar::running(false);
        }
        $string = pathinfo(__FILE__);
        $dir = parse_url($string['dirname'] . '/' . $string['basename']);

        return realpath($dir['path']);
    }

    /**
     * Is console instance.
     *
     * @return bool
     */
    public static function isCli()
    {
        return
            defined('STDIN') ||
            php_sapi_name() === 'cli' ||
            (
                empty($_SERVER['REMOTE_ADDR']) &&
                !isset($_SERVER['HTTP_USER_AGENT']) &&
                count($_SERVER['argv']) > 0
            );
    }

    /**
     * Is windows environment.
     *
     * @return bool
     */
    public static function isWindows()
    {
        return stripos(PHP_OS, 'WIN') === 0;
    }

    /**
     * Interrupt.
     */
    protected function interrupt()
    {
        $this->interrupt = true;
        if (self::isCli()) {
            exit();
        }
    }

    /**
     * @return bool
     */
    public static function isLogEnabled()
    {
        return isset(self::$settings['log']) ? self::$settings['log'] : false;
    }

    /**
     * @return self
     */
    public static function enableLogs()
    {
        self::$settings['log'] = true;

        return new static();
    }

    /**
     * @return self
     */
    public static function disableLogs()
    {
        self::$settings['log'] = false;

        return new static();
    }

    /**
     * @return self
     */
    public static function setOffset($offset)
    {
        self::$settings['offset'] = $offset;

        return new static();
    }

    /**
     * @return self
     */
    public static function setLimit($limit)
    {
        self::$settings['limit'] = $limit;

        return new static();
    }

    /**
     * @return self
     */
    public static function setSilentMode($mode = true)
    {
        self::$settings['silent'] = $mode;
        if ($mode) {
            if (self::$prompt === null) {
                self::setAutoSkip(true);
            }
            self::setReportMode(false);
        }

        return new static();
    }

    /**
     * @return bool
     */
    public static function isSilentMode()
    {
        return isset(self::$settings['silent']) ? self::$settings['silent'] : false;
    }

    /**
     * @return self
     */
    public static function setColors($mode = true)
    {
        self::$settings['colors'] = $mode;

        return new static();
    }

    /**
     * @return bool
     */
    public static function isColorEnabled()
    {
        return isset(self::$settings['colors']) ? self::$settings['colors'] : true;
    }

    /**
     * @return self
     */
    public static function setCache($mode = true)
    {
        self::$settings['cache'] = $mode;

        return new static();
    }

    /**
     * @return bool
     */
    public static function isCacheEnabled()
    {
        return isset(self::$settings['cache']) ? self::$settings['cache'] : true;
    }

    /**
     * @return self
     */
    public static function setOnlyFunctionsMode($mode = true)
    {
        self::$settings['functions'] = $mode;
        if ($mode) {
            self::$settings['exploits'] = false;
            self::$settings['signatures'] = false;
        }

        return new static();
    }

    /**
     * @return bool
     */
    public static function isOnlyFunctionsMode()
    {
        return isset(self::$settings['functions']) ? self::$settings['functions'] : false;
    }

    /**
     * @return self
     */
    public static function setOnlyExploitsMode($mode = true)
    {
        self::$settings['exploits'] = $mode;
        if ($mode) {
            self::$settings['functions'] = false;
            self::$settings['signatures'] = false;
        }

        return new static();
    }

    /**
     * @return bool
     */
    public static function isOnlyExploitsMode()
    {
        return isset(self::$settings['exploits']) ? self::$settings['exploits'] : false;
    }

    /**
     * @return self
     */
    public static function setOnlySignaturesMode($mode = true)
    {
        self::$settings['signatures'] = $mode;
        if ($mode) {
            self::$settings['exploits'] = false;
            self::$settings['functions'] = false;
        }

        return new static();
    }

    /**
     * @return bool
     */
    public static function isOnlySignaturesMode()
    {
        return isset(self::$settings['signatures']) ? self::$settings['signatures'] : false;
    }

    /**
     * @return self
     */
    public static function setAgileMode($mode = true)
    {
        self::$settings['agile'] = $mode;
        if ($mode) {
            self::$settings['exploits'] = true;
        }

        return new static();
    }

    /**
     * @return bool
     */
    public static function isAgileMode()
    {
        return isset(self::$settings['agile']) ? self::$settings['agile'] : false;
    }

    /**
     * @return self
     */
    public static function setReportMode($mode = true)
    {
        self::$settings['report'] = $mode;

        return new static();
    }

    /**
     * @return bool
     */
    public static function isReportMode()
    {
        return isset(self::$settings['report']) ? self::$settings['report'] : false;
    }

    /**
     * @return self
     */
    public static function enableBackups()
    {
        self::$settings['backup'] = true;

        return new static();
    }

    /**
     * @return self
     */
    public static function disableBackups()
    {
        self::$settings['backup'] = false;

        return new static();
    }

    /**
     * @return bool
     */
    public static function isBackupEnabled()
    {
        return isset(self::$settings['backup']);
    }

    /**
     * @return self
     */
    public static function setOnlyPathWhitelistMode($mode = true)
    {
        self::$settings['whitelist-only-path'] = $mode;

        return new static();
    }

    /**
     * @return bool
     */
    public static function isOnlyPathWhitelistMode()
    {
        return isset(self::$settings['whitelist-only-path']) ? self::$settings['whitelist-only-path'] : false;
    }

    /**
     * @return array
     */
    public static function getIgnorePaths()
    {
        return self::$ignorePaths;
    }

    /**
     * @param array $ignorePaths
     */
    public static function setIgnorePaths($ignorePaths)
    {
        self::$ignorePaths = $ignorePaths;

        return new static();
    }

    /**
     * @return array
     */
    public static function getFilterPaths()
    {
        return self::$filterPaths;
    }

    /**
     * @param array $filterPaths
     */
    public static function setFilterPaths($filterPaths)
    {
        self::$filterPaths = $filterPaths;

        return new static();
    }

    /**
     * @return string
     */
    public static function getPrompt()
    {
        return self::$prompt;
    }

    /**
     * @param string $prompt
     */
    public static function setPrompt($prompt)
    {
        if (!empty($prompt)) {
            self::setReportMode(false);
        }
        self::$prompt = $prompt;

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoDelete($mode = true)
    {
        self::setPrompt($mode !== '' ? 'delete' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoClean($mode = true)
    {
        self::setPrompt($mode !== '' ? 'clean' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoCleanLine($mode = true)
    {
        self::setPrompt($mode !== '' ? 'clean-line' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoQuarantine($mode = true)
    {
        self::setPrompt($mode !== '' ? 'quarantine' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoWhitelist($mode = true)
    {
        self::setPrompt($mode !== '' ? 'whitelist' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoSkip($mode = true)
    {
        self::setPrompt($mode !== '' ? 'skip' : null);

        return new static();
    }

    /**
     * @return string
     */
    public static function getPathBackups()
    {
        return self::$pathBackups;
    }

    /**
     * @param string $pathBackups
     */
    public static function setPathBackups($pathBackups)
    {
        self::$pathBackups = $pathBackups;

        return new static();
    }

    /**
     * @return string
     */
    public static function getPathQuarantine()
    {
        return self::$pathQuarantine;
    }

    /**
     * @param string $pathQuarantine
     */
    public static function setPathQuarantine($pathQuarantine)
    {
        self::$pathQuarantine = $pathQuarantine;

        return new static();
    }

    /**
     * @return string
     */
    public static function getPathLogs()
    {
        return self::$pathLogs;
    }

    /**
     * @param string $pathLogs
     */
    public static function setPathLogs($pathLogs)
    {
        self::$pathLogs = $pathLogs;

        return new static();
    }

    /**
     * @return string
     */
    public static function getPathReport()
    {
        return self::$pathReport;
    }

    /**
     * @param string $pathReport
     */
    public static function setPathReport($pathReport)
    {
        self::$pathReport = $pathReport;

        return new static();
    }

    /**
     * @return string
     */
    public static function getPathWhitelist()
    {
        return self::$pathWhitelist;
    }

    /**
     * @param string $pathWhitelist
     */
    public static function setPathWhitelist($pathWhitelist)
    {
        self::$pathWhitelist = $pathWhitelist;

        return new static();
    }

    /**
     * @return string
     */
    public static function getPathScan()
    {
        return self::$pathScan;
    }

    /**
     * @param string $pathScan
     */
    public static function setPathScan($pathScan)
    {
        self::$pathScan = $pathScan;

        return new static();
    }

    /**
     * @return int
     */
    public static function getMaxFilesize()
    {
        return self::$maxFilesize;
    }

    /**
     * @param mixed $maxFilesize
     */
    public static function setMaxFilesize($maxFilesize)
    {
        $maxFilesize = trim($maxFilesize);
        if (!is_numeric(self::$argv['max-filesize'])) {
            $maxFilesize = self::convertToBytes($maxFilesize);
        }
        self::$maxFilesize = $maxFilesize;

        return new static();
    }

    /**
     * @return array
     */
    public static function getExtensions()
    {
        return self::$extensions;
    }

    /**
     * @return self
     */
    public static function setFunctions($functions)
    {
        self::$functions = $functions;

        return new static();
    }

    /**
     * @return self
     */
    public static function setExploits($exploits)
    {
        self::$exploits = $exploits;

        return new static();
    }

    /**
     * @param array $extensions
     */
    public static function setExtensions($extensions)
    {
        self::$extensions = $extensions;

        return new static();
    }

    /**
     * @return int
     */
    public static function getReportFilesScanned()
    {
        return self::$report['scanned'];
    }

    /**
     * @return int
     */
    public static function getReportMalwareDetected()
    {
        return self::$report['detected'];
    }

    /**
     * @return array
     */
    public static function getReportMalwareRemoved()
    {
        return self::$report['removed'];
    }

    /**
     * @return array
     */
    public static function getReportFilesIgnored()
    {
        return self::$report['ignored'];
    }

    /**
     * @return array
     */
    public static function getReportFilesEdited()
    {
        return self::$report['edited'];
    }

    /**
     * @return array
     */
    public static function getReportQuarantine()
    {
        return self::$report['quarantine'];
    }

    /**
     * @return array
     */
    public static function getReportWhitelist()
    {
        return self::$report['quarantine'];
    }

    /**
     * @return string
     */
    public static function getName()
    {
        return self::$name;
    }

    /**
     * @return string
     */
    public static function getVersion()
    {
        return self::$version;
    }

    /**
     * @return string
     */
    public static function getRoot()
    {
        return self::$root;
    }

    /**
     * @return Argv
     */
    public static function getArgv()
    {
        return self::$argv;
    }

    /**
     * @return array
     */
    public static function getWhitelist()
    {
        return self::$whitelist;
    }

    /**
     * @param array $whitelist
     */
    public static function setWhitelist($whitelist)
    {
        self::$whitelist = $whitelist;

        return new static();
    }

    /**
     * @return array
     */
    public static function getFunctions()
    {
        return self::$functions;
    }

    /**
     * @return array
     */
    public static function getExploits()
    {
        return array_keys(self::$exploits);
    }

    /**
     * @return array
     */
    public static function getSettings()
    {
        return self::$settings;
    }

    /**
     * @return object
     */
    public static function getReport()
    {
        return (object)self::$report;
    }

    /**
     * @return bool
     */
    public function isInterrupted()
    {
        return $this->interrupt;
    }

    /**
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * @param string $lastError
     */
    protected function setLastError($lastError)
    {
        $this->lastError = $lastError;

        return $this;
    }
}
