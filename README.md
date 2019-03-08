# ![amwscan](amwscan.png)

# AMWSCAN - PHP Antimalware Scanner

**Version:** 0.4.0.50 beta

**Github:** https://github.com/marcocesarato/PHP-Antimalware-Scanner

**Author:** Marco Cesarato

## PHP Malware Scanner Free Tool

This package, written in php, can scan PHP files and analyze your project for find malicious code inside it.
It provides a text terminal console interface to scan files in a given directory and find PHP code files the seem to contain malicious code.
The package can also scan the PHP files without outputting anything to the terminal console. In that case the results are stored in a log file.
This scanner can work on your own php projects and on a lot of others platform.
Use this command `php -d disable_functions` for run the program without issues.

## Requirements

- php 5+

## Install
 
### Release

You can use one of this method for install the scanner downloading it from github or directly from console.

#### Download

Go on GitHub page and press on Releases tab or download the raw file from:
https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner

#### Console

1. Run this command from console (scanner will be download on your current directory): 

   `wget https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner --no-check-certificate`

2. Run the scanner:

   `php scanner ./dir-to-scan` ...

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

## Distribute

For compile `/src/` folder to single file `/dist/scanner` you need to do this:

1. Install composer requirements:
   `composer install`
2. Run distribute script *(replace 0.4.x.x with your version number)*:
   `php distribute 0.4.x.x`

## Test

For test detection of malwares you can try detect they from this collection:

https://github.com/marcocesarato/PHP-Malware-Collection

## Suggestion

If you run the scanner on a Wordpress project type *--agile* as argument for a check with less false positive.

## Usage

```		
Arguments:
<path>                       Define the path to scan (default: current directory)

Flags:
-a   --agile                 - Help to have less false positive on WordPress and others platforms
                               enabling exploits mode and removing some common exploit pattern
                               but this method could not find some specific malwares
-e   --only-exploits         - Check only exploits and not the functions,
                               this is recommended for WordPress or others platforms
-f   --only-functions        - Check only functions and not the exploits
-h   --help                  - Show the available flags and arguments
-l   --log                   - Write a log file 'scanner.log' with all the operations done
-s   --scan                  - Scan only mode without check and remove malware. It also write
                               all malware paths found to 'scanner_infected.log' file
-u   --update                - Update scanner to last version
-v   --version               - Get version number
                             
     --exploits="..."        Filter exploits
     --functions="..."       Define functions to search
     --whitelist-only-path   Check on whitelist only file path and not line number
     
Notes: For open files with nano or vim run the scripts with "-d disable_functions=''"
       examples: php -d disable_functions='' scanner ./mywebsite/http/ --log --agile --only-exploits
                 php -d disable_functions='' scanner --agile --only-exploits
                 php -d disable_functions='' scanner --exploits="double_var2" --functions="eval, str_replace"
                 
Usage: php scanner [--agile|-a] [--help|-h] [--log|-l] [--scan|-s] [--version|-v] [--update|-u] [--exploits <exploits>] [--functions <functions>] [--only-exploits|-e] [--only-functions|-f] [--whitelist-only-path] [<path>]
```

## Screenshots

![Screen 1](screenshots/screenshot_1.png)![Screen 2](screenshots/screenshot_2.png)![Screen 3](screenshots/screenshot_3.png)
