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

ini_set('memory_limit', '5G'); // Suggested by Sam http://goo.gl/tUw4wY
$fileroot = __DIR__ . DIRECTORY_SEPARATOR . "tmp";

$options = getopt("a::", array(
	"app::"
	));

$globals_settings = parse_ini_file ('config/globals.ini');
// The servers webroot,
$webroot = $globals_settings['webroot'];

// Request URI (Suggested by Sam http://goo.gl/tUw4wY ).
$request_uri = $globals_settings['request_uri'];

// Populate site settings using a globbing pattern.
$sites = array();

if (isset($options['app'])) {
    $sites[$options['app']] = parse_ini_file("config" . DIRECTORY_SEPARATOR . "apps" . DIRECTORY_SEPARATOR . $options['app'].".ini");
} else {
  // Add site configuration files.
  foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "apps" . DIRECTORY_SEPARATOR . "*.ini") as $filename) {
    $sites[basename($filename, '.ini')] = parse_ini_file($filename);
  }
}
// @todo: Search webroot for site settings files / drupal settings.php
