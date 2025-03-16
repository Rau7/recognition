<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2024122709;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2022112800;        // Requires this Moodle version.
$plugin->component = 'local_recognition'; // Full name of the plugin (used for diagnostics).
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.1.0';

$plugin->dependencies = array();

// AMD JavaScript modüllerini tanımla
$plugin->amd = array(
    'local_recognition/main' => array(
        'version' => 1,
        'path' => '/local/recognition/amd/src/main.js'
    )
);
