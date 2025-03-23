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
	// public static function getCache(): array {
		// return self::$cache;
	// }

	/**
	 * Tracks how many times files are actually loaded.
	 *
	 * @var array<string, int> $loadCounter
	 */
	// private static array $loadCounter = [];

	/**
	 * Debug function: Returns the file load count.
	 * 
	 * @return array<string, int> Number of times each file has been loaded.
	 */
	// public static function getLoadCounts(): array {
		// return self::$loadCounter;
	// }

	/**
	 * Retrieves a static text value from a specific language file.
	 * 
	 * If the file has not yet been loaded, it will be included and cached to avoid redundant disk reads.
	 * If the file does not exist or does not return a valid array, an optional warning will be logged.
	 * 
	 * @param string $filePath  	The full path to the PHP language file (including ".php" extension).
	 * @param string $key       	The specific key to retrieve from the loaded file array.
	 * @param string $default   	Optional fallback text to return if the key is not found.
	 * @param string|null $logFile	Optional path to a log file for warnings. If null, logging is disabled.
	 * 
	 * @return string 				The resolved text string, or the fallback default if the key is missing.
	 */
	public static function get(string $filePath, string $key, string $default = '', ?string $logFile = null): string {

		// Check if the file is already cached to avoid redundant file reads
		if (!isset(self::$cache[$filePath])) {

			// Count how many times each file is loaded
			// self::$loadCounter[$filePath] = (self::$loadCounter[$filePath] ?? 0) + 1;

			// Include the file only if it exists; otherwise, set $data to null
			$data = is_file($filePath) ? include $filePath : null;

			// Ensure only valid arrays are cached
			self::$cache[$filePath] = is_array($data) ? $data : [];

			// Check if $data is an array
			// Only log if $logFile is provided, preventing unnecessary logging when disabled
			if (!is_array($data) && $logFile !== null) {
				// Log an error if the file is missing or invalid. 
				error_log(json_encode([
					'timestamp' => date('Y-m-d H:i:s'),
					'level' => 'WARNING',
					'message' => "LiteTxt Warning: '$filePath' does not return a valid PHP array."
				]) . PHP_EOL, 3, $logFile);
			}
		}

		// Check if the requested text exists and is not empty
		if ((!isset(self::$cache[$filePath][$key]) || self::$cache[$filePath][$key] === null || self::$cache[$filePath][$key] === '') && $logFile !== null) {
			error_log(json_encode([
				'timestamp' => date('Y-m-d H:i:s'),
				'level' => 'WARNING',
				'message' => "LiteTxt Warning: Key '$key' is missing or empty in file '$filePath'."
			]) . PHP_EOL, 3, $logFile);
		}

		// Return the requested text if available, otherwise return the default value
		return self::$cache[$filePath][$key] ?? $default;
	}

}
