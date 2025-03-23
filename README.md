# LiteTxt - Lightweight Static Text Manager for PHP

LiteTxt is a **minimalist, high-performance PHP class** for managing static text files with **caching** and **multi-language support**. It is designed to be **fast, efficient, and easy to use**, with no unnecessary overhead.

## 🚀 Features
- ✅ **Ultra-Fast Performance** – Loads each text file only once per request.
- ✅ **Fast file caching** – Retrieves cached text in under half a microsecond.
- ✅ **Minimal Overhead** – Single-file implementation with zero external dependencies.
- ✅ **Multi-language support** – Organize language files by directory structure.
- ✅ **Simple API** – Just call `LiteTxt::get()` with a full file path and a key.
- ✅ **JSON-based error logging** – Detects and logs missing/invalid text files or keys.
- ✅ **PHP 7.4+ compatible** – Lightweight and future-proof.

## 📥 Installation
Just **download** `LiteTxt.php` and include it in your project:
```php
require_once 'LiteTxt.php';
```

## Usage
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
$filePath = __DIR__ . '/app/lang/en/public.php';
echo LiteTxt::get($filePath, 'welcome'); // Outputs: "Welcome to LiteTxt!"
```

### **3️⃣ Handle missing keys/files**
If the key or file is missing, LiteTxt returns a default value:
```php
echo LiteTxt::get($filePath, 'missing_key', 'Default value');
```

### **4️⃣ Use multiple language directories**
Switch between languages dynamically:
```php
$lang = 'da';
$filePath = __DIR__ . "/app/lang/$lang/public.php";
echo LiteTxt::get($filePath, 'welcome'); // Outputs: "Velkommen til LiteTxt!"
```

## 🔧 Configuration
LiteTxt logs errors in JSON format. You can define a custom log path by modifying the constant inside `LiteTxt.php`:
```php
private const LOG_FILE = '/path/to/logs/litetxt_errors.json';
```

## Error logging
If a text file is missing or invalid, LiteTxt logs a JSON-formatted warning.

- **Log file:** configurable via parameter to `LiteTxt::get()`
- **Example log entry:**
```json
{
    "timestamp": "2025-01-23 02:41:37",
    "level": "WARNING",
    "message": "LiteTxt Warning: '/path/to/missing_file.php' does not return a valid PHP array."
}
```

## 💡 Why LiteTxt?

LiteTxt is designed for **blazing-fast static text management** with caching and multi-language support.  
It ensures **minimal disk access**, fast lookups, and **zero dependencies**, making it ideal for high-performance PHP applications.  
Whether you're managing UI text, translations, or content snippets, LiteTxt provides a **simple yet powerful API** with instant retrieval.

## License
LiteTxt is released under the **GNU General Public License v3.0**. See [LICENSE](LICENSE) for details.

## Contributing
Contributions are welcome! Feel free to fork this repository, submit issues, or open a pull request.

## Author
Developed by **Lars Grove Mortensen** © 2025. Feel free to reach out or contribute!

---

🌟 **If you find this library useful, give it a star on GitHub!** 🌟

