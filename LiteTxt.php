<?php
/**
 * LiteTxt - Lightweight static text manager for PHP
 * 
 * Copyright (C) 2025 Lars Grove Mortensen. All rights reserved.
 * 
 * LiteTxt is a single-file PHP class for efficiently handling static text files 
 * with caching and multi-language support.
 * 
 * LiteTxt is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * LiteTxt is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with LiteTxt. If not, see <https://www.gnu.org/licenses/>.
 */

class LiteTxt {
    /**
     * Path to the JSON log file for errors.
     * Example: CITOMNI_SYSTEM_PATH . '/logs/litetxt_errors.json'
     */
    private const LOG_FILE = CITOMNI_SYSTEM_PATH . '/logs/litetxt_errors.json';

    /**
     * Cache for loaded text files to prevent redundant file reads.
     * Stores file paths as keys and their corresponding loaded arrays as values.
     *
     * @var array<string, array<string, string>> $cache
     */
    private static array $cache = [];

    /**
     * Debug function: Returns the internal cache state for testing.
     * 
     * @return array The cached text files and their contents.
     */
    public static function getCache(): array {
        return self::$cache;
    }

    /**
     * Retrieves a static text value from a language file.
     * If the file has not been loaded yet, it will be included and cached.
     * 
     * @param string $basePath The base directory where text files are stored.
     * @param string $file The name of the text file (without ".php" extension).
     * @param string $key The specific key to retrieve from the file.
     * @param string $default The default value if the key is not found.
     * @return string The retrieved text value or the default value.
     */
    public static function get(string $basePath, string $file, string $key, string $default = ''): string {
        // Construct the full file path
        $filePath = (substr($basePath, -1) === '/' ? $basePath : $basePath . '/') . "$file.php";

        // Check if the file is already cached to avoid redundant file reads
        if (!isset(self::$cache[$filePath])) {
			
			// Include the file only if it exists; otherwise, set $data to null
            $data = is_file($filePath) ? include $filePath : null;

            // Ensure only valid arrays are cached
            self::$cache[$filePath] = is_array($data) ? $data : [];

            // Check if $data is an array
			if (!is_array($data)) {
                // Log an error if the file is missing or invalid
                error_log(json_encode([
                    'timestamp' => date('Y-m-d H:i:s'),
                    'level' => 'WARNING',
                    'message' => "LiteTxt Warning: '$filePath' does not return a valid PHP array."
                ]) . PHP_EOL, 3, self::LOG_FILE);
            }
        }

        // Return the requested text if available, otherwise return the default value
        return self::$cache[$filePath][$key] ?? $default;
    }
	
}
