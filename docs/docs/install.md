---
sidebar_position: 3
---

# Install

## Release

You can use one of these methods to install the scanner downloading it from github or directly from console.

### Download from browser

Go on GitHub page and press on Releases tab
or [download from here](https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner)

### Download from console

1. Run this command from console (scanner will be download on your current directory):

   ```shell
   wget https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner --no-check-certificate
   ```
2. Run the scanner:

   ```shell
   php scanner ./dir-to-scan -l ...
   ```

3. <span class="optional">Optional</span> Install as bin command (Unix Bash)

   Run this command:

    ```shell
    wget https://raw.githubusercontent.com/marcocesarato/PHP-Antimalware-Scanner/master/dist/scanner --no-check-certificate -O /usr/bin/awscan.phar && \
    printf "#!/bin/bash\nphp /usr/bin/awscan.phar \$@" > /usr/bin/awscan && \
    chmod u+x,g+x /usr/bin/awscan.phar && \
    chmod u+x,g+x /usr/bin/awscan && \
    export PATH=$PATH":/usr/bin"
    ```

   Now you can run the scanner simply with this command:

   ```shell
   awscan ./dir-to-scan -l # ...
   ```

## Source

#### Download

Click on GitHub page "Clone or download"
or [download from here](https://codeload.github.com/marcocesarato/PHP-Antimalware-Scanner/zip/master)

#### Composer

1. Install composer *(if not installed)*
2. Install the library using composer:

   ```shell
   composer require marcocesarato/amwscan
   ```
3. Go on `vendor/marcocesarato/amwscan/` to have the source

#### Git

1. Install git
2. Copy the command and link from below in your terminal:

    ```shell
   git clone https://github.com/marcocesarato/PHP-Antimalware-Scanner
   ```
3. Change directories to the new `~/PHP-Antimalware-Scanner` directory:

   ```shell
   cd ~/PHP-Antimalware-Scanner/
   ```
4. To ensure that your master branch is up-to-date, use the pull command:

    ```shell
   git pull https://github.com/marcocesarato/PHP-Antimalware-Scanner
   ```

## Docker

1. Download the source
2. Build command

   ```shell
   docker build --tag amwscan-docker .
   ```
3. Run command

   ```shell
   docker run -it --rm amwscan-docker bash
   ```
