# LiteTxt – A Lightweight & High-Performance Static Text Manager for PHP

LiteTxt is a **fast, minimal, single-file** utility for **loading static texts** (labels, messages, i18n) from **PHP array files** with **in-memory caching** and **optional JSON logging**.
It focuses on **robustness**, **low overhead**, and **predictable performance** - with **zero dependencies** (**PHP 7.4+**).

---

## 🚀 Features that make LiteTxt stand out

* **Blazing-fast lookups** – Single include per file per request; subsequent reads are served from memory.
* **Zero deps, tiny API** – One class, static methods; PSR-4 autoloadable.
* **Optional structured logging** – JSON log lines for **invalid files** (ERROR) and **missing/null/empty keys** (WARNING).
* **Strict typing & defensive casting** – Always returns `string`; scalars are safely cast; non-scalars fall back to default + warning.
* **Cache control** – `clearCache()` for tests and long-running processes.
* **Production-ready** – Verified by functional tests and browser-based competitor benchmarks.

---

## 📦 Installation

Via Composer (recommended):

```bash
composer require larsgmortensen/litetxt
```

Or include the class manually (PSR-4):

```php
use LiteTxt\LiteTxt;
require_once __DIR__.'/src/LiteTxt/LiteTxt.php';
```

**PHP 7.4+** is required (typed properties). Benchmarks in the repo were run on PHP 8.2 with OPcache enabled.

---

## 🚀 Quick Start

### 1) Define your texts in PHP files

`texts/en.php`

```php
<?php
return [
	'welcome' => 'Welcome!',
	'bye'     => 'Goodbye',
	'empty'   => '',     // treated as missing
	'null'    => null,   // treated as missing
];
```

### 2) Retrieve texts with LiteTxt

```php
use LiteTxt\LiteTxt;

$en = __DIR__.'/texts/en.php';

// Normal read
echo LiteTxt::get($en, 'welcome');                  // "Welcome!"

// Missing key -> default
echo LiteTxt::get($en, 'missing', 'Default');       // "Default"

// Empty/null treated as missing -> default
echo LiteTxt::get($en, 'empty', 'Fallback');        // "Fallback"
echo LiteTxt::get($en, 'null', 'Fallback');         // "Fallback"

// Optional JSON logging (to a file you control)
$log = __DIR__.'/storage/litetxt.log.jsonl';
echo LiteTxt::get($en, 'missing', 'Default', $log); // writes a WARNING line
```

### Example log line (JSON)

```json
{
  "timestamp": "2025-09-18 12:34:56",
  "level": "WARNING",
  "uri": "/example/page",
  "message": "LiteTxt: Key 'missing' is missing or empty in '/path/texts/en.php'."
}
```

NOTE: In CLI or non-HTTP contexts, `uri` is recorded as `"CLI/unknown"`.

---

## 🧰 API

```php
LiteTxt::get(string $filePath, string $key, string $default = '', ?string $logFile = null): string
LiteTxt::clearCache(): void
```

**Parameters**

* **\$filePath**: Absolute path to a PHP file returning `array<string,string>`.
* **\$key**: Text key to retrieve from the loaded array.
* **\$default**: Fallback string if the key is missing/null/empty.
* **\$logFile**: Optional path to a writable log file. When provided:

  * **ERROR** if file missing / not an array.
  * **WARNING** if key is missing, `null`, or empty string.

**Behavioral notes**

* Values that are **not strings** but **scalar** (int/float/bool) are cast to string.
* **Non-scalar** values (arrays/objects/resources) yield the **default** and log a **WARNING** (if `$logFile` is set).
* Empty string is **treated as missing** (by design).
* Invalid/missing files are cached as **empty arrays** (avoids repeated includes).

---

## 📖 Usage Notes

* **Trust the path.** `$filePath` must be application-controlled, never raw user input. Files are *included* (execute PHP) - only use trusted sources.
* **OPcache recommended.** LiteTxt benefits from OPcache; tests were run with it enabled.
* **Cache lifecycle.** Each unique file path is included at most once per request; `clearCache()` forces reload on next access (useful in tests/daemons).
* **Logging is optional.** If you pass `null` as `$logFile`, no logs are written (behavior is otherwise identical).

---

## 🧪 Tests

LiteTxt ships with a dedicated `/tests` directory:

1. **Functional tests** (`LiteTxtTest.php`)
   Validate caching, defaults, null/empty handling, invalid/missing files, scalar casting, non-scalars, and JSON logging.
   Includes a tiny harness and atomic fixture writer.

2. **Competitor benchmarks (browser-based)** (`benchmark_competitors.php`)
   Compares LiteTxt vs:

   * **NativeArray** (baseline),
   * **Symfony Translation** (`ArrayLoader`),
   * **Laminas I18n** (`PhpArray`).

   Configurable via query string (e.g. `files`, `keysPer`, `lookups`, `rounds`, `warmLoops`, `missingRatio`).
   Outputs both an HTML summary and a JSON block (P50, **P95**, **σ**, ops/s) for copy-paste into docs.

### How to run benchmarks

```bash
# Only if you test against competitors
composer require symfony/translation laminas/laminas-i18n
```

Place `tests/benchmark_competitors.php` in a web-accessible directory and open, e.g.:

```
benchmark_competitors.php?files=5&keysPer=20&lookups=5000&rounds=30&warmLoops=2&missingRatio=0.2&run=1
```

---

## 📊 Benchmark Highlights

Representative “realistic” scenario (PHP 8.2, OPcache ON; 30 rounds × 5,000 lookups; 5 files × 20 keys; warm cache; 20% missing):

* **LiteTxt**: **P50 ≈ 1.11 ms**, **P95 ≈ 1.13 ms**, **σ ≈ 0.023 ms**
* **Laminas PhpArray**: P50 ≈ 1.28 ms, P95 ≈ 1.32 ms
* **Symfony ArrayLoader**: P50 ≈ 3.89 ms, P95 ≈ 3.97 ms

**Takeaway:** LiteTxt runs close to the NativeArray baseline and is typically **\~3.5–4× faster** than Symfony ArrayLoader at P95, with **tight tails** (low variance).
Full methodology and tables (P50/P95/σ) are documented in `/tests/README.md`.

---

## 💡 Why LiteTxt?

* ✨ **Fast** – Minimal overhead; single include per file, then memory lookups.
* ✨ **Predictable** – Tight P95 and low σ -> stable under load.
* ✨ **Simple** – Two methods, zero dependencies, PSR-4.
* ✨ **Robust** – Structured JSON logging, strict typing, defensive casting.
* ✨ **Portable** – Works anywhere PHP 7.4+ runs; excels with OPcache.

---

## 📦 Packagist

LiteTxt on Packagist:
👉 `https://packagist.org/packages/larsgmortensen/litetxt` *(publish when ready)*

---

## 📜 License

LiteTxt is released under the **GNU General Public License v3.0**. See [LICENSE](LICENSE) for details.

---

## 🤝 Contributing

Issues and pull requests are welcome. Please run the functional tests and include benchmark notes for performance-sensitive changes.

---

## ✍️ Author

Developed by **Lars Grove Mortensen** © 2025.
If you find LiteTxt useful, please ⭐ the repo and share!

---

LiteTxt – the lightweight static text manager you can trust 🚀
