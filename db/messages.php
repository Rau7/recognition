<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'mention_notification' => [
        'capability' => 'moodle/site:sendmessage',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + 1, // yerine: MESSAGE_DEFAULT_LOGGEDIN
            'email' => MESSAGE_PERMITTED + 2, // yerine: MESSAGE_DEFAULT_LOGGEDOFF
        ],
    ],
];