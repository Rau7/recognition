<?php
// This file is part of Moodle - http://moodle.org/
//
namespace local_recognition;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class external extends \external_api {
    /**
     * Returns description of handle_reaction() parameters.
     *
     * @return \external_function_parameters
     */
    public static function handle_reaction_parameters() {
        return new \external_function_parameters([
            'action' => new \external_value(PARAM_ALPHANUMEXT, 'Action to perform'),
            'recordid' => new \external_value(PARAM_INT, 'Record ID'),
            'type' => new \external_value(PARAM_ALPHANUMEXT, 'Type of reaction'),
            'content' => new \external_value(PARAM_TEXT, 'Content for comment', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Returns description of handle_reaction() result value.
     *
     * @return \external_description
     */
    public static function handle_reaction_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Status of the operation'),
            'message' => new \external_value(PARAM_TEXT, 'Message describing the result'),
            'data' => new \external_single_structure([
                'html' => new \external_value(PARAM_RAW, 'HTML content', VALUE_OPTIONAL),
                'count' => new \external_value(PARAM_INT, 'Count of items', VALUE_OPTIONAL),
                'likes' => new \external_value(PARAM_INT, 'Number of likes', VALUE_OPTIONAL),
                'isLiked' => new \external_value(PARAM_BOOL, 'Whether the user has liked', VALUE_OPTIONAL),
            ], 'Response data', VALUE_OPTIONAL, []),  // Default boş array olarak ayarlandı
        ]);
    }

    /**
     * Handle reactions (likes and comments).
     *
     * @param string $action Action to perform
     * @param int $recordid Record ID
     * @param string $type Type of reaction
     * @param string $content Content for comment
     * @return array
     */
    public static function handle_reaction($action, $recordid, $type, $content = '') {
        global $DB, $USER, $OUTPUT;

        // Parameter validation.
        $params = self::validate_parameters(self::handle_reaction_parameters(), [
            'action' => $action,
            'recordid' => $recordid,
            'type' => $type,
            'content' => $content,
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/recognition:view', $context);

        $result = [
            'success' => false,
            'message' => '',
            'data' => [],  // Default boş array olarak ayarlandı
        ];

        try {
            switch ($params['action']) {
                case 'get_comments':
                    $post = $DB->get_record('local_recognition_records', ['id' => $params['recordid']]);
                    if (!$post) {
                        throw new \moodle_exception('postnotfound', 'local_recognition', '', $params['recordid']);
                    }

                    $sql = "SELECT r.id, r.recordid, r.userid, r.type, r.content, r.timecreated, r.timemodified, 
                                   u.firstname, u.lastname 
                            FROM {local_recognition_reactions} r 
                            JOIN {user} u ON u.id = r.userid 
                            WHERE r.recordid = :recordid AND r.type = :type
                            ORDER BY r.timecreated ASC";
                    
                    $comments = $DB->get_records_sql($sql, ['recordid' => $params['recordid'], 'type' => 'comment']);
                    
                    $html = '';
                    if (empty($comments)) {
                        $html = \html_writer::tag('div', 'Henüz yorum yapılmamış.', ['class' => 'text-muted text-center py-3']);
                    } else {
                        $html .= \html_writer::start_div('comments-list');
                        $index = 0;
                        foreach ($comments as $comment) {
                            $style = "--comment-depth: {$index}";
                            
                            $html .= \html_writer::start_div('comment mb-2', ['style' => $style]);
                            
                            // User avatar with initials
                            $initials = mb_substr($comment->firstname, 0, 1) . mb_substr($comment->lastname, 0, 1);
                            $html .= \html_writer::div($initials, 'user-avatar');
                            
                            $html .= \html_writer::start_div('comment-content');
                            $html .= \html_writer::tag('strong', fullname($comment), ['class' => 'mr-2']);
                            $html .= \html_writer::tag('span', $comment->content);
                            $html .= \html_writer::end_div(); // comment-content
                            $html .= \html_writer::end_div(); // comment
                            
                            $index++;
                        }
                        $html .= \html_writer::end_div(); // comments-list
                    }
                    
                    $result['success'] = true;
                    $result['data'] = [
                        'html' => $html,
                        'count' => count($comments),
                    ];
                    break;

                case 'like':
                    $post = $DB->get_record('local_recognition_records', ['id' => $params['recordid']], '*', MUST_EXIST);
                    $existing = $DB->get_record('local_recognition_reactions', [
                        'recordid' => $params['recordid'],
                        'userid' => $USER->id,
                        'type' => 'like'
                    ]);
                    
                    if ($existing) {
                        // Unlike
                        $DB->delete_records('local_recognition_reactions', ['id' => $existing->id]);
                        $likecount = $DB->count_records('local_recognition_reactions', [
                            'recordid' => $params['recordid'],
                            'type' => 'like'
                        ]);
                        
                        $result['success'] = true;
                        $result['data'] = [
                            'likes' => $likecount,
                            'isLiked' => false,
                        ];
                    } else {
                        // Like
                        $reaction = new \stdClass();
                        $reaction->recordid = $params['recordid'];
                        $reaction->userid = $USER->id;
                        $reaction->type = 'like';
                        $reaction->timecreated = time();
                        $reaction->timemodified = time();
                        
                        $DB->insert_record('local_recognition_reactions', $reaction);
                        $likecount = $DB->count_records('local_recognition_reactions', [
                            'recordid' => $params['recordid'],
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
                        throw new \moodle_exception('emptycomment', 'local_recognition');
                    }

                    $post = $DB->get_record('local_recognition_records', ['id' => $params['recordid']], '*', MUST_EXIST);
                    
                    $reaction = new \stdClass();
                    $reaction->recordid = $params['recordid'];
                    $reaction->userid = $USER->id;
                    $reaction->type = 'comment';
                    $reaction->content = $params['content'];
                    $reaction->timecreated = time();
                    $reaction->timemodified = time();
                    
                    $DB->insert_record('local_recognition_reactions', $reaction);
                    
                    // Yorum HTML'ini oluştur
                    $commenthtml = \html_writer::start_div('comment mb-2');
                    
                    // User avatar with initials
                    $initials = mb_substr($USER->firstname, 0, 1) . mb_substr($USER->lastname, 0, 1);
                    $commenthtml .= \html_writer::div($initials, 'user-avatar');
                    
                    $commenthtml .= \html_writer::start_div('comment-content');
                    $commenthtml .= \html_writer::tag('strong', fullname($USER), ['class' => 'mr-2']);
                    $commenthtml .= \html_writer::tag('span', $params['content']);
                    $commenthtml .= \html_writer::end_div(); // comment-content
                    $commenthtml .= \html_writer::end_div(); // comment
                    
                    $result['success'] = true;
                    $result['data'] = [
                        'html' => $commenthtml,
                    ];
                    break;

                default:
                    throw new \moodle_exception('invalidaction', 'local_recognition');
            }
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            return $result;
        }

        return $result;
    }

    /**
     * Returns description of add_like() parameters.
     *
     * @return \external_function_parameters
     */
    public static function add_like_parameters() {
        return new \external_function_parameters([
            'recordid' => new \external_value(PARAM_INT, 'Record ID'),
        ]);
    }

    /**
     * Returns description of add_like() result value.
     *
     * @return \external_description
     */
    public static function add_like_returns() {
        return new \external_single_structure([
            'error' => new \external_value(PARAM_BOOL, 'Error status of the operation'),
            'data' => new \external_single_structure([
                'success' => new \external_value(PARAM_BOOL, 'Status of the operation'),
                'message' => new \external_value(PARAM_TEXT, 'Message describing the result'),
                'data' => new \external_single_structure([
                    'likes' => new \external_value(PARAM_INT, 'Number of likes'),
                    'isLiked' => new \external_value(PARAM_BOOL, 'Whether the user has liked'),
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
            $reaction = new \stdClass();
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
     * @return \external_function_parameters
     */
    public static function add_comment_parameters() {
        return new \external_function_parameters([
            'recordid' => new \external_value(PARAM_INT, 'Record ID'),
            'content' => new \external_value(PARAM_TEXT, 'Content for comment'),
        ]);
    }

    /**
     * Returns description of add_comment() result value.
     *
     * @return \external_description
     */
    public static function add_comment_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_BOOL, 'Status of the operation'),
            'message' => new \external_value(PARAM_TEXT, 'Message describing the result'),
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
