<?php
/**
 * Main Web Entry Point & Router
 */

require_once __DIR__ . '/config.php';

// Check if system is installed
if (!file_exists(DB_SQLITE_PATH)) {
    header('Location: install.php');
    exit;
}

// Redirect to Client Store App
header('Location: app/index.html');
exit;
