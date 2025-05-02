<?php
require_once 'config.php';
require_once 'database.php';

// Установка заголовков безопасности
setSecurityHeaders();

// Проверка авторизации
$isLoggedIn = checkAuth();
$username = $isLoggedIn ? ($_SESSION['username'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- SEO метатеги -->
    <meta name="description" content="Young Forever - Ваш гид по уходу за собой">
    <meta name="keywords" content="уход за кожей, уход за волосами, уход за ногтями, уход за телом">
    <meta name="robots" content="index, follow">
    <title>Young Forever - Главная</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #fff;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        body {
            padding-top: 80px;
        }
        .header-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }
        nav ul {
            display: flex;
            gap: 20px;
            list-style: none;
            padding: 0;
            margin: 0;
        }
        nav ul li a {
            text-decoration: none;
            color: #000;
            font-size: 1.2em;
            transition: color 0.3s ease;
        }
        nav ul li a:hover {
            color: #fbdde2;
        }
        .auth-buttons {
            display: flex;
            gap: 15px;
        }
        .auth-link {
            text-decoration: none;
            color: #000;
            padding: 8px 15px;
            border-radius: 20px;
            transition: background-color 0.3s ease;
        }
        .auth-link:hover {
            background-color: #fbdde2;
        }
        .profile-icon {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #000;
            padding: 8px 15px;
            border-radius: 20px;
            transition: background-color 0.3s ease;
        }
        .profile-icon:hover {
            background-color: #fbdde2;
        }
        .profile-icon img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            transition: transform 0.3s ease;
        }
        .profile-icon:hover img {
            transform: scale(1.1);
        }
                .gallery-container {
            width: 90vw;
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
        }

        /* Стили изображений */
        .gallery-image {
            width: 100%;
            height: auto;
            margin-bottom: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        /* Эффект при наведении на изображения */
        .gallery-image:hover {
            transform: scale(1.02);
        }

        /* Заголовок секции */
        .section-title {
            text-align: center;
            font-size: 2em;
            color: #000000;
            margin: 40px 0;
            font-family: 'Times New Roman', serif;
        }

        /* Адаптивность для больших экранов */
        @media screen and (min-width: 1920px) {
            .gallery-container {
                max-width: 1900px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <nav>
                <ul>
                    <li class="large-text"><a href="index.php" class="active">Главная</a></li>
                    <li class="large-text"><a href="skincare.php">Уход за кожей</a></li>
                    <li class="large-text"><a href="haircare.php">Уход за волосами</a></li>
                    <li class="large-text"><a href="nailcare.php">Уход за ногтями</a></li>
                    <li class="large-text"><a href="bodycare.php">Уход за телом</a></li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <?php if ($isLoggedIn): ?>
                    <a href="profile.php" class="profile-icon">
                        <?php echo safeOutput($username); ?>
                        <img src="profile-icon.png" alt="Профиль">
                    </a>
                <?php else: ?>
                    <a href="register.php" class="auth-link">Регистрация</a>
                    <a href="login.php" class="auth-link">Вход</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="gallery-container">
        <img src="image1.jpg" alt="Young Forever - Уход за собой" class="gallery-image">
        <img src="image2.jpg" alt="Young Forever - Красота и здоровье" class="gallery-image">
        <img src="image3.jpg" alt="Young Forever - Советы по уходу" class="gallery-image">
        <img src="image4.jpg" alt="Young Forever - Процедуры" class="gallery-image">
        <img src="image5.jpg" alt="Young Forever - Красота" class="gallery-image">
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Обработчик события прокрутки
        document.addEventListener('wheel', function(event) {
            event.preventDefault();
            
            const scrollAmount = event.deltaY > 0 ? 120 : -120;
            const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            const newPosition = currentScroll + scrollAmount;
            
            window.scrollTo({
                top: newPosition,
                behavior: 'smooth'
            });
        }, { passive: false });
    </script>
</body>
</html> 