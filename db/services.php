<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_recognition_like_post' => array(
        'classname' => 'local_recognition\external',
        'methodname' => 'like_post',
        'description' => 'Like or unlike a post',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => '',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_recognition_add_comment' => array(
        'classname' => 'local_recognition\external',
        'methodname' => 'add_comment',
        'description' => 'Add a comment to a post',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => '',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    )
);
