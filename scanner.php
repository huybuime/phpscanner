#!/usr/bin/php
<?php

/**
 * Antimalware Scanner
 * @author Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright Copyright (c) 2018
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link https://github.com/marcocesarato/PHP-Class-Markdown-Docs
 * @version 0.3.11.15
 */

$isCLI = (php_sapi_name() == 'cli');
if(!$isCLI) {
	die("This file must run from a console session.");
}

// Settings
define("__NAME__", "amwscan");
define("__VERSION__", "0.3.12.16");
define("__ROOT__", dirname(__FILE__));
define("__PATH_QUARANTINE__", __ROOT__ . "/quarantine");
define("__PATH_LOGS__", __ROOT__ . "/scanner.log");
define("__PATH_WHITELIST__", __ROOT__ . "/scanner_whitelist.csv");
define("__PATH_LOGS_INFECTED__", __ROOT__ . "/scanner_infected.log");

define("PHP_EOL2", PHP_EOL . PHP_EOL);
define("PHP_EOL3", PHP_EOL2 . PHP_EOL);

error_reporting(0);
ini_set('display_errors', 0);

set_time_limit(- 1);
ini_set("memory_limit", - 1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

$version = __VERSION__;
$header  = <<<EOD


 █████╗ ███╗   ███╗██╗    ██╗███████╗ ██████╗ █████╗ ███╗   ██╗
██╔══██╗████╗ ████║██║    ██║██╔════╝██╔════╝██╔══██╗████╗  ██║
███████║██╔████╔██║██║ █╗ ██║███████╗██║     ███████║██╔██╗ ██║
██╔══██║██║╚██╔╝██║██║███╗██║╚════██║██║     ██╔══██║██║╚██╗██║
██║  ██║██║ ╚═╝ ██║╚███╔███╔╝███████║╚██████╗██║  ██║██║ ╚████║
╚═╝  ╚═╝╚═╝     ╚═╝ ╚══╝╚══╝ ╚══════╝ ╚═════╝╚═╝  ╚═╝╚═╝  ╚═══╝

                      version $version

EOD;
Console::display($header, "green");
Console::display(PHP_EOL);
Console::display("                                                               ", 'black', 'green');
Console::display(PHP_EOL);
Console::display("                     Antimalware Scanner                       ", 'black', 'green');
Console::display(PHP_EOL);
Console::display("                  Created by Marco Cesarato                    ", 'black', 'green');
Console::display(PHP_EOL);
Console::display("                                                               ", 'black', 'green');
Console::display(PHP_EOL2);

// Globals

$_EXPLOITS  = array();
$_FUNCTIONS = array();

$summary_scanned    = 0;
$summary_detected   = 0;
$summary_removed    = 0;
$summary_ignored    = array();
$summary_edited     = array();
$summary_quarantine = array();
$summary_whitelist  = array();
$path               = __ROOT__;

// Classes
class Console {

	/**
	 * Font colors
	 * @var array
	 */
	public static $foreground_colors = array(
		'black'        => '0;30',
		'dark_gray'    => '1;30',
		'blue'         => '0;34',
		'light_blue'   => '1;34',
		'green'        => '0;32',
		'light_green'  => '1;32',
		'cyan'         => '0;36',
		'light_cyan'   => '1;36',
		'red'          => '0;31',
		'light_red'    => '1;31',
		'purple'       => '0;35',
		'light_purple' => '1;35',
		'brown'        => '0;33',
		'yellow'       => '1;33',
		'light_gray'   => '0;37',
		'white'        => '1;37',
	);

	/**
	 * Background colors
	 * @var array
	 */
	public static $background_colors = array(
		'black'      => '40',
		'red'        => '41',
		'green'      => '42',
		'yellow'     => '43',
		'blue'       => '44',
		'magenta'    => '45',
		'cyan'       => '46',
		'light_gray' => '47',
	);

	/**
	 * Print progress
	 * @param $done
	 * @param $total
	 * @param int $size
	 */
	public static function progress($done, $total, $size = 30) {
		static $start_time;
		if($done > $total) {
			return;
		}
		if(empty($start_time)) {
			$start_time = time();
		}
		$now        = time();
		$perc       = (double) ($done / $total);
		$bar        = floor($perc * $size);
		$status_bar = "\r[";
		$status_bar .= str_repeat("=", $bar);
		if($bar < $size) {
			$status_bar .= ">";
			$status_bar .= str_repeat(" ", $size - $bar);
		} else {
			$status_bar .= "=";
		}
		$disp       = number_format($perc * 100, 0);
		$status_bar .= "] $disp%";
		$rate       = ($now - $start_time) / $done;
		$left       = $total - $done;
		$eta        = round($rate * $left, 2);
		$elapsed    = $now - $start_time;
		self::display("$status_bar ", "black", "green");
		self::display(" ");
		self::display("$done/$total", "green");
		self::display(" remaining: " . number_format($eta) . " sec.  elapsed: " . number_format($elapsed) . " sec.");
		flush();
		if($done == $total) {
			self::display(PHP_EOL);
		}
	}

	/**
	 * Print message without writing logs
	 * @param $string
	 * @param string $foreground_color
	 * @param null $background_color
	 */
	public static function display($string, $foreground_color = "white", $background_color = null) {
		self::write($string, $foreground_color, $background_color, false);
	}

	/**
	 * Print message
	 * @param $string
	 * @param string $foreground_color
	 * @param null $background_color
	 * @param null $log
	 */
	public static function write($string, $foreground_color = "white", $background_color = null, $log = null) {
		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$foreground_color = null;
			$background_color = null;
		}
		if(isset($_REQUEST['log']) && $log === null) {
			$log = true;
		}
		$colored_string = "";
		if(isset(self::$foreground_colors[$foreground_color])) {
			$colored_string .= "\033[" . self::$foreground_colors[$foreground_color] . "m";
		}
		if(isset(self::$background_colors[$background_color])) {
			$colored_string .= "\033[" . self::$background_colors[$background_color] . "m";
		}
		$colored_string .= $string . "\033[0m";

		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			echo $string;
		} else {
			echo $colored_string;
		}

		if($log) {
			self::log($string);
		}
	}

	/**
	 * Read input
	 * @param $string
	 * @param string $foreground_color
	 * @param null $background_color
	 * @return string
	 */
	public static function read($string, $foreground_color = "white", $background_color = null) {
		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$foreground_color = null;
			$background_color = null;
		}
		$colored_string = "";
		if(isset(self::$foreground_colors[$foreground_color])) {
			$colored_string .= "\033[" . self::$foreground_colors[$foreground_color] . "m";
		}
		if(isset(self::$background_colors[$background_color])) {
			$colored_string .= "\033[" . self::$background_colors[$background_color] . "m";
		}
		$colored_string .= $string . "\033[0m";

		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			return trim(readline(__NAME__ . " > " . trim($string) . " "));
		} else {
			return trim(readline(__NAME__ . " > " . trim($colored_string) . " "));
		}
	}

	/**
	 * Print code
	 * @param $string
	 * @param null $log
	 */
	public static function code($string, $log = null) {
		if(isset($_REQUEST['log']) && $log === null) {
			$log = true;
		}
		$lines = explode("\n", $string);
		for($i = 0; $i < count($lines); $i ++) {
			if($i != 0) {
				self::display(PHP_EOL);
			}
			self::display("  " . str_pad((string) ($i + 1), strlen((string) count($lines)), " ", STR_PAD_LEFT) . ' | ', 'yellow');
			self::display($lines[$i]);
		}
		if($log) {
			self::log($string);
		}
	}

	/**
	 * Write logs
	 * @param $string
	 */
	public static function log($string) {
		file_put_contents(__PATH_LOGS__, $string, FILE_APPEND);
	}

	/**
	 * Print Helper
	 */
	public static function helper() {
		echo $_REQUEST->usage();
		$help = <<<EOD
		
		
Arguments:
[<path>]             Define the path to scan
[<functions>]        Set some specific functions to search [ex. func1,func2,...] (no spaces or use quotes)

Flags:
-e   --exploits      Check only exploits and not the functions (recommended for wordpress or others platforms)
-h   --help          Show the available options
-l   --log           Write a log file 'scanner.log' with all the operations done
-s   --scan          Scan only mode without check and remove malware. It also write
                     all malware paths found to 'scanner_infected.log' file

NOTES: Better if your run with php -d disable_functions=''
USAGE: php -d disable_functions='' scanner -p ./mywebsite/http/ -l
EOD;
		self::display($help . PHP_EOL2);
		die();
	}
}

class Flag {
	public
		$name,
		$callback,
		$aliases = array(),
		$hasValue = false,
		$defaultValue,
		$var,
		$help;

	function __construct($name, $options = array(), $callback = null) {
		$this->name         = $name;
		$this->callback     = $callback;
		$this->aliases      = array_merge(array("--$name"), (array) @$options["alias"]);
		$this->defaultValue = @$options["default"];
		$this->hasValue     = (bool) @$options["has_value"];
		$this->help         = @$options["help"];
		if(array_key_exists("var", $options)) {
			$this->var =& $options["var"];
		}
	}

	function __toString() {
		$s = join('|', $this->aliases);
		if($this->hasValue) {
			$s = "$s <{$this->name}>";
		}

		return "[$s]";
	}
}

class Argument {
	public
		$name,
		$vararg = false,
		$required = false,
		$defaultValue,
		$help;

	function __construct($name, $options = array()) {
		$this->name         = $name;
		$this->vararg       = (bool) @$options["var_arg"];
		$this->required     = (bool) @$options["required"];
		$this->defaultValue = @$options["default"];
		$this->help         = @$options["help"];
	}

	function __toString() {
		$arg = "<{$this->name}>";
		if($this->vararg) {
			$arg = "$arg ...";
		}
		if(!$this->required) {
			return "[$arg]";
		}

		return $arg;
	}
}

class Argv implements \ArrayAccess {
	protected $name,
		$description,
		$examples = array(),
		$flags = array(),
		$args = array(),
		$parsedFlags = array(),
		$parsedNamedArgs = array(),
		$parsedArgs = array();

	static function build($callback) {
		$parser = new static;
		if($callback instanceof \Closure and is_callable(array($callback, "bindTo"))) {
			$callback = $callback->bindTo($parser);
		}
		call_user_func($callback, $parser);

		return $parser;
	}

	function __construct($description = '', $name = null, $examples = array()) {
		$this->description = $description;
		$this->name        = $name;
		$this->examples    = $examples;
	}

	function setName($name) {
		$this->name = $name;

		return $this;
	}

	function setDescription($description) {
		$this->description = $description;

		return $this;
	}

	function setExamples($examples) {
		$this->examples = $examples;

		return $this;
	}

	function parse($args = null) {
		if($args === null) {
			$args = array_slice($_SERVER['argv'], 1);
		}
		foreach($args as $pos => $arg) {
			// reset value
			$value = null;
			if(substr($arg, 0, 1) === '-') {
				if(preg_match('/^(.+)=(.+)$/', $arg, $matches)) {
					$arg   = $matches[1];
					$value = $matches[2];
				}
				if(!$flag = @$this->flags[$arg]) {
					return;
				}
				unset($args[$pos]);
				if($flag->hasValue) {
					if(!isset($value)) {
						$value = $args[$pos + 1];
						unset($args[$pos + 1]);
					}
				} else {
					$value = true;
				}
				if(null !== $flag->callback) {
					call_user_func_array($flag->callback, array(&$value));
				}
				// Set the reference given as the flag's 'var'.
				$flag->var = $this->parsedFlags[$flag->name] = $value;
			}
		}
		foreach($this->flags as $flag) {
			if(!array_key_exists($flag->name, $this->parsedFlags)) {
				$flag->var = $this->parsedFlags[$flag->name] = $flag->defaultValue;
			}
		}
		$this->parsedArgs = $args = array_values($args);
		$pos              = 0;
		foreach($this->args as $arg) {
			if($arg->required and !isset($args[$pos])) {
			    return;
			}
			if(isset($args[$pos])) {
				if($arg->vararg) {
					$value = array_slice($args, $pos);
					$pos   += count($value);
				} else {
					$value = $args[$pos];
					$pos ++;
				}
			} else {
				$value = $arg->defaultValue;
			}
			$this->parsedNamedArgs[$arg->name] = $value;
		}
	}

	function addFlag($name, $options = array(), $callback = null) {
		$flag = new Flag($name, $options, $callback);
		foreach($flag->aliases as $alias) {
			$this->flags[$alias] = $flag;
		}

		return $this;
	}

	function addFlagVar($name, &$var, $options = array()) {
		$options["var"] =& $var;

		return $this->addFlag($name, $options);
	}

	function addArgument($name, $options = array()) {
		$arg          = new Argument($name, $options);
		$this->args[] = $arg;

		return $this;
	}

	function args() {
		return $this->parsedArgs;
	}

	function count() {
		return count($this->args());
	}

	function get($name) {
		return $this->flag($name) ? : $this->arg($name);
	}

	function arg($pos) {
		if(array_key_exists($pos, $this->parsedNamedArgs)) {
			return $this->parsedNamedArgs[$pos];
		}
		if(array_key_exists($pos, $this->parsedArgs)) {
			return $this->parsedArgs[$pos];
		}
	}

	function flag($name) {
		if(array_key_exists($name, $this->parsedFlags)) {
			return $this->parsedFlags[$name];
		}
	}

	function slice($start, $length = null) {
		return array_slice($this->parsedArgs, $start, $length);
	}

	function usage() {
		$flags  = join(' ', array_unique(array_values($this->flags)));
		$args   = join(' ', $this->args);
		$script = $this->name ? : 'php ' . basename($_SERVER["SCRIPT_NAME"]);
		$usage  = "Usage: $script $flags $args";
		if($this->examples) {
			$usage .= "\n\nExamples\n\n" . join("\n", $this->examples);
		}
		if($this->description) {
			$usage .= "\n\n{$this->description}";
		}

		return $usage;
	}

	function longUsage() {
		$long  = '';
		$short = $this->usage();
		$flags = join("\n", array_map(
			function($flag) {
				$aliases = $flag->aliases;
				array_multisort(array_map('strlen', $flag->aliases), $aliases);
				$desc = "  " . join(', ', $aliases);
				if($flag->hasValue) {
					$desc .= " <{$flag->name}>";
				}
				if($flag->defaultValue !== null) {
					$desc .= sprintf(' (default: %s)', var_export($flag->defaultValue, true));
				}
				if($flag->help) {
					$desc .= ": {$flag->help}";
				}

				return $desc;
			},
			array_unique($this->flags)
		));
		$args  = join("\n", array_map(
			function($arg) {
				$desc = "  {$arg->name}" . ($arg->required ? " (required)" : '');
				if($arg->help) {
					$desc .= ": {$arg->help}";
				}

				return $desc;
			},
			$this->args
		));
		$long  .= $short;
		if($this->args) {
			$long .= "\n\nArguments:\n\n$args";
		}
		if($this->flags) {
			$long .= "\n\nFlags:\n\n$flags";
		}

		return $long;
	}

	function offsetGet($offset) {
		return $this->get($offset);
	}

	function offsetExists($offset) {
		return null !== $this->get($offset);
	}

	function offsetSet($offset, $value) {
	}

	function offsetUnset($offset) {
	}
}

Class CSV {

	/**
	 * Read
	 * @param $filename
	 * @return array
	 */
	public static function read($filename) {
		if(!file_exists($filename)) {
			return array();
		}
		$file_handle = fopen($filename, 'r');
		$array       = array();
		while(!feof($file_handle)) {
			$array[] = fgetcsv($file_handle, 1024);
		}
		fclose($file_handle);

		return $array;
	}

	/**
	 * Generate
	 * @param $data
	 * @param string $delimiter
	 * @param string $enclosure
	 * @return string
	 */
	public static function generate($data, $delimiter = ',', $enclosure = '"') {
		$handle = fopen('php://temp', 'r+');
		foreach($data as $line) {
			fputcsv($handle, $line, $delimiter, $enclosure);
		}
		$contents = '';
		rewind($handle);
		while(!feof($handle)) {
			$contents .= fread($handle, 8192);
		}
		fclose($handle);

		return $contents;
	}

	/**
	 * Write
	 * @param $filename
	 * @param $data
	 * @param string $delimiter
	 * @param string $enclosure
	 */
	public static function write($filename, $data, $delimiter = ',', $enclosure = '"') {
		$csv = self::generate($data, $delimiter, $enclosure);
		file_put_contents($filename, $csv);
	}
}

// Arguments

array_shift($_SERVER['argv']);
$_REQUEST = new Argv();
$_REQUEST->addFlag("exploits", ["alias" => "-e", "default" => false]);
$_REQUEST->addFlag("help", ["alias" => array("?", "-h"), "default" => false]);
$_REQUEST->addFlag("log", ["alias" => "-l", "default" => false]);
$_REQUEST->addFlag("scan", ["alias" => "-s", "default" => false]);
$_REQUEST->addArgument("path", ["var_args" => true, "default" => "", 'help' => 'Define the path to scan']);
$_REQUEST->addArgument("functions", ["var_args" => true, "default" => "", 'help' => 'Set some specific functions to search [ex. func1, func2, ...]']);
$_REQUEST->parse($_SERVER['argv']);

/*
 * @BEGIN Scanner
 */

if(isset($_REQUEST['help'])) {
	Console::helper();
}

Console::display("Start scanning..." . PHP_EOL, 'green');

if(isset($_REQUEST['scan']) && $_REQUEST['scan']) {
	$_REQUEST['scan'] = true;
} else {
	$_REQUEST['scan'] = false;
}

if(isset($_REQUEST['log']) && $_REQUEST['log'] && isset($_REQUEST['scan']) && $_REQUEST['scan']) {
	unset($_REQUEST['log']);
}

if(isset($_REQUEST['exploits']) && $_REQUEST['exploits']) {
	$_REQUEST['exploits'] = true;
} else {
	$_REQUEST['exploits'] = false;
}

if(!empty($_REQUEST->arg(0))) {
	$_path = trim($_REQUEST->arg(0));
	if(file_exists(realpath($_path))) {
		$path = realpath($_path);
	} else {
		$_FUNCTIONS = trim($_REQUEST->arg(0), '"');
		$_FUNCTIONS = trim($_FUNCTIONS);
		$_FUNCTIONS = @explode(',', $_FUNCTIONS);
	    if(!empty($_FUNCTIONS) && count($_FUNCTIONS) > 0){
		    Console::write("Functions to search: " . implode(', ', $_FUNCTIONS) . PHP_EOL);
		} else {
		    Console::write("Path: " . $_REQUEST['path'] . PHP_EOL, 'red');
		    Console::write("Path not found" . PHP_EOL, 'red');
	    }
	}
}

if(!is_dir($path)) {
	$path = pathinfo($path, PATHINFO_DIRNAME);
}

$_WHITELIST = CSV::read(__PATH_WHITELIST__);

@unlink(__PATH_LOGS__);

Console::write("Scan date: " . date("d-m-Y H:i:s") . PHP_EOL);
Console::write("Scanning $path" . PHP_EOL2);

if(empty($_FUNCTIONS) && !empty($_REQUEST->arg(1))) {
	$_FUNCTIONS = trim($_REQUEST->arg(1), '"');
	$_FUNCTIONS = trim($_FUNCTIONS);
	$_FUNCTIONS = @explode(',', $_FUNCTIONS);
	if(!empty($_FUNCTIONS) && count($_FUNCTIONS) > 0) {
		Console::write("Functions to search: " . implode(', ', $_FUNCTIONS) . PHP_EOL);
	} else {
		Console::write("Path: " . $_REQUEST['path'] . PHP_EOL, 'red');
		Console::write("Path not found" . PHP_EOL, 'red');
	}
}

// Malware Definitions
if(!$_REQUEST['exploits'] && !empty($_FUNCTIONS)) {
	$_FUNCTIONS = array(
		"il_exec",
		"shell_exec",
		"eval",
		"system",
		"create_function",/*
        "str_rot13",
		"exec",
		"assert",
		"syslog",
		"passthru",
		"dl",
		"define_syslog_variables",
		"debugger_off",
		"debugger_on",
		"stream_select",
		"parse_ini_file",
		"show_source",
		"symlink",*/
		"popen",
		"posix_kill",
		"posix_getpwuid",
		"posix_mkfifo",
		"posix_setpgid",
		"posix_setsid",
		"posix_setuid",
		"posix_uname",
		"proc_close",
		"proc_get_status",
		"proc_nice",
		"proc_open",
		"proc_terminate",/*
		"ini_alter",
		"ini_get_all",
		"ini_restore",
		"parse_ini_file",
		"inject_code",
		"apache_child_terminate",
		"apache_setenv",
		"apache_note",
		"define_syslog_variables",
		"escapeshellarg",
		"escapeshellcmd",
		"ob_start",*/
	);
} else if($_REQUEST['exploits']){
	Console::write("Exploits mode enabled" . PHP_EOL);
}

$_EXPLOITS = array(
	"dollar_eval"          => "/\\\$eval/i",
	"eval_chr"             => "/chr[\s\r\n]*\([\s\r\n]*101[\s\r\n]*\)[\s\r\n]*\.[\s\r\n]*chr[\s\r\n]*\([\s\r\n]*118[\s\r\n]*\)[\s\r\n]*\.[\s\r\n]*chr[\s\r\n]*\([\s\r\n]*97[\s\r\n]*\)[\s\r\n]*\.[\s\r\n]*chr[\s\r\n]*\([\s\r\n]*108[\s\r\n]*\)/i",
	//"eval_preg"               => "/(preg_replace(_callback)?|mb_ereg_replace|preg_filter)[\s\r\n]*\(.+(\/|\\x2f)(e|\\x65)['\"]/i",
	"eval_base64"          => "/eval[\s\r\n]*\([\s\r\n]*base64_decode[\s\r\n]*\(/i",
	"align"                => "/(\\\$\w+=[^;]*)*;\\\$\w+=@?\\\$\w+\(/i",
	"b374k"                => "/'ev'\.'al'\.'\(\"\?>/i",	// b374k shell
	"weevely3"             => "/\\\$\w=\\\$[a-zA-Z]\('',\\\$\w\);\\\$\w\(\);/i",	// weevely3 launcher
	"c99_launcher"         => "/;\\\$\w+\(\\\$\w+(,\s?\\\$\w+)+\);/i",	// http://bartblaze.blogspot.fr/2015/03/c99shell-not-dead.html
	"too_many_chr"         => "/(chr\([\d]+\)\.){8}/i",	// concatenation of more than eight `chr()`
	"concat"               => "/(\\\$[\w\[\]\'\\\"]+\\.[\n\r]*){10}/i",
	// concatenation of vars array
	//"conact_vars"             => "/(\\\$[^\n\r]+\\.){5}/i",	// concatenation of more than 5 words
	//"concat_vars_with_spaces" => "/(\\\$[^\\n\\r]+\\. ){5}/i",	// concatenation of more than 5 words, with spaces
	"concat_vars_array"    => "/(\\\$([a-zA-Z0-9]+)\{([0-9]+)\}[\s\r\n]*\.[\s\r\n]*){5}/i",	// concatenation of more than 5 words, with spaces
	"var_as_func"          => "/\\\$_(GET|POST|COOKIE|REQUEST|SERVER)[\s\r\n]*\[[^\]]+\][\s\r\n]*\(/i",
	"global_var_string"    => "/\\\$\{[\s\r\n]*\\\"_(GET|POST|COOKIE|REQUEST|SERVER)\\\"[\s\r\n]*\}/i",
	"extract_global"       => "/extract\([\s\r\n]*\\\$_(GET|POST|COOKIE|REQUEST|SERVER)/i",
	"escaped_path"         => "/(\\x[0-9abcdef]{2}[a-z0-9.-\/]{1,4}){4,}/i",
	"include_icon"         => "/include\(?[\s\r\n]*(\\\"|\')(.*?)(\.|\\056\\046\\2E)(\i|\\\\151|\\x69|\\105)(c|\\143\\099\\x63)(o|\\157\\111|\\x6f)(\\\"|\')\)?/mi", // Icon inclusion
	"backdoor_code"        => "/eva1fYlbakBcVSir/i",
	"infected_comment"     => "/\/\*[a-z0-9]{5}\*\//i",	// usually used to detect if a file is infected yet
	"hex_char"             => "/\\[Xx](5[Ff])/i",
	"hacked_by"            => "/hacked/i",
	"killall"              => "/killall[\s\r\n]*\-9/i",
	"download_remote_code" => "/echo\s+file_get_contents[\s\r\n]*\([\s\r\n]*base64_url_decode[\s\r\n]*\([\s\r\n]*@*\\\$_(GET|POST|SERVER|COOKIE|REQUEST)/i",
	"globals_concat"       => "/\\\$GLOBALS\[[\s\r\n]*\\\$GLOBALS['[a-z0-9]{4,}'\]/i",
	"globals_assign"       => "/\\\$GLOBALS\['[a-z0-9]{5,}'\][\s\r\n]*=[\s\r\n]*\\\$[a-z]+\d+\[\d+\]\.\\\$[a-z]+\d+\[\d+\]\.\\\$[a-z]+\d+\[\d+\]\.\\\$[a-z]+\d+\[\d+\]\./i",
	/*"php_long"                => "/^.*<\?php.{800,}\?>.*$/i",*/
	//"base64_long"             => "/['\"][A-Za-z0-9+\/]{260,}={0,3}['\"]/",
	"clever_include"       => "/include[\s\r\n]*\([\s\r\n]*[^\.]+\.(png|jpe?g|gif|bmp)/i",
	"basedir_bypass"       => "/curl_init[\s\r\n]*\([\s\r\n]*[\"']file:\/\//i",
	"basedir_bypass2"      => "/file\:file\:\/\//i",	// https://www.intelligentexploit.com/view-details.html?id=8719
	"non_printable"        => "/(function|return|base64_decode).{,256}[^\\x00-\\x1F\\x7F-\\xFF]{3}/i",
	"double_var"           => "/\\\${[\s\r\n]*\\\${/i",
	"double_var2"          => "/\${\$[0-9a-zA-z]+}/i",
	"global_save"          => "/\[\s\r\n]*=[\s\r\n]*\\\$GLOBALS[\s\r\n]*\;[\s\r\n]*\\\$[\s\r\n]*\{/i",
	"hex_var"              => "/\\\$\{[\s\r\n]*\\\"\\\\x/i",	// check for ${"\xFF"}, IonCube use this method ${"\x
	"register_function"    => "/register_[a-z]+_function[\s\r\n]*\([\s\r\n]*['\\\"][\s\r\n]*(eval|assert|passthru|exec|include|system|shell_exec|`)/i",	// https://github.com/nbs-system/php-malware-finder/issues/41
	"safemode_bypass"      => "/\\x00\/\.\.\/|LD_PRELOAD/i",
	"ioncube_loader"       => "/IonCube\_loader/i"
);

if($_REQUEST['scan']) {
	Console::write("Scan mode enabled" . PHP_EOL);
}

Console::write("Mapping files..." . PHP_EOL);

// Mapping
$directory = new \RecursiveDirectoryIterator($path);
$files     = new \RecursiveIteratorIterator($directory);
$iterator  = new CallbackFilterIterator($files, function($cur, $key, $iter) {
	return $cur->isFile();
});

$files_count = iterator_count($iterator);
Console::write("Found " . $files_count . " files" . PHP_EOL2);
Console::write("Checking files..." . PHP_EOL2);
Console::progress(0, $files_count);

// Scanning
foreach($iterator as $info) {

	Console::progress($summary_scanned, $files_count);

	$filename = $info->getFilename();
	$pathname = $info->getPathname();

	// caso in cui ci sono file favicon_[caratteri a caso].ico
	$is_favicon = (((strpos($filename, 'favicon_') === 0) && (substr($filename, - 4) === '.ico') && (strlen($filename) > 12)) || preg_match('/^\.[\w]+\.ico/i', trim($filename)));
	if((in_array(substr($filename, - 4), array('.php', 'php4', 'php5', 'php7'))
	    && (!file_exists(__PATH_QUARANTINE__) || strpos(realpath($pathname), realpath(__PATH_QUARANTINE__)) === false)
		   /*&& (strpos($filename, '-') === FALSE)*/)
	   || $is_favicon) {

		$found         = false;
		$pattern_found = array();

		$fc          = file_get_contents($pathname);
		$fc_filtered = preg_replace("/<\?php(.*?)(?!\B\"[^\"]*)\?>(?![^\"]*\"\B)/si", "$1", $fc); // Only php code
		$fc_filtered = preg_replace("/\/\*.*?\*\/|\/\/.*?\n|\#.*?\n/i", "", $fc_filtered); // Remove comments
		$fc_filtered = preg_replace("/(\\'|\\\")[\s\r\n]*\.[\s\r\n]*('|\")/i", "", $fc_filtered); // Remove "ev"."al"
		$fc_filtered = preg_replace("/([\s]+)/i", " ", $fc_filtered); // Remove multiple spaces

		// Convert hex
		$fc_filtered = preg_replace_callback('/\\\\x[A-Fa-f0-9]{2}/i', function($match) {
			return @hex2bin(str_replace('\\x', '', $match));
		}, $fc_filtered);

		// Convert dec and oct
		$fc_filtered = preg_replace_callback('/\\\\[0-9]{3}/i', function($match) {
			return chr(intval($match));
		}, $fc_filtered);

		// Scan exploits
		foreach($_EXPLOITS as $key => $pattern) {
			if(@preg_match($pattern, $fc, $match, PREG_OFFSET_CAPTURE) ||
			   @preg_match($pattern, $fc_filtered, $match, PREG_OFFSET_CAPTURE)) {
				$found                                               = true;
				$lineNumber                                          = count(explode("\n", substr($fc, 0, $match[0][1])));
				$pattern_found[$key . " [line " . $lineNumber . "]"] = $pattern;
			}
		}

		// Scan php commands
		foreach($_FUNCTIONS as $pattern) {
			$pattern       = preg_quote(trim($pattern));
			$regex_pattern = "/(" . $pattern . ")[\s\r\n]*\(/i";
			if(@preg_match($regex_pattern, $fc_filtered, $match, PREG_OFFSET_CAPTURE)) {
				$found                                                   = true;
				$lineNumber                                              = count(explode("\n", substr($fc, 0, $match[0][1])));
				$pattern_found[$pattern . " [line " . $lineNumber . "]"] = $regex_pattern;
			}

			if($found == false) {
				$regex_pattern = "/(" . $pattern . ")[\s\r\n]*\(/i";
				if(@preg_match($regex_pattern, $fc, $match, PREG_OFFSET_CAPTURE)) {
					$found                                                   = true;
					$lineNumber                                              = count(explode("\n", substr($fc, 0, $match[0][1])));
					$pattern_found[$pattern . " [line " . $lineNumber . "]"] = $regex_pattern;
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
		unset($fc_filtered);

		$pattern_found = array_unique($pattern_found);

		$in_whitelist = 0;
		foreach($_WHITELIST as $item) {
			foreach($pattern_found as $key => $pattern) {
				$exploit    = preg_replace("/^(\S+) \[line [0-9]+\]/i", "$1", $key);
				$lineNumber = preg_replace("/^\S+ \[line ([0-9]+)\]/i", "$1", $key);
				if(realpath($pathname) == realpath($item[0]) && $exploit == $item[1] && $lineNumber == $item[2]) {
					$in_whitelist ++;
				}
			}
		}

		if($is_favicon) {
			$pattern_found['infected_icon'] = '';
		}

		if(realpath($pathname) != realpath(__FILE__) && ($is_favicon || $found) && ($in_whitelist === 0 || $in_whitelist != count($pattern_found))) {
			$summary_detected ++;
			// Scan mode only
			if($_REQUEST['scan']) {
				$summary_ignored[] = $pathname;
				continue;
				// Scan with code check
			} else {
				$_WHILE = true;
				Console::display(PHP_EOL2);
				Console::write(PHP_EOL);
				Console::write("PROBABLE MALWARE FOUND!", 'red');
				Console::write(PHP_EOL . "$pathname", 'yellow');
				while($_WHILE) {
					$fc = file_get_contents($pathname);
					Console::write(PHP_EOL2);
					Console::write("=========================================== SOURCE ===========================================", 'white', 'red');
					Console::write(PHP_EOL2);
					Console::code(trim($fc));
					Console::write(PHP_EOL2);
					Console::write("==============================================================================================", 'white', 'red');
					Console::write(PHP_EOL2);
					Console::write("File path: " . $pathname, 'yellow');
					Console::write("\n");
					Console::write("Exploit: " . implode(", ", array_keys($pattern_found)), 'red');
					Console::display(PHP_EOL2);
					Console::display("OPTIONS:" . PHP_EOL2);
					Console::display("    [1] Delete file" . PHP_EOL);
					Console::display("    [2] Move to quarantine" . PHP_EOL);
					Console::display("    [3] Remove evil code" . PHP_EOL);
					Console::display("    [4] Edit with vim" . PHP_EOL);
					Console::display("    [5] Edit with nano" . PHP_EOL);
					Console::display("    [6] Add to whitelist" . PHP_EOL);
					Console::display("    [-] Ignore" . PHP_EOL2);
					$confirmation = Console::read("What is your choice? ", "purple");
					Console::display(PHP_EOL);

					// Remove file
					if(in_array($confirmation, array('1'))) {
						unlink($pathname);
						$summary_removed ++;
						Console::write("File '$pathname' removed!" . PHP_EOL2, 'green');
						$_WHILE = false;
						// Move to quarantine
					} else if(in_array($confirmation, array('2'))) {
						$quarantine = __PATH_QUARANTINE__ . str_replace(realpath(__DIR__), '', $pathname);

						if(!is_dir(dirname($quarantine))) {
							mkdir(dirname($quarantine), 0755, true);
						}
						rename($pathname, $quarantine);
						$summary_quarantine[] = $quarantine;
						Console::write("File '$pathname' moved to quarantine!" . PHP_EOL2, 'green');
						$_WHILE = false;
						// Remove evil code
					} else if(in_array($confirmation, array('3')) && count($pattern_found) > 0) {
						foreach($pattern_found as $pattern) {
							$contents = preg_replace("/\/\*.*?\*\/|\/\/.*?\n|\#.*?\n/si", "", $fc); // Remove comments
							$contents = preg_replace("/('|\")[\s\r\n]*\.[\s\r\n]*('|\")/i", "", $contents); // Remove "ev"."al"
							$pattern  = str_replace('\/', '__$L4$H__', $pattern);
							$pattern  = preg_replace('#.*/(.*?)/.*#si', "$1", $pattern);
							$pattern  = trim(str_replace('__$L4$H__', '\/', $pattern));
							$fc       = preg_replace('/<\?php.*' . $pattern . '(.*?)((?!\B"[^"]*)\?>(?![^"]*"\B)|$)/si', "", $contents);
						}
						Console::write(PHP_EOL);
						Console::write("========================================== SANITIZED ==========================================", 'black', 'green');
						Console::write(PHP_EOL2);
						Console::code(trim($fc));
						Console::write(PHP_EOL2);
						Console::write("===============================================================================================", 'black', 'green');
						Console::display(PHP_EOL2);
						Console::display("File sanitized, now you must verify if has been fixed correctly." . PHP_EOL2, "yellow");
						$confirm2 = Console::read("Confirm and save [y|N]? ", "purple");
						Console::display(PHP_EOL);
						if($confirm2 == 'y') {
							Console::write("File '$pathname' sanitized!" . PHP_EOL2, 'green');
							file_put_contents($pathname, $fc);
							$summary_removed ++;
							$_WHILE = false;
						} else {
							$summary_ignored[] = $pathname;
						}
						// Edit with vim
					} else if(in_array($confirmation, array('4'))) {
						$descriptors = array(
							array('file', '/dev/tty', 'r'),
							array('file', '/dev/tty', 'w'),
							array('file', '/dev/tty', 'w')
						);
						$process     = proc_open("vim '$pathname'", $descriptors, $pipes);
						while(true) {
							if(proc_get_status($process)['running'] == false) {
								break;
							}
						}
						$summary_edited[] = $pathname;
						Console::write("File '$pathname' edited with vim!" . PHP_EOL2, 'green');
						$summary_removed ++;
						// Edit with nano
					} else if(in_array($confirmation, array('5'))) {
						$descriptors = array(
							array('file', '/dev/tty', 'r'),
							array('file', '/dev/tty', 'w'),
							array('file', '/dev/tty', 'w')
						);
						$process     = proc_open("nano -c '$pathname'", $descriptors, $pipes);
						while(true) {
							if(proc_get_status($process)['running'] == false) {
								break;
							}
						}
						$summary_edited[] = $pathname;
						Console::write("File '$pathname' edited with nano!" . PHP_EOL2, 'green');
						$summary_removed ++;
						// Add to whitelist
					} else if(in_array($confirmation, array('6'))) {
						foreach($pattern_found as $key => $pattern) {
							$exploit      = preg_replace("/^(\S+) \[line [0-9]+\]/i", "$1", $key);
							$lineNumber   = preg_replace("/^\S+ \[line ([0-9]+)\]/i", "$1", $key);
							$_WHITELIST[] = array(realpath($pathname), $exploit, $lineNumber);
						}
						$_WHITELIST = array_map("unserialize", array_unique(array_map("serialize", $_WHITELIST)));
						CSV::write(__PATH_WHITELIST__, $_WHITELIST);
						$summary_whitelist[] = $pathname;
						Console::write("Exploits of file '$pathname' added to whitelist!" . PHP_EOL2, 'green');
						$_WHILE = false;
						// None
					} else {
						Console::write("File '$pathname' skipped!" . PHP_EOL2, 'green');
						$summary_ignored[] = $pathname;
						$_WHILE            = false;
					}

					Console::write(PHP_EOL);
				}
				unset($fc);
			}
		}
	}
	$summary_scanned ++;
}

Console::write(PHP_EOL2);
Console::write("Scan finished!", 'green');
Console::write(PHP_EOL3);

// Statistics
Console::write("                SUMMARY                ", 'black', 'cyan');
Console::write(PHP_EOL2);
Console::write("Files scanned: " . $summary_scanned . PHP_EOL);
if(!$_REQUEST['scan']) {
	$summary_ignored = array_unique($summary_ignored);
	$summary_edited  = array_unique($summary_edited);
	Console::write("Files edited: " . count($summary_edited) . PHP_EOL);
	Console::write("Files quarantined: " . count($summary_quarantine) . PHP_EOL);
	Console::write("Files whitelisted: " . count($summary_whitelist) . PHP_EOL);
	Console::write("Files ignored: " . count($summary_ignored) . PHP_EOL2);
}
Console::write("Malware detected: " . $summary_detected . PHP_EOL);
if(!$_REQUEST['scan']) {
	Console::write("Malware removed: " . $summary_removed . PHP_EOL);
}

if($_REQUEST['scan']) {
	Console::write(PHP_EOL . "Files infected: '" . __PATH_LOGS_INFECTED__ . "'" . PHP_EOL, 'red');
	file_put_contents(__PATH_LOGS_INFECTED__, "Log date: " . date("d-m-Y H:i:s") . PHP_EOL . implode(PHP_EOL, $summary_ignored));
	Console::write(PHP_EOL2);
} else {
	if(count($summary_edited) > 0) {
		Console::write(PHP_EOL . "Files edited:" . PHP_EOL, 'green');
		foreach($summary_edited as $un) {
			Console::write($un . PHP_EOL);
		}
	}
	if(count($summary_quarantine) > 0) {
		Console::write(PHP_EOL . "Files quarantined:" . PHP_EOL, 'yellow');
		foreach($summary_ignored as $un) {
			Console::write($un . PHP_EOL);
		}
	}
	if(count($summary_whitelist) > 0) {
		Console::write(PHP_EOL . "Files whitelisted:" . PHP_EOL, 'cyan');
		foreach($summary_whitelist as $un) {
			Console::write($un . PHP_EOL);
		}
	}
	if(count($summary_ignored) > 0) {
		Console::write(PHP_EOL . "Files ignored:" . PHP_EOL, 'cyan');
		foreach($summary_ignored as $un) {
			Console::write($un . PHP_EOL);
		}
	}
	Console::write(PHP_EOL2);
}
