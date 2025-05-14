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

$message = required_param('message', PARAM_RAW);
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

    // --- Mention notification for posts ---
    preg_match_all('/data-userid="(\\d+)"/', $message, $matches);
    $mentioned_user_ids = array_unique($matches[1]);
   
    if (!empty($mentioned_user_ids)) {
        $posturl = new moodle_url('/local/recognition/post.php', ['id' => $recordid]);
        local_recognition_notify_mentions($mentioned_user_ids, $USER, $message, $posturl->out(false));
    }

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

    $action = optional_param('action', '', PARAM_TEXT);
    $content = optional_param('content', '', PARAM_TEXT);

    // Add like
    if ($action === 'like') {
        $reaction = new stdClass();
        $reaction->recordid = $recordid;
        $reaction->userid = $USER->id;
        $reaction->type = 'like';
        $reaction->timecreated = time();
        $reaction->timemodified = time();
        
        $existing = $DB->get_record('local_recognition_reactions', [
            'recordid' => $recordid,
            'userid' => $USER->id,
            'type' => 'like'
        ]);

        if ($existing) {
            // Unlike
            if ($DB->delete_records('local_recognition_reactions', ['id' => $existing->id])) {
                // Puanları sil
                require_once(__DIR__ . '/lib.php');
                local_recognition_like_removed($recordid, $USER->id);
                
                $likecount = $DB->count_records('local_recognition_reactions', [
                    'recordid' => $recordid,
                    'type' => 'like'
                ]);
                
                echo json_encode(array(
                    'error' => false,
                    'data' => array(
                        'success' => true,
                        'message' => '',
                        'data' => array(
                            'likes' => $likecount,
                            'isLiked' => false
                        )
                    )
                ));
            } else {
                echo json_encode(array(
                    'error' => true,
                    'data' => array(
                        'success' => false,
                        'message' => get_string('likeerror', 'local_recognition'),
                        'data' => null
                    )
                ));
            }
        } else {
            // Like
            if ($DB->insert_record('local_recognition_reactions', $reaction)) {
                // Puan ekle
                require_once(__DIR__ . '/lib.php');
                local_recognition_like_added($recordid, $USER->id);
                
                $likecount = $DB->count_records('local_recognition_reactions', [
                    'recordid' => $recordid,
                    'type' => 'like'
                ]);
                
                echo json_encode(array(
                    'error' => false,
                    'data' => array(
                        'success' => true,
                        'message' => '',
                        'data' => array(
                            'likes' => $likecount,
                            'isLiked' => true
                        )
                    )
                ));
            } else {
                echo json_encode(array(
                    'error' => true,
                    'data' => array(
                        'success' => false,
                        'message' => get_string('likeerror', 'local_recognition'),
                        'data' => null
                    )
                ));
            }
        }
    }

    // Add comment
    if ($action === 'comment') {
        $reaction = new stdClass();
        $reaction->recordid = $recordid;
        $reaction->userid = $USER->id;
        $reaction->type = 'comment';
        $reaction->content = $content;
        $reaction->timecreated = time();
        $reaction->timemodified = time();
        
        if ($DB->insert_record('local_recognition_reactions', $reaction)) {
            // Puan ekle
            require_once(__DIR__ . '/lib.php');
            local_recognition_comment_added($recordid, $USER->id);
            
            $commentcount = $DB->count_records('local_recognition_reactions', [
                'recordid' => $recordid,
                'type' => 'comment'
            ]);
            
            echo json_encode(array(
                'error' => false,
                'data' => array(
                    'success' => true,
                    'message' => '',
                    'data' => array(
                        'comments' => $commentcount
                    )
                )
            ));
        } else {
            echo json_encode(array(
                'error' => true,
                'data' => array(
                    'success' => false,
                    'message' => get_string('commenterror', 'local_recognition'),
                    'data' => null
                )
            ));
        }
    }

    redirect($returnurl, get_string('postsuccessful', 'local_recognition'));
} catch (Exception $e) {
    redirect($returnurl, get_string('posterror', 'local_recognition'), null, \core\output\notification::NOTIFY_ERROR);
}
?>

<script>
function addLike(recordId) {
    fetch('post.php?action=like&recordid=' + recordId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.error && data.data.success) {
            // Sayfayı yenile
            window.location.reload();
        } else {
            alert(data.message || data.data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo get_string('likeerror', 'local_recognition'); ?>');
    });
}

function addComment(recordId) {
    const content = document.getElementById('comment-content-' + recordId).value;
    if (!content) {
        alert('<?php echo get_string('commentempty', 'local_recognition'); ?>');
        return;
    }

    fetch('post.php?action=comment&recordid=' + recordId + '&content=' + encodeURIComponent(content), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.data.success) {
            // Yorum alanını temizle
            document.getElementById('comment-content-' + recordId).value = '';
            // Sayfayı yenile
            window.location.reload();
        } else {
            alert(data.data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo get_string('commenterror', 'local_recognition'); ?>');
    });
}
</script>
