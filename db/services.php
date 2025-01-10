<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_recognition_handle_reaction' => array(
        'classname'     => 'local_recognition\external',
        'methodname'    => 'handle_reaction',
        'description'   => 'Handle reactions (likes and comments)',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ),
);
