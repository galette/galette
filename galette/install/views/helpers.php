<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * Helper utilities for installer views
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

/**
 * Escape HTML and return safe string
 *
 * @param string $text Text to escape
 */
function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Check if current request is POST
 */
function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Get POST value with default
 *
 * @param string $key     POST key
 * @param mixed  $default Default value if not found
 */
function getPost(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

/**
 * Check if POST key exists and is set
 *
 * @param string $key POST key
 */
function hasPost(string $key): bool
{
    return isset($_POST[$key]);
}

/**
 * Redirect to installer page
 *
 * @param array<string, string> $params Query parameters
 */
function redirectToInstaller(array $params = []): never
{
    $url = 'installer.php';
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Format file size for display
 *
 * @param int $bytes File size in bytes
 */
function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    $size = (float)$bytes;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return round($size, 2) . ' ' . $units[$unitIndex];
}

/**
 * Get PHP configuration value formatted for display
 *
 * @param string $key Configuration key
 */
function getPhpConfig(string $key): string
{
    $value = ini_get($key);
    if ($value === false) {
        return _T("Not set");
    }
    if ($value === '') {
        return _T("Empty");
    }
    return $value;
}

/**
 * Check if a PHP extension is loaded
 *
 * @param string $extension Extension name
 */
function isExtensionLoaded(string $extension): bool
{
    return extension_loaded($extension);
}

/**
 * Render a simple loading spinner
 *
 * @param string $message Message to display
 */
function renderLoadingSpinner(string $message = ''): void
{
    echo '<div class="ui active centered inline loader"></div>';
    if ($message !== '') {
        echo '<p class="center aligned">' . escapeHtml($message) . '</p>';
    }
}

/**
 * Get error class for form field
 *
 * @param array<string> $errors   List of error messages
 * @param string        $fieldKey Field key to check
 */
function getFieldErrorClass(array $errors, string $fieldKey): string
{
    foreach ($errors as $error) {
        if (str_contains($error, $fieldKey) || str_contains(strtolower($error), strtolower($fieldKey))) {
            return ' error';
        }
    }
    return '';
}

/**
 * Render debug information (only if debug mode is enabled)
 *
 * @param mixed $data Data to debug
 */
function renderDebugInfo(mixed $data): void
{
    if (defined('GALETTE_DEBUG') && GALETTE_DEBUG) {
        echo '<div class="ui message">';
        echo '<div class="header">Debug Information</div>';
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        echo '</div>';
    }
}
