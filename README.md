# ![amwscan](images/amwscan.png)

# AMWSCAN - PHP Antimalware Scanner

**Last Release:** 0.7.5.177

**Github:** https://github.com/marcocesarato/PHP-Antimalware-Scanner

#### If this project helped you out, please support us with a star :star:

## PHP Malware Scanner Free Tool

A tool to scan PHP files and analyze your project to find any malicious code inside it.
It provides a text terminal console interface to scan files in a given directory and find PHP code files the seem to contain malicious code.
The package can also scan the PHP files without outputting anything to the terminal console. In that case the results are stored in a log file.
This scanner can work on your own php projects and on a lot of others platform.
Use this command `php -d disable_functions` for run the program without issues.

:warning: *Remember that you will be solely responsible for any damage to your computer system or loss of data that results from such activities.
You are solely responsible for adequate protection and backup of the data before execute the scanner.*

### How to contribute

Have an idea? Found a bug? Please raise to [ISSUES](https://github.com/marcocesarato/PHP-Antimalware-Scanner/issues) or [PULL REQUEST](https://github.com/marcocesarato/PHP-Antimalware-Scanner/pulls).
Contributions are welcome and are greatly appreciated! Every little bit helps.

## :blue_book: Requirements

- php 5.5+

## :book: Install

### Release

You can use one of this method for install the scanner downloading it from github or directly from console.

#### Download

Go on GitHub page and press on Releases tab or download the raw file from:
https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner

#### Console

1. Run this command from console (scanner will be download on your current directory):

   `wget https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner --no-check-certificate`

2. Run the scanner:

   `php scanner ./dir-to-scan -a ...`

3. *(Optional)* Install as bin command (Unix Bash)

    Run this command:
    
    ```sh
    wget https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner --no-check-certificate -O /usr/bin/awscan.phar && \
    printf "#!/bin/bash\nphp /usr/bin/awscan.phar \$@" > /usr/bin/awscan && \
    chmod u+x,g+x /usr/bin/awscan.phar && \
    chmod u+x,g+x /usr/bin/awscan && \
    export PATH=$PATH":/usr/bin"
    ```
   
   Now you can run the scanner simply with this command: `awscan ./dir-to-scan -a ...`

### Source

##### Download

Click on GitHub page "Clone or download" or download from:
https://codeload.github.com/marcocesarato/PHP-Antimalware-Scanner/zip/master

##### Composer

1. Install composer
2. Type `composer require marcocesarato/amwscan`
3. Go on `vendor/marcocesarato/amwscan/` for have source
4. Enjoy

##### Git

1. Install git
2. Copy the command and link from below in your terminal:
   `git clone https://github.com/marcocesarato/PHP-Antimalware-Scanner`
3. Change directories to the new `~/PHP-Antimalware-Scanner` directory:
   `cd ~/PHP-Antimalware-Scanner/`
4. To ensure that your master branch is up-to-date, use the pull command:
   `git pull https://github.com/marcocesarato/PHP-Antimalware-Scanner`
5. Enjoy

## :hammer: Build

For compile `/src/` folder to single file `/dist/scanner` you need to do this:

1. Install composer requirements:
   `composer install`
2. Run command
   `composer build`

## :microscope: Test

For test detection of malware you can try detect they from this collection:

https://github.com/marcocesarato/PHP-Malware-Collection

## :whale: Docker

1. Build command
   `docker build --tag amwscan-docker .`
2. Run command
   `docker run -it --rm amwscan-docker bash`

## :mag_right: Scanning mode

You could find some false positive during scanning. For this you can choice the aggression level as following:

| Flags                       | :rocket:            | Description                                                                                                                                                                       |
| --------------------------- | ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| None (`default`)            | :red_circle:        | Search for all functions, exploits and malware signs without any restrictions                                                                                                     |
| `--agile` or `-a`           | :yellow_circle:     | Search for all functions, exploits with some restrictions and malware signs *(on Wordpress and others platform could find more malware and more false positive)*                  |
| `--only-signatures` or `-s` | :green_circle:      | Search only for malware signatures *(could be a good solution for Wordpress and others platform to found less false positive)*                                                    |
| `--only-exploits` or `-e`   | :orange_circle:     | Search only for exploits definitions                                                                                                                                              |
| `--only-functions` or `-f`  | :yellow_circle:     | Search only for functions (on some obfuscated code functions couldn't be detected)                                                                                                |

### Suggestions

If you are running the scanner on a Wordpress project or other popular platform use `--only-signatures` or `--agile` flag for have check with less false positive but 
this could miss some dangerous exploits like `nano`.

#### Examples:

```
php -d disable_functions='' scanner -s
php -d disable_functions='' scanner -a
```

## Detection Options

When a malware is detected you will have the following choices (except when scanner is in report scan mode `--report`):

- Delete file [`--auto-delete`]
- Move to quarantine `(move to ./scanner-quarantine)` [`--auto-quarantine`]
- Dry run evil code fixer `(fix code and confirm after a visual check)` [`--auto-clean`]
- Dry run evil line code fixer `(fix code and confirm after a visual check)` [`--auto-clean-line`]
- Open with vim `(need php -d disable_functions='')`
- Open with nano `(need php -d disable_functions='')`
- Add to whitelist `(add to ./scanner-whitelist.json)`
- Show source
- Ignore [`--auto-skip`]

## :computer: Usage

### Command line
```
Arguments:

<path>   - Define the path of the file or directory to scan

Flags:

--agile|-a                                - Help to have less false positive on WordPress and others platforms enabling
                                            exploits mode and removing some common exploit pattern
--auto-clean                              - Auto clean code (without confirmation, use with caution)
--auto-clean-line                         - Auto clean line code (without confirmation, use with caution)
--auto-delete                             - Auto delete infected (without confirmation, use with caution)
--auto-prompt <prompt>                    - Set auto prompt command .
                                            ex. --auto-prompt="delete" or --auto-prompt="1" (alias of auto-delete)
--auto-quarantine                         - Auto quarantine
--auto-skip                               - Auto skip
--auto-whitelist                          - Auto whitelist (if you sure that source isn't compromised)
--backup|-b                               - Make a backup of every touched files
--definitions-exploits                    - Get default definitions exploits list
--definitions-functions                   - Get default definitions functions lists
--definitions-list                        - Get default definitions exploit and functions list
--disable-cache|--no-cache                - Disable Cache
--disable-colors|--no-colors|--no-color   - Disable CLI colors
--disable-report|--no-report              - Disable Report
--exploits <exploits>                     - Filter exploits
--filter-paths|--filter-path <paths>      - Filter path/s, for multiple value separate with comma.
                                            Wildcards are enabled ex. /path/*/htdocs or /path/*.php
--functions <functions>                   - Define functions to search
--help|-h                                 - Check only functions and not the exploits
--ignore-paths|--ignore-path <paths>      - Ignore path/s, for multiple value separate with comma.
                                            Wildcards are enabled ex. /path/*/cache or /path/*.log
--limit <limit>                           - Set file mapping limit
--log|-l <path>                           - Write a log file on the specified file path
                                            [default: ./scanner.log]
--max-filesize <filesize>                 - Set max filesize to scan
                                            [default: -1]
--offset <offset>                         - Set file mapping offset
--only-exploits|-e                        - Check only exploits and not the functions
--only-functions|-f                       - Check only functions and not the exploits
--only-signatures|-s                      - Check only functions and not the exploits.
                                            This is recommended for WordPress or others platforms
--path-backups <path>                     - Set backups path directory.
                                            Is recommended put files outside the public document path
                                            [default: /scanner-backups/]
--path-logs <path>                        - Set quarantine log file
                                            [default: ./scanner.log]
--path-quarantine <path>                  - Set quarantine path directory.
                                            Is recommended put files outside the public document path
                                            [default: ./scanner-quarantine/]
--path-report <path>                      - Set report log file
                                            [default: ./scanner-report.html]
--path-whitelist <path>                   - Set whitelist file
                                            [default: ./scanner-whitelist.json]
--report-format <format>                  - Report format (html|txt)
--report|-r                               - Report scan only mode without check and remove malware (like --auto-skip).
                                            It also write a report with all malware paths found
--silent                                  - No output and prompt
--update|-u                               - Update to last version
--version|-v                              - Get version number
--whitelist-only-path                     - Check on whitelist only file path and not line number

Usage: amwscan [--agile|-a] [--help|-h] [--log|-l <path>] [--backup|-b] [--offset
        <offset>] [--limit <limit>] [--report|-r] [--report-format <format>]
        [--version|-v] [--update|-u] [--only-signatures|-s] [--only-exploits|-e]
        [--only-functions|-f] [--definitions-list] [--definitions-exploits]
        [--definitions-functions] [--exploits <exploits>] [--functions <functions>]
        [--whitelist-only-path] [--max-filesize <filesize>] [--silent]
        [--ignore-paths|--ignore-path <paths>] [--filter-paths|--filter-path <paths>]
        [--auto-clean] [--auto-clean-line] [--auto-delete] [--auto-quarantine]
        [--auto-skip] [--auto-whitelist] [--auto-prompt <prompt>] [--path-whitelist
        <path>] [--path-backups <path>] [--path-quarantine <path>] [--path-logs <path>]
        [--path-report <path>] [--disable-colors|--no-colors|--no-color]
        [--disable-cache|--no-cache] [--disable-report|--no-report] [<path>]

Examples:

php amwscan ./mywebsite/http/ -l -s --only-exploits
php amwscan -s --max-filesize="5MB"
php amwscan -s -logs="/user/marco/scanner.log"
php amwscan --agile --only-exploits
php amwscan --exploits="double_var2" --functions="eval, str_replace"
php amwscan --ignore-paths="/my/path/*.log,/my/path/*/cache/*"

Notes:
For open files with nano or vim run the scripts with "php -d disable_functions=''"
```

### Programmatically

On programmatically silent mode and auto skip are automatically enabled.

```php
use marcocesarato\amwscan\Scanner;

$app = new Scanner();
$report = $app->setPathScan("my/path/to/scan")
              ->enableBackups()
              ->setPathBackups("/my/path/backups")
              ->setAgileMode(true)
              ->setAutoClean(true)
              ->run();
```

##### Report Object

```php
object(stdClass) (7) {
  ["scanned"]    => int(0)
  ["detected"]   => int(0)
  ["removed"]    => array(0) {}
  ["ignored"]    => array(0) {}
  ["edited"]     => array(0) {}
  ["quarantine"] => array(0) {}
  ["whitelist"]  => array(0) {}
}
```

### Exploits and Functions List

#### Exploits

- `eval_chr`, `eval_preg`, `eval_base64`, `eval_comment`, `eval_execution`, `align`, `b374k`, `weevely3`, `c99_launcher`, `too_many_chr`, `concat`, `concat_vars_with_spaces`, `concat_vars_array`, `var_as_func`, `global_var_string`, `extract_global`, `escaped_path`, `include_icon`, `backdoor_code`, `infected_comment`, `hex_char`, `hacked_by`, `killall`, `globals_concat`, `globals_assign`, `base64_long`, `base64_inclusion`, `clever_include`, `basedir_bypass`, `basedir_bypass2`, `non_printable`, `double_var`, `double_var2`, `global_save`, `hex_var`, `register_function`, `safemode_bypass`, `ioncube_loader`, `nano`, `ninja`, `execution`, `execution2`, `execution3`, `shellshock`, `silenced_eval`, `silence_inclusion`, `ssi_exec`, `htaccess_handler`, `htaccess_type`, `file_prepend`, `iis_com`, `reversed`, `rawurlendcode_rot13`, `serialize_phpversion`, `md5_create_function`, `god_mode`, `wordpress_filter`, `password_protection_md5`, `password_protection_sha`, `custom_math`, `custom_math2`, `uncommon_function`, `download_remote_code`, `download_remote_code2`, `download_remote_code3`, `php_uname`, `etc_passwd`, `etc_shadow`, `explode_chr`

#### Functions

- `il_exec`, `shell_exec`, `eval`, `system`, `create_function`, `exec`, `assert`, `syslog`, `passthru`, `define_syslog_variables`, `posix_kill`, `posix_uname`, `proc_close`, `proc_get_status`, `proc_nice`, `proc_open`, `proc_terminate`, `inject_code`, `apache_child_terminate`, `apache_note`, `define_syslog_variables`

## :art: Screenshots

### Report

> HTML report format (default)

![Screen Report](images/screenshot_report.png)

### Interactive CLI
![Screen Full](images/screenshot_full.png)