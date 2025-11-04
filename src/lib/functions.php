<?php
// src/lib/functions.php

/**
 * Fetches a single setting from the database.
 * Caches settings in a static variable to avoid multiple queries.
 *
 * @param string $key The setting_key to fetch.
 * @param mysqli $db The database connection object.
 * @param mixed $default The default value to return if the key is not found.
 * @return mixed The setting value or the default.
 */
function get_setting($key, $db, $default = '') {
    static $settings = null;

    if ($settings === null) {
        $settings = [];
        $result = $db->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $settings[$key] ?? $default;
}

/**
 * Fetches a single content block from the cms_content table.
 */
function get_content($key, $db, $default = '') {
    static $content = null;

    if ($content === null) {
        $content = [];
        $result = $db->query("SELECT content_key, content_value FROM cms_content");
        while ($row = $result->fetch_assoc()) {
            $content[$row['content_key']] = $row['content_value'];
        }
    }

    return $content[$key] ?? $default;
}

/**
 * Updates or inserts a content block into the cms_content table.
 */
function update_content($key, $value, $db) {
    $stmt = $db->prepare("INSERT INTO cms_content (content_key, content_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE content_value = ?");
    $stmt->bind_param('sss', $key, $value, $value);
    return $stmt->execute();
}
