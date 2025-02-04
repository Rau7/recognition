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

// Debugging
debugging('Starting leaderboard download process', DEBUG_DEVELOPER);

try {
    // Önce tablo isimlerini kontrol edelim
    $tables = $DB->get_tables();
    debugging('Available tables: ' . print_r($tables, true), DEBUG_DEVELOPER);

    // Tablo yapısını kontrol edelim
    $records_table = $DB->get_columns('local_recognition_records');
    debugging('Recognition records table structure: ' . print_r($records_table, true), DEBUG_DEVELOPER);
    
    $reactions_table = $DB->get_columns('local_recognition_reactions');
    debugging('Recognition reactions table structure: ' . print_r($reactions_table, true), DEBUG_DEVELOPER);

    // Basit bir sorgu ile test edelim
    $test_sql = "SELECT * FROM {local_recognition_records} LIMIT 1";
    $test_record = $DB->get_record_sql($test_sql);
    debugging('Test record: ' . print_r($test_record, true), DEBUG_DEVELOPER);

    // Ana sorguyu basitleştirelim
    $sql = "
        SELECT u.id, u.firstname, u.lastname,
               0 as post_count,
               0 as comment_count,
               0 as likes_given,
               0 as likes_received,
               0 as comments_received
        FROM {user} u
        WHERE u.deleted = 0 AND u.id > 1
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
        // Test için sabit değerler kullanalım
        $data[] = array(
            fullname($user),
            0, // post_count
            0, // comment_count
            0, // likes_given
            0, // likes_received
            0, // comments_received
            0  // total_points
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
