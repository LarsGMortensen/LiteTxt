<?php
declare(strict_types=1);

/**
 * Text Lookup Benchmark (Browser-based, multi-round)
 *
 * Compares:
 *  - LiteTxt (your library)
 *  - Symfony Translation (ArrayLoader)
 *  - Laminas-I18n (PhpArray)
 *  - NativeArray (pure include + array lookup)
 *
 * Features:
 *  - Runs ROUNDS times (default 30) and reports median + average for ms and ops/s.
 *  - Cold start toggle, warm-up loops, and missing/null/empty mix support.
 *  - No RAM measurements (omitted by request).
 *  - Renders an HTML table and a JSON <textarea> with rounded values.
 *
 * Requirements:
 *  - PHP 8.2+
 *  - Optional: composer require symfony/translation laminas/laminas-i18n
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

const APP_NAME = 'Text Lookup Benchmark';
const VERSION  = '2.0.0';

// ------------------------------------------------------------
// UI Params (GET form)
// ------------------------------------------------------------
$files      = max(1, (int)($_GET['files']      ?? 20));   // number of files
$keysPer    = max(1, (int)($_GET['keysPer']    ?? 200));  // keys per file
$lookups    = max(1, (int)($_GET['lookups']    ?? 5000)); // total random lookups per round
$warmLoops  = max(0, (int)($_GET['warmLoops']  ?? 2));    // warm runs before timed (per round)
$coldStart  = (bool)($_GET['cold'] ?? false);             // cold start simulate
$doMissing  = (bool)($_GET['missing'] ?? false);          // include missing/null/empty lookups mix
$missingRatio = max(0.0, min(0.9, (float)($_GET['missingRatio'] ?? 0.2))); // 0..0.9
$rounds     = max(1, (int)($_GET['rounds']     ?? 30));   // how many rounds
$runNow     = (bool)($_GET['run'] ?? false);

// ------------------------------------------------------------
// Try to load Composer autoload (if present)
// ------------------------------------------------------------
$autoloadCandidates = [
	__DIR__ . '/vendor/autoload.php',
	__DIR__ . '/../vendor/autoload.php',
	__DIR__ . '/../../vendor/autoload.php',
];
foreach ($autoloadCandidates as $a) {
	if (is_file($a)) {
		require_once $a;
		break;
	}
}

// Try to load LiteTxt if not class_exists (adjust path as needed)
$litetxtCandidates = [
	__DIR__ . '/src/LiteTxt.php',
	__DIR__ . '/../src/LiteTxt.php',
];
if (!class_exists(\LiteTxt\LiteTxt::class)) {
	foreach ($litetxtCandidates as $c) {
		if (is_file($c)) {
			require_once $c;
			break;
		}
	}
}

// ------------------------------------------------------------
// Env info
// ------------------------------------------------------------
$env = [
	'php_version'   => PHP_VERSION,
	'sapi'          => PHP_SAPI,
	'os'            => PHP_OS_FAMILY,
	'opcache'       => (function (): string {
		$en = ini_get('opcache.enable');
		$cli = ini_get('opcache.enable_cli');
		return 'enable=' . ($en ? '1' : '0') . ', enable_cli=' . ($cli ? '1' : '0');
	})(),
];

// ------------------------------------------------------------
// Utility: dataset (PHP array files) & helpers
// ------------------------------------------------------------
$root = __DIR__ . '/_bench_tmp';
$fixtures = $root . '/fixtures';
@mkdir($fixtures, 0777, true);

function rrmdir(string $dir): void {
	if (!is_dir($dir)) return;
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($it as $f) {
		$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
	}
	@rmdir($dir);
}

function writePhpArrayFile(string $path, array $map): void {
	$tmp = $path . '.tmp';
	$code = "<?php\nreturn " . var_export($map, true) . ";\n";
	file_put_contents($tmp, $code);
	rename($tmp, $path);
}

function genDataset(string $fixturesDir, int $files, int $keysPer): array {
	rrmdir($fixturesDir);
	@mkdir($fixturesDir, 0777, true);
	$filesOut = [];
	for ($i = 0; $i < $files; $i++) {
		$map = [];
		for ($k = 0; $k < $keysPer; $k++) {
			$key = "k{$k}";
			$map[$key] = "v{$i}_{$k}";
		}
		$path = "{$fixturesDir}/f{$i}.php";
		writePhpArrayFile($path, $map);
		$filesOut[] = $path;
	}
	return $filesOut;
}

// For missing/null/empty mixing we use:
//  - 10% null keys, 10% empty keys (inside files)
//  - missingRatio (GET) of lookups will target missing keys
function genDatasetWithNullEmpty(string $fixturesDir, int $files, int $keysPer): array {
	rrmdir($fixturesDir);
	@mkdir($fixturesDir, 0777, true);
	$filesOut = [];
	for ($i = 0; $i < $files; $i++) {
		$map = [];
		for ($k = 0; $k < $keysPer; $k++) {
			$key = "k{$k}";
			$map[$key] = "v{$i}_{$k}";
		}
		// overwrite ~10% to null, ~10% to empty (bounded by keysPer)
		$nullCnt = max(1, (int)floor($keysPer * 0.1));
		$emptyCnt = max(1, (int)floor($keysPer * 0.1));
		for ($n = 0; $n < $nullCnt; $n++) {
			$idx = $n % $keysPer;
			$map["k{$idx}"] = null;
		}
		for ($e = 0; $e < $emptyCnt; $e++) {
			$idx = ($e + 17) % $keysPer;
			$map["k{$idx}"] = '';
		}

		$path = "{$fixturesDir}/f{$i}.php";
		writePhpArrayFile($path, $map);
		$filesOut[] = $path;
	}
	return $filesOut;
}

// ------------------------------------------------------------
// Driver Interfaces
// ------------------------------------------------------------
interface DriverInterface {
	public function name(): string;
	public function reset(): void; // clear in-process caches
	public function preload(array $files): void; // optional, can be no-op
	public function get(string $file, string $key, string $default=''): string;
}

// NativeArray (baseline)
final class NativeArrayDriver implements DriverInterface {
	private array $cache = [];
	public function name(): string { return 'NativeArray'; }
	public function reset(): void { $this->cache = []; }
	public function preload(array $files): void { /* no-op */ }
	public function get(string $file, string $key, string $default=''): string {
		if (!isset($this->cache[$file])) {
			$this->cache[$file] = is_file($file) ? (include $file) : [];
			if (!is_array($this->cache[$file])) $this->cache[$file] = [];
		}
		return (string)($this->cache[$file][$key] ?? $default);
	}
}

// LiteTxt
final class LiteTxtDriver implements DriverInterface {
	public function name(): string { return 'LiteTxt'; }
	public function reset(): void {
		if (method_exists(\LiteTxt\LiteTxt::class, 'clearCache')) {
			\LiteTxt\LiteTxt::clearCache();
		}
	}
	public function preload(array $files): void { /* no-op */ }
	public function get(string $file, string $key, string $default=''): string {
		return \LiteTxt\LiteTxt::get($file, $key, $default, null); // no logging in benchmarks
	}
}

// Symfony Translation (ArrayLoader)
final class SymfonyArrayDriver implements DriverInterface {
	private ?object $translator = null; // Symfony\Component\Translation\Translator
	private array $loaded = [];
	public function __construct(string $locale='en') {
		if (class_exists(\Symfony\Component\Translation\Translator::class)) {
			$this->translator = new \Symfony\Component\Translation\Translator($locale);
			$this->translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		}
	}
	public function name(): string { return 'Symfony(ArrayLoader)'; }
	public function reset(): void {
		$this->translator = class_exists(\Symfony\Component\Translation\Translator::class)
			? new \Symfony\Component\Translation\Translator('en') : null;
		if ($this->translator) {
			$this->translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		}
		$this->loaded = [];
	}
	public function preload(array $files): void { /* no-op */ }
	public function get(string $file, string $key, string $default=''): string {
		if (!$this->translator) return $default;
		if (!isset($this->loaded[$file])) {
			$catalog = is_file($file) ? include $file : [];
			if (!is_array($catalog)) $catalog = [];
			$this->translator->addResource('array', $catalog, 'en', 'messages');
			$this->loaded[$file] = true;
		}
		$val = $this->translator->trans($key, [], 'messages', 'en');
		return $val === $key ? $default : (string)$val;
	}
}

// Laminas I18n (PhpArray)
final class LaminasPhpArrayDriver implements DriverInterface {
	private ?\Laminas\I18n\Translator\Translator $t = null;
	private array $loaded = [];
	public function __construct(string $locale='en') {
		if (class_exists(\Laminas\I18n\Translator\Translator::class)) {
			$this->t = new \Laminas\I18n\Translator\Translator();
			$this->t->setLocale($locale);
		}
	}
	public function name(): string { return 'Laminas(PhpArray)'; }
	public function reset(): void {
		if (class_exists(\Laminas\I18n\Translator\Translator::class)) {
			$this->t = new \Laminas\I18n\Translator\Translator();
			$this->t->setLocale('en');
		} else {
			$this->t = null;
		}
		$this->loaded = [];
	}
	public function preload(array $files): void { /* no-op */ }
	public function get(string $file, string $key, string $default=''): string {
		if (!$this->t) return $default;
		if (!isset($this->loaded[$file])) {
			$this->t->addTranslationFile('phparray', $file, 'default', 'en');
			$this->loaded[$file] = true;
		}
		$val = $this->t->translate($key, 'default', 'en');
		return $val === $key ? $default : (string)$val;
	}
}

// ------------------------------------------------------------
// Statistics helpers
// ------------------------------------------------------------
final class Stats {
	public static function avg(array $nums): float {
		$cnt = count($nums);
		if ($cnt === 0) return 0.0;
		return array_sum($nums) / $cnt;
	}
	public static function median(array $nums): float {
		$cnt = count($nums);
		if ($cnt === 0) return 0.0;
		sort($nums, SORT_NUMERIC);
		$mid = intdiv($cnt, 2);
		if ($cnt % 2 === 1) {
			return (float)$nums[$mid];
		}
		return ( (float)$nums[$mid - 1] + (float)$nums[$mid] ) / 2.0;
	}
}

// ------------------------------------------------------------
// Benchmark core (per round)
// ------------------------------------------------------------
final class Bench {
	public static function runRound(DriverInterface $drv, array $files, int $lookups, bool $cold, int $warmLoops, float $missingRatio): array {
		$keysPer = self::detectKeysPerFile($files);
		$allKeys = range(0, $keysPer - 1);

		// Sequence of lookups: random files & keys
		$seq = [];
		for ($i = 0; $i < $lookups; $i++) {
			$f = $files[array_rand($files)];
			if ($missingRatio > 0 && mt_rand() / mt_getrandmax() < $missingRatio) {
				$key = 'missing_' . mt_rand(0, 999999);
			} else {
				$key = 'k' . $allKeys[array_rand($allKeys)];
			}
			$seq[] = [$f, $key];
		}

		$drv->reset();

		// Warm-up (not timed)
		for ($w = 0; $w < $warmLoops; $w++) {
			foreach ($seq as [$f, $k]) {
				$drv->get($f, $k, '');
			}
		}

		// Cold option: reset again before timing
		if ($cold) {
			$drv->reset();
		}

		// Timed
		$t0 = hrtime(true);
		foreach ($seq as [$f, $k]) {
			$drv->get($f, $k, '');
		}
		$t1 = hrtime(true);

		$ns = max(1, $t1 - $t0);
		$ms = $ns / 1_000_000;

		return [
			'ms'    => $ms,
			'ops'   => $lookups,
			'ops_s' => $lookups / ($ms / 1000.0),
		];
	}

	private static function detectKeysPerFile(array $files): int {
		if (!$files) return 0;
		$map = @include $files[0];
		if (!is_array($map)) return 0;
		return count($map);
	}
}

// ------------------------------------------------------------
// Assemble drivers that are available
// ------------------------------------------------------------
$drivers = [];
$drivers[] = new NativeArrayDriver();

if (class_exists(\LiteTxt\LiteTxt::class)) {
	$drivers[] = new LiteTxtDriver();
}

if (class_exists(\Symfony\Component\Translation\Translator::class)) {
	$drivers[] = new SymfonyArrayDriver('en');
}

if (class_exists(\Laminas\I18n\Translator\Translator::class)) {
	$drivers[] = new LaminasPhpArrayDriver('en');
}

// ------------------------------------------------------------
// Run (if requested)
// ------------------------------------------------------------
$resultsSummary = []; // per-driver summary rows for table
$jsonExport = [];     // rounded summary for textarea

if ($runNow) {
	$filesList = $doMissing
		? genDatasetWithNullEmpty($fixtures, $files, $keysPer)
		: genDataset($fixtures, $files, $keysPer);

	// For each driver, run N rounds and compute stats
	foreach ($drivers as $drv) {
		$msRuns = [];
		$opsSRuns = [];
		for ($r = 0; $r < $rounds; $r++) {
			$round = Bench::runRound($drv, $filesList, $lookups, $coldStart, $warmLoops, $missingRatio);
			$msRuns[] = $round['ms'];
			$opsSRuns[] = $round['ops_s'];
		}

		$avgMs    = Stats::avg($msRuns);
		$medMs    = Stats::median($msRuns);
		$avgOpsS  = Stats::avg($opsSRuns);
		$medOpsS  = Stats::median($opsSRuns);

		$summary = [
			'label'     => $drv->name(),
			'rounds'    => $rounds,
			'ops'       => $lookups,
			'avg_ms'    => $avgMs,
			'median_ms' => $medMs,
			'avg_ops_s' => $avgOpsS,
			'median_ops_s' => $medOpsS,
		];

		$resultsSummary[] = $summary;

		$jsonExport[] = [
			'label'        => $summary['label'],
			'rounds'       => $summary['rounds'],
			'ops'          => $summary['ops'],
			'avg_ms'       => round($summary['avg_ms'], 2),
			'median_ms'    => round($summary['median_ms'], 2),
			'avg_ops_s'    => round($summary['avg_ops_s'], 1),
			'median_ops_s' => round($summary['median_ops_s'], 1),
		];
	}

	// Sort by avg_ms ascending for table readability
	usort($resultsSummary, fn($a, $b) => $a['avg_ms'] <=> $b['avg_ms']);
}

// ------------------------------------------------------------
// Render HTML
// ------------------------------------------------------------
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(VERSION) ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 16px; line-height: 1.4; }
		h1 { margin: 0 0 8px 0; }
		.env, .panel { background: #f6f8fa; padding: 12px; border-radius: 12px; margin: 12px 0; }
		label { display: inline-block; min-width: 160px; }
		input[type="number"] { width: 110px; }
		table { width: 100%; border-collapse: collapse; margin-top: 12px; }
		th, td { border-bottom: 1px solid #e5e7eb; text-align: left; padding: 8px; }
		th { background: #eef2f7; }
		.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #eef2f7; margin-left: 8px; font-size: 12px; }
		.note { font-size: 12px; color: #475569; }
		.driver { font-weight: 600; }
		textarea { width:100%; height:300px; font-family: monospace; font-size: 13px; }
		button.copy { margin-top: 6px; }
	</style>
</head>
<body>

<h1><?= htmlspecialchars(APP_NAME) ?> <span class="badge">v<?= htmlspecialchars(VERSION) ?></span></h1>
<div class="env">
	<strong>Environment:</strong>
	<div>PHP: <?= htmlspecialchars($env['php_version']) ?> (<?= htmlspecialchars($env['sapi']) ?> on <?= htmlspecialchars($env['os']) ?>)</div>
	<div>OPcache: <?= htmlspecialchars($env['opcache']) ?></div>
</div>

<form class="panel" method="get">
	<div><label>Files</label><input type="number" name="files" value="<?= (int)$files ?>" min="1"></div>
	<div><label>Keys per file</label><input type="number" name="keysPer" value="<?= (int)$keysPer ?>" min="1"></div>
	<div><label>Lookups per round</label><input type="number" name="lookups" value="<?= (int)$lookups ?>" min="1"></div>
	<div><label>Rounds</label><input type="number" name="rounds" value="<?= (int)$rounds ?>" min="1"></div>
	<div><label>Warm-up loops (per round)</label><input type="number" name="warmLoops" value="<?= (int)$warmLoops ?>" min="0"></div>
	<div><label>Cold start</label><input type="checkbox" name="cold" value="1" <?= $coldStart ? 'checked' : '' ?>></div>
	<div><label>Mix missing/null/empty</label><input type="checkbox" name="missing" value="1" <?= $doMissing ? 'checked' : '' ?>></div>
	<div><label>Missing ratio</label><input type="number" step="0.05" min="0" max="0.9" name="missingRatio" value="<?= htmlspecialchars((string)$missingRatio) ?>"></div>
	<div style="margin-top:8px;">
		<button type="submit" name="run" value="1">Run benchmark</button>
	</div>
	<div class="note">Tip: Prøv både med/uden OPcache, og datasets som 10×100, 50×500, 100×1000.</div>
</form>

<div class="panel">
	<strong>Available drivers this run:</strong>
	<ul>
		<?php foreach ($drivers as $d): ?>
			<li class="driver"><?= htmlspecialchars($d->name()) ?></li>
		<?php endforeach; ?>
		<?php if (!$drivers): ?>
			<li><em>No drivers detected.</em></li>
		<?php endif; ?>
	</ul>
	<div class="note">
		Install to enable: <code>composer require symfony/translation laminas/laminas-i18n</code>.
	</div>
</div>

<?php if ($runNow && $resultsSummary): ?>
	<table>
		<thead>
		<tr>
			<th>Driver</th>
			<th>Rounds</th>
			<th>Lookups/round</th>
			<th>Avg ms (total)</th>
			<th>Median ms (total)</th>
			<th>Avg ops/s</th>
			<th>Median ops/s</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($resultsSummary as $r): ?>
			<tr>
				<td><?= htmlspecialchars($r['label']) ?></td>
				<td><?= (int)$r['rounds'] ?></td>
				<td><?= (int)$r['ops'] ?></td>
				<td><?= number_format((float)$r['avg_ms'], 2) ?></td>
				<td><?= number_format((float)$r['median_ms'], 2) ?></td>
				<td><?= number_format((float)$r['avg_ops_s'], 1) ?></td>
				<td><?= number_format((float)$r['median_ops_s'], 1) ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2>JSON output</h2>
	<textarea readonly onfocus="this.select();"><?=
		htmlspecialchars(json_encode($jsonExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES)
	?></textarea>
	<button class="copy" type="button" onclick="(function(){
		const ta = document.querySelector('textarea'); ta.focus(); ta.select();
		try { document.execCommand('copy'); } catch (e) {}
	})();">Copy to clipboard</button>
<?php endif; ?>

</body>
</html>
