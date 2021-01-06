<?php

/**
 * PHP Antimalware Scanner.
 *
 * @author Marco Cesarato <cesarato.developer@gmail.com>
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see https://github.com/marcocesarato/PHP-Antimalware-Scanner
 */

namespace marcocesarato\amwscan;

class Functions
{
    /**
     * Default functions definitions.
     *
     * @var array
     */
    public static $default = [
        'il_exec',
        'shell_exec',
        'eval',
        'system',
        'create_function',
        'exec',
        'assert',
        'syslog',
        'passthru',
        'define_syslog_variables',
        /*
        "dl",
        "debugger_off",
        "debugger_on",
        "parse_ini_file",
        "show_source",
        "symlink",
        "popen",
        */
        'posix_kill',
        /*
        "posix_getpwuid",
        "posix_mkfifo",
        "posix_setpgid",
        "posix_setsid",
        "posix_setuid",
        */
        'posix_uname',
        'proc_close',
        'proc_get_status',
        'proc_nice',
        'proc_open',
        'proc_terminate',
        /*
        "ini_alter",
        "ini_get_all",
        "ini_restore",
        "parse_ini_file",
        */
        'inject_code',
        'apache_child_terminate',
        'apache_note',
        'define_syslog_variables',
        /*
        "apache_setenv",
        "escapeshellarg",
        "escapeshellcmd",
        */
    ];

    /**
     * Default encoded functions definitions.
     *
     * @var array
     */
    public static $dangerous = [
        'il_exec',
        'shell_exec',
        'eval',
        'system',
        'create_function',
        'exec',
        'assert',
        'syslog',
        'passthru',
        'define_syslog_variables',
        'debugger_off',
        'debugger_on',
        'parse_ini_file',
        'show_source',
        'symlink',
        'popen',
        'posix_kill',
        'posix_getpwuid',
        'posix_mkfifo',
        'posix_setpgid',
        'posix_setsid',
        'posix_setuid',
        'posix_uname',
        'proc_close',
        'proc_get_status',
        'proc_nice',
        'proc_open',
        'proc_terminate',
        'ini_alter',
        'ini_get_all',
        'ini_restore',
        'parse_ini_file',
        'inject_code',
        'apache_child_terminate',
        'apache_setenv',
        'apache_note',
        'define_syslog_variables',
        'escapeshellarg',
        'escapeshellcmd',
        'base64_decode',
        'urldecode',
        'rawurldecode',
        'str_rot13',
        'preg_replace',
        'create_function',
    ];

    /**
     * Get all default functions to check.
     *
     * @return array
     */
    public static function getDefault()
    {
        return self::$default;
    }

    /**
     * Get all dangerous functions to check.
     *
     * @return array
     */
    public static function getDangerous()
    {
        return self::$dangerous;
    }
}
