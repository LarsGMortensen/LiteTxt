# LiteTxt - Lightweight Static Text Manager for PHP

LiteTxt is a **minimalist, high-performance PHP class** for managing static text files with **caching** and **multi-language support**. It is designed to be **fast, efficient, and easy to use**, with no unnecessary overhead.

## 🚀 Features
- ✅ **Ultra-Fast Performance** – Loads text files only once per request, reducing disk access.
- ✅ **Fast file caching** – Loads text files only once per request. Retrieves cached text in under half a microsecond per request.
- ✅ **Minimal Overhead** – Single-file implementation with zero external dependencies.
- ✅ **Supports multiple languages** – Easily switch between language directories.
- ✅ **Simple API** – Just call `LiteTxt::get()` with your file and key.
- ✅ **JSON-based error logging** – Detects and logs invalid or missing text files for easy debugging.
- ✅ **Compatible with PHP 7.4+** – Works seamlessly with **PHP 7.4+** and modern frameworks.

## 📥 Installation
Just **download** `LiteTxt.php` and include it in your project:
```php
require_once 'LiteTxt.php';
```

## 🔧 Usage
### **1️⃣ Set up your text files**
LiteTxt loads text files stored in PHP arrays. Create files like `/app/txts/en/public.php`:
```php
<?php
return [
    'welcome' => 'Welcome to LiteTxt!',
    'footer' => 'This is a test page',
];
```

### **2️⃣ Retrieve a text string**
```php
$langPath = __DIR__ . '/app/txts/en/';
echo LiteTxt::get($langPath, 'public', 'welcome'); // Outputs: "Welcome to LiteTxt!"
```

### **3️⃣ Handle missing keys/files**
If the key or file is missing, LiteTxt returns a default value:
```php
echo LiteTxt::get($langPath, 'public', 'missing_key', 'Default value');
```

### **4️⃣ Use multiple language directories**
Switch between languages dynamically by changing the path:
```php
$langPath = __DIR__ . '/app/txts/da/';
echo LiteTxt::get($langPath, 'public', 'welcome'); // Outputs: "Velkommen til LiteTxt!"
```

## 🛠 Configuration
LiteTxt logs errors in JSON format. You can define a custom log path by modifying the constant inside `LiteTxt.php`:
```php
private const LOG_FILE = '/path/to/logs/litetxt_errors.json';
```

## 🛠 Error Logging
If a text file is missing or invalid, LiteTxt logs a warning in a JSON-formatted error log:
- **Log file:** `SYSTEM_PATH . '/logs/litetxt_errors.json'`
- **Example log entry:**
```json
{
    "timestamp": "2025-01-23 02:00:00",
    "level": "WARNING",
    "message": "LiteTxt Warning: '/path/to/missing_file.php' does not return a valid PHP array."
}
```

## 📊 Benchmark: Caching Performance
A test was conducted with **10,000,000 cached requests** after an initial file load.

| **Metric** | **Result** |
|------------|-----------|
| **First Load** | `0.000000 sec` (PHP's precision limit) |
| **Total Cached Loads (10M ops)** | `4.405006 sec` |
| **Avg Cached Load Time** | `0.000000441 sec` (0.441 microseconds) |

This confirms that **LiteTxt’s caching mechanism is incredibly fast**, ensuring text retrieval with minimal overhead.

## 📜 License
LiteTxt is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.
See the [LICENSE](LICENSE) file for details.

---

### 🎯 Why LiteTxt?
💡 **No bloated frameworks. No unnecessary complexity. Just fast, efficient text management.**

🚀 **Try it out and let me know how it works for you!** 😃

