DMARC-reports-analyzer
=================

A PHP application for analyzing DMARC aggregation reports using the [Nette](https://nette.org) framework.

* Import reports from IMAP mailbox.
* View reports summary data per domain, IP address...
* Filter the data by date, results, IP address...
* Easily see the source of your DMARC failures.

The application uses similar data structure to that created by [dmarcts-report-parser.pl](https://github.com/techsneeze/dmarcts-report-parser) 
script. So it should also parse data imported by that script.

Requirements
------------

Webserver with PHP 5.6 or higher and mysql server.


Installation
------------

Download the files:

    git clone https://github.com/petr22/DMARC-reports-analyzer.git

Use Composer to download Nette framework files. If you don't have Composer yet,
download it following [the instructions](https://doc.nette.org/en/2.4/composer). 
Then in the directory with composer.json run:

    composer update

Make directories `temp/` and `log/` writable:

    chmod -R a+rw temp log

Install needed php extensions:

    php-xml php-pecl-zip php-pecl-memcache php-pdo php-json php-imap php-gd

Web Server Setup
----------------

For Apache or Nginx, setup a virtual host to point to the `www/` directory of the project.

**It is CRITICAL that whole `app/`, `log/` and `temp/` directories are not accessible directly
via a web browser. See [security warning](https://nette.org/en/security-warning).**

Configuration
----------------

Copy local configuration file for the application `./app/config/config.local.neon.sample` to `./app/config/config.local.neon` 
and modify database/login settings.

Copy configuration file for the import script `./app/import.script/import.conf.php.sample` to `./app/import.script/import.conf.php`
and modify IMAP email settings and database settings.

Usage
----------------

First you need to run the import script `./app/import.script/import.php`, which will create database tables and import data from IMAP email.
Or you can create the tables manually. Their structure is at the end of the import script.

Navigate your browser to the location of the ./www directory in the application.

Run the import script periodically.