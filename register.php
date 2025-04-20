<?php
require_once 'config.php';
require_once 'database.php';



// Установка заголовков безопасности
setSecurityHeaders();

$errors = [];
$success = false;
$username = '';
$email = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF токена
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Пожалуйста, попробуйте еще раз.';
    } else {
        try {
            // Получение и очистка данных
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Валидация
            if (empty($username)) {
                $errors[] = 'Имя пользователя обязательно';
            } elseif (strlen($username) < 3) {
                $errors[] = 'Имя пользователя должно содержать минимум 3 символа';
            }

            if (empty($email)) {
                $errors[] = 'Email обязателен';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Некорректный формат email';
            }

            if (empty($password)) {
                $errors[] = 'Пароль обязателен';
            } elseif (strlen($password) < 6) {
                $errors[] = 'Пароль должен содержать минимум 6 символов';
            }

            if ($password !== $confirm_password) {
                $errors[] = 'Пароли не совпадают';
            }

            // Проверка уникальности email
            if (empty($errors)) {
                $db = Database::getInstance();
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = 'Пользователь с таким email уже существует';
                }
            }

            // Регистрация пользователя
            if (empty($errors)) {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password, created_at, is_active, role)
                    VALUES (?, ?, ?, NOW(), 1, 'user')
                ");
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$username, $email, $hashed_password]);
                
                $userId = $db->lastInsertId();
                
                // Автоматический вход после регистрации
                initUserSession($userId, $username);
                
                $db->commit();
                $success = true;
                
                // Перенаправление на главную страницу
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            logError("Ошибка при регистрации", [
                'username' => $username,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            $errors[] = 'Ошибка при регистрации. Пожалуйста, попробуйте позже.';
        }
    }
}

// Генерация CSRF токена
$csrf_token = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Young Forever</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: "Montserrat Alternates", sans-serif;
            margin: 0;
            padding: 20px;
            padding-bottom: 80px;
            background-color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding-top: 120px;
        }

        .form-container {
            width: 100%;
            max-width: 600px;
            background-color: #fbdde2;
            padding: 40px 80px;
            border-radius: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            margin: 0 auto;
        }

        h2 {
            text-align: center;
            font-size: 32px;
            font-weight: normal;
            margin: 0 0 20px 0;
            color: #000000;
        }

        form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        input {
            width: 100%;
            padding: 15px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            box-sizing: border-box;
            font-family: "Montserrat Alternates", sans-serif;
            background-color: #ffffff;
        }

        input::placeholder {
            color: #000000;
        }

        .toggle-link {
            text-align: center;
            margin-top: 10px;
            font-size: 16px;
        }

        .toggle-link a {
            color: #000000;
            text-decoration: underline;
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            width: 100%;
            color: #000000;
            background-color: #fbdde2;
            padding: 20px 0;
        }

        /* Убираем стандартные стили для автозаполнения */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0px 1000px #ffffff inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        /* Убираем outline при фокусе */
        input:focus {
            outline: none;
        }

        /* Стили для кнопки регистрации */
        button {
            width: 100%;
            padding: 15px 25px;
            border: none;
            border-radius: 25px;
            background-color: #ffffff;
            color: #000000;
            font-size: 16px;
            cursor: pointer;
            font-family: "Montserrat Alternates", sans-serif;
            margin-top: 10px;
        }

        button:hover {
            background-color: #f8f8f8;
        }

        /* Стили для сообщений об ошибках */
        .alert {
            width: 100%;
            padding: 15px;
            border-radius: 25px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Медиа-запрос для мобильных устройств */
        @media (max-width: 576px) {
            .form-container {
                padding: 30px 20px;
                max-width: 90%;
            }

            body {
                padding-top: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Регистрация</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                Регистрация успешна! Теперь вы можете <a href="login.php">войти</a>.
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo safeOutput($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo safeOutput($csrf_token); ?>">
            
            <input type="text" 
                   name="username" 
                   placeholder="Имя пользователя" 
                   value="<?php echo safeOutput($username); ?>" 
                   required>
            
            <input type="email" 
                   name="email" 
                   placeholder="Email" 
                   value="<?php echo safeOutput($email); ?>" 
                   required>
            
            <input type="password" 
                   name="password" 
                   placeholder="Пароль" 
                   required>
            
            <input type="password" 
                   name="confirm_password" 
                   placeholder="Подтверждение пароля" 
                   required>
            
            <button type="submit">Зарегистрироваться</button>
        </form>
        
        <div class="toggle-link">
            <p>Уже есть аккаунт? <a href="login.php">Войдите</a></p>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Young Forever. Все права защищены.</p>
    </footer>
</body>
</html>