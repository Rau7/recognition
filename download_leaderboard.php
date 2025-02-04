<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/recognition/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->libdir . '/outputlib.php');

require_login();

// Yetki kontrolü
if (!has_capability('moodle/site:config', context_system::instance())) {
    throw new moodle_exception('nopermissions', 'error', '', 'download leaderboard');
}

try {
    // Ana sorgu - tüm istatistikleri tek sorguda al
    $sql = "
        SELECT u.id, u.firstname, u.lastname,
               (SELECT COUNT(*) 
                FROM {local_recognition_records} 
                WHERE fromid = u.id) as post_count,
               (SELECT COUNT(*) 
                FROM {local_recognition_reactions} 
                WHERE userid = u.id AND type = 'comment') as comment_count,
               (SELECT COUNT(*) 
                FROM {local_recognition_reactions} 
                WHERE userid = u.id AND type = 'like') as likes_given,
               (SELECT COUNT(*) 
                FROM {local_recognition_records} r
                JOIN {local_recognition_reactions} lr ON lr.recordid = r.id 
                WHERE lr.type = 'like' AND r.fromid = u.id) as likes_received,
               (SELECT COUNT(*) 
                FROM {local_recognition_records} r
                JOIN {local_recognition_reactions} lr ON lr.recordid = r.id 
                WHERE lr.type = 'comment' AND r.fromid = u.id) as comments_received
        FROM {user} u
        WHERE u.deleted = 0 AND u.id > 1
        ORDER BY post_count DESC, likes_received DESC
        LIMIT 10";

    debugging('Executing SQL query: ' . $sql, DEBUG_DEVELOPER);

    // Sorguyu çalıştır
    $users = $DB->get_records_sql($sql);
    
    if (!$users) {
        debugging('No users found or query failed', DEBUG_DEVELOPER);
        throw new moodle_exception('No users found or query failed');
    }

    debugging('Found ' . count($users) . ' users', DEBUG_DEVELOPER);

    // CSV başlıkları
    $headers = array(
        get_string('fullname', 'local_recognition'),
        get_string('postcount', 'local_recognition'),
        get_string('commentcount', 'local_recognition'),
        get_string('likesgiven', 'local_recognition'),
        get_string('likesreceived', 'local_recognition'),
        get_string('commentsreceived', 'local_recognition'),
        get_string('totalpoints', 'local_recognition')
    );

    // CSV verilerini hazırla
    $data = array();
    foreach ($users as $user) {
        // Toplam puanı hesapla (lib.php'deki sabitler kullanılarak)
        $total_points = ($user->post_count * RECOGNITION_POINTS_POST) + 
                       ($user->comment_count * RECOGNITION_POINTS_COMMENT_GIVEN) + 
                       ($user->likes_given * RECOGNITION_POINTS_LIKE_GIVEN) + 
                       ($user->likes_received * RECOGNITION_POINTS_LIKE_RECEIVED) + 
                       ($user->comments_received * RECOGNITION_POINTS_COMMENT_RECEIVED);

        $data[] = array(
            fullname($user),
            (int)$user->post_count,
            (int)$user->comment_count,
            (int)$user->likes_given,
            (int)$user->likes_received,
            (int)$user->comments_received,
            (int)$total_points
        );
    }

    // CSV dosyasını oluştur ve indir
    $filename = 'recognition_leaderboard_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);

} catch (moodle_exception $e) {
    debugging('Error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->footer();
}

exit();
