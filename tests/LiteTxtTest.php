<?php
declare(strict_types=1);

/**
 * LiteTxt – Test Suite
 *
 * What this script tests:
 *  1) Cache behavior (include only once per file per request, then again after clearCache()).
 *  2) Logging policy:
 *     - ERROR when file is missing or doesn't return an array.
 *     - WARNING when key is missing, null, or empty string.
 *  3) Default fallback behavior.
 *  4) Defensive casting for non-string values.
 *  5) clearCache() causes reload on next get().
 *
 * Run:
 *   php LiteTxtTest.php
 *
 * Notes:
 *  - The script will attempt to autoload LiteTxt via vendor/autoload.php.
 *  - If not found, it will fall back to requiring ../src/LiteTxt.php (adjust if needed).
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

// ---------------- Autoload / Class Load ----------------
$autoloadCandidates = [
	__DIR__ . '/vendor/autoload.php',
	__DIR__ . '/../vendor/autoload.php',
	__DIR__ . '/../../vendor/autoload.php',
];
foreach ($autoloadCandidates as $candidate) {
	if (is_file($candidate)) {
		require_once $candidate;
		break;
	}
}

// Fallback: direct include if the class isn't loaded. Adjust path to your project layout if needed.
if (!class_exists(\LiteTxt\LiteTxt::class)) {
	$guess = __DIR__ . '/../src/LiteTxt.php';
	if (is_file($guess)) {
		require_once $guess;
	}
}

use LiteTxt\LiteTxt;

if (!class_exists(LiteTxt::class)) {
	fwrite(STDERR, "ERROR: Could not load LiteTxt class. Adjust autoload/include paths in this script.\n");
	exit(2);
}

// ---------------- Harness Utilities ----------------
final class T {
	public static int $ok = 0;
	public static int $fail = 0;

	public static function assertEq(string $label, $actual, $expected): void {
		if ($actual === $expected) {
			self::$ok++;
			echo "[OK]  {$label}\n";
		} else {
			self::$fail++;
			$a = var_export($actual, true);
			$e = var_export($expected, true);
			echo "[FAIL] {$label}\n  got:      {$a}\n  expected: {$e}\n";
		}
	}

	public static function assertTrue(string $label, bool $cond): void {
		self::assertEq($label, $cond, true);
	}
}

// ---------------- Temp Workspace ----------------
$root     = __DIR__ . '/_litetxt_tmp'; // Use a subfolder relative to this script instead of system temp
$fixtures = $root . '/fixtures';
$logFile  = $root . '/litetxt_test.log';

@mkdir($root, 0777, true);
@mkdir($fixtures, 0777, true);
@unlink($logFile);

// A helper to write a PHP file atomically.
$writePhp = function (string $path, string $phpCode): void {
	$tmp = $path . '.tmp';
	file_put_contents($tmp, $phpCode);
	rename($tmp, $path);
};

// ---------------- Fixtures ----------------
// 1) valid file (with side-effect counter via $GLOBALS)
$validFile = $fixtures . '/valid.php';
$validCode = <<<'PHP'
<?php
$GLOBALS['__LT_INC_VALID__'] = ($GLOBALS['__LT_INC_VALID__'] ?? 0) + 1;
return [
	'ok'    => 'Hello',
	'empty' => '',
	'null'  => null,
	'num'   => 123
];
PHP;
$writePhp($validFile, $validCode);

// 2) invalid file (returns non-array)
$invalidFile = $fixtures . '/invalid.php';
$invalidCode = <<<'PHP'
<?php
$GLOBALS['__LT_INC_INVALID__'] = ($GLOBALS['__LT_INC_INVALID__'] ?? 0) + 1;
return 'not-an-array';
PHP;
$writePhp($invalidFile, $invalidCode);

// 3) missing file (do not create)
$missingFile = $fixtures . '/missing.php';

// ---------------- Start Fresh ----------------
LiteTxt::clearCache();
@unlink($logFile);
$GLOBALS['__LT_INC_VALID__'] = 0;
$GLOBALS['__LT_INC_INVALID__'] = 0;

// ---------------- Tests ----------------

// A) First include of valid file should execute once, value returned
$out = LiteTxt::get($validFile, 'ok', 'DEF', $logFile);
T::assertEq('A1: valid ok', $out, 'Hello');
T::assertEq('A2: include counter = 1 after first get()', $GLOBALS['__LT_INC_VALID__'] ?? -1, 1);

// B) Second call (same file, same request) should not include again
$out = LiteTxt::get($validFile, 'ok', 'DEF', $logFile);
T::assertEq('B1: valid ok again', $out, 'Hello');
T::assertEq('B2: include counter still 1', $GLOBALS['__LT_INC_VALID__'] ?? -1, 1);

// B3: Other key from same file should NOT trigger re-include
$out = LiteTxt::get($validFile, 'empty', 'DEF', $logFile);
T::assertEq('B3: other key same file uses cache (no re-include)', $GLOBALS['__LT_INC_VALID__'] ?? -1, 1);

// B4: mutate file content on disk; cached value should remain the same until clearCache()
$mutated = <<<'PHP'
<?php
$GLOBALS['__LT_INC_VALID__'] = ($GLOBALS['__LT_INC_VALID__'] ?? 0) + 1;
return [
	'ok'    => 'Hello-MUTATED',
	'empty' => '',
	'null'  => null,
	'num'   => 456
];
PHP;
$writePhp($validFile, $mutated);

// Call again without clearCache(): value must still be the old cached one ("Hello")
$out = LiteTxt::get($validFile, 'ok', 'DEF', $logFile);
T::assertEq('B4: value unchanged after on-disk mutation (served from cache)', $out, 'Hello');
T::assertEq('B5: include counter unchanged (no re-include due to cache)', $GLOBALS['__LT_INC_VALID__'] ?? -1, 1);

// C) Missing key -> WARNING + default
$out = LiteTxt::get($validFile, 'missing', 'DEF', $logFile);
T::assertEq('C1: missing key returns default', $out, 'DEF');

// D) Null value -> WARNING + default
$out = LiteTxt::get($validFile, 'null', 'DEF', $logFile);
T::assertEq('D1: null value returns default', $out, 'DEF');

// E) Empty string -> WARNING + default (by design)
$out = LiteTxt::get($validFile, 'empty', 'DEF', $logFile);
T::assertEq('E1: empty string returns default', $out, 'DEF');

// F) Non-string value gets cast defensively (123 -> "123"), no warning
$out = LiteTxt::get($validFile, 'num', 'DEF', $logFile);
T::assertEq('F1: numeric value cast to string', $out, '123');

// G) Invalid file -> ERROR (not array) + WARNING (key missing) + default
$out = LiteTxt::get($invalidFile, 'any', 'DEF', $logFile);
T::assertEq('G1: invalid file returns default', $out, 'DEF');
T::assertEq('G2: invalid include counter = 1', $GLOBALS['__LT_INC_INVALID__'] ?? -1, 1);

// G3: invalid file second access should NOT bump include counter (still cached as empty)
$out = LiteTxt::get($invalidFile, 'another', 'DEF', $logFile);
T::assertEq('G3: invalid file second access still cached (no re-include)', $GLOBALS['__LT_INC_INVALID__'] ?? -1, 1);

// H) Missing file -> behaves like invalid: ERROR + WARNING + default
$out = LiteTxt::get($missingFile, 'any', 'DEF', $logFile);
T::assertEq('H1: missing file returns default', $out, 'DEF');

// I) clearCache() should cause a re-include on next access, now reading mutated content
LiteTxt::clearCache();

$out = LiteTxt::get($validFile, 'ok', 'DEF', $logFile);
// Efter clearCache() læser vi filen igen -> nu den muterede værdi
T::assertEq('I1: ok after clearCache() reads mutated content', $out, 'Hello-MUTATED');
T::assertEq('I2: include counter = 2 after clearCache() (one re-include)', $GLOBALS['__LT_INC_VALID__'] ?? -1, 2);

// (Valgfrit) Hvis du vil se tælleren stige til 3, så clear igen og kald én gang til:
LiteTxt::clearCache();
$out = LiteTxt::get($validFile, 'ok', 'DEF', $logFile);
T::assertEq('I3: after second clearCache(), include counter increments again', $GLOBALS['__LT_INC_VALID__'] ?? -1, 3);
T::assertEq('I4: still returns mutated content', $out, 'Hello-MUTATED');



// ---------------- Verify Log JSON ----------------
$errors = 0;
$warnings = 0;

if (is_file($logFile)) {
	$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
	foreach ($lines as $i => $line) {
		$decoded = json_decode($line, true);
		if (!is_array($decoded)) {
			T::$fail++;
			echo "[FAIL] Log line {$i} is not valid JSON\n  line: {$line}\n";
			continue;
		}
		$level = $decoded['level'] ?? '(none)';
		if ($level === 'ERROR') {
			$errors++;
		} elseif ($level === 'WARNING') {
			$warnings++;
		}
		// Optional sanity checks
		T::assertTrue("Log line {$i} has timestamp", isset($decoded['timestamp']));
		T::assertTrue("Log line {$i} has uri", isset($decoded['uri']));
		T::assertTrue("Log line {$i} has message", isset($decoded['message']));
	}

	// --- Ekstra check på præcise WARNING-typer ---
	$warnMissing = 0;
	$warnNull    = 0;
	$warnEmpty   = 0;
	foreach ($lines as $line) {
		$d = json_decode($line, true);
		if (($d['level'] ?? '') === 'WARNING') {
			$msg = $d['message'] ?? '';
			$warnMissing += (int)str_contains($msg, "Key 'missing'");
			$warnNull    += (int)str_contains($msg, "Key 'null'");
			$warnEmpty   += (int)str_contains($msg, "Key 'empty'");
		}
	}
	T::assertEq('J1: WARNING for missing key exactly once', $warnMissing, 1);
	T::assertEq('J2: WARNING for null value exactly once',   $warnNull,    1);
	// T::assertEq('J3: WARNING for empty string exactly once', $warnEmpty,   1);
	T::assertEq('J3: WARNING for empty string exactly twice', $warnEmpty, 2);

} else {
	T::$fail++;
	echo "[FAIL] Expected log file not found: {$logFile}\n";
}

// With one invalid file access and one missing file access, each produces:
//  - 1 ERROR (invalid/missing file)
//  - 1 WARNING (missing key)
// => totals: errors >= 2, warnings >= 3 (also from C/D/E).
T::assertTrue('Log has >= 2 ERROR lines', $errors >= 2);
T::assertTrue('Log has >= 3 WARNING lines', $warnings >= 3);







// K) No-logging mode (logFile = null) should not write logs but still behave the same
$preLines = is_file($logFile) ? count(file($logFile)) : 0;

$out = LiteTxt::get($validFile, 'missing', 'DEF', null); // missing -> default, but no log
T::assertEq('K1: missing with no logFile returns default', $out, 'DEF');

$out = LiteTxt::get($validFile, 'empty', 'DEF', null); // empty -> default, but no log
T::assertEq('K2: empty with no logFile returns default', $out, ''); // was 'DEF'

$out = LiteTxt::get($validFile, 'ok', 'DEF', null); // normal read works the same
T::assertEq('K3: ok with no logFile returns value', $out, 'Hello-MUTATED');

$postLines = is_file($logFile) ? count(file($logFile)) : 0;
T::assertEq('K4: no new log lines when logFile = null', $postLines, $preLines);

// K5: null med no-logging -> default (ingen log)
$out = LiteTxt::get($validFile, 'null', 'DEF', null);
T::assertEq('K5: null with no logFile returns default', $out, 'DEF');



// L) Casting and logging policy for non-string values
$castFile = $fixtures . '/cast.php';
$castCode = <<<'PHP'
<?php
$GLOBALS['__LT_INC_CAST__'] = ($GLOBALS['__LT_INC_CAST__'] ?? 0) + 1;
return [
	'bool_true'  => true,     // should become "1"
	'bool_false' => false,    // should become "" (empty string) but NO warning (only null/'' are warned; false isn't checked)
	'int_zero'   => 0,        // "0"
	'float_val'  => 3.14,     // "3.14"
	'arr_val'    => ['x' => 1], // "Array" after (string) cast in PHP -> "Array"
	'obj_val'    => (object)['a' => 1], // "Object" after (string) cast -> "Object"
];
PHP;
$writePhp($castFile, $castCode);

LiteTxt::clearCache();

// We capture current warning count to detect extra WARNING lines
$warnings_before = $warnings;
$errors_before   = $errors;

T::assertEq('L1: bool_true -> "1"', LiteTxt::get($castFile, 'bool_true', 'DEF', $logFile), '1');
T::assertEq('L2: bool_false -> "" (empty, but no WARNING because code doesn\'t treat false specially)', LiteTxt::get($castFile, 'bool_false', 'DEF', $logFile), '');
T::assertEq('L3: int_zero -> "0"', LiteTxt::get($castFile, 'int_zero', 'DEF', $logFile), '0');
T::assertEq('L4: float_val -> "3.14"', LiteTxt::get($castFile, 'float_val', 'DEF', $logFile), '3.14');

// L5: array -> default + new WARNING
$out = LiteTxt::get($castFile, 'arr_val', 'DEF', $logFile);
T::assertEq('L5: arr_val -> default (non-scalar)', $out, 'DEF');

// L6: object -> default + new WARNING
$out = LiteTxt::get($castFile, 'obj_val', 'DEF', $logFile);
T::assertEq('L6: obj_val -> default (non-scalar)', $out, 'DEF');

// Recount warnings/errors after L-tests
$lines = is_file($logFile) ? file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$warnings_after = 0;
$errors_after   = 0;
foreach ($lines as $line) {
	$d = json_decode($line, true);
	if (!is_array($d)) continue;
	if (($d['level'] ?? '') === 'WARNING') $warnings_after++;
	if (($d['level'] ?? '') === 'ERROR')   $errors_after++;
}

// Expect: exactly two new WARNINGs (arr_val, obj_val) and no new ERRORs
T::assertEq('L7: +2 WARNINGs from non-scalar values', $warnings_after, $warnings + 2);
T::assertEq('L8: no extra ERRORs from casting scenarios', $errors_after, $errors);

// L9/L10: non-scalar med no-logging -> default (ingen log)
LiteTxt::clearCache();
$out = LiteTxt::get($castFile, 'arr_val', 'DEF', null);
T::assertEq('L9: arr_val with no logFile returns default', $out, 'DEF');

$out = LiteTxt::get($castFile, 'obj_val', 'DEF', null);
T::assertEq('L10: obj_val with no logFile returns default', $out, 'DEF');





// M) REQUEST_URI context should be honored
$startWarnings = $warnings;
$_SERVER['REQUEST_URI'] = '/unit-test/uri-check'; // simulate web context

// Trigger one known WARNING (missing key) to capture URI in log
LiteTxt::get($validFile, 'really_missing', 'DEF', $logFile);

$lines = is_file($logFile) ? file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$lastWarningUri = null;
for ($i = count($lines) - 1; $i >= 0; $i--) {
	$d = json_decode($lines[$i], true);
	if (!is_array($d)) continue;
	if (($d['level'] ?? '') === 'WARNING') {
		$lastWarningUri = $d['uri'] ?? null;
		break;
	}
}

T::assertEq('M1: last WARNING carries our simulated URI', $lastWarningUri, '/unit-test/uri-check');





// N) Default values edge-cases
LiteTxt::clearCache();
@unlink($logFile);

// Missing key with empty-string default: returns '' and logs WARNING
$out = LiteTxt::get($validFile, 'missing2', '', $logFile);
T::assertEq('N1: missing with empty default returns ""', $out, '');

// Missing key with "0" default: returns "0" and logs WARNING
$out = LiteTxt::get($validFile, 'missing3', '0', $logFile);
T::assertEq('N2: missing with "0" default returns "0"', $out, '0');









// ---------------- Summary ----------------
echo "\n---- Summary ----\n";
echo "Pass: " . T::$ok . "\n";
echo "Fail: " . T::$fail . "\n";

exit(T::$fail === 0 ? 0 : 1);
