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

// En çok beğeni alan gönderiler
$most_liked_posts = local_recognition_get_most_liked_posts(3);
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header text-white d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-heart text-danger me-2'));
echo html_writer::tag('h5', get_string('mostlikedposts', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::start_tag('ul', array('class' => 'list-unstyled mb-0'));
foreach ($most_liked_posts as $post) {
    $userobj = $DB->get_record('user', array('id' => $post->fromid));
    if ($userobj) {
        echo html_writer::start_tag('li', array('class' => 'mb-2'));
        echo html_writer::start_div('d-flex align-items-center');
        echo html_writer::div($OUTPUT->user_picture($userobj, array('size' => 32)), 'user-avatar me-2');
        echo html_writer::start_div('flex-grow-1');
        echo html_writer::tag('div', fullname($userobj));
        echo html_writer::tag('small', mb_substr($post->message, 0, 30) . '...', array('class' => 'text-muted d-block'));
        echo html_writer::end_div();
        echo html_writer::tag('span', $post->like_count, array('class' => 'badge bg-danger'));
        echo html_writer::end_div();
        echo html_writer::end_tag('li');
    }
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();
echo html_writer::end_div();

// En çok yorum alan gönderiler
$most_commented_posts = local_recognition_get_most_commented_posts(3);
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header text-white d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-comments text-success me-2'));
echo html_writer::tag('h5', get_string('mostcommentedposts', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::start_tag('ul', array('class' => 'list-unstyled mb-0'));
foreach ($most_commented_posts as $post) {
    $userobj = $DB->get_record('user', array('id' => $post->fromid));
    if ($userobj) {
        echo html_writer::start_tag('li', array('class' => 'mb-2'));
        echo html_writer::start_div('d-flex align-items-center');
        echo html_writer::div($OUTPUT->user_picture($userobj, array('size' => 32)), 'user-avatar me-2');
        echo html_writer::start_div('flex-grow-1');
        echo html_writer::tag('div', fullname($userobj));
        echo html_writer::tag('small', mb_substr($post->message, 0, 30) . '...', array('class' => 'text-muted d-block'));
        echo html_writer::end_div();
        echo html_writer::tag('span', $post->comment_count, array('class' => 'badge bg-success'));
        echo html_writer::end_div();
        echo html_writer::end_tag('li');
    }
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();
echo html_writer::end_div();

// En çok beğeni yapan kullanıcılar
$most_liking_users = local_recognition_get_most_liking_users(3);
echo html_writer::start_div('card mt-4');
echo html_writer::start_div('card-header text-white d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-thumbs-up text-info me-2'));
echo html_writer::tag('h5', get_string('mostactivelikers_title', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();

echo html_writer::start_div('card-body p-0');
echo html_writer::start_tag('ul', array('class' => 'list-group list-group-flush'));

foreach ($most_liking_users as $user) {
    $userobj = $DB->get_record('user', array('id' => $user->id));
    echo html_writer::start_tag('li', array('class' => 'list-group-item d-flex align-items-center'));
    echo html_writer::start_div('d-flex align-items-center flex-grow-1');
    echo html_writer::div($OUTPUT->user_picture($userobj, array('size' => 32)), 'user-avatar me-2');
    echo html_writer::start_div();
    echo html_writer::tag('div', fullname($userobj), array('class' => 'fw-bold'));
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::tag('span', $user->like_count, array('class' => 'badge bg-info'));
    echo html_writer::end_tag('li');
}

echo html_writer::end_tag('ul');
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// En çok yorum yapan kullanıcılar
$most_commenting_users = local_recognition_get_most_commenting_users(3);
echo html_writer::start_div('card mt-4');
echo html_writer::start_div('card-header text-white d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-comments text-warning me-2'));
echo html_writer::tag('h5', get_string('mostactivecommenters_title', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();

echo html_writer::start_div('card-body p-0');
echo html_writer::start_tag('ul', array('class' => 'list-group list-group-flush'));

foreach ($most_commenting_users as $user) {
    $userobj = $DB->get_record('user', array('id' => $user->id));
    echo html_writer::start_tag('li', array('class' => 'list-group-item d-flex align-items-center'));
    echo html_writer::start_div('d-flex align-items-center flex-grow-1');
    echo html_writer::div($OUTPUT->user_picture($userobj, array('size' => 32)), 'user-avatar me-2');
    echo html_writer::start_div();
    echo html_writer::tag('div', fullname($userobj), array('class' => 'fw-bold'));
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::tag('span', $user->comment_count, array('class' => 'badge bg-warning text-dark'));
    echo html_writer::end_tag('li');
}

echo html_writer::end_tag('ul');
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

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

// Pagination için değişkenler
$page = optional_param('page', 0, PARAM_INT); // Şu anki sayfa
$perpage = 5; // Sayfa başına gösterilecek post sayısı

$sql_count = "SELECT COUNT(*) 
              FROM {local_recognition_records} r";
$total_posts = $DB->count_records_sql($sql_count);

$sql = "SELECT r.*, rb.name as badgename, rb.icon as badgeicon,
               (SELECT COUNT(*) FROM {local_recognition_reactions} rl 
                WHERE rl.recordid = r.id AND rl.type = 'like') as likes,
               (SELECT COUNT(*) FROM {local_recognition_reactions} rc 
                WHERE rc.recordid = r.id AND rc.type = 'comment') as comments,
               (SELECT 1 FROM {local_recognition_reactions} rl 
                WHERE rl.recordid = r.id AND rl.type = 'like' AND rl.userid = ?) as isliked,
               (SELECT COUNT(*) FROM {local_recognition_reactions} rt 
                WHERE rt.recordid = r.id AND rt.type = 'thanks') as thanks,
               (SELECT 1 FROM {local_recognition_reactions} rt 
                WHERE rt.recordid = r.id AND rt.type = 'thanks' AND rt.userid = ?) as isthanked,
               (SELECT COUNT(*) FROM {local_recognition_reactions} rc 
                WHERE rc.recordid = r.id AND rc.type = 'celebration') as celebration,
               (SELECT 1 FROM {local_recognition_reactions} rc 
                WHERE rc.recordid = r.id AND rc.type = 'celebration' AND rc.userid = ?) as iscelebrated
        FROM {local_recognition_records} r
        LEFT JOIN {local_recognition_badges} rb ON r.badgeid = rb.id
        ORDER BY r.timecreated DESC";

// Pagination için SQL'i düzenle
$sql_paginated = $sql . " LIMIT $perpage OFFSET " . ($page * $perpage);

$records = $DB->get_records_sql($sql_paginated, array($USER->id, $USER->id, $USER->id));

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
    echo html_writer::start_div('card-header post-header position-relative');
    echo html_writer::start_div('d-flex align-items-center');
    
    // User avatar and name
    echo html_writer::div($OUTPUT->user_picture($fromuser, array('size' => 35)), 'user-avatar me-2');
    echo html_writer::start_div();
    echo html_writer::tag('div', fullname($fromuser), array('class' => 'fw-bold'));
    echo html_writer::div(userdate($post->timecreated, $timeformat), 'post-time text-muted');
    echo html_writer::end_div();

    // Düzenleme ve silme ikonları (sadece post sahibi veya admin/yönetici görebilir)
    if ($USER->id == $post->fromid || has_capability('moodle/site:config', context_system::instance())) {
        echo html_writer::start_div('post-actions');
        echo html_writer::start_tag('a', array(
            'href' => '#',
            'class' => 'post-action-btn edit-btn me-2',
            'title' => get_string('edit')
        ));
        echo html_writer::tag('i', '', array('class' => 'fas fa-edit'));
        echo html_writer::end_tag('a');

        echo html_writer::start_tag('a', array(
            'href' => '#',
            'class' => 'post-action-btn delete-btn',
            'title' => get_string('delete')
        ));
        echo html_writer::tag('i', '', array('class' => 'fas fa-trash'));
        echo html_writer::end_tag('a');
        echo html_writer::end_div();
    }

    echo html_writer::end_div();

    // Badge if exists
    if (!empty($post->badgename)) {
        echo html_writer::start_div('ms-auto badge-container');
        echo html_writer::tag('i', '', array('class' => $post->badgeicon));
        echo html_writer::tag('span', $post->badgename, array('class' => 'badge-name'));
        echo html_writer::end_div();
    }

    echo html_writer::end_div(); // end card-header
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
    
    // Post interactions
    echo html_writer::start_div('post-interactions mt-3 d-flex align-items-center');
    
    // Like button
    echo html_writer::start_tag('button', array(
        'class' => 'btn btn-link recognition-like-btn p-0 me-4' . ($post->isliked ? ' liked' : ''),
        'data-record-id' => $post->id
    ));
    echo html_writer::tag('i', '', array('class' => 'fa fa-heart me-1'));
    echo html_writer::span($post->likes, 'likes-count');
    echo html_writer::end_tag('button');
    
    // Thanks button
    echo html_writer::start_tag('button', array(
        'class' => 'btn btn-link recognition-thanks-btn p-0 me-4' . (isset($post->isthanked) && $post->isthanked ? ' thanked' : ''),
        'data-record-id' => $post->id
    ));
    echo html_writer::tag('i', '', array('class' => 'fa fa-hands-helping me-1'));
    echo html_writer::span(isset($post->thanks) ? $post->thanks : 0, 'thanks-count');
    echo html_writer::end_tag('button');
    
    // Celebration button
    echo html_writer::start_tag('button', array(
        'class' => 'btn btn-link recognition-celebration-btn p-0 me-4' . (isset($post->iscelebrated) && $post->iscelebrated ? ' celebrated' : ''),
        'data-record-id' => $post->id
    ));
    echo html_writer::tag('i', '', array('class' => 'fa fa-glass-cheers me-1'));
    echo html_writer::span(isset($post->celebration) ? $post->celebration : 0, 'celebration-count');
    echo html_writer::end_tag('button');
    
    // Comment button
    echo html_writer::start_tag('button', array(
        'class' => 'btn btn-link recognition-comments-btn p-0',
        'data-record-id' => $post->id
    ));
    echo html_writer::tag('i', '', array('class' => 'fa fa-comment me-1'));
    echo html_writer::span($post->comments, 'comments-count');
    echo html_writer::end_tag('button');
    
    echo html_writer::end_div(); // post-interactions

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
        echo html_writer::start_div('comments-section mt-3');
        foreach ($comments as $comment) {
            $commentuser = core_user::get_user($comment->userid);
            
            echo html_writer::start_div('comment-item position-relative mb-2');
            echo html_writer::start_div('d-flex align-items-start');
            
            // Comment user avatar
            echo html_writer::div($OUTPUT->user_picture($commentuser, array('size' => 30)), 'comment-avatar me-2');
            
            // Comment content
            echo html_writer::start_div('comment-content flex-grow-1');
            echo html_writer::tag('div', fullname($commentuser), array('class' => 'fw-bold'));
            echo html_writer::div($comment->content, 'comment-text');
            echo html_writer::div(userdate($comment->timecreated, get_string('strftimerecentfull', 'core_langconfig')), 'comment-time text-muted small');
            echo html_writer::end_div();

            // Düzenleme ve silme ikonları (sadece yorum sahibi veya admin/yönetici görebilir)
            if ($USER->id == $comment->userid || has_capability('moodle/site:config', context_system::instance())) {
                echo html_writer::start_div('comment-actions');
                echo html_writer::start_tag('a', array(
                    'href' => '#',
                    'class' => 'comment-action-btn edit-btn me-2',
                    'title' => get_string('edit')
                ));
                echo html_writer::tag('i', '', array('class' => 'fas fa-edit'));
                echo html_writer::end_tag('a');

                echo html_writer::start_tag('a', array(
                    'href' => '#',
                    'class' => 'comment-action-btn delete-btn',
                    'title' => get_string('delete')
                ));
                echo html_writer::tag('i', '', array('class' => 'fas fa-trash'));
                echo html_writer::end_tag('a');
                echo html_writer::end_div();
            }

            echo html_writer::end_div(); // d-flex
            echo html_writer::end_div(); // comment-item
        }
        echo html_writer::end_div(); // comments-section
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

// Pagination ekleme
if ($total_posts > $perpage) {
    echo html_writer::start_div('pagination-container d-flex justify-content-center mb-4');
    $pagination = new paging_bar($total_posts, $page, $perpage, $PAGE->url);
    echo $OUTPUT->render($pagination);
    echo html_writer::end_div();
}

echo html_writer::end_div(); // col-md-6

// Right column - User Stats
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('user-stats-container mt-4');

// User header
echo html_writer::start_div('card-header bg-primary text-white');
echo html_writer::start_div('d-flex align-items-center');
global $OUTPUT;
$userpicture = $OUTPUT->user_picture($USER, array('size' => 40));
echo html_writer::div($userpicture, 'user-avatar me-2');
echo html_writer::start_div('user-info');
echo html_writer::tag('h5', fullname($USER), array('class' => 'mb-0'));
echo html_writer::tag('small', get_string('rank', 'local_recognition') . ': #' . $user_rank, array('class' => 'text-white-50'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Stats cards
$stats_data = array(
    array(
        'icon' => 'fas fa-hand-holding-heart',
        'title' => 'Appreciation',
        'received' => $user_stats['likes_received'],
        'sent' => $user_stats['likes_given'],
        'color' => 'purple'
    ),
    array(
        'icon' => 'fas fa-praying-hands',
        'title' => 'Thanks',
        'received' => 15,
        'sent' => 20,
        'color' => 'indigo'
    ),
    array(
        'icon' => 'fas fa-star',
        'title' => 'Celebration',
        'received' => 5,
        'sent' => 10,
        'color' => 'violet'
    )
);

foreach ($stats_data as $stat) {
    echo html_writer::start_div('stat-card mb-3');
    echo html_writer::start_div('stat-card-content d-flex align-items-center');
    
    // İkon ve başlık
    echo html_writer::start_div('stat-icon-section me-3');
    echo html_writer::tag('i', '', array('class' => $stat['icon'] . ' stat-icon ' . $stat['color']));
    echo html_writer::tag('div', $stat['title'], array('class' => 'stat-title'));
    echo html_writer::end_div();

    // Received ve Sent sayıları
    echo html_writer::start_div('stat-numbers d-flex align-items-center ms-auto');
    echo html_writer::start_div('received-stats text-center me-4');
    echo html_writer::tag('div', $stat['received'], array('class' => 'stat-number'));
    echo html_writer::tag('div', 'Received', array('class' => 'stat-label'));
    echo html_writer::end_div();

    echo html_writer::start_div('sent-stats text-center');
    echo html_writer::tag('div', $stat['sent'], array('class' => 'stat-number'));
    echo html_writer::tag('div', 'Sent', array('class' => 'stat-label'));
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_div(); // stat-card-content
    echo html_writer::end_div(); // stat-card
}

echo html_writer::end_div(); // user-stats-container

// Top Users Section
echo html_writer::start_div('card mt-4');
echo html_writer::start_div('card-header bg-white d-flex align-items-center');
echo html_writer::tag('i', '', array('class' => 'fas fa-trophy text-warning me-2'));
echo html_writer::tag('h5', get_string('leaderboard', 'local_recognition'), array('class' => 'mb-0'));
echo html_writer::end_div();

echo html_writer::start_div('card-body');

// Top 3 users with special layout
echo html_writer::start_div('top-3-container mb-4');
$top_3_users = array_slice($rankings, 0, 3);

echo html_writer::start_div('row align-items-center');

// First place
echo html_writer::start_div('col-md-4 text-center');
$first_user = core_user::get_user($top_3_users[0]['userid']);
echo html_writer::start_div('position-relative d-inline-block');
global $OUTPUT;
$userpicture = $OUTPUT->user_picture($first_user, array('size' => 65));
echo html_writer::div($userpicture . html_writer::tag('i', '', array('class' => 'fas fa-crown text-warning crown-icon')), 'user-avatar-large mb-2');
echo html_writer::tag('div', fullname($first_user), array('class' => 'fw-bold fs-5'));
echo html_writer::tag('small', number_format($top_3_users[0]['points']) . ' ' . get_string('points', 'local_recognition'), array('class' => 'text-muted d-block'));
echo html_writer::end_div();
echo html_writer::end_div();

// Second place
echo html_writer::start_div('col-md-4 text-center');
$second_user = core_user::get_user($top_3_users[1]['userid']);
echo html_writer::start_div('position-relative d-inline-block');
$userpicture = $OUTPUT->user_picture($second_user, array('size' => 50));
echo html_writer::div($userpicture . html_writer::tag('i', '', array('class' => 'fas fa-medal text-secondary medal-icon')), 'user-avatar-medium mb-2');
echo html_writer::tag('div', fullname($second_user), array('class' => 'fw-bold'));
echo html_writer::tag('small', number_format($top_3_users[1]['points']) . ' ' . get_string('points', 'local_recognition'), array('class' => 'text-muted'));
echo html_writer::end_div();
echo html_writer::end_div();

// Third place
echo html_writer::start_div('col-md-4 text-center');
$third_user = core_user::get_user($top_3_users[2]['userid']);
echo html_writer::start_div('position-relative d-inline-block');
$userpicture = $OUTPUT->user_picture($third_user, array('size' => 50));
echo html_writer::div($userpicture . html_writer::tag('i', '', array('class' => 'fas fa-medal text-bronze medal-icon')), 'user-avatar-medium mb-2');
echo html_writer::tag('div', fullname($third_user), array('class' => 'fw-bold'));
echo html_writer::tag('small', number_format($top_3_users[2]['points']) . ' ' . get_string('points', 'local_recognition'), array('class' => 'text-muted'));
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // end row
echo html_writer::end_div(); // end top-3-container

// CSV indirme butonu (sadece admin ve yöneticiler için)
if (has_capability('moodle/site:config', context_system::instance())) {
    echo html_writer::start_div('text-center mt-3');
    echo html_writer::start_tag('a', array(
        'href' => new moodle_url('/local/recognition/download_leaderboard.php'),
        'class' => 'btn btn-secondary',
        'target' => '_blank'
    ));
    echo html_writer::tag('i', '', array('class' => 'fas fa-download me-2'));
    echo get_string('downloadleaderboard', 'local_recognition');
    echo html_writer::end_tag('a');
    echo html_writer::end_div();
}

// Remaining top 10 users
echo html_writer::start_tag('ul', array('class' => 'list-unstyled mb-0'));
$remaining_users = array_slice($rankings, 3, 7);
foreach ($remaining_users as $index => $rank) {
    $rank_count = $index + 4;
    $user = core_user::get_user($rank['userid']);
    
    echo html_writer::start_tag('li', array('class' => 'mb-3'));
    echo html_writer::start_div('d-flex align-items-center position-relative');
    
    // User Avatar
    $userpicture = $OUTPUT->user_picture($user, array('size' => 40));
    echo html_writer::div($userpicture, 'user-avatar me-2');
    
    // User Info
    echo html_writer::start_div('flex-grow-1');
    echo html_writer::tag('div', fullname($user), array('class' => 'fw-bold'));
    echo html_writer::tag('small', number_format($rank['points']) . ' ' . get_string('points', 'local_recognition'), array('class' => 'text-muted'));
    echo html_writer::end_div();
    
    // Rank Badge
    echo html_writer::tag('span', '#' . $rank_count, array('class' => 'end-0 badge bg-light text-dark border'));
    
    echo html_writer::end_div();
    echo html_writer::end_tag('li');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

echo html_writer::end_div(); // col-md-3

echo html_writer::end_div(); // row
echo html_writer::end_div(); // container-fluid

echo $OUTPUT->footer();
