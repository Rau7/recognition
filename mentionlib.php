<?php
require_once(__DIR__ . '/../../config.php');
/**
 * Mesaj içindeki @mention'ları kullanıcı profiline linkler.
 * @param string $message
 * @return string
 */
function local_recognition_format_mentions($message) {
    global $DB;
    // Regex: @ ile başlayan, en az bir isim ve soyisim (unicode, boşluk dahil)
    return preg_replace_callback('/@([\p{L} .\'-]{2,})/u', function ($matches) use ($DB) {
        $name = trim($matches[1]);
        // Adı ve soyadı ayır
        $parts = preg_split('/\s+/', $name, 2);
        if (count($parts) < 1) return $matches[0];
        $firstname = $parts[0];
        $lastname = isset($parts[1]) ? $parts[1] : '';
        // Kullanıcıyı bul
        $params = ['firstname' => $firstname];
        $sql = "SELECT id, firstname, lastname FROM {user} WHERE deleted = 0 AND suspended = 0 AND confirmed = 1 AND firstname = ?";
        if ($lastname !== '') {
            $sql .= " AND lastname = ?";
            $params[] = $lastname;
        }
        $user = $DB->get_record_sql($sql, $params);
        if ($user) {
            $url = new moodle_url('/user/profile.php', ['id' => $user->id]);
            return '<a href="' . $url . '" class="mention-link" target="_blank">@' . fullname($user) . '</a>';
        }
        return $matches[0];
    }, $message);
}
