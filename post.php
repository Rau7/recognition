<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/local/recognition/lib.php');

require_login();
require_sesskey();

$returnurl = new moodle_url('/local/recognition/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($returnurl);
}

$message = required_param('message', PARAM_TEXT);
$badgeid = optional_param('badgeid', 0, PARAM_INT);
$fs = get_file_storage();
$context = context_system::instance();

try {
    $record = new stdClass();
    $record->fromid = $USER->id;
    $record->toid = $USER->id; // For now, sending to self
    $record->message = $message;
    $record->badgeid = $badgeid;
    $record->points = 0;
    $record->timecreated = time();

    // First insert the record to get the ID
    $recordid = $DB->insert_record('local_recognition_records', $record);

    // Handle file upload
    if (!empty($_FILES['attachment']['name'])) {
        $filename = $_FILES['attachment']['name'];
        $filetype = $_FILES['attachment']['type'];
        
        // Only allow image files
        if (strpos($filetype, 'image/') === 0) {
            $fileinfo = array(
                'contextid' => $context->id,
                'component' => 'local_recognition',
                'filearea' => 'record_images',
                'itemid' => $recordid,
                'filepath' => '/',
                'filename' => $filename
            );
            
            if ($fs->file_exists($fileinfo['contextid'], $fileinfo['component'], 
                $fileinfo['filearea'], $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename'])) {
                $fs->delete_area_files($context->id, 'local_recognition', 'record_images', $recordid);
            }
            
            $fs->create_file_from_pathname($fileinfo, $_FILES['attachment']['tmp_name']);
        }
    }

    // Puan ekle
    local_recognition_post_created($recordid);

    redirect($returnurl, get_string('postsuccessful', 'local_recognition'));
} catch (Exception $e) {
    redirect($returnurl, get_string('posterror', 'local_recognition'), null, \core\output\notification::NOTIFY_ERROR);
}
