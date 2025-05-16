<?php
require_once(__DIR__.'/../../config.php');
require_login();

$term = optional_param('q', '', PARAM_TEXT);

global $DB;
$courses = $DB->get_records_sql(
    "SELECT id, fullname FROM {course} WHERE fullname LIKE ? ORDER BY fullname ASC LIMIT 20",
    ['%' . $term . '%']
);

$results = [];
foreach ($courses as $course) {
    $results[] = [
        'id' => $course->id,
        'key' => $course->id,
        'value' => $course->fullname,
        'fullname' => $course->fullname
    ];
}

header('Content-Type: application/json');
echo json_encode($results);
exit;
