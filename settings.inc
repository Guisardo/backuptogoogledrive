<?php
/**
 * @file
 * Backup to GoogleDrive example script.
 *
 * Copyright (C) 2013 Matthew Hipkin <http://www.matthewhipkin.co.uk>
 * Copyright (C) 2016 Reese Creative <http://www.reesecreativestudio.com>
 *
 * settings.inc.php
 * Settings required for script execution.
 */

ini_set('memory_limit', '1024M'); // Suggested by Sam http://goo.gl/tUw4wY

// The servers webroot,
$webroot = "~/Sites";

$fileroot = __DIR__ . "/tmp";

// Google Drive Client ID.
$client_id = "1021542467974-vsag462uak0lkju9s0qkcgbvoik07dos.apps.googleusercontent.com";

// Google Drive Client Secret.
$client_secret = "7xs8fzmKe2icF9A5DsaNPLXC";

//$api_key = 'AIzaSyBTGAg1f1RB2j9IZa4TVmp0raY9YGSYAoM';

//$service_key_path = '~/.credentials/rcbackup-credentials.json';


// Google Drive authentication code.
// Needs to be set using getauthcode.php first!
//$auth_code = "";

// Request URI (Suggested by Sam http://goo.gl/tUw4wY ).
$request_uri = "urn:ietf:wg:oauth:2.0:oob";

// Populate site settings using a globbing pattern.
$sites = array();

// Add site configuration files.
foreach (glob("sites/*.site.inc") as $filename) {
  require_once $filename;
}

// @todo: Search webroot for site settings files / drupal settings.php
