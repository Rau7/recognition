<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->dirroot . '/local/recognition/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/recognition/index.php'));
$PAGE->set_title(get_string('recognitionwall', 'local_recognition'));
$PAGE->set_heading(get_string('recognitionwall', 'local_recognition'));

// Add required CSS and JS
$PAGE->requires->css('/local/recognition/styles.css');
$PAGE->requires->js_call_amd('local_recognition/main', 'init');

echo $OUTPUT->header();

// Main container with grid layout
echo html_writer::start_div('container-fluid');
echo html_writer::start_div('row');

// Left sidebar (3 columns)
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card mb-4');
echo html_writer::div(get_string('topposts', 'local_recognition'), 'card-header');
echo html_writer::start_div('card-body');

// Most liked posts
$likedposts = $DB->get_records('local_recognition_posts', null, 'likes DESC', '*', 0, 5);
if ($likedposts) {
    echo html_writer::tag('h6', get_string('mostlikedposts', 'local_recognition'));
    echo html_writer::start_tag('ul', array('class' => 'list-unstyled'));
    foreach ($likedposts as $post) {
        $user = $DB->get_record('user', array('id' => $post->userid));
        $content = shorten_text(strip_tags($post->content), 50);
        echo html_writer::tag('li', 
            fullname($user) . ': ' . $content . 
            html_writer::tag('span', ' (' . $post->likes . ' ' . get_string('likes', 'local_recognition') . ')', 
            array('class' => 'text-muted'))
        );
    }
    echo html_writer::end_tag('ul');
}

// Most commented posts
$commentedposts = $DB->get_records('local_recognition_posts', null, 'comments DESC', '*', 0, 5);
if ($commentedposts) {
    echo html_writer::tag('h6', get_string('mostcommentedposts', 'local_recognition'));
    echo html_writer::start_tag('ul', array('class' => 'list-unstyled'));
    foreach ($commentedposts as $post) {
        $user = $DB->get_record('user', array('id' => $post->userid));
        $content = shorten_text(strip_tags($post->content), 50);
        echo html_writer::tag('li', 
            fullname($user) . ': ' . $content . 
            html_writer::tag('span', ' (' . $post->comments . ' ' . get_string('comments', 'local_recognition') . ')', 
            array('class' => 'text-muted'))
        );
    }
    echo html_writer::end_tag('ul');
}

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// Top users by points
echo html_writer::start_div('card mb-4');
echo html_writer::div(get_string('topusers', 'local_recognition'), 'card-header');
echo html_writer::start_div('card-body');

$topusers = $DB->get_records('local_recognition_points', null, 'total_points DESC', '*', 0, 10);
if ($topusers) {
    echo html_writer::start_tag('ul', array('class' => 'list-unstyled'));
    foreach ($topusers as $userpoints) {
        $user = $DB->get_record('user', array('id' => $userpoints->userid));
        echo html_writer::tag('li', 
            fullname($user) . 
            html_writer::tag('span', ' (' . $userpoints->total_points . ' ' . get_string('points', 'local_recognition') . ')', 
            array('class' => 'text-muted'))
        );
    }
    echo html_writer::end_tag('ul');
}

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card
echo html_writer::end_div(); // col-md-3

// Main content area (6 columns)
echo html_writer::start_div('col-md-6');

// Create post form
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::start_tag('form', array('action' => 'post.php', 'method' => 'post', 'enctype' => 'multipart/form-data', 'class' => 'post-form'));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
echo html_writer::tag('textarea', '', array(
    'name' => 'content',
    'class' => 'form-control mb-3',
    'placeholder' => get_string('writepost', 'local_recognition'),
    'rows' => 3
));
echo html_writer::start_div('d-flex justify-content-between align-items-center');
echo html_writer::empty_tag('input', array(
    'type' => 'file',
    'name' => 'image',
    'class' => 'form-control-file',
    'accept' => 'image/*'
));
echo html_writer::tag('button', get_string('post', 'local_recognition'), array(
    'type' => 'submit',
    'class' => 'btn btn-primary'
));
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// Posts list
$posts = $DB->get_records('local_recognition_posts', null, 'timecreated DESC', '*', 0, 20);
foreach ($posts as $post) {
    $user = $DB->get_record('user', array('id' => $post->userid));
    
    echo html_writer::start_div('card mb-4 post', array('data-id' => $post->id));
    echo html_writer::start_div('card-header d-flex align-items-center');
    echo $OUTPUT->user_picture($user, array('size' => 35));
    echo html_writer::div(fullname($user), 'ml-2');
    echo html_writer::end_div();
    
    // Post content
    echo html_writer::start_div('card-body');
    echo html_writer::div($post->content, 'mb-3');
    if ($post->imagepath) {
        echo html_writer::empty_tag('img', array(
            'src' => $post->imagepath,
            'class' => 'img-fluid mb-3',
            'alt' => get_string('postimage', 'local_recognition')
        ));
    }
    
    // Like and comment buttons
    echo html_writer::start_div('d-flex align-items-center mb-3');
    echo html_writer::tag('button', 
        '<i class="fa fa-heart' . ($DB->record_exists('local_recognition_likes', array('postid' => $post->id, 'userid' => $USER->id)) ? '' : '-o') . '"></i> ' . 
        get_string('like', 'local_recognition') . ' (' . $post->likes . ')',
        array('class' => 'btn btn-link like-btn', 'data-id' => $post->id)
    );
    echo html_writer::tag('button', 
        '<i class="fa fa-comment-o"></i> ' . get_string('comment', 'local_recognition') . ' (' . $post->comments . ')',
        array('class' => 'btn btn-link comment-btn', 'data-id' => $post->id)
    );
    echo html_writer::end_div();
    
    // Comments section
    $comments = $DB->get_records('local_recognition_comments', array('postid' => $post->id), 'timecreated DESC');
    if ($comments) {
        echo html_writer::start_div('comments-section');
        foreach ($comments as $comment) {
            $commentuser = $DB->get_record('user', array('id' => $comment->userid));
            echo html_writer::start_div('d-flex mb-2');
            echo $OUTPUT->user_picture($commentuser, array('size' => 25));
            echo html_writer::start_div('ml-2');
            echo html_writer::tag('strong', fullname($commentuser));
            echo html_writer::div($comment->content);
            echo html_writer::end_div();
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }
    
    // Comment form
    echo html_writer::start_tag('form', array('class' => 'comment-form', 'data-id' => $post->id));
    echo html_writer::empty_tag('input', array(
        'type' => 'text',
        'class' => 'form-control',
        'placeholder' => get_string('writecomment', 'local_recognition')
    ));
    echo html_writer::end_tag('form');
    
    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
}

echo html_writer::end_div(); // col-md-6

// Right sidebar (3 columns) - can be used for additional features
echo html_writer::start_div('col-md-3');
// Add additional content here if needed
echo html_writer::end_div();

echo html_writer::end_div(); // row
echo html_writer::end_div(); // container-fluid

echo $OUTPUT->footer();
