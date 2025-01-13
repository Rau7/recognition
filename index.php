<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->dirroot . '/local/recognition/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/recognition/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_recognition'));
$PAGE->set_heading(get_string('pluginname', 'local_recognition'));

// Add necessary JavaScript
$PAGE->requires->js_call_amd('local_recognition/main', 'init');

// Add required CSS and JS
$PAGE->requires->css('/local/recognition/styles.css');

echo $OUTPUT->header();

// Get user statistics
$user_stats = local_recognition_calculate_points($USER->id);
$rankings = local_recognition_get_user_rankings();

// Find the user's rank
$user_rank = 0;
foreach ($rankings as $rank) {
    if ($rank['userid'] == $USER->id) {
        $user_rank = $rank['rank'];
        break;
    }
}

// Start main container
echo html_writer::start_div('container-fluid mt-4');
echo html_writer::start_div('row');

// Left column - Top Posts
echo html_writer::start_div('col-md-3');

// İlk 5 kullanıcı
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header bg-primary text-white');
echo html_writer::tag('h5', get_string('topusers', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::start_tag('ul', array('class' => 'list-unstyled mb-0'));
$rank_count = 1;
foreach (array_slice($rankings, 0, 5) as $rank) {
    $user = $DB->get_record('user', array('id' => $rank['userid']));
    echo html_writer::start_tag('li', array('class' => 'mb-2'));
    echo html_writer::start_div('d-flex align-items-center');
    echo html_writer::tag('span', '#' . $rank_count, array('class' => 'badge bg-primary me-2'));
    echo html_writer::tag('span', fullname($user), array('class' => 'flex-grow-1'));
    echo html_writer::tag('span', $rank['points'] . ' pts', array('class' => 'text-muted'));
    echo html_writer::end_div();
    echo html_writer::end_tag('li');
    $rank_count++;
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();
echo html_writer::end_div();

// En çok beğeni alan gönderiler
$most_liked_posts = local_recognition_get_most_liked_posts(3);
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header bg-danger text-white');
echo html_writer::tag('h5', get_string('mostlikedposts', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::start_tag('ul', array('class' => 'list-unstyled mb-0'));
foreach ($most_liked_posts as $post) {
    echo html_writer::start_tag('li', array('class' => 'mb-2'));
    echo html_writer::start_div('d-flex align-items-center');
    echo html_writer::tag('i', '', array('class' => 'fas fa-heart text-danger me-2'));
    echo html_writer::start_div('flex-grow-1');
    echo html_writer::tag('div', fullname((object)['firstname' => $post->firstname, 'lastname' => $post->lastname]));
    echo html_writer::tag('small', mb_substr($post->message, 0, 30) . '...', array('class' => 'text-muted d-block'));
    echo html_writer::end_div();
    echo html_writer::tag('span', $post->like_count, array('class' => 'badge bg-danger'));
    echo html_writer::end_div();
    echo html_writer::end_tag('li');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();
echo html_writer::end_div();

// En çok yorum alan gönderiler
$most_commented_posts = local_recognition_get_most_commented_posts(3);
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header bg-success text-white');
echo html_writer::tag('h5', get_string('mostcommentedposts', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::start_tag('ul', array('class' => 'list-unstyled mb-0'));
foreach ($most_commented_posts as $post) {
    echo html_writer::start_tag('li', array('class' => 'mb-2'));
    echo html_writer::start_div('d-flex align-items-center');
    echo html_writer::tag('i', '', array('class' => 'fas fa-comments text-success me-2'));
    echo html_writer::start_div('flex-grow-1');
    echo html_writer::tag('div', fullname((object)['firstname' => $post->firstname, 'lastname' => $post->lastname]));
    echo html_writer::tag('small', mb_substr($post->message, 0, 30) . '...', array('class' => 'text-muted d-block'));
    echo html_writer::end_div();
    echo html_writer::tag('span', $post->comment_count, array('class' => 'badge bg-success'));
    echo html_writer::end_div();
    echo html_writer::end_tag('li');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();
echo html_writer::end_div();

// En çok beğeni yapan kullanıcılar
$most_liking_users = local_recognition_get_most_liking_users(3);
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header bg-info text-white');
echo html_writer::tag('h5', get_string('mostlikingusers', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::start_tag('ul', array('class' => 'list-unstyled mb-0'));
foreach ($most_liking_users as $user) {
    echo html_writer::start_tag('li', array('class' => 'mb-2'));
    echo html_writer::start_div('d-flex align-items-center');
    echo html_writer::tag('i', '', array('class' => 'fas fa-thumbs-up text-info me-2'));
    echo html_writer::tag('span', fullname((object)['firstname' => $user->firstname, 'lastname' => $user->lastname]), array('class' => 'flex-grow-1'));
    echo html_writer::tag('span', $user->like_count, array('class' => 'badge bg-info'));
    echo html_writer::end_div();
    echo html_writer::end_tag('li');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();
echo html_writer::end_div();

// En çok yorum yapan kullanıcılar
$most_commenting_users = local_recognition_get_most_commenting_users(3);
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header bg-warning text-dark');
echo html_writer::tag('h5', get_string('mostcommentingusers', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::start_tag('ul', array('class' => 'list-unstyled mb-0'));
foreach ($most_commenting_users as $user) {
    echo html_writer::start_tag('li', array('class' => 'mb-2'));
    echo html_writer::start_div('d-flex align-items-center');
    echo html_writer::tag('i', '', array('class' => 'fas fa-comment text-warning me-2'));
    echo html_writer::tag('span', fullname((object)['firstname' => $user->firstname, 'lastname' => $user->lastname]), array('class' => 'flex-grow-1'));
    echo html_writer::tag('span', $user->comment_count, array('class' => 'badge bg-warning text-dark'));
    echo html_writer::end_div();
    echo html_writer::end_tag('li');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // col-md-3 end

// Middle column - Posts
echo html_writer::start_div('col-md-6');

// Create post form
echo html_writer::start_div('card mb-4 post-form-card');
echo html_writer::start_div('card-body');

echo html_writer::start_tag('form', array(
    'action' => new moodle_url('/local/recognition/post.php'),
    'method' => 'post',
    'enctype' => 'multipart/form-data',
    'class' => 'recognition-form'
));

// Hidden sesskey
echo html_writer::empty_tag('input', array(
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey()
));

// Message textarea
echo html_writer::start_div('mb-3');
echo html_writer::tag('textarea', '', array(
    'name' => 'message',
    'class' => 'form-control post-textarea',
    'placeholder' => get_string('writepost', 'local_recognition'),
    'required' => 'required'
));
echo html_writer::end_div();

// Preview area for attachments
echo html_writer::start_div('post-attachments', array('style' => 'display: none;'));
echo html_writer::end_div();

// Post form footer
echo html_writer::start_div('post-form-footer');

// Left side - File upload and badge selection
echo html_writer::start_div('d-flex align-items-center gap-3');

// File upload button
echo html_writer::start_div('file-upload-wrapper');
echo html_writer::empty_tag('input', array(
    'type' => 'file',
    'name' => 'attachment',
    'class' => 'file-input',
    'accept' => '.jpg,.jpeg,.png,.gif',
    'data-max-size' => '5242880' // 5MB
));
echo html_writer::start_tag('button', array(
    'type' => 'button',
    'class' => 'file-upload-btn',
    'onclick' => 'document.querySelector(".file-input").click()'
));
echo html_writer::tag('i', '', array('class' => 'fa fa-image'));
echo html_writer::span(get_string('attachfile', 'local_recognition'));
echo html_writer::end_tag('button');
echo html_writer::end_div();

// Badge selection
$badges = $DB->get_records('local_recognition_badges', array('enabled' => 1));
if ($badges) {
    echo html_writer::start_tag('select', array(
        'name' => 'badgeid',
        'class' => 'badge-select'
    ));
    echo html_writer::tag('option', get_string('selectbadge', 'local_recognition'), array('value' => ''));
    foreach ($badges as $badge) {
        echo html_writer::tag('option', $badge->name, array('value' => $badge->id));
    }
    echo html_writer::end_tag('select');
}

echo html_writer::end_div(); // End left side

// Right side - Submit button
echo html_writer::start_div('ms-auto');
echo html_writer::tag('button', get_string('post', 'local_recognition'), array(
    'type' => 'submit',
    'class' => 'post-submit-btn'
));
echo html_writer::end_div();

echo html_writer::end_div(); // End post-form-footer
echo html_writer::end_tag('form');
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// Display posts
$posts = array();
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

    $posts[] = $record;
}

foreach ($posts as $post) {
    $fromuser = $DB->get_record('user', array('id' => $post->fromid));
    
    echo html_writer::start_div('card mb-4 recognition-post');
    
    // Post header
    echo html_writer::start_div('card-header post-header');
    echo html_writer::start_div('d-flex align-items-center');
    
    // User avatar
    $initials = mb_substr($fromuser->firstname, 0, 1) . mb_substr($fromuser->lastname, 0, 1);
    echo html_writer::div($initials, 'user-avatar');
    
    // User info and time
    echo html_writer::start_div('post-meta ms-2');
    echo html_writer::tag('h6', fullname($fromuser), array('class' => 'mb-0 post-author'));
    $timeformat = get_string('strftimerecentfull', 'core_langconfig');
    echo html_writer::div(userdate($post->timecreated, $timeformat), 'post-time text-muted');
    echo html_writer::end_div();

    // Badge if exists
    if (!empty($post->badgename)) {
        echo html_writer::start_div('ms-auto badge-container');
        echo html_writer::div($post->badgename, 'recognition-badge');
        echo html_writer::end_div();
    }
    
    echo html_writer::end_div(); // d-flex
    echo html_writer::end_div(); // card-header
    
    // Post content
    echo html_writer::start_div('card-body post-content');
    echo html_writer::div($post->message, 'post-message');
    
    // Post image if exists
    if (!empty($post->attachment)) {
        echo html_writer::start_div('post-image-container mt-3');
        echo html_writer::empty_tag('img', array(
            'src' => $post->attachment,
            'alt' => 'Post image',
            'class' => 'post-image img-fluid'
        ));
        echo html_writer::end_div();
    }
    echo html_writer::end_div(); // card-body
    
    // Post footer
    echo html_writer::start_div('card-footer post-footer');
    echo html_writer::start_div('d-flex align-items-center');
    
    // Like button
    echo html_writer::start_tag('button', array(
        'class' => 'btn btn-link recognition-like-btn p-0 me-4' . ($post->isliked ? ' liked' : ''),
        'data-record-id' => $post->id
    ));
    echo html_writer::tag('i', '', array('class' => 'fa fa-heart me-1'));
    echo html_writer::span($post->likes, 'likes-count');
    echo html_writer::end_tag('button');
    
    // Comment button
    echo html_writer::start_tag('button', array(
        'class' => 'btn btn-link recognition-comments-btn p-0',
        'data-record-id' => $post->id
    ));
    echo html_writer::tag('i', '', array('class' => 'fa fa-comment me-1'));
    echo html_writer::span($post->comments, 'comments-count');
    echo html_writer::end_tag('button');
    
    echo html_writer::end_div(); // d-flex
    echo html_writer::end_div(); // card-footer
    
    // Comments section (initially hidden)
    echo html_writer::start_div('recognition-comments mt-3', array('id' => 'comments-' . $post->id, 'style' => 'display: none;'));
    
    // Get comments for this post
    $comments = $DB->get_records_sql(
        "SELECT c.*, u.firstname, u.lastname 
         FROM {local_recognition_reactions} c
         JOIN {user} u ON u.id = c.userid
         WHERE c.recordid = ? AND c.type = 'comment'
         ORDER BY c.timecreated ASC",
        array($post->id)
    );

    if ($comments) {
        echo html_writer::start_div('comments-list');
        foreach ($comments as $comment) {
            // Hesapla yorum derinliğini (varsayılan 0)
            $depth = isset($comment->parent_id) ? 1 : 0;
            
            echo html_writer::start_div('comment mb-2', array('style' => '--comment-depth: ' . $depth));
            
            // User avatar
            $initials = mb_substr($comment->firstname, 0, 1) . mb_substr($comment->lastname, 0, 1);
            echo html_writer::div($initials, 'user-avatar');
            
            // Comment content
            echo html_writer::start_div('comment-content');
            echo html_writer::tag('strong', fullname($comment), array('class' => 'mr-2'));
            echo html_writer::tag('span', $comment->content);
            echo html_writer::end_div();
            
            echo html_writer::end_div(); // comment
        }
        echo html_writer::end_div(); // comments-list
    }

    // Comment form
    echo html_writer::start_tag('form', array(
        'class' => 'recognition-comment-form mt-3',
        'data-record-id' => $post->id
    ));
    echo html_writer::start_div('input-group');
    echo html_writer::empty_tag('input', array(
        'type' => 'text',
        'name' => 'content',
        'class' => 'form-control',
        'placeholder' => get_string('writecomment', 'local_recognition')
    ));
    echo html_writer::start_div('input-group-append');
    echo html_writer::tag('button', get_string('comment', 'local_recognition'), array(
        'type' => 'submit',
        'class' => 'btn'
    ));
    echo html_writer::end_div(); // input-group-append
    echo html_writer::end_div(); // input-group
    echo html_writer::end_tag('form');
    
    echo html_writer::end_div(); // recognition-comments
    
    echo html_writer::end_div(); // card
}

echo html_writer::end_div(); // col-md-6

// Right column - User Stats
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card user-stats-card');

// User header
echo html_writer::start_div('card-header bg-primary text-white');
echo html_writer::start_div('d-flex align-items-center');
echo html_writer::div(mb_substr($USER->firstname, 0, 1) . mb_substr($USER->lastname, 0, 1), 'user-avatar me-2');
echo html_writer::start_div('user-info');
echo html_writer::tag('h5', fullname($USER), array('class' => 'mb-0'));
echo html_writer::tag('small', get_string('rank', 'local_recognition') . ': #' . $user_rank, array('class' => 'text-white-50'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Stats list
echo html_writer::start_div('card-body');
echo html_writer::start_tag('ul', array('class' => 'list-unstyled mb-0'));

// Points
echo html_writer::start_tag('li', array('class' => 'mb-3'));
echo html_writer::start_div('d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-star text-warning me-2'));
echo html_writer::start_div('flex-grow-1');
echo html_writer::tag('h6', get_string('points', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::tag('small', number_format($user_stats['points']), array('class' => 'text-muted'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('li');

// Posts
echo html_writer::start_tag('li', array('class' => 'mb-3'));
echo html_writer::start_div('d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-file-alt text-primary me-2'));
echo html_writer::start_div('flex-grow-1');
echo html_writer::tag('h6', get_string('posts', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::tag('small', number_format($user_stats['posts']), array('class' => 'text-muted'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('li');

// Comments
echo html_writer::start_tag('li', array('class' => 'mb-3'));
echo html_writer::start_div('d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-comments text-success me-2'));
echo html_writer::start_div('flex-grow-1');
echo html_writer::tag('h6', get_string('comments', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::tag('small', number_format($user_stats['comments_given']), array('class' => 'text-muted'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('li');

// Likes Given
echo html_writer::start_tag('li', array('class' => 'mb-3'));
echo html_writer::start_div('d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-heart text-danger me-2'));
echo html_writer::start_div('flex-grow-1');
echo html_writer::tag('h6', get_string('likesgiven', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::tag('small', number_format($user_stats['likes_given']), array('class' => 'text-muted'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('li');

// Likes Received
echo html_writer::start_tag('li', array('class' => 'mb-3'));
echo html_writer::start_div('d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-heart text-danger me-2'));
echo html_writer::start_div('flex-grow-1');
echo html_writer::tag('h6', get_string('likesreceived', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::tag('small', number_format($user_stats['likes_received']), array('class' => 'text-muted'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('li');

echo html_writer::end_tag('ul');
echo html_writer::end_div(); // card-body

echo html_writer::end_div(); // user-stats-card
echo html_writer::end_div(); // col-md-3

echo html_writer::end_div(); // row
echo html_writer::end_div(); // container-fluid

echo $OUTPUT->footer();
