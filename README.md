# LiteTxt - Lightweight Static Text Manager for PHP

LiteTxt is a **minimalist, high-performance PHP class** for managing static text files with **caching** and **multi-language support**. It is designed to be **fast, efficient, and easy to use**, with no unnecessary overhead.

## 🚀 Features
- ✅ **Ultra-lightweight** – Single-file class, no dependencies.
- ✅ **Fast file caching** – Loads text files only once per request.
- ✅ **Supports multiple languages** – Easily switch between language directories.
- ✅ **Simple API** – Just call `LiteTxt::get()` with your file and key.
- ✅ **JSON-based error logging** – Ensures easy debugging.
- ✅ **Compatible with PHP 7.4+** – Works with legacy and modern PHP versions.

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

## 📜 License
LiteTxt is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.
See the [LICENSE](LICENSE) file for details.

---

### 🎯 Why LiteTxt?
💡 **No bloated frameworks. No unnecessary complexity. Just fast, efficient text management.**

🚀 **Try it out and let me know how it works for you!** 😃

