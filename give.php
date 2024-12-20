<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->dirroot . '/local/recognition/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/recognition/give.php'));
$PAGE->set_title(get_string('givebadge', 'local_recognition'));
$PAGE->set_heading(get_string('givebadge', 'local_recognition'));

echo $OUTPUT->header();

// Simple form for testing
echo html_writer::start_div('card p-4');
echo html_writer::tag('h3', get_string('givebadge', 'local_recognition'));
echo html_writer::tag('p', 'This is a test page for the recognition system.');
echo html_writer::end_div();

echo $OUTPUT->footer();
