<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External API for the recognition plugin.
 */
class local_recognition_external extends external_api {
    /**
     * Returns description of handle_reaction() parameters.
     *
     * @return external_function_parameters
     */
    public static function handle_reaction_parameters() {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'Post ID'),
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Action to perform'),
            'content' => new external_value(PARAM_TEXT, 'Content for comment', VALUE_DEFAULT, ''),
            'commentid' => new external_value(PARAM_INT, 'Comment ID', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns description of handle_reaction() result value.
     *
     * @return external_description
     */
    public static function handle_reaction_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Status of the operation'),
            'message' => new external_value(PARAM_TEXT, 'Message describing the result'),
            'data' => new external_single_structure([
                'html' => new external_value(PARAM_RAW, 'HTML content', VALUE_OPTIONAL),
                'count' => new external_value(PARAM_INT, 'Count of items', VALUE_OPTIONAL),
                'likes' => new external_value(PARAM_INT, 'Number of likes', VALUE_OPTIONAL),
                'isLiked' => new external_value(PARAM_BOOL, 'Whether the user has liked', VALUE_OPTIONAL),
                'thanks' => new external_value(PARAM_INT, 'Number of thanks', VALUE_OPTIONAL),
                'isThanked' => new external_value(PARAM_BOOL, 'Whether the user has thanked', VALUE_OPTIONAL),
                'celebration' => new external_value(PARAM_INT, 'Number of celebrations', VALUE_OPTIONAL),
                'isCelebrated' => new external_value(PARAM_BOOL, 'Whether the user has celebrated', VALUE_OPTIONAL),
            ], 'Response data', VALUE_OPTIONAL, []),  // Default boş array olarak ayarlandı
        ]);
    }

    /**
     * Handle reactions (likes and comments).
     *
     * @param int $postid Post ID
     * @param string $action Action (like, comment, thanks, celebration)
     * @param string $content Content (for comments)
     * @param int $commentid Comment ID (for comment actions)
     * @return array
     */
    public static function handle_reaction($postid, $action, $content = '', $commentid = 0) {
        global $DB, $USER;

        // Parameter validation
        $params = self::validate_parameters(self::handle_reaction_parameters(),
            array(
                'postid' => $postid,
                'action' => $action,
                'content' => $content,
                'commentid' => $commentid
            )
        );

        // Context validation
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/recognition:view', $context);

        $result = [
            'success' => false,
            'message' => '',
            'data' => [],  // Default boş array olarak ayarlandı
        ];

        try {
            switch ($params['action']) {
                case 'getcomments':
                    $post = $DB->get_record('local_recognition_records', ['id' => $params['postid']]);
                    if (!$post) {
                        throw new moodle_exception('postnotfound', 'local_recognition', '', $params['postid']);
                    }

                    $sql = "SELECT r.id, r.recordid, r.userid, r.type, r.content, r.timecreated, r.timemodified, 
                                   u.firstname, u.lastname 
                            FROM {local_recognition_reactions} r 
                            JOIN {user} u ON u.id = r.userid 
                            WHERE r.recordid = :recordid AND r.type = :type
                            ORDER BY r.timecreated ASC";
                    
                    $comments = $DB->get_records_sql($sql, ['recordid' => $params['postid'], 'type' => 'comment']);
                    
                    $html = '';
                    if (empty($comments)) {
                        $html = html_writer::tag('div', 'Henüz yorum yapılmamış.', ['class' => 'text-muted text-center py-3']);
                    } else {
                        
                        $index = 0;
                        foreach ($comments as $comment) {
                            $style = "--comment-depth: {$index}";
                            
                            $html .= html_writer::start_div('comment mb-2', ['style' => $style]);
                            
                            // User avatar with initials
                            global $OUTPUT, $DB;
                            $user = $DB->get_record('user', array('id' => $comment->userid));
                            $userpicture = $OUTPUT->user_picture($user, array('size' => 40));
                            $html .= html_writer::div($userpicture, 'user-avatar');
                            
                            $html .= html_writer::start_div('comment-content');
                            $html .= html_writer::tag('strong', fullname($comment), ['class' => 'mr-2']);
                            $html .= html_writer::tag('span', $comment->content);
                            $html .= html_writer::end_div(); // comment-content
                            $html .= html_writer::end_div(); // comment
                            
                            $index++;
                        }
                        $html .= html_writer::end_div(); // comments-list
                    }
                    
                    $result['success'] = true;
                    $result['data'] = [
                        'html' => $html,
                        'count' => count($comments),
                    ];
                    break;

                case 'like':
                    $post = $DB->get_record('local_recognition_records', ['id' => $params['postid']], '*', MUST_EXIST);
                    $existing = $DB->get_record('local_recognition_reactions', [
                        'recordid' => $params['postid'],
                        'userid' => $USER->id,
                        'type' => 'like'
                    ]);
                    
                    if ($existing) {
                        // Unlike
                        $DB->delete_records('local_recognition_reactions', ['id' => $existing->id]);
                        $likecount = $DB->count_records('local_recognition_reactions', [
                            'recordid' => $params['postid'],
                            'type' => 'like'
                        ]);
                        
                        $result['success'] = true;
                        $result['data'] = [
                            'likes' => $likecount,
                            'isLiked' => false,
                        ];
                    } else {
                        // Like
                        $reaction = new stdClass();
                        $reaction->recordid = $params['postid'];
                        $reaction->userid = $USER->id;
                        $reaction->type = 'like';
                        $reaction->timecreated = time();
                        $reaction->timemodified = time();
                        
                        $DB->insert_record('local_recognition_reactions', $reaction);
                        $likecount = $DB->count_records('local_recognition_reactions', [
                            'recordid' => $params['postid'],
                            'type' => 'like'
                        ]);
                        
                        $result['success'] = true;
                        $result['data'] = [
                            'likes' => $likecount,
                            'isLiked' => true,
                        ];
                    }
                    break;
                
                case 'add_comment':
                    if (empty($params['content'])) {
                        throw new moodle_exception('emptycomment', 'local_recognition');
                    }

                    $post = $DB->get_record('local_recognition_records', ['id' => $params['postid']], '*', MUST_EXIST);
                    
                    $reaction = new stdClass();
                    $reaction->recordid = $params['postid'];
                    $reaction->userid = $USER->id;
                    $reaction->type = 'comment';
                    $reaction->content = $params['content'];
                    $reaction->timecreated = time();
                    $reaction->timemodified = time();
                    
                    $DB->insert_record('local_recognition_reactions', $reaction);
                    
                    // Yorum HTML'ini oluştur
                    $commenthtml = html_writer::start_div('comment mb-2');
                    
                    // User avatar with initials
                    global $OUTPUT;
                    $userpicture = $OUTPUT->user_picture($USER, array('size' => 40));
                    $commenthtml .= html_writer::div($userpicture, 'user-avatar');
                    
                    $commenthtml .= html_writer::start_div('comment-content');
                    $commenthtml .= html_writer::tag('strong', fullname($USER), ['class' => 'mr-2']);
                    $commenthtml .= html_writer::tag('span', $params['content']);
                    $commenthtml .= html_writer::end_div(); // comment-content
                    $commenthtml .= html_writer::end_div(); // comment
                    
                    $result['success'] = true;
                    $result['data'] = [
                        'html' => $commenthtml,
                    ];
                    break;

                case 'thanks':
                    $post = $DB->get_record('local_recognition_records', ['id' => $params['postid']], '*', MUST_EXIST);
                    $existing = $DB->get_record('local_recognition_reactions', [
                        'recordid' => $params['postid'],
                        'userid' => $USER->id,
                        'type' => 'thanks'
                    ]);
                    
                    if ($existing) {
                        // Teşekkürü kaldır
                        $DB->delete_records('local_recognition_reactions', ['id' => $existing->id]);
                        $thankscount = $DB->count_records('local_recognition_reactions', [
                            'recordid' => $params['postid'],
                            'type' => 'thanks'
                        ]);
                        
                        // Puanları güncelle
                        require_once(__DIR__ . '/../lib.php');
                        local_recognition_thanks_removed($params['postid'], $USER->id);
                        
                        $result['success'] = true;
                        $result['data'] = [
                            'thanks' => $thankscount,
                            'isThanked' => false,
                        ];
                    } else {
                        // Teşekkür ekle
                        $reaction = new stdClass();
                        $reaction->recordid = $params['postid'];
                        $reaction->userid = $USER->id;
                        $reaction->type = 'thanks';
                        $reaction->timecreated = time();
                        $reaction->timemodified = time();
                        
                        $DB->insert_record('local_recognition_reactions', $reaction);
                        $thankscount = $DB->count_records('local_recognition_reactions', [
                            'recordid' => $params['postid'],
                            'type' => 'thanks'
                        ]);
                        
                        // Puanları güncelle
                        require_once(__DIR__ . '/../lib.php');
                        local_recognition_thanks_added($params['postid'], $USER->id);
                        
                        $result['success'] = true;
                        $result['data'] = [
                            'thanks' => $thankscount,
                            'isThanked' => true,
                        ];
                    }
                    break;
                    
                case 'celebration':
                    $post = $DB->get_record('local_recognition_records', ['id' => $params['postid']], '*', MUST_EXIST);
                    $existing = $DB->get_record('local_recognition_reactions', [
                        'recordid' => $params['postid'],
                        'userid' => $USER->id,
                        'type' => 'celebration'
                    ]);
                    
                    if ($existing) {
                        // Kutlamayı kaldır
                        $DB->delete_records('local_recognition_reactions', ['id' => $existing->id]);
                        $celebrationcount = $DB->count_records('local_recognition_reactions', [
                            'recordid' => $params['postid'],
                            'type' => 'celebration'
                        ]);
                        
                        // Puanları güncelle
                        require_once(__DIR__ . '/../lib.php');
                        local_recognition_celebration_removed($params['postid'], $USER->id);
                        
                        $result['success'] = true;
                        $result['data'] = [
                            'celebration' => $celebrationcount,
                            'isCelebrated' => false,
                        ];
                    } else {
                        // Kutlama ekle
                        $reaction = new stdClass();
                        $reaction->recordid = $params['postid'];
                        $reaction->userid = $USER->id;
                        $reaction->type = 'celebration';
                        $reaction->timecreated = time();
                        $reaction->timemodified = time();
                        
                        $DB->insert_record('local_recognition_reactions', $reaction);
                        $celebrationcount = $DB->count_records('local_recognition_reactions', [
                            'recordid' => $params['postid'],
                            'type' => 'celebration'
                        ]);
                        
                        // Puanları güncelle
                        require_once(__DIR__ . '/../lib.php');
                        local_recognition_celebration_added($params['postid'], $USER->id);
                        
                        $result['success'] = true;
                        $result['data'] = [
                            'celebration' => $celebrationcount,
                            'isCelebrated' => true,
                        ];
                    }
                    break;

                default:
                    throw new moodle_exception('invalidaction', 'local_recognition');
            }
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            return $result;
        }

        return $result;
    }

    /**
     * Returns description of add_like() parameters.
     *
     * @return external_function_parameters
     */
    public static function add_like_parameters() {
        return new external_function_parameters([
            'recordid' => new external_value(PARAM_INT, 'Record ID'),
        ]);
    }

    /**
     * Returns description of add_like() result value.
     *
     * @return external_description
     */
    public static function add_like_returns() {
        return new external_single_structure([
            'error' => new external_value(PARAM_BOOL, 'Error status of the operation'),
            'data' => new external_single_structure([
                'success' => new external_value(PARAM_BOOL, 'Status of the operation'),
                'message' => new external_value(PARAM_TEXT, 'Message describing the result'),
                'data' => new external_single_structure([
                    'likes' => new external_value(PARAM_INT, 'Number of likes'),
                    'isLiked' => new external_value(PARAM_BOOL, 'Whether the user has liked'),
                ], 'Response data', VALUE_OPTIONAL, []),
            ], 'Response data', VALUE_OPTIONAL, []),
        ]);
    }

    /**
     * Add like to a record.
     *
     * @param int $recordid Record ID
     * @return array
     */
    public static function add_like($recordid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::add_like_parameters(), array('recordid' => $recordid));
        
        $existing = $DB->get_record('local_recognition_reactions', [
            'recordid' => $params['recordid'],
            'userid' => $USER->id,
            'type' => 'like'
        ]);

        if ($existing) {
            // Unlike
            $DB->delete_records('local_recognition_reactions', ['id' => $existing->id]);
            
            // Puanları sil
            require_once(__DIR__ . '/../lib.php');
            local_recognition_like_removed($params['recordid'], $USER->id);
            
            $likecount = $DB->count_records('local_recognition_reactions', [
                'recordid' => $params['recordid'],
                'type' => 'like'
            ]);
            
            return array(
                'error' => false,
                'data' => array(
                    'success' => true,
                    'message' => '',
                    'data' => array(
                        'likes' => $likecount,
                        'isLiked' => false
                    )
                )
            );
        } else {
            // Like
            $reaction = new stdClass();
            $reaction->recordid = $params['recordid'];
            $reaction->userid = $USER->id;
            $reaction->type = 'like';
            $reaction->timecreated = time();
            $reaction->timemodified = time();
            
            if ($DB->insert_record('local_recognition_reactions', $reaction)) {
                // Puan ekle
                require_once(__DIR__ . '/../lib.php');
                local_recognition_like_added($params['recordid'], $USER->id);
                
                $likecount = $DB->count_records('local_recognition_reactions', [
                    'recordid' => $params['recordid'],
                    'type' => 'like'
                ]);
                
                return array(
                    'error' => false,
                    'data' => array(
                        'success' => true,
                        'message' => '',
                        'data' => array(
                            'likes' => $likecount,
                            'isLiked' => true
                        )
                    )
                );
            }
        }
        
        return array(
            'error' => true,
            'data' => array(
                'success' => false,
                'message' => get_string('likeerror', 'local_recognition'),
                'data' => null
            )
        );
    }

    /**
     * Returns description of add_comment() parameters.
     *
     * @return external_function_parameters
     */
    public static function add_comment_parameters() {
        return new external_function_parameters([
            'recordid' => new external_value(PARAM_INT, 'Record ID'),
            'content' => new external_value(PARAM_TEXT, 'Content for comment'),
        ]);
    }

    /**
     * Returns description of add_comment() result value.
     *
     * @return external_description
     */
    public static function add_comment_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status of the operation'),
            'message' => new external_value(PARAM_TEXT, 'Message describing the result'),
        ]);
    }

    /**
     * Add comment to a record.
     *
     * @param int $recordid Record ID
     * @param string $content Content for comment
     * @return array
     */
    public static function add_comment($recordid, $content) {
        global $DB, $USER;

        $params = self::validate_parameters(self::add_comment_parameters(), 
            array('recordid' => $recordid, 'content' => $content));
        
        $reaction = new stdClass();
        $reaction->recordid = $params['recordid'];
        $reaction->userid = $USER->id;
        $reaction->type = 'comment';
        $reaction->content = $params['content'];
        $reaction->timecreated = time();
        $reaction->timemodified = time();
        
        if ($DB->insert_record('local_recognition_reactions', $reaction)) {
            // Puan ekle
            require_once(__DIR__ . '/../lib.php');
            local_recognition_comment_added($params['recordid'], $USER->id);
            
            $commentcount = $DB->count_records('local_recognition_reactions', [
                'recordid' => $params['recordid'],
                'type' => 'comment'
            ]);
            
            return array(
                'status' => true, 
                'message' => get_string('commentadded', 'local_recognition'),
                'comments' => $commentcount
            );
        }
        
        return array('status' => false, 'message' => get_string('commenterror', 'local_recognition'));
    }

    public static function get_posts() {
        global $DB, $USER, $OUTPUT;

        $context = context_system::instance();
        $fs = get_file_storage();

        $sql = "SELECT r.*, rb.name as badgename, rb.icon as badgeicon,
                       (SELECT COUNT(*) FROM {local_recognition_reactions} rl 
                        WHERE rl.recordid = r.id AND rl.type = 'like') as likes,
                       (SELECT COUNT(*) FROM {local_recognition_reactions} rc 
                        WHERE rc.recordid = r.id AND rc.type = 'comment') as comments,
                       (SELECT 1 FROM {local_recognition_reactions} rl 
                        WHERE rl.recordid = r.id AND rl.type = 'like' AND rl.userid = ?) as isliked
                FROM {local_recognition_records} r
                LEFT JOIN {local_recognition_badges} rb ON r.badgeid = rb.id
                ORDER BY r.timecreated DESC";

        $records = $DB->get_records_sql($sql, array($USER->id));
        $return = array();

        foreach ($records as $record) {
            // Get attached file
            $files = $fs->get_area_files(
                $context->id,
                'local_recognition',
                'record_images',
                $record->id,
                'filename',
                false
            );

            if ($files) {
                $file = reset($files);
                $url = moodle_url::make_pluginfile_url(
                    $context->id,
                    'local_recognition',
                    'record_images',
                    $record->id,
                    '/',
                    $file->get_filename()
                );
                $record->attachment = $url->out();
            } else {
                $record->attachment = '';
            }

            // Format data
            $record->likes = (int)$record->likes;
            $record->comments = (int)$record->comments;
            $record->isliked = !empty($record->isliked);

            $return[] = array(
                'id' => $record->id,
                'fromid' => $record->fromid,
                'message' => $record->message,
                'attachment' => $record->attachment,
                'badgename' => $record->badgename,
                'badgeicon' => $record->badgeicon,
                'timecreated' => (int)$record->timecreated,
                'likes' => $record->likes,
                'comments' => $record->comments,
                'isliked' => $record->isliked
            );
        }

        return array('posts' => $return);
    }
}
