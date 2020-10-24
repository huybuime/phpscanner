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

use CallbackFilterIterator;
use Exception;
use LimitIterator;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
    public static $version = '0.7.0.110';

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
    public static $extensions = array(
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
        'phtm',
        'phtml',
        'ico',
    );
    /**
     * Arguments.
     *
     * @var Argv
     */
    public static $argv = array();

    /**
     * Whitelist.
     *
     * @var array
     */
    public static $whitelist = array();

    /**
     * Functions.
     *
     * @var array
     */
    public static $functions = array();

    /**
     * Exploits.
     *
     * @var array
     */
    public static $exploits = array();

    /**
     * Settings.
     *
     * @var array
     */
    public static $settings = array();

    /**
     * Report.
     *
     * @var array
     */
    protected static $report = array(
        'scanned' => 0,
        'detected' => 0,
        'removed' => array(),
        'ignored' => array(),
        'edited' => array(),
        'quarantine' => array(),
        'whitelist' => array(),
    );

    /**
     * Ignore paths.
     *
     * @var array
     */
    public static $ignorePaths = array();

    /**
     * Filter paths.
     *
     * @var array
     */
    public static $filterPaths = array();

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
                self::$whitelist = array();
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
            Console::writeLine('Mapping and verifying files. It may take a while, please wait...');
            $iterator = $this->mapping();

            // Counting files
            $files_count = iterator_count($iterator);
            Console::writeLine('Found ' . $files_count . ' files to check', 2);
            Console::writeLine('Checking files...', 2);
            Console::progress(0, $files_count);

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
        self::$argv->addFlag('agile', array('alias' => '-a', 'default' => false));
        self::$argv->addFlag('help', array('alias' => '-h', 'default' => false));
        self::$argv->addFlag('log', array('alias' => '-l', 'default' => null, 'has_value' => true));
        self::$argv->addFlag('backup', array('alias' => '-b', 'default' => false));
        self::$argv->addFlag('offset', array('default' => null, 'has_value' => true));
        self::$argv->addFlag('limit', array('default' => null, 'has_value' => true));
        self::$argv->addFlag('report', array('alias' => '-r', 'default' => false));
        self::$argv->addFlag('version', array('alias' => '-v', 'default' => false));
        self::$argv->addFlag('update', array('alias' => '-u', 'default' => false));
        self::$argv->addFlag('only-signatures', array('alias' => '-s', 'default' => false));
        self::$argv->addFlag('only-exploits', array('alias' => '-e', 'default' => false));
        self::$argv->addFlag('only-functions', array('alias' => '-f', 'default' => false));
        self::$argv->addFlag('definitions-list', array('default' => false));
        self::$argv->addFlag('definitions-exploits', array('default' => false));
        self::$argv->addFlag('definitions-functions', array('default' => false));
        self::$argv->addFlag('exploits', array('default' => false, 'has_value' => true));
        self::$argv->addFlag('functions', array('default' => false, 'has_value' => true));
        self::$argv->addFlag('whitelist-only-path', array('default' => false));
        self::$argv->addFlag('max-filesize', array('default' => -1, 'has_value' => true));
        self::$argv->addFlag('silent', array('default' => false));
        self::$argv->addFlag('ignore-paths', array('alias' => '--ignore-path', 'default' => null, 'has_value' => true));
        self::$argv->addFlag('filter-paths', array('alias' => '--filter-path', 'default' => null, 'has_value' => true));
        self::$argv->addFlag('auto-clean', array('default' => false));
        self::$argv->addFlag('auto-clean-line', array('default' => false));
        self::$argv->addFlag('auto-delete', array('default' => false));
        self::$argv->addFlag('auto-quarantine', array('default' => false));
        self::$argv->addFlag('auto-skip', array('default' => false));
        self::$argv->addFlag('auto-whitelist', array('default' => false));
        self::$argv->addFlag('auto-prompt', array('default' => null, 'has_value' => true));
        self::$argv->addFlag('path-whitelist', array('default' => false, 'has_value' => true));
        self::$argv->addFlag('path-backups', array('default' => false, 'has_value' => true));
        self::$argv->addFlag('path-quarantine', array('default' => false, 'has_value' => true));
        self::$argv->addFlag('path-logs', array('default' => false, 'has_value' => true));
        self::$argv->addFlag('path-report', array('default' => false, 'has_value' => true));
        self::$argv->addFlag('disable-colors', array('default' => false));
        self::$argv->addArgument('path', array('var_args' => true, 'default' => ''));
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
        if ((isset(self::$argv['report']) && self::$argv['report']) || !self::isCli()) {
            self::setReportMode(true);
        }

        // Backups
        if ((isset(self::$argv['backup']) && self::$argv['backup'])) {
            self::enableBackups();
        }

        // Silent
        if (isset(self::$argv['silent']) && self::$argv['silent']) {
            self::setSilentMode(true);
        }

        // Colors
        if (isset(self::$argv['disable-colors']) && self::$argv['disable-colors']) {
            self::setColors(false);
        } else {
            if (function_exists('ncurses_has_colors')) {
                self::setColors(ncurses_has_colors());
            }
        }

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
            $ignorePaths = array();
            foreach ($paths as $path) {
                $path = trim($path);
                $ignorePaths[] = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
            }
            self::setIgnorePaths($ignorePaths);
        }

        // Filter paths
        if (isset(self::$argv['filter-paths']) && !empty(self::$argv['filter-paths'])) {
            $paths = explode(',', self::$argv['filter-paths']);
            $filterPaths = array();
            foreach ($paths as $path) {
                $path = trim($path);
                $filterPaths[] = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
            }
            self::setFilterPaths($filterPaths);
        }

        // Check on whitelist only file path and not line number
        if (isset(self::$argv['whitelist-only-path']) && self::$argv['whitelist-only-path']) {
            self::setOnlyPathWhitelistMode(true);
        }

        // Check Filter exploits
        if (isset(self::$argv['exploits']) && self::$argv['exploits']) {
            if (is_string(self::$argv['exploits'])) {
                $exploits = array();
                $filtered = str_replace(array("\n", "\r", "\t", ' '), '', self::$argv['exploits']);
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
                        $exploits = array();
                    }
                }
                self::setExploits($exploits);
            }
        }

        // Check if exploit mode is enabled
        if (isset(self::$argv['only-exploits']) && self::$argv['only-exploits']) {
            self::setOnlyExploitsMode(true);
        }

        // Check functions to search
        if (isset(self::$argv['functions']) && self::$argv['functions']) {
            if (is_string(self::$argv['functions'])) {
                $functions = str_replace(array("\n", "\r", "\t", ' '), '', self::$argv['functions']);
                $functions = @explode(',', $functions);
                if (!empty($functions) && count($functions) > 0) {
                    Console::writeLine('Functions to search: ' . implode(', ', $functions));
                } else {
                    $functions = array();
                }
                self::setFunctions($functions);
            }
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

        // Check path
        if (!is_dir(self::$pathScan)) {
            self::setPathScan(pathinfo(self::$pathScan, PATHINFO_DIRNAME));
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
            self::setExploits(array());
        }

        if (self::isOnlyExploitsMode() && !self::isAgileMode()) {
            Console::writeLine('Only exploit mode enabled');
            self::setFunctions(array());
        }

        if (self::isOnlySignaturesMode()) {
            Console::writeLine('Only signatures mode enabled');
            self::setExploits(array());
            self::setFunctions(array());
        }
    }

    /**
     * Map files.
     *
     * @return CallbackFilterIterator
     */
    public function mapping()
    {
        // Mapping files
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

        $iterator = new CallbackFilterIterator($filtered, function ($cur) {
            return $cur->isFile() && !Modules::isVerified($cur->getPathname());
        });

        return $iterator;
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
        $_FILE_NAME = $file->getFilename();
        $_FILE_EXTENSION = $file->getExtension();

        return ((strpos($_FILE_NAME, 'favicon_') === 0) && ($_FILE_EXTENSION === 'ico') && (strlen($_FILE_NAME) > 12)) || preg_match('/^\.[\w]+\.ico/i', trim($_FILE_NAME));
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
        $_FILE_PATH = $info->getPathname();

        $is_favicon = self::isInfectedFavicon($info);
        $pattern_found = array();

        $mime_type = 'text/php';
        if (function_exists('mime_content_type')) {
            $mime_type = mime_content_type($_FILE_PATH);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mime_type = finfo_file($finfo, $_FILE_PATH);
            finfo_close($finfo);
        }

        if (0 === stripos($mime_type, 'text')) {
            $deobfuctator = new Deobfuscator();

            $fc = file_get_contents($_FILE_PATH);
            $fc_clean = php_strip_whitespace($_FILE_PATH);
            $fc_deobfuscated = $deobfuctator->deobfuscate($fc);
            $fc_decoded = $deobfuctator->decode($fc_deobfuscated);

            // Scan exploits
            $last_match = null;
            foreach (self::$exploits as $key => $pattern) {
                $match_description = null;
                $lineNumber = null;
                if (@preg_match($pattern, $fc, $match, PREG_OFFSET_CAPTURE) || // Original
                   @preg_match($pattern, $fc_clean, $match, PREG_OFFSET_CAPTURE) || // No comments
                   @preg_match($pattern, $fc_decoded, $match, PREG_OFFSET_CAPTURE)) { // Decoded
                    $last_match = $match[0][0];
                    $match_description = $key . "\n => " . $last_match;
                    if (!empty($last_match) && @preg_match('/' . preg_quote($last_match, '/') . '/i', $fc, $match, PREG_OFFSET_CAPTURE)) {
                        $lineNumber = count(explode("\n", substr($fc, 0, $match[0][1])));
                        $match_description = $key . ' [line ' . $lineNumber . "]\n => " . $last_match;
                    }
                    if (!empty($match_description)) {
                        //$pattern_found[$match_description] = $pattern;
                        $pattern_found[$match_description] = array(
                            'key' => $key,
                            'line' => $lineNumber,
                            'pattern' => $pattern,
                            'match' => $last_match,
                        );
                    }
                }
            }
            unset($last_match, $match_description, $lineNumber, $match);

            // Scan php commands
            $last_match = null;
            foreach (self::$functions as $_func) {
                $match_description = null;
                $func = preg_quote(trim($_func), '/');
                // Basic search
                $regex_pattern = "/(?:^|[\s\r\n]+|[^a-zA-Z0-9_>]+)(" . $func . "[\s\r\n]*\((?<=\().*?(?=\))\))/si";
                if (@preg_match($regex_pattern, $fc_decoded, $match, PREG_OFFSET_CAPTURE) ||
                   @preg_match($regex_pattern, $fc_clean, $match, PREG_OFFSET_CAPTURE)) {
                    $last_match = explode($_func, $match[0][0]);
                    $last_match = $_func . $last_match[1];
                    $match_description = $_func . "\n => " . $last_match;
                    if (!empty($last_match) && @preg_match('/' . preg_quote($last_match, '/') . '/', $fc, $match, PREG_OFFSET_CAPTURE)) {
                        $lineNumber = count(explode("\n", substr($fc, 0, $match[0][1])));
                        $match_description = $_func . ' [line ' . $lineNumber . "]\n => " . $last_match;
                    }
                    if (!empty($match_description)) {
                        $pattern_found[$match_description] = array(
                            'key' => $_func,
                            'line' => $lineNumber,
                            'pattern' => $regex_pattern,
                            'match' => $last_match,
                        );
                    }
                }
                // Check of base64
                $regex_pattern_base64 = '/' . base64_encode($_func) . '/s';
                if (@preg_match($regex_pattern_base64, $fc_decoded, $match, PREG_OFFSET_CAPTURE) ||
                   @preg_match($regex_pattern_base64, $fc_clean, $match, PREG_OFFSET_CAPTURE)) {
                    $last_match = explode($_func, $match[0][0]);
                    $last_match = $_func . $last_match[1];
                    $match_description = $_func . "_base64\n => " . $last_match;

                    if (!empty($last_match) && @preg_match('/' . preg_quote($last_match, '/') . '/', $fc, $match, PREG_OFFSET_CAPTURE)) {
                        $lineNumber = count(explode("\n", substr($fc, 0, $match[0][1])));
                        $match_description = $_func . '_base64 [line ' . $lineNumber . "]\n => " . $last_match;
                    }
                    if (!empty($match_description)) {
                        $pattern_found[$match_description] = array(
                            'key' => $_func . '_base64',
                            'line' => $lineNumber,
                            'pattern' => $regex_pattern_base64,
                            'match' => $last_match,
                        );
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

                unset($last_match, $match_description, $lineNumber, $regex_pattern, $regex_pattern_base64, $match);
            }

            foreach (Definitions::$SIGNATURES as $key => $pattern) {
                $regex_pattern = '#' . $pattern . '#smiS';
                if (preg_match($regex_pattern, $fc_deobfuscated, $match, PREG_OFFSET_CAPTURE)) {
                    $last_match = $match[0][0];
                    if (!empty($last_match) && @preg_match('/' . preg_quote($match[0][0], '/') . '/', $fc, $match, PREG_OFFSET_CAPTURE)) {
                        $lineNumber = count(explode("\n", substr($fc, 0, $match[0][1])));
                        $match_description = 'Sign ' . $key . ' [line ' . $lineNumber . "]\n => " . $last_match;
                    }
                    if (!empty($match_description)) {
                        $pattern_found[$match_description] = array(
                            'key' => $key,
                            'line' => $lineNumber,
                            'pattern' => $regex_pattern,
                            'match' => $last_match,
                        );
                    }
                }
            }

            unset($fc, $fc_decoded, $fc_clean, $fc_deobfuscated);
        }

        if ($is_favicon) {
            $pattern_found['infected_icon'] = array(
                'key' => 'infected_icon',
                'line' => '',
                'pattern' => '',
                'match' => '',
            );
        }

        return $pattern_found;
    }

    /**
     * Run index.php.
     *
     * @param $iterator
     */
    private function scan($iterator)
    {
        $files_count = iterator_count($iterator);
        $limit = !empty(self::$settings['limit']) ? self::$settings['limit'] : null;
        if (!empty(self::$settings['offset'])) {
            self::$report['scanned'] = self::$settings['offset'];
            $iterator = new LimitIterator($iterator, self::$settings['offset'], $limit);
        } elseif (!empty($limit)) {
            $iterator = new LimitIterator($iterator, 0, $limit);
        }

        // Scanning
        foreach ($iterator as $info) {
            Console::progress(self::$report['scanned'], $files_count);

            $_FILE_PATH = $info->getPathname();
            $_FILE_EXTENSION = $info->getExtension();
            $_FILE_SIZE = filesize($_FILE_PATH);

            $is_favicon = self::isInfectedFavicon($info);

            if ((
                in_array($_FILE_EXTENSION, self::$extensions) &&
                (self::$maxFilesize < 1 || $_FILE_SIZE <= self::$maxFilesize) &&
                (!file_exists(self::$pathQuarantine) || strpos(realpath($_FILE_PATH), realpath(self::$pathQuarantine)) === false)
                   /*&& (strpos($filename, '-') === FALSE)*/
            ) ||
               $is_favicon) {
                $pattern_found = $this->scanFile($info);

                // Check whitelist
                $in_whitelist = 0;
                foreach (self::$whitelist as $item) {
                    foreach ($pattern_found as $key => $pattern) {
                        $lineNumber = $pattern['line'];
                        $exploit = $pattern['key'];
                        $match = $pattern['match'];

                        if (strpos($_FILE_PATH, $item['file']) !== false &&
                            $match === $item['match'] &&
                            $exploit === $item['exploit'] &&
                           (self::isOnlyPathWhitelistMode() || (!self::isOnlyPathWhitelistMode() && $lineNumber == $item['line']))) {
                            $in_whitelist++;
                        }
                    }
                }

                // Scan finished

                self::$report['scanned']++;
                usleep(10);

                if (realpath($_FILE_PATH) != realpath(__FILE__) && ($is_favicon || !empty($pattern_found)) && ($in_whitelist === 0 || $in_whitelist != count($pattern_found))) {
                    self::$report['detected']++;
                    if (self::isReportMode()) {
                        // Scan mode only
                        self::$report['ignored'][] = 'File: ' . $_FILE_PATH . PHP_EOL .
                                                   'Exploits:' . PHP_EOL .
                                                   ' => ' . implode(PHP_EOL . ' => ', array_keys($pattern_found));
                        continue;
                    }

                    // Scan with code check
                    $_WHILE = true;
                    $last_command = '0';
                    Console::newLine(2);
                    Console::writeBreak();
                    Console::writeLine('PROBABLE MALWARE FOUND!', 1, 'red');

                    while ($_WHILE) {
                        $fc = file_get_contents($_FILE_PATH);
                        $preview_lines = explode(Console::eol(1), trim($fc));
                        $preview = implode(Console::eol(1), array_slice($preview_lines, 0, 1000));
                        if (!in_array($last_command, array('4', '5', '7'))) {
                            Console::displayLine("$_FILE_PATH", 2, 'yellow');

                            $title = Console::title(' PREVIEW ', '=');
                            Console::display($title, 'white', 'red');
                            Console::newLine(2);

                            Console::code($preview, $pattern_found);
                            if (count($preview_lines) > 1000) {
                                Console::newLine(2);
                                Console::display('  [ ' . (count($preview_lines) - 1000) . ' rows more ]');
                            }
                            Console::newLine(2);

                            $title = Console::title('', '=');
                            Console::display($title, 'white', 'red');
                        }
                        Console::newLine(2);
                        Console::writeLine('Checksum: ' . md5_file($_FILE_PATH), 1, 'yellow');
                        Console::writeLine('File path: ' . $_FILE_PATH, 1, 'yellow');
                        Console::writeLine('Exploits found: ' . Console::eol(1) . implode(Console::eol(1), array_keys($pattern_found)), 2, 'red');
                        Console::displayLine('OPTIONS:', 2);

                        $confirmation = self::$prompt;
                        if (self::$prompt === null) {
                            $confirmation = Console::choice('What is your choice? ', array(
                                1 => 'Delete file',
                                2 => 'Move to quarantine',
                                3 => 'Dry run evil code fixer',
                                4 => 'Dry run evil line code fixer',
                                5 => 'Open with vim',
                                6 => 'Open with nano',
                                7 => 'Add to whitelist',
                                8 => 'Show source',
                                '-' => 'Ignore',
                            ));
                        }
                        Console::newLine();

                        $last_command = $confirmation;
                        unset($preview_lines, $preview);

                        if (in_array($confirmation, array('1', 'delete'))) {
                            // Remove file
                            Console::writeLine('File path: ' . $_FILE_PATH, 1, 'yellow');
                            $confirm2 = 'y';
                            if (self::$prompt === null) {
                                $confirm2 = Console::read('Want delete this file [y|N]? ', 'purple');
                            }
                            Console::newLine();
                            if ($confirm2 === 'y') {
                                Actions::deleteFile($_FILE_PATH);
                                self::$report['removed'][] = $_FILE_PATH;
                                Console::writeLine("File '$_FILE_PATH' removed!", 2, 'green');
                                $_WHILE = false;
                            }
                        } elseif (in_array($confirmation, array('2', 'quarantine'))) {
                            // Move to quarantine
                            $quarantine = Actions::moveToQuarantine($_FILE_PATH);
                            self::$report['quarantine'][] = $quarantine;
                            Console::writeLine("File '$_FILE_PATH' moved to quarantine!", 2, 'green');
                            $_WHILE = false;
                        } elseif (in_array($confirmation, array('3', 'clean')) && count($pattern_found) > 0) {
                            // Remove evil code
                            $fc = Actions::cleanEvilCode($fc, $pattern_found);
                            Console::newLine();

                            $title = Console::title(' SANITIZED ', '=');
                            Console::display($title, 'black', 'green');
                            Console::newLine(2);
                            Console::code($fc);
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
                                Console::writeLine("File '$_FILE_PATH' sanitized!", 2, 'green');
                                Actions::putContents($_FILE_PATH, $fc);
                                self::$report['removed'][] = $_FILE_PATH;
                                $_WHILE = false;
                            } else {
                                self::$report['ignored'][] = $_FILE_PATH;
                            }
                        } elseif (in_array($confirmation, array('4', 'clean-line')) && count($pattern_found) > 0) {
                            // Remove evil line code
                            $fc = Actions::cleanEvilCodeLine($fc, $pattern_found);

                            Console::newLine();

                            $title = Console::title(' SANITIZED ', '=');
                            Console::display($title, 'black', 'green');
                            Console::newLine(2);
                            Console::code($fc);
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
                                Console::writeLine("File '$_FILE_PATH' sanitized!", 2, 'green');
                                Actions::putContent($_FILE_PATH, $fc);
                                self::$report['removed'][] = $_FILE_PATH;
                                $_WHILE = false;
                            } else {
                                self::$report['ignored'][] = $_FILE_PATH;
                            }
                        } elseif (in_array($confirmation, array('5', 'vim'))) {
                            // Open with vim
                            Actions::openWithVim($_FILE_PATH);
                            self::$report['edited'][] = $_FILE_PATH;
                            Console::writeLine("File '$_FILE_PATH' edited with vim!", 2, 'green');
                            self::$report['removed'][] = $_FILE_PATH;
                        } elseif (in_array($confirmation, array('6', 'nano'))) {
                            // Open with nano
                            Actions::openWithNano($_FILE_PATH);
                            self::$report['edited'][] = $_FILE_PATH;
                            Console::writeLine("File '$_FILE_PATH' edited with nano!", 2, 'green');
                            self::$report['removed'][] = $_FILE_PATH;
                        } elseif (in_array($confirmation, array('7', 'whitelist'))) {
                            // Add to whitelist
                            if (Actions::addToWhitelist($_FILE_PATH, $pattern_found)) {
                                self::$report['whitelist'][] = $_FILE_PATH;
                                Console::writeLine("Exploits of file '$_FILE_PATH' added to whitelist!", 2, 'green');
                                $_WHILE = false;
                            } else {
                                Console::writeLine("Exploits of file '$_FILE_PATH' failed adding file to whitelist! Check write permission of '" . self::$pathWhitelist . "' file!", 2, 'red');
                            }
                        } elseif (in_array($confirmation, array('8', 'show'))) {
                            // Show source code
                            Console::newLine();
                            Console::displayLine("$_FILE_PATH", 2, 'yellow');

                            $title = Console::title(' SOURCE ', '=');
                            Console::display($title, 'white', 'red');
                            Console::newLine(2);

                            Console::code($fc, $pattern_found);
                            Console::newLine(2);

                            $title = Console::title('', '=');
                            Console::display($title, 'white', 'red');
                            Console::newLine(2);
                        } else {
                            // Skip
                            Console::writeLine("File '$_FILE_PATH' skipped!", 2, 'green');
                            self::$report['ignored'][] = $_FILE_PATH;
                            $_WHILE = false;
                        }

                        Console::writeBreak();
                    }
                    unset($fc);
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
                    $new_version = file_get_contents('https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner');
                    file_put_contents(self::currentFilename(), $new_version);
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
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
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
            die();
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
        return isset(self::$settings['backup']) ? true : false;
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
        self::setPrompt($mode ? 'delete' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoClean($mode = true)
    {
        self::setPrompt($mode ? 'clean' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoCleanLine($mode = true)
    {
        self::setPrompt($mode ? 'clean-line' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoQuarantine($mode = true)
    {
        self::setPrompt($mode ? 'quarantine' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoWhitelist($mode = true)
    {
        self::setPrompt($mode ? 'whitelist' : null);

        return new static();
    }

    /**
     * @param string $mode
     */
    public static function setAutoSkip($mode = true)
    {
        self::setPrompt($mode ? 'skip' : null);

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