<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

// Puan sabitleri
define('RECOGNITION_POINTS_POST', 100);      // Post paylaşma puanı
define('RECOGNITION_POINTS_LIKE_RECEIVED', 30);  // Beğeni alma puanı
define('RECOGNITION_POINTS_COMMENT_RECEIVED', 50); // Yorum alma puanı
define('RECOGNITION_POINTS_COMMENT_GIVEN', 20);   // Yorum yapma puanı
define('RECOGNITION_POINTS_LIKE_GIVEN', 10);      // Beğeni yapma puanı

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
    global $DB;

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if ($filearea !== 'record_images') {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    if (!$record = $DB->get_record('local_recognition_records', array('id' => $itemid))) {
        return false;
    }

    $fs = get_file_storage();
    if (!$file = $fs->get_file($context->id, 'local_recognition', $filearea, $itemid, $filepath, $filename)) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

function local_recognition_save_post($data) {
    global $DB, $USER;

    $context = context_system::instance();
    
    // Save post record
    $record = new stdClass();
    $record->fromid = $USER->id;
    $record->message = $data->message;
    $record->badgeid = !empty($data->badgeid) ? $data->badgeid : null;
    $record->timecreated = time();
    
    $recordid = $DB->insert_record('local_recognition_records', $record);

    // Handle file upload
    if (!empty($_FILES['attachment']['name'])) {
        $fs = get_file_storage();
        
        // Prepare file record
        $fileinfo = array(
            'contextid' => $context->id,
            'component' => 'local_recognition',
            'filearea' => 'attachment',
            'itemid' => $recordid,
            'filepath' => '/',
            'filename' => $_FILES['attachment']['name']
        );

        // Save file
        $fs->create_file_from_pathname($fileinfo, $_FILES['attachment']['tmp_name']);
    }

    return $recordid;
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

/**
 * Kullanıcıya puan ekler
 * @param int $userid Kullanıcı ID
 * @param int $points Eklenecek puan
 * @return bool
 */
function local_recognition_add_points($userid, $points) {
    global $DB;
    
    // Kullanıcının mevcut puanlarını al
    $record = $DB->get_record('local_recognition_points', array('userid' => $userid));
    
    if ($record) {
        // Mevcut kayıt varsa güncelle
        $record->totalpoints += $points;
        $record->monthpoints += $points;
        $record->lastupdate = time();
        return $DB->update_record('local_recognition_points', $record);
    } else {
        // Yeni kayıt oluştur
        $record = new stdClass();
        $record->userid = $userid;
        $record->totalpoints = $points;
        $record->monthpoints = $points;
        $record->lastupdate = time();
        return $DB->insert_record('local_recognition_points', $record);
    }
}

/**
 * Post paylaşıldığında çağrılır
 */
function local_recognition_post_created($postid) {
    global $DB;
    
    $post = $DB->get_record('local_recognition_records', array('id' => $postid));
    if ($post) {
        local_recognition_add_points($post->fromid, RECOGNITION_POINTS_POST);
    }
}

/**
 * Beğeni eklendiğinde çağrılır
 */
function local_recognition_like_added($recordid, $userid) {
    global $DB;
    
    $post = $DB->get_record('local_recognition_records', array('id' => $recordid));
    if ($post) {
        // Post sahibine beğeni puanı
        local_recognition_add_points($post->fromid, RECOGNITION_POINTS_LIKE_RECEIVED);
        // Beğeni yapana puan
        local_recognition_add_points($userid, RECOGNITION_POINTS_LIKE_GIVEN);
    }
}

/**
 * Yorum eklendiğinde çağrılır
 */
function local_recognition_comment_added($recordid, $userid) {
    global $DB;
    
    $post = $DB->get_record('local_recognition_records', array('id' => $recordid));
    if ($post) {
        // Post sahibine yorum puanı
        local_recognition_add_points($post->fromid, RECOGNITION_POINTS_COMMENT_RECEIVED);
        // Yorum yapana puan
        local_recognition_add_points($userid, RECOGNITION_POINTS_COMMENT_GIVEN);
    }
}
