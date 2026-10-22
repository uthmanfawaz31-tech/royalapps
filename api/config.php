<?php
/**
 * Public Store Configuration API
 */

require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDbConnection();
$settings = getAllSettings();

// Fetch Active DNS Servers
$dnsStmt = $pdo->query("SELECT server_url, protocol FROM dns_servers WHERE is_active = 1 ORDER BY sort_order ASC");
$dnsServers = $dnsStmt->fetchAll();

$baseUrl = getBaseUrl();

$data = [
    'store_name' => $settings['store_name'] ?? 'Royal Media',
    'store_subtitle' => $settings['store_subtitle'] ?? 'All your Royal streaming apps in one place',
    'store_logo' => !empty($settings['store_logo']) ? (preg_match('#^(https?://|data:)#i', $settings['store_logo']) ? $settings['store_logo'] : $baseUrl . '/' . ltrim($settings['store_logo'], '/')) : $baseUrl . '/app/assets/store_logo.jpg',
    'store_banner' => !empty($settings['store_banner']) ? (preg_match('#^(https?://|data:)#i', $settings['store_banner']) ? $settings['store_banner'] : $baseUrl . '/' . ltrim($settings['store_banner'], '/')) : '',
    'theme_accent_color' => $settings['theme_accent_color'] ?? '#f59e0b',
    'theme_glow_color' => $settings['theme_glow_color'] ?? '#00d2d3',
    'xtream_enabled' => (bool)($settings['xtream_enabled'] ?? true),
    'onesignal_app_id' => $settings['onesignal_app_id'] ?? '',
    'dns_servers' => array_column($dnsServers, 'server_url'),
    'features' => [
        'apps_ready_count' => (int)$pdo->query("SELECT COUNT(*) FROM apps")->fetchColumn(),
        'one_login' => true,
        'fast_downloads' => true,
        'auto_updates' => true
    ]
];

jsonResponse(['success' => true, 'config' => $data]);
