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
define('RECOGNITION_POINTS_THANKS_RECEIVED', 40); // Teşekkür alma puanı
define('RECOGNITION_POINTS_THANKS_GIVEN', 15);    // Teşekkür etme puanı
define('RECOGNITION_POINTS_CELEBRATION_RECEIVED', 50); // Kutlama alma puanı
define('RECOGNITION_POINTS_CELEBRATION_GIVEN', 20);    // Kutlama yapma puanı

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

    // --- Mention notification for posts ---
    error_log('DEBUG: local_recognition_save_post mention block reached.');
    if (!empty($data->message)) {
        preg_match_all('/data-userid="(\\d+)"/', $data->message, $matches);
        $mentioned_user_ids = array_unique($matches[1]);
        error_log('DEBUG: Mentioned user IDs: ' . json_encode($mentioned_user_ids));
        if (!empty($mentioned_user_ids)) {
            $posturl = new moodle_url('/local/recognition/post.php', ['id' => $recordid]);
            local_recognition_notify_mentions($mentioned_user_ids, $USER, $data->message, $posturl->out(false));
        }
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

    // --- Mention notification for comments ---
    // Yorum içeriğini bulmak için reactions tablosundan çekiyoruz
    $reaction = $DB->get_record('local_recognition_reactions', array('recordid' => $recordid, 'userid' => $userid, 'type' => 'comment'), '*', IGNORE_MULTIPLE);
    if ($reaction && !empty($reaction->content)) {
        preg_match_all('/data-userid="(\\d+)"/', $reaction->content, $matches);
        $mentioned_user_ids = array_unique($matches[1]);
        if (!empty($mentioned_user_ids)) {
            global $CFG;
            require_once($CFG->dirroot . '/lib/weblib.php');
            $user = $DB->get_record('user', array('id' => $userid));
            $commenturl = new moodle_url('/local/recognition/post.php', ['id' => $recordid, 'commentid' => $reaction->id]);
            local_recognition_notify_mentions($mentioned_user_ids, $user, $reaction->content, $commenturl->out(false));
        }
    }
}

/**
 * Teşekkür eklendiğinde çağrılır
 */
function local_recognition_thanks_added($recordid, $userid) {
    global $DB;
    
    // Hangi tabloyu kullanacağımızı kontrol et
    $table_exists_records = $DB->get_manager()->table_exists('local_recognition_records');
    $table_exists_posts = $DB->get_manager()->table_exists('local_recognition_posts');
    
    // Post sahibini kontrol et
    $post = null;
    if ($table_exists_records) {
        $post = $DB->get_record('local_recognition_records', array('id' => $recordid));
    }
    
    if (!$post && $table_exists_posts) {
        $post = $DB->get_record('local_recognition_posts', array('id' => $recordid));
    }
    
    if (!$post) {
        return;
    }
    
    // Post sahibi bilgisini al
    $post_owner_id = isset($post->fromid) ? $post->fromid : $post->userid;
    
    // Kendi postuna teşekkür yapmışsa puan verme
    if ($post_owner_id == $userid) {
        return;
    }
    
    // Teşekkür yapana puan ver
    $record = new stdClass();
    $record->badgeid = 0; // Varsayılan badge
    $record->fromid = $userid; // Teşekkür eden
    $record->toid = $userid; // Teşekkür eden (kendine puan)
    $record->message = get_string('thanksgiven', 'local_recognition', fullname($DB->get_record('user', array('id' => $post_owner_id))));
    $record->points = RECOGNITION_POINTS_THANKS_GIVEN;
    $record->timecreated = time();
    $record->type = 'thanks_given';
    $DB->insert_record('local_recognition_records', $record);
    
    // Post sahibine teşekkür puanı ver
    $record = new stdClass();
    $record->badgeid = 0; // Varsayılan badge
    $record->fromid = $userid; // Teşekkür eden
    $record->toid = $post_owner_id; // Post sahibi
    $record->message = get_string('thanksreceived', 'local_recognition', fullname($DB->get_record('user', array('id' => $userid))));
    $record->points = RECOGNITION_POINTS_THANKS_RECEIVED;
    $record->timecreated = time();
    $record->type = 'thanks_received';
    $DB->insert_record('local_recognition_records', $record);
}

/**
 * Teşekkür silindiğinde çağrılır
 */
function local_recognition_thanks_removed($recordid, $userid) {
    global $DB;
    
    // Hangi tabloyu kullanacağımızı kontrol et
    $table_exists_records = $DB->get_manager()->table_exists('local_recognition_records');
    $table_exists_posts = $DB->get_manager()->table_exists('local_recognition_posts');
    
    // Post sahibini kontrol et
    $post = null;
    if ($table_exists_records) {
        $post = $DB->get_record('local_recognition_records', array('id' => $recordid));
    }
    
    if (!$post && $table_exists_posts) {
        $post = $DB->get_record('local_recognition_posts', array('id' => $recordid));
    }
    
    if (!$post) {
        return;
    }
    
    // Post sahibi bilgisini al
    $post_owner_id = isset($post->fromid) ? $post->fromid : $post->userid;
    
    // Kendi postuna teşekkür yapmışsa işlem yapma
    if ($post_owner_id == $userid) {
        return;
    }
    
    // Teşekkür edenin puanını sil
    $DB->delete_records('local_recognition_records', array(
        'fromid' => $userid,
        'toid' => $userid,
        'type' => 'thanks_given'
    ));
    
    // Post sahibinin puanını sil
    $DB->delete_records('local_recognition_records', array(
        'fromid' => $userid,
        'toid' => $post_owner_id,
        'type' => 'thanks_received'
    ));
}

/**
 * Kutlama eklendiğinde çağrılır
 */
function local_recognition_celebration_added($recordid, $userid) {
    global $DB;
    
    // Hangi tabloyu kullanacağımızı kontrol et
    $table_exists_records = $DB->get_manager()->table_exists('local_recognition_records');
    $table_exists_posts = $DB->get_manager()->table_exists('local_recognition_posts');
    
    // Post sahibini kontrol et
    $post = null;
    if ($table_exists_records) {
        $post = $DB->get_record('local_recognition_records', array('id' => $recordid));
    }
    
    if (!$post && $table_exists_posts) {
        $post = $DB->get_record('local_recognition_posts', array('id' => $recordid));
    }
    
    if (!$post) {
        return;
    }
    
    // Post sahibi bilgisini al
    $post_owner_id = isset($post->fromid) ? $post->fromid : $post->userid;
    
    // Kendi postuna kutlama yapmışsa puan verme
    if ($post_owner_id == $userid) {
        return;
    }
    
    // Kutlama yapana puan ver
    $record = new stdClass();
    $record->badgeid = 0; // Varsayılan badge
    $record->fromid = $userid; // Kutlama yapan
    $record->toid = $userid; // Kutlama yapan (kendine puan)
    $record->message = get_string('celebrationgiven', 'local_recognition', fullname($DB->get_record('user', array('id' => $post_owner_id))));
    $record->points = RECOGNITION_POINTS_CELEBRATION_GIVEN;
    $record->timecreated = time();
    $record->type = 'celebration_given';
    $DB->insert_record('local_recognition_records', $record);
    
    // Post sahibine kutlama puanı ver
    $record = new stdClass();
    $record->badgeid = 0; // Varsayılan badge
    $record->fromid = $userid; // Kutlama yapan
    $record->toid = $post_owner_id; // Post sahibi
    $record->message = get_string('celebrationreceived', 'local_recognition', fullname($DB->get_record('user', array('id' => $userid))));
    $record->points = RECOGNITION_POINTS_CELEBRATION_RECEIVED;
    $record->timecreated = time();
    $record->type = 'celebration_received';
    $DB->insert_record('local_recognition_records', $record);
}

/**
 * Kutlama silindiğinde çağrılır
 */
function local_recognition_celebration_removed($recordid, $userid) {
    global $DB;
    
    // Hangi tabloyu kullanacağımızı kontrol et
    $table_exists_records = $DB->get_manager()->table_exists('local_recognition_records');
    $table_exists_posts = $DB->get_manager()->table_exists('local_recognition_posts');
    
    // Post sahibini kontrol et
    $post = null;
    if ($table_exists_records) {
        $post = $DB->get_record('local_recognition_records', array('id' => $recordid));
    }
    
    if (!$post && $table_exists_posts) {
        $post = $DB->get_record('local_recognition_posts', array('id' => $recordid));
    }
    
    if (!$post) {
        return;
    }
    
    // Post sahibi bilgisini al
    $post_owner_id = isset($post->fromid) ? $post->fromid : $post->userid;
    
    // Kendi postuna kutlama yapmışsa işlem yapma
    if ($post_owner_id == $userid) {
        return;
    }
    
    // Kutlama yapanın puanını sil
    $DB->delete_records('local_recognition_records', array(
        'fromid' => $userid,
        'toid' => $userid,
        'type' => 'celebration_given'
    ));
    
    // Post sahibinin puanını sil
    $DB->delete_records('local_recognition_records', array(
        'fromid' => $userid,
        'toid' => $post_owner_id,
        'type' => 'celebration_received'
    ));
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
    
    // Aldığı teşekkür puanları (40 puan)
    $thanks_received = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {local_recognition_records} r
        WHERE r.toid = ? AND r.type = 'thanks_received'", array($userid));
    $points += $thanks_received * RECOGNITION_POINTS_THANKS_RECEIVED;
    
    // Yaptığı teşekkür puanları (15 puan)
    $thanks_given = $DB->count_records('local_recognition_records', array(
        'fromid' => $userid,
        'type' => 'thanks_given'
    ));
    $points += $thanks_given * RECOGNITION_POINTS_THANKS_GIVEN;
    
    // Aldığı kutlama puanları (50 puan)
    $celebrations_received = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {local_recognition_records} r
        WHERE r.toid = ? AND r.type = 'celebration_received'", array($userid));
    $points += $celebrations_received * RECOGNITION_POINTS_CELEBRATION_RECEIVED;
    
    // Yaptığı kutlama puanları (20 puan)
    $celebrations_given = $DB->count_records('local_recognition_records', array(
        'fromid' => $userid,
        'type' => 'celebration_given'
    ));
    $points += $celebrations_given * RECOGNITION_POINTS_CELEBRATION_GIVEN;
    
    return array(
        'points' => $points,
        'posts' => $posts,
        'likes_received' => $likes_received,
        'comments_received' => $comments_received,
        'likes_given' => $likes_given,
        'comments_given' => $comments_given,
        'thanks_received' => $thanks_received,
        'thanks_given' => $thanks_given,
        'celebrations_received' => $celebrations_received,
        'celebrations_given' => $celebrations_given
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

/**
 * En çok beğeni alan gönderileri getirir
 */
function local_recognition_get_most_liked_posts($limit = 3) {
    global $DB;
    
    return $DB->get_records_sql("
        SELECT p.*, u.firstname, u.lastname, 
               COUNT(r.id) as like_count
        FROM {local_recognition_records} p
        JOIN {user} u ON p.fromid = u.id
        LEFT JOIN {local_recognition_reactions} r ON p.id = r.recordid AND r.type = 'like'
        GROUP BY p.id, p.fromid, p.message, p.timecreated, u.firstname, u.lastname
        HAVING COUNT(r.id) > 0
        ORDER BY like_count DESC
        LIMIT " . intval($limit));
}

/**
 * En çok yorum alan gönderileri getirir
 */
function local_recognition_get_most_commented_posts($limit = 3) {
    global $DB;
    
    return $DB->get_records_sql("
        SELECT p.*, u.firstname, u.lastname, 
               COUNT(r.id) as comment_count
        FROM {local_recognition_records} p
        JOIN {user} u ON p.fromid = u.id
        LEFT JOIN {local_recognition_reactions} r ON p.id = r.recordid AND r.type = 'comment'
        GROUP BY p.id, p.fromid, p.message, p.timecreated, u.firstname, u.lastname
        HAVING COUNT(r.id) > 0
        ORDER BY comment_count DESC
        LIMIT " . intval($limit));
}

/**
 * En çok beğeni yapan kullanıcıları getirir
 */
function local_recognition_get_most_liking_users($limit = 3) {
    global $DB;
    
    return $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname, 
               COUNT(r.id) as like_count
        FROM {user} u
        JOIN {local_recognition_reactions} r ON u.id = r.userid AND r.type = 'like'
        GROUP BY u.id, u.firstname, u.lastname
        ORDER BY like_count DESC
        LIMIT " . intval($limit));
}

/**
 * En çok yorum yapan kullanıcıları getirir
 */
function local_recognition_get_most_commenting_users($limit = 3) {
    global $DB;
    
    return $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname, 
               COUNT(r.id) as comment_count
        FROM {user} u
        JOIN {local_recognition_reactions} r ON u.id = r.userid AND r.type = 'comment'
        GROUP BY u.id, u.firstname, u.lastname
        ORDER BY comment_count DESC
        LIMIT " . intval($limit));
}

function local_recognition_notify_mentions(array $mentioned_user_ids, $userfrom, $content, $contexturl) {
    foreach ($mentioned_user_ids as $userto) {
        if ($userto == $userfrom->id) continue; // Don't notify self
        $eventdata = new \core\message\message();
        $eventdata->component = 'local_recognition';
        $eventdata->name = 'mention_notification';
        $eventdata->userfrom = $userfrom;
        $eventdata->userto = $userto;
        $eventdata->subject = "Bir gönderide/yorumda sizden bahsedildi!";
        $eventdata->fullmessage = html_to_text($content);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = $content;
        $eventdata->smallmessage = "Bir gönderide sizden bahsedildi!";
        $eventdata->notification = true;
        $eventdata->contexturl = $contexturl;
        $eventdata->contexturlname = "Gönderiyi Gör";
        $msgid = message_send($eventdata);
    }
}
