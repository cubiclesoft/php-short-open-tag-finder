Short Open Tag Finder for PHP
=============================

An intelligent command-line tool to find software references to short open tags with an optional "ask to replace" mode.  Compatible with PHP 5.4 and later, including PHP 8.

PHP 7.4 deprecates short open tags and PHP 8 and later removes short open tag support altogether.

If you are scrambling around looking for a tool that aids in finding and replacing short open tags that also works with PHP 8 (e.g. you deployed PHP 8 without reading the changelog and therefore can't easily rollback to PHP 7) but doesn't just bulk update your files like a grep/sed solution would, then you've come to the right spot.

This tool has been successfully validated against 9.45 million lines of PHP code spanning 31,000+ files across multiple systems with a variety of mixed short open and regular tags in various configurations.  This tool utilizes the PHP `token_get_all()` function to correctly parse and make modifications against each document instead of incorrectly using regular expressions.  The tool is relatively short (~275 lines) and reasonably well-commented.

Features
--------

* Finds files that use the PHP short open tag `<?` and outputs the line number, the file, and the line itself.
* When the `-ask` option is used, shows proposal replacement lines with normalized `<?php` tags and asks whether or not to update the file with the list of changes.
* Skips `<?=`, `<?xml`, and `<?mso-`.
* Scans alphanumerically!  (English only)
* Has a liberal open source license.  MIT or LGPL, your choice.
* Designed for relatively painless conversion of your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

Getting Started
---------------

Download or clone the latest software release.

Run it to get instructions/syntax:

```
php find.php
```

Which shows something like:

```
Short open tag finder for PHP
Purpose:  Finds short open tag references in PHP files.
          Works under PHP 8 w/ pull request #3975 (https://github.com/php/php-src/pull/3975).

Syntax:  find.php [options] directory
Options:
    -ask   Ask about the references that are found and modify the file only if the suggested changes are accepted.
    -ext   Additional case-insensitive file extension to look for.  Default is 'php', 'php3', 'php4', 'php5', 'php7', and 'phtml'.

Examples:
    php find.php /var/www
    php find.php -ext phpapp -ext html /var/www
    php find.php -ask /var/www
```

This tool, by default, will not make any changes unless directed to do so via the `-ask` option.  When the `-ask` option is used, it isn't a blunt instrument that indiscriminately changes files like some tools and the tool defaults to the safest option of not making any changes even in `-ask` mode.  Still, the `-ask` option is faster than manually editing each individual file yet allows for fairly refined control over each file's changes.  It'll take a couple of hours if you have a few thousand files to modify, but you remain in total control of each set of changes.

If you encounter a complex parsing test case that produces false positives/negatives with this tool in the wild, please submit an issue/pull request.

Additional Information
----------------------

For the curious, here's how the removal of short open tag support went down:

https://wiki.php.net/rfc/deprecate_php_short_tags

Notably, almost everyone who said "No" has `php-src.git` rights (i.e. aka "karma", which allows them to commit changes that modify the core of PHP).  My ballpark estimate is that there's about a 50-50 split among the votes of those users who have `php-src.git` rights.  Also of note, Zeev Suraski and Rasmus Lerdorf, two of the original designers of PHP and current members of the [PHP Group](https://www.php.net/credits.php) voted "No" for the change.
