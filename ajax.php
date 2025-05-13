<?php
// This file is part of Moodle - http://moodle.org/
//
define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot . '/local/recognition/lib.php');

// Hata raporlamayı açık tut
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_login();
require_sesskey();

header('Content-Type: application/json');

$result = array(
    'success' => false,
    'message' => '',
    'data' => null
);

try {
    // Parametreleri kontrol et
    /*if (!isset($_REQUEST['action']) || !isset($_REQUEST['postid'])) {
        throw new Exception('Missing required parameters: action or postid');
    }*/

    $action = optional_param('action', '', PARAM_ALPHA);
    $postid = optional_param('postid', 0, PARAM_INT);
    $content = optional_param('content', '', PARAM_TEXT);
    $commentid = optional_param('commentid', 0, PARAM_INT);

    // Veritabanı tablolarını kontrol et
    $table_records = 'local_recognition_records';
    $table_reactions = 'local_recognition_reactions';

    if (!$DB->get_manager()->table_exists($table_records)) {
        throw new Exception($table_records . ' table does not exist');
    }

    if (!$DB->get_manager()->table_exists($table_reactions)) {
        throw new Exception($table_reactions . ' table does not exist');
    }

    switch ($action) {
        case 'getcomments':
            try {
                // Post'u kontrol et
                $post = $DB->get_record('local_recognition_records', array('id' => $postid));
                if (!$post) {
                    throw new Exception('Post not found with ID: ' . $postid);
                }

                // Yorumları al
                $sql = "SELECT r.*, u.firstname, u.lastname 
                        FROM {local_recognition_reactions} r 
                        JOIN {user} u ON u.id = r.userid 
                        WHERE r.recordid = ? AND r.type = ?
                        ORDER BY r.timecreated ASC";
                
                $comments = $DB->get_records_sql($sql, array($postid, 'comment'));
                
                $html = '';
                if (empty($comments)) {
                    $html = html_writer::tag('div', 'Henüz yorum yapılmamış.', array('class' => 'text-muted text-center py-3'));
                } else {
                    foreach ($comments as $comment) {
                        $html .= html_writer::start_div('comment mb-2');
                        $html .= html_writer::empty_tag('img', array(
                            'src' => $OUTPUT->user_picture($comment, array('size' => 24)),
                            'class' => 'rounded-circle mr-2',
                            'style' => 'width: 24px; height: 24px;'
                        ));
                        $html .= html_writer::start_div('comment-content');
                        $html .= html_writer::tag('strong', fullname($comment), array('class' => 'mr-2'));
                        $html .= html_writer::tag('span', $comment->content);
                        $html .= html_writer::end_div(); // comment-content
                        $html .= html_writer::end_div(); // comment
                    }
                }
                
                $result['success'] = true;
                $result['data'] = array(
                    'html' => $html,
                    'count' => count($comments)
                );
            } catch (Exception $e) {
                $result['success'] = false;
                $result['message'] = 'Error getting comments: ' . $e->getMessage();
            }
            break;

        case 'like':
            try {
                $post = $DB->get_record('local_recognition_records', array('id' => $postid), '*', MUST_EXIST);
                $existing = $DB->get_record('local_recognition_reactions', array(
                    'recordid' => $postid,
                    'userid' => $USER->id,
                    'type' => 'like'
                ));
                
                if ($existing) {
                    // Unlike
                    $DB->delete_records('local_recognition_reactions', array('id' => $existing->id));
                    $likecount = $DB->count_records('local_recognition_reactions', array(
                        'recordid' => $postid,
                        'type' => 'like'
                    ));
                    
                    $result['success'] = true;
                    $result['data'] = array(
                        'likes' => $likecount,
                        'isLiked' => false
                    );
                } else {
                    // Like
                    $reaction = new stdClass();
                    $reaction->recordid = $postid;
                    $reaction->userid = $USER->id;
                    $reaction->type = 'like';
                    $reaction->timecreated = time();
                    $reaction->timemodified = time();
                    
                    $DB->insert_record('local_recognition_reactions', $reaction);
                    $likecount = $DB->count_records('local_recognition_reactions', array(
                        'recordid' => $postid,
                        'type' => 'like'
                    ));
                    
                    $result['success'] = true;
                    $result['data'] = array(
                        'likes' => $likecount,
                        'isLiked' => true
                    );
                }
            } catch (Exception $e) {
                $result['success'] = false;
                $result['message'] = 'Error liking post: ' . $e->getMessage();
            }
            break;
            
        case 'addcomment':
            try {
                $content = required_param('content', PARAM_TEXT);
                
                $post = $DB->get_record('local_recognition_records', array('id' => $postid), '*', MUST_EXIST);
                
                $reaction = new stdClass();
                $reaction->recordid = $postid;
                $reaction->userid = $USER->id;
                $reaction->type = 'comment';
                $reaction->content = $content;
                $reaction->timecreated = time();
                $reaction->timemodified = time();
                
                $DB->insert_record('local_recognition_reactions', $reaction);
                
                // Yorum HTML'ini oluştur
                $commenthtml = html_writer::start_div('comment mb-2');
                $commenthtml .= html_writer::empty_tag('img', array(
                    'src' => $OUTPUT->user_picture($USER, array('size' => 24)),
                    'class' => 'rounded-circle mr-2',
                    'style' => 'width: 24px; height: 24px;'
                ));
                $commenthtml .= html_writer::start_div('comment-content');
                $commenthtml .= html_writer::tag('strong', fullname($USER), array('class' => 'mr-2'));
                $commenthtml .= html_writer::tag('span', $content);
                $commenthtml .= html_writer::end_div(); // comment-content
                $commenthtml .= html_writer::end_div(); // comment
                
                $result['success'] = true;
                $result['data'] = array(
                    'html' => $commenthtml
                );
            } catch (Exception $e) {
                $result['success'] = false;
                $result['message'] = 'Error adding comment: ' . $e->getMessage();
            }
            break;

        case 'thanks':
            try {
                $post = $DB->get_record('local_recognition_records', array('id' => $postid), '*', MUST_EXIST);
                $existing = $DB->get_record('local_recognition_reactions', array(
                    'recordid' => $postid,
                    'userid' => $USER->id,
                    'type' => 'thanks'
                ));
                
                if ($existing) {
                    // Teşekkürü kaldır
                    $DB->delete_records('local_recognition_reactions', array('id' => $existing->id));
                    $thankscount = $DB->count_records('local_recognition_reactions', array(
                        'recordid' => $postid,
                        'type' => 'thanks'
                    ));
                    
                    // Puanları güncelle
                    local_recognition_thanks_removed($postid, $USER->id);
                    
                    $result['success'] = true;
                    $result['data'] = array(
                        'thanks' => $thankscount,
                        'isThanked' => false
                    );
                } else {
                    // Teşekkür ekle
                    $reaction = new stdClass();
                    $reaction->recordid = $postid;
                    $reaction->userid = $USER->id;
                    $reaction->type = 'thanks';
                    $reaction->timecreated = time();
                    $reaction->timemodified = time();
                    
                    $DB->insert_record('local_recognition_reactions', $reaction);
                    $thankscount = $DB->count_records('local_recognition_reactions', array(
                        'recordid' => $postid,
                        'type' => 'thanks'
                    ));
                    
                    // Puanları güncelle
                    local_recognition_thanks_added($postid, $USER->id);
                    
                    $result['success'] = true;
                    $result['data'] = array(
                        'thanks' => $thankscount,
                        'isThanked' => true
                    );
                }
            } catch (Exception $e) {
                $result['success'] = false;
                $result['message'] = 'Error adding thanks: ' . $e->getMessage();
            }
            break;
            
        case 'celebration':
            try {
                $post = $DB->get_record('local_recognition_records', array('id' => $postid), '*', MUST_EXIST);
                $existing = $DB->get_record('local_recognition_reactions', array(
                    'recordid' => $postid,
                    'userid' => $USER->id,
                    'type' => 'celebration'
                ));
                
                if ($existing) {
                    // Kutlamayı kaldır
                    $DB->delete_records('local_recognition_reactions', array('id' => $existing->id));
                    $celebrationcount = $DB->count_records('local_recognition_reactions', array(
                        'recordid' => $postid,
                        'type' => 'celebration'
                    ));
                    
                    // Puanları güncelle
                    local_recognition_celebration_removed($postid, $USER->id);
                    
                    $result['success'] = true;
                    $result['data'] = array(
                        'celebration' => $celebrationcount,
                        'isCelebrated' => false
                    );
                } else {
                    // Kutlama ekle
                    $reaction = new stdClass();
                    $reaction->recordid = $postid;
                    $reaction->userid = $USER->id;
                    $reaction->type = 'celebration';
                    $reaction->timecreated = time();
                    $reaction->timemodified = time();
                    
                    $DB->insert_record('local_recognition_reactions', $reaction);
                    $celebrationcount = $DB->count_records('local_recognition_reactions', array(
                        'recordid' => $postid,
                        'type' => 'celebration'
                    ));
                    
                    // Puanları güncelle
                    local_recognition_celebration_added($postid, $USER->id);
                    
                    $result['success'] = true;
                    $result['data'] = array(
                        'celebration' => $celebrationcount,
                        'isCelebrated' => true
                    );
                }
            } catch (Exception $e) {
                $result['success'] = false;
                $result['message'] = 'Error adding celebration: ' . $e->getMessage();
            }
            break;
        case 'searchusers':
            require_login();
            require_sesskey();
            $query = optional_param('query', '', PARAM_TEXT);
            $users = [];
            if ($query !== '') {
                global $DB, $USER;
                $like = '%' . $DB->sql_like_escape($query) . '%';
                $sql = "SELECT id, firstname, lastname FROM {user} WHERE deleted = 0 AND suspended = 0 AND id <> ? AND (firstname LIKE ? OR lastname LIKE ?) ORDER BY firstname, lastname LIMIT 10";
                $params = [$USER->id, $like, $like];
                $records = $DB->get_records_sql($sql, $params);
                foreach ($records as $u) {
                    $users[] = [ 'id' => $u->id, 'fullname' => fullname($u) ];
                }
            }
            $result['success'] = true;
            $result['data'] = $users;
            break;
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    $result['success'] = false;
    $result['message'] = $e->getMessage();
}

echo json_encode($result);
die();
