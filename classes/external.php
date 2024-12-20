<?php
// This file is part of Moodle - http://moodle.org/
//
namespace local_recognition;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class external extends \external_api {
    /**
     * Returns description of like_post() parameters.
     * @return \external_function_parameters
     */
    public static function like_post_parameters() {
        return new \external_function_parameters(array(
            'postid' => new \external_value(PARAM_INT, 'Post ID')
        ));
    }

    /**
     * Like or unlike a post
     * @param int $postid Post ID
     * @return array
     */
    public static function like_post($postid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::like_post_parameters(), array('postid' => $postid));
        
        $result = array(
            'success' => false,
            'message' => '',
            'data' => null
        );

        try {
            $post = $DB->get_record('local_recognition_posts', array('id' => $params['postid']), '*', MUST_EXIST);
            $existing = $DB->get_record('local_recognition_likes', array('postid' => $params['postid'], 'userid' => $USER->id));
            
            if ($existing) {
                // Unlike
                $DB->delete_records('local_recognition_likes', array('id' => $existing->id));
                $post->likes--;
                
                // Update user points
                $points = $DB->get_record('local_recognition_points', array('userid' => $post->userid));
                if ($points) {
                    $likepoints = $DB->get_field('local_recognition_settings', 'value', array('name' => 'points_like_received'));
                    $points->total_points -= $likepoints;
                    $points->post_likes_received--;
                    $DB->update_record('local_recognition_points', $points);
                }
                
                $giver_points = $DB->get_record('local_recognition_points', array('userid' => $USER->id));
                if ($giver_points) {
                    $giverpoints = $DB->get_field('local_recognition_settings', 'value', array('name' => 'points_like_given'));
                    $giver_points->total_points -= $giverpoints;
                    $giver_points->likes_given--;
                    $DB->update_record('local_recognition_points', $giver_points);
                }
            } else {
                // Like
                $like = new \stdClass();
                $like->postid = $params['postid'];
                $like->userid = $USER->id;
                $like->timecreated = time();
                $DB->insert_record('local_recognition_likes', $like);
                $post->likes++;
                
                // Update user points
                $points = $DB->get_record('local_recognition_points', array('userid' => $post->userid));
                if (!$points) {
                    $points = new \stdClass();
                    $points->userid = $post->userid;
                    $points->total_points = 0;
                    $points->post_likes_received = 0;
                    $points->post_comments_received = 0;
                    $points->comments_made = 0;
                    $points->likes_given = 0;
                    $points->lastupdate = time();
                }
                $likepoints = $DB->get_field('local_recognition_settings', 'value', array('name' => 'points_like_received'));
                $points->total_points += $likepoints;
                $points->post_likes_received++;
                $DB->update_record('local_recognition_points', $points);
                
                // Update giver points
                $giver_points = $DB->get_record('local_recognition_points', array('userid' => $USER->id));
                if (!$giver_points) {
                    $giver_points = new \stdClass();
                    $giver_points->userid = $USER->id;
                    $giver_points->total_points = 0;
                    $giver_points->post_likes_received = 0;
                    $giver_points->post_comments_received = 0;
                    $giver_points->comments_made = 0;
                    $giver_points->likes_given = 0;
                    $giver_points->lastupdate = time();
                }
                $giverpoints = $DB->get_field('local_recognition_settings', 'value', array('name' => 'points_like_given'));
                $giver_points->total_points += $giverpoints;
                $giver_points->likes_given++;
                $DB->update_record('local_recognition_points', $giver_points);
            }
            
            $DB->update_record('local_recognition_posts', $post);
            
            $result['success'] = true;
            $result['data'] = array(
                'likes' => $post->likes,
                'isLiked' => !$existing
            );
        } catch (\Exception $e) {
            $result['message'] = get_string('likeerror', 'local_recognition');
        }

        return $result;
    }

    /**
     * Returns description of like_post() result value.
     * @return \external_description
     */
    public static function like_post_returns() {
        return new \external_single_structure(array(
            'success' => new \external_value(PARAM_BOOL, 'Success status'),
            'message' => new \external_value(PARAM_TEXT, 'Error message if any'),
            'data' => new \external_single_structure(array(
                'likes' => new \external_value(PARAM_INT, 'Number of likes'),
                'isLiked' => new \external_value(PARAM_BOOL, 'Whether the user has liked the post')
            ), VALUE_OPTIONAL)
        ));
    }

    /**
     * Returns description of add_comment() parameters.
     * @return \external_function_parameters
     */
    public static function add_comment_parameters() {
        return new \external_function_parameters(array(
            'postid' => new \external_value(PARAM_INT, 'Post ID'),
            'content' => new \external_value(PARAM_TEXT, 'Comment content')
        ));
    }

    /**
     * Add a comment to a post
     * @param int $postid Post ID
     * @param string $content Comment content
     * @return array
     */
    public static function add_comment($postid, $content) {
        global $DB, $USER, $OUTPUT;

        $params = self::validate_parameters(self::add_comment_parameters(), array(
            'postid' => $postid,
            'content' => $content
        ));
        
        $result = array(
            'success' => false,
            'message' => '',
            'data' => null
        );

        try {
            $post = $DB->get_record('local_recognition_posts', array('id' => $params['postid']), '*', MUST_EXIST);
            
            // Add comment
            $comment = new \stdClass();
            $comment->postid = $params['postid'];
            $comment->userid = $USER->id;
            $comment->content = $params['content'];
            $comment->timecreated = time();
            $comment->timemodified = time();
            $commentid = $DB->insert_record('local_recognition_comments', $comment);
            
            // Update post comment count
            $post->comments++;
            $DB->update_record('local_recognition_posts', $post);
            
            // Update receiver points
            $points = $DB->get_record('local_recognition_points', array('userid' => $post->userid));
            if (!$points) {
                $points = new \stdClass();
                $points->userid = $post->userid;
                $points->total_points = 0;
                $points->post_likes_received = 0;
                $points->post_comments_received = 0;
                $points->comments_made = 0;
                $points->likes_given = 0;
                $points->lastupdate = time();
            }
            $commentpoints = $DB->get_field('local_recognition_settings', 'value', array('name' => 'points_comment_received'));
            $points->total_points += $commentpoints;
            $points->post_comments_received++;
            $DB->update_record('local_recognition_points', $points);
            
            // Update commenter points
            $commenter_points = $DB->get_record('local_recognition_points', array('userid' => $USER->id));
            if (!$commenter_points) {
                $commenter_points = new \stdClass();
                $commenter_points->userid = $USER->id;
                $commenter_points->total_points = 0;
                $commenter_points->post_likes_received = 0;
                $commenter_points->post_comments_received = 0;
                $commenter_points->comments_made = 0;
                $commenter_points->likes_given = 0;
                $commenter_points->lastupdate = time();
            }
            $commenterpoints = $DB->get_field('local_recognition_settings', 'value', array('name' => 'points_comment_made'));
            $commenter_points->total_points += $commenterpoints;
            $commenter_points->comments_made++;
            $DB->update_record('local_recognition_points', $commenter_points);
            
            // Get comment HTML
            $commentuser = $DB->get_record('user', array('id' => $USER->id));
            $commenthtml = \html_writer::start_div('d-flex mb-2');
            $commenthtml .= $OUTPUT->user_picture($commentuser, array('size' => 25));
            $commenthtml .= \html_writer::start_div('ml-2');
            $commenthtml .= \html_writer::tag('strong', fullname($commentuser));
            $commenthtml .= \html_writer::div($params['content']);
            $commenthtml .= \html_writer::end_div();
            $commenthtml .= \html_writer::end_div();
            
            $result['success'] = true;
            $result['data'] = array(
                'comments' => $post->comments,
                'commentHtml' => $commenthtml
            );
        } catch (\Exception $e) {
            $result['message'] = get_string('commenterror', 'local_recognition');
        }

        return $result;
    }

    /**
     * Returns description of add_comment() result value.
     * @return \external_description
     */
    public static function add_comment_returns() {
        return new \external_single_structure(array(
            'success' => new \external_value(PARAM_BOOL, 'Success status'),
            'message' => new \external_value(PARAM_TEXT, 'Error message if any'),
            'data' => new \external_single_structure(array(
                'comments' => new \external_value(PARAM_INT, 'Number of comments'),
                'commentHtml' => new \external_value(PARAM_RAW, 'HTML for the new comment')
            ), VALUE_OPTIONAL)
        ));
    }
}
