<?php
/**
 * Notices & Announcements REST API
 */

require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDbConnection();
$baseUrl = getBaseUrl();

$stmt = $pdo->query("
    SELECT n.id, n.title, n.content, n.app_id, n.created_at,
           a.title as app_title, a.icon_url as app_icon
    FROM notices n
    LEFT JOIN apps a ON n.app_id = a.id
    WHERE n.is_active = 1
    ORDER BY n.id DESC
");

$notices = [];
while ($row = $stmt->fetch()) {
    $notices[] = [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'content' => $row['content'],
        'app_id' => $row['app_id'] ? (int)$row['app_id'] : null,
        'app_title' => $row['app_title'],
        'app_icon' => !empty($row['app_icon']) ? $baseUrl . '/' . ltrim($row['app_icon'], '/') : '',
        'date_formatted' => date('d M Y', strtotime($row['created_at']))
    ];
}

jsonResponse(['success' => true, 'notices' => $notices]);
