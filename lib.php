<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

/**
 * Serves files for the recognition plugin
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise
 */
function local_recognition_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    // Make sure the filearea is one of our areas.
    if ($filearea !== 'post_images') {
        return false;
    }

    // Get the itemid.
    $itemid = array_shift($args);

    // Extract the filename.
    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_recognition', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // Send the file back.
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

function local_recognition_extend_navigation(global_navigation $navigation) {
    global $CFG;

    if (isloggedin() && !isguestuser()) {
        // Add to custom menu items
        if (stripos($CFG->custommenuitems, "/local/recognition/") === false) {
            $nodes = explode("\n", $CFG->custommenuitems);
            $node = get_string('recognitionwall', 'local_recognition');
            $node .= "|";
            $node .= "/local/recognition/index.php";
            array_push($nodes, $node);
            $CFG->custommenuitems = implode("\n", $nodes);
        }
    }
}

function local_recognition_give_badge($data) {
    global $DB, $USER;
    
    try {
        // Create recognition record
        $record = new stdClass();
        $record->badgeid = $data->badgeid;
        $record->fromid = $USER->id;
        $record->toid = $data->toid;
        $record->message = $data->message;
        $record->timecreated = time();
        
        return $DB->insert_record('local_recognition_records', $record);
    } catch (Exception $e) {
        return false;
    }
}
