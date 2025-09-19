<?php
/**
 * LiteTxt - Lightweight static text manager for PHP
 * 
 * Copyright (C) 2025 Lars Grove Mortensen. All rights reserved.
 * 
 * LiteTxt is a single-file PHP utility class for efficiently loading,
 * caching, and retrieving static text entries from PHP array files.
 * It is designed for applications that require lightweight i18n or
 * centralized text management without the overhead of full-scale
 * translation frameworks.
 * 
 * LiteTxt is free software: You can redistribute it and/or modify
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

declare(strict_types=1);

namespace LiteTxt;


/**
 * LiteTxt - Lightweight static text manager for PHP.
 *
 * LiteTxt provides a minimal, dependency-free way to manage static text files
 * (i.e. translations, messages, labels) stored as associative arrays.
 * Files are loaded once per request and cached in memory to minimize I/O.
 *
 * ## Key Features
 * - Zero dependencies: Self-contained, PSR-4 compatible.
 * - File-based storage: Each PHP file returns an array<string,string>.
 * - In-memory cache: Avoids redundant file reads during a request.
 * - Optional logging:
 *   - ERROR if the file does not return a valid array.
 *   - WARNING if a requested key is missing, `null`, or an empty string.
 * - Strict return type: Always returns a string (casts non-strings defensively).
 * - Utility method: `clearCache()` allows cache reset (useful for tests).
 *
 * ## Security Notes
 * - `$filePath` must be trusted application input (never raw user input).
 *   Using unvalidated paths risks path traversal or arbitrary file inclusion.
 * - `$logFile` should point to a writable, controlled log destination.
 * - Files are included as PHP; only use with trusted static array files.
 *
 * ## Typical Usage
 * ```php
 * use LiteTxt\LiteTxt;
 *
 * // texts/en.php
 * return [
 *     'welcome' => 'Welcome!',
 *     'bye'     => 'Goodbye',
 * ];
 *
 * echo LiteTxt::get(__DIR__ . '/texts/en.php', 'welcome'); // "Welcome!"
 * echo LiteTxt::get(__DIR__ . '/texts/en.php', 'missing', 'Default'); // "Default" (+ WARNING if $logFile set)
 *
 * // Between tests or on-demand reloads
 * LiteTxt::clearCache();
 * ```
 *
 * ## API
 * - {@see LiteTxt::get()}        Retrieve a text value by file + key.
 * - {@see LiteTxt::clearCache()} Reset the in-memory cache.
 *
 * ## Limitations
 * - No pluralization, formatting, or parameter substitution.
 * - Empty strings are treated as missing and will log a WARNING (by design).
 * - Not a sandbox: The included file executes in the current scope.
 *   Only use with trusted static array files. 
 *
 */
final class LiteTxt {

	/**
	 * Cache for loaded text files to prevent redundant file reads.
	 * Keys: Absolute file path. Values: Associative array of text keys.
	 *
	 * @var array<string, array<string,string>>
	 */
	private static array $cache = [];

	/**
	 * Prevent instantiation - this is an all-static utility.
	 */
	private function __construct() {}


	/**
	 * Returns a static text value from a specific language/text file.
	 *
	 * Behavior:
	 * - On first access for a given file path, the file is included and its returned array is cached.
	 * - If the file is missing or does not return an array, an ERROR is logged (if `$logFile` is provided)
	 *   and the cache entry is stored as an empty array.
	 * - When retrieving `$key`:
	 *   - If the key is missing, `null`, or an empty string, a WARNING is logged (if `$logFile` is provided),
	 *     and `$default` is returned.
	 *   - Otherwise, the value is returned (cast to string defensively).
	 *
	 * Performance:
	 * - Designed for minimal overhead: One include per file per request; subsequent lookups are in-memory.
	 *
	 * @param string      $filePath Full path to the PHP file returning an array<string,string>.
	 * @param string      $key      The key to retrieve from the loaded array.
	 * @param string      $default  Fallback text if the key is not found or empty.
	 * @param string|null $logFile  Optional log file path. If null, no logging is performed.
	 *
	 * @return string The resolved text string, or $default if the key is missing/null/empty.
	 */
	public static function get(string $filePath, string $key, string $default = '', ?string $logFile = null): string {
		// If we have not seen this file in the current request, load and cache it.
		if (!isset(self::$cache[$filePath])) {
			// Only include if the file exists; otherwise mark as invalid.
			$data = is_file($filePath) ? include $filePath : null;

			// Cache the valid array (or an empty array on invalid file) — avoids re-including bad files per request.
			self::$cache[$filePath] = is_array($data) ? $data : [];

			// File didn’t return an array -> log ERROR (but only if $logFile is set).
			if (!is_array($data) && $logFile !== null) {

				// Capture the current request URI for log context.
				// If running under CLI (no REQUEST_URI available), fall back to a clear marker ("CLI/unknown")
				// so logs remain understandable and not just empty.
				$uri = $_SERVER['REQUEST_URI'] ?? 'CLI/unknown'; 

				// Write a structured ERROR log entry (JSON) to the given log file (file missing/invalid array).
				error_log(json_encode([
					'timestamp' => date('Y-m-d H:i:s'),
					'level'     => 'ERROR',
					'uri'       => $uri,
					'message'   => "LiteTxt: '$filePath' does not return a valid PHP array."
				]) . PHP_EOL, 3, $logFile);
			}
		}


		// If logging is enabled: Log a WARNING when the key is missing, null, or an empty string, then return the default.
		if ((!isset(self::$cache[$filePath][$key]) || self::$cache[$filePath][$key] === null || self::$cache[$filePath][$key] === '') && $logFile !== null) {			
			
			// Capture the current request URI for log context.
			// If running under CLI (no REQUEST_URI available), fall back to a clear marker ("CLI/unknown")
			// so logs remain understandable and not just empty.
			$uri = $_SERVER['REQUEST_URI'] ?? 'CLI/unknown';
			
			// Write a structured WARNING log entry (JSON) to the given log file (key is missing, null, or an empty string).
			error_log(json_encode([
				'timestamp' => date('Y-m-d H:i:s'),
				'level'     => 'WARNING',
				'uri'       => $uri,
				'message'   => "LiteTxt: Key '$key' is missing or empty in '$filePath'."
			]) . PHP_EOL, 3, $logFile);

			return $default;
		}

		// Fetch raw value from cache (or $default if missing)
		// NOTE: we have already returned $default earlier if missing/null/'' with logging enabled.
		$value = self::$cache[$filePath][$key] ?? $default;

		// If value is already string, return it
		if (is_string($value)) {
			return $value;
		}

		// Scalars are safe to cast (int/float/bool)
		if (is_scalar($value)) {
			return (string)$value;
		}

		// If logging is enabled: Arrays/objects/resources are not safe to cast -> log WARNING and return default
		if ($logFile !== null) {
			$uri = $_SERVER['REQUEST_URI'] ?? 'CLI/unknown';
			error_log(json_encode([
				'timestamp' => date('Y-m-d H:i:s'),
				'level'     => 'WARNING',
				'uri'       => $uri,
				'message'   => "LiteTxt: Non-scalar value for key '$key' in '$filePath' (array/object/resource). Returning default."
			]) . PHP_EOL, 3, $logFile);
		}

		return $default;
	}


	/**
	 * Clear the in-memory cache of loaded text files.
	 *
	 * Use-cases:
	 * - Test isolation between scenarios.
	 * - On-demand reload in long-running processes (if applicable).
	 *
	 * @return void
	 */
	public static function clearCache(): void {
        // Reset cache to an empty array; subsequent calls to get() will reload files.
		self::$cache = [];
	}
}
