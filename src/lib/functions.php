<?php
// src/lib/functions.php

function get_setting($key, $default = null) {
    global $mysqli;
    static $settings = [];

    if (empty($settings)) {
        $result = $mysqli->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $settings[$key] ?? $default;
}

function get_content($key, $default = null) {
    global $mysqli;
    static $content = [];

    if (empty($content)) {
        $result = $mysqli->query("SELECT content_key, content_value FROM cms_content");
        while ($row = $result->fetch_assoc()) {
            $content[$row['content_key']] = $row['content_value'];
        }
    }

    return $content[$key] ?? $default;
}

function create_notification($user_id, $message, $link = '#') {
    global $mysqli;
    $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $message, $link);
    $stmt->execute();
}
