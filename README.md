# ATICA
[![Build Status](https://travis-ci.org/iesoretania/atica.png?branch=master)](https://travis-ci.org/iesoretania/atica)

Web application for supporting Quality Management Systems.

ATICA is an open source Web application that intends to ease the work with QMS. Some of its features include:

  * Manage users and profiles.
  * Set up activities so users only see what they need to see.
  * Bring to the table an "actions calendar" so users know what they need to do.
  * Manage the document workflow, including upload, review and approval.
  * Keep document revisions under control using version tracking features.
  * Trace non-conformances and the correspondent actions.
  
## Requirements
These are the requirements for running ATICA:

  * Web server with PHP 5.3 or later. Tested on Apache2.
  * Composer, Dependency Manager for PHP.
  * MySQL, PostgreSQL, MSSQL, or Oracle database access.
  * For the client: IE 8.0+, Firefox 4+ or Webkit-based browser (Safari 3+, Chrome 10+, iOS 3+, Android Browser 2.2+), etc.

## Install
Follow these easy steps:

  * Unpack the source code into a folder which is accesible by the web server.
  * Install dependencies by issuing a `composer install` in the project folder.
  * Optional: for security reasons, please use the *public/* folder as the DocumentRoot.
  * Create a database schema and a database user which has all privileges on that schema.
  * Open a browser.
  * Run the setup wizard (WIP).

## Acknowledgments
This application uses the following libraries and frameworks:

  * [Slim], a PHP framework.
  * [Twig], a template engine.
  * [Idiorm & Paris], a database toolkit.
  * [Bootstrap], a collection of HTML, CSS and JS design templates.
  * [TinyMCE], a WYSIWYG HTML editor.

Special care has been taken to ensure that their respectives licenses are being respected. Should you find any uncompliance, don't hesitate
in opening an issue ticket.

## License
This application is licensed under [AGPL version 3].

[TinyMCE]: http://www.tinymce.com/index.php
[Slim]: http://www.slimframework.com/
[Idiorm & Paris]: http://j4mie.github.io/idiormandparis/
[Bootstrap]: http://getbootstrap.com/
[Twig]: http://twig.sensiolabs.org/
[AGPL version 3]: http://www.gnu.org/licenses/agpl.htmlu.org/licenses/agpl.html
