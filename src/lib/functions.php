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
