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
 * Post paylaşıldığında çağrılır
 */
function local_recognition_post_created($postid) {
    global $DB;
    
    $post = $DB->get_record('local_recognition_records', array('id' => $postid));
    if ($post) {
        // Post puanını güncelle
        $post->points = RECOGNITION_POINTS_POST;
        $DB->update_record('local_recognition_records', $post);
    }
}

/**
 * Beğeni eklendiğinde çağrılır
 */
function local_recognition_like_added($recordid, $userid) {
    global $DB;
    
    // Post sahibini kontrol et
    $post = $DB->get_record('local_recognition_records', array('id' => $recordid));
    if (!$post) {
        return;
    }
    
    // Kendi postuna beğeni yapmışsa puan verme
    if ($post->fromid == $userid) {
        return;
    }
    
    // Beğeni yapana puan ver
    $record = new stdClass();
    $record->badgeid = 0; // Varsayılan badge
    $record->fromid = $userid; // Beğeniyi yapan
    $record->toid = $userid; // Beğeniyi yapan (kendine puan)
    $record->message = get_string('likegiven', 'local_recognition');
    $record->points = RECOGNITION_POINTS_LIKE_GIVEN;
    $record->timecreated = time();
    $record->type = 'like_given';
    $DB->insert_record('local_recognition_records', $record);
    
    // Post sahibine beğeni puanı ver
    $record = new stdClass();
    $record->badgeid = 0; // Varsayılan badge
    $record->fromid = $userid; // Beğeniyi yapan
    $record->toid = $post->fromid; // Post sahibi
    $record->message = get_string('likereceived', 'local_recognition');
    $record->points = RECOGNITION_POINTS_LIKE_RECEIVED;
    $record->timecreated = time();
    $record->type = 'like_received';
    $DB->insert_record('local_recognition_records', $record);
}

/**
 * Beğeni silindiğinde çağrılır
 */
function local_recognition_like_removed($recordid, $userid) {
    global $DB;
    
    // Post sahibini kontrol et
    $post = $DB->get_record('local_recognition_records', array('id' => $recordid));
    if (!$post) {
        return;
    }
    
    // Kendi postuna beğeni yapmışsa işlem yapma
    if ($post->fromid == $userid) {
        return;
    }
    
    // Beğeni yapanın puanını sil
    $DB->delete_records('local_recognition_records', array(
        'fromid' => $userid,
        'toid' => $userid,
        'type' => 'like_given'
    ));
    
    // Post sahibinin puanını sil
    $DB->delete_records('local_recognition_records', array(
        'fromid' => $userid,
        'toid' => $post->fromid,
        'type' => 'like_received'
    ));
}

/**
 * Yorum eklendiğinde çağrılır
 */
function local_recognition_comment_added($recordid, $userid) {
    global $DB;
    
    // Post sahibini kontrol et
    $post = $DB->get_record('local_recognition_records', array('id' => $recordid));
    if (!$post) {
        return;
    }
    
    // Kendi postuna yorum yapmışsa puan verme
    if ($post->fromid == $userid) {
        return;
    }
    
    // Yorum yapana puan ver
    $record = new stdClass();
    $record->badgeid = 0; // Varsayılan badge
    $record->fromid = $userid; // Yorumu yapan
    $record->toid = $userid; // Yorumu yapan (kendine puan)
    $record->message = get_string('commentgiven', 'local_recognition');
    $record->points = RECOGNITION_POINTS_COMMENT_GIVEN;
    $record->timecreated = time();
    $record->type = 'comment_given';
    $DB->insert_record('local_recognition_records', $record);
    
    // Post sahibine yorum puanı ver
    $record = new stdClass();
    $record->badgeid = 0; // Varsayılan badge
    $record->fromid = $userid; // Yorumu yapan
    $record->toid = $post->fromid; // Post sahibi
    $record->message = get_string('commentreceived', 'local_recognition');
    $record->points = RECOGNITION_POINTS_COMMENT_RECEIVED;
    $record->timecreated = time();
    $record->type = 'comment_received';
    $DB->insert_record('local_recognition_records', $record);
}

/**
 * Kullanıcının toplam puanını hesaplar
 */
function local_recognition_calculate_points($userid) {
    global $DB;
    
    $points = 0;
    
    // Post paylaşma puanları (100 puan)
    $posts = $DB->count_records('local_recognition_records', array('fromid' => $userid));
    $points += $posts * RECOGNITION_POINTS_POST;
    
    // Aldığı beğeni puanları (30 puan)
    $likes_received = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {local_recognition_reactions} r
        JOIN {local_recognition_records} p ON r.recordid = p.id
        WHERE p.fromid = ? AND r.type = 'like'", array($userid));
    $points += $likes_received * RECOGNITION_POINTS_LIKE_RECEIVED;
    
    // Aldığı yorum puanları (50 puan)
    $comments_received = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {local_recognition_reactions} r
        JOIN {local_recognition_records} p ON r.recordid = p.id
        WHERE p.fromid = ? AND r.type = 'comment'", array($userid));
    $points += $comments_received * RECOGNITION_POINTS_COMMENT_RECEIVED;
    
    // Yaptığı beğeni puanları (10 puan)
    $likes_given = $DB->count_records('local_recognition_reactions', array(
        'userid' => $userid,
        'type' => 'like'
    ));
    $points += $likes_given * RECOGNITION_POINTS_LIKE_GIVEN;
    
    // Yaptığı yorum puanları (20 puan)
    $comments_given = $DB->count_records('local_recognition_reactions', array(
        'userid' => $userid,
        'type' => 'comment'
    ));
    $points += $comments_given * RECOGNITION_POINTS_COMMENT_GIVEN;
    
    return array(
        'points' => $points,
        'posts' => $posts,
        'likes_received' => $likes_received,
        'comments_received' => $comments_received,
        'likes_given' => $likes_given,
        'comments_given' => $comments_given
    );
}

/**
 * Tüm kullanıcıların sıralamasını hesaplar
 */
function local_recognition_get_user_rankings() {
    global $DB;
    
    $users = $DB->get_records('user', array('deleted' => 0));
    $rankings = array();
    
    foreach ($users as $user) {
        $stats = local_recognition_calculate_points($user->id);
        $rankings[] = array(
            'userid' => $user->id,
            'points' => $stats['points']
        );
    }
    
    // Puanlara göre sırala
    usort($rankings, function($a, $b) {
        return $b['points'] - $a['points'];
    });
    
    // Sıra numarası ekle
    $rank = 1;
    foreach ($rankings as &$ranking) {
        $ranking['rank'] = $rank++;
    }
    
    return $rankings;
}
