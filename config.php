<?php
// Базовые настройки безопасности
function setSecurityHeaders() {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.ckeditor.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.ckeditor.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'");
}

// CSRF защита
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'u3111049_YoungForever');
define('DB_USER', 'u3111049_ghouliha');
define('DB_PASS', '010206Danilka');

// Проверка подключения к базе данных
function checkDatabaseConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        // Попытка подключения без указания базы данных для проверки доступа
        try {
            $tempDb = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, $options);
            logError("Успешное подключение к серверу MySQL");
        } catch (PDOException $e) {
            logError("Ошибка подключения к серверу MySQL: " . $e->getMessage());
            return false;
        }

        // Попытка подключения к конкретной базе данных
        try {
            $db = new PDO($dsn, DB_USER, DB_PASS, $options);
            logError("Успешное подключение к базе данных " . DB_NAME);
            return true;
        } catch (PDOException $e) {
            logError("Ошибка подключения к базе данных " . DB_NAME . ": " . $e->getMessage());
            return false;
        }
    } catch (Exception $e) {
        logError("Неожиданная ошибка при подключении к базе данных: " . $e->getMessage());
        return false;
    }
}

// Настройки загрузки файлов
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Настройки сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 1800); // 30 минут

// Старт сессии с проверкой
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка и обновление времени активности сессии
function checkSessionActivity() {
    $timeout = 1800; // 30 минут
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // Сессия истекла
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// Проверка авторизации пользователя
function checkAuth() {
    // Проверяем наличие необходимых данных в сессии
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        return false;
    }

    // Проверяем активность сессии
    if (!checkSessionActivity()) {
        return false;
    }

    // Проверяем IP пользователя (опционально)
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
        session_unset();
        session_destroy();
        return false;
    }

    // Проверяем User-Agent (опционально)
    if (isset($_SESSION['user_agent']) && isset($_SERVER['HTTP_USER_AGENT']) && 
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_unset();
        session_destroy();
        return false;
    }

    return true;
}

// Инициализация сессии при входе
function initUserSession($userId, $username) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['last_activity'] = time();
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    }
    
    // Генерируем новый ID сессии для предотвращения фиксации сессии
    session_regenerate_id(true);
}

// Выход из системы
function logout() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// Проверка роли пользователя
function checkRole($requiredRole) {
    if (!checkAuth()) {
        return false;
    }
    
    try {
        $db = new Database();
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['role'] === $requiredRole;
    } catch (PDOException $e) {
        error_log("Ошибка при проверке роли: " . $e->getMessage());
        return false;
    }
}

// Безопасный вывод данных
function safeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Логирование ошибок
function logError($message, $context = []) {
    $logMessage = date('[Y-m-d H:i:s] ') . $message . "\n";
    if (!empty($context)) {
        $logMessage .= "Контекст: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
    }
    error_log($logMessage, 3, 'error.log');
}

// Обработка ошибок
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logError("Ошибка [$errno] $errstr в $errfile на строке $errline");
    return true;
});

set_exception_handler(function($e) {
    logError("Необработанное исключение: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo "Произошла внутренняя ошибка сервера.";
});

// Проверка подключения к базе данных при загрузке конфигурации
try {
    if (!checkDatabaseConnection()) {
        throw new Exception('Ошибка подключения к базе данных. Проверьте логи для подробной информации.');
    }
} catch (Exception $e) {
    logError("Критическая ошибка: " . $e->getMessage());
    die('Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.');
} 