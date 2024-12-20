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

$content = required_param('content', PARAM_TEXT);
$fs = get_file_storage();
$context = context_system::instance();

try {
    $record = new stdClass();
    $record->userid = $USER->id;
    $record->content = $content;
    $record->likes = 0;
    $record->comments = 0;
    $record->timecreated = time();
    $record->timemodified = time();

    // First insert the record to get the ID
    $postid = $DB->insert_record('local_recognition_posts', $record);

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $filename = $_FILES['image']['name'];
        $filetype = $_FILES['image']['type'];
        
        // Only allow image files
        if (strpos($filetype, 'image/') === 0) {
            $fileinfo = array(
                'contextid' => $context->id,
                'component' => 'local_recognition',
                'filearea' => 'post_images',
                'itemid' => $postid, // Use post ID as item ID
                'filepath' => '/',
                'filename' => $filename
            );
            
            if ($fs->file_exists($fileinfo['contextid'], $fileinfo['component'], 
                $fileinfo['filearea'], $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename'])) {
                $fs->delete_area_files($fileinfo['contextid'], $fileinfo['component'], 
                    $fileinfo['filearea'], $fileinfo['itemid']);
            }
            
            $storedfile = $fs->create_file_from_pathname($fileinfo, $_FILES['image']['tmp_name']);
            
            // Update the post record with the image path
            $record->id = $postid;
            $record->imagepath = moodle_url::make_pluginfile_url(
                $fileinfo['contextid'],
                $fileinfo['component'],
                $fileinfo['filearea'],
                $fileinfo['itemid'],
                $fileinfo['filepath'],
                $fileinfo['filename']
            )->out();
            
            $DB->update_record('local_recognition_posts', $record);
        }
    }

    redirect($returnurl, get_string('postsuccessful', 'local_recognition'));
} catch (Exception $e) {
    redirect($returnurl, get_string('posterror', 'local_recognition'), null, \core\output\notification::NOTIFY_ERROR);
}
