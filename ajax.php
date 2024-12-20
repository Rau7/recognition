<?php
// This file is part of Moodle - http://moodle.org/
//
define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot . '/local/recognition/lib.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHA);
$postid = required_param('postid', PARAM_INT);

$result = array(
    'success' => false,
    'message' => '',
    'data' => null
);

switch ($action) {
    case 'like':
        try {
            $post = $DB->get_record('local_recognition_posts', array('id' => $postid), '*', MUST_EXIST);
            $existing = $DB->get_record('local_recognition_likes', array('postid' => $postid, 'userid' => $USER->id));
            
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
                $like = new stdClass();
                $like->postid = $postid;
                $like->userid = $USER->id;
                $like->timecreated = time();
                $DB->insert_record('local_recognition_likes', $like);
                $post->likes++;
                
                // Update user points
                $points = $DB->get_record('local_recognition_points', array('userid' => $post->userid));
                if (!$points) {
                    $points = new stdClass();
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
                    $giver_points = new stdClass();
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
        } catch (Exception $e) {
            $result['message'] = get_string('likeerror', 'local_recognition');
        }
        break;
        
    case 'comment':
        $content = required_param('content', PARAM_TEXT);
        
        try {
            $post = $DB->get_record('local_recognition_posts', array('id' => $postid), '*', MUST_EXIST);
            
            // Add comment
            $comment = new stdClass();
            $comment->postid = $postid;
            $comment->userid = $USER->id;
            $comment->content = $content;
            $comment->timecreated = time();
            $comment->timemodified = time();
            $commentid = $DB->insert_record('local_recognition_comments', $comment);
            
            // Update post comment count
            $post->comments++;
            $DB->update_record('local_recognition_posts', $post);
            
            // Update receiver points
            $points = $DB->get_record('local_recognition_points', array('userid' => $post->userid));
            if (!$points) {
                $points = new stdClass();
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
                $commenter_points = new stdClass();
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
            $commenthtml = html_writer::start_div('d-flex mb-2');
            $commenthtml .= $OUTPUT->user_picture($commentuser, array('size' => 25));
            $commenthtml .= html_writer::start_div('ml-2');
            $commenthtml .= html_writer::tag('strong', fullname($commentuser));
            $commenthtml .= html_writer::div($content);
            $commenthtml .= html_writer::end_div();
            $commenthtml .= html_writer::end_div();
            
            $result['success'] = true;
            $result['data'] = array(
                'comments' => $post->comments,
                'commentHtml' => $commenthtml
            );
        } catch (Exception $e) {
            $result['message'] = get_string('commenterror', 'local_recognition');
        }
        break;
        
    default:
        $result['message'] = get_string('invalidaction', 'local_recognition');
}

echo json_encode($result);
