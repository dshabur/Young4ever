<?php
require_once 'config.php';
require_once 'database.php';

// Проверка авторизации
if (!checkAuth()) {
    header('Location: login.php');
    exit;
}

// Получение данных пользователя
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT u.*, 
               GROUP_CONCAT(DISTINCT a.id) as saved_articles_ids,
               GROUP_CONCAT(DISTINCT a.title) as saved_articles_titles
        FROM users u
        LEFT JOIN saved_articles sa ON u.id = sa.user_id
        LEFT JOIN articles a ON sa.article_id = a.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Пользователь не найден');
    }

    // Получение первой буквы имени для аватара
    $avatarLetter = mb_strtoupper(mb_substr($user['username'], 0, 1));

    // Обработка сохраненных статей
    $savedArticles = [];
    if (!empty($user['saved_articles_ids'])) {
        $articleIds = explode(',', $user['saved_articles_ids']);
        $articleTitles = explode(',', $user['saved_articles_titles']);
        foreach ($articleIds as $index => $id) {
            $savedArticles[] = [
                'id' => $id,
                'title' => $articleTitles[$index] ?? 'Без названия'
            ];
        }
    }

} catch (Exception $e) {
    logError("Ошибка при получении данных пользователя: " . $e->getMessage());
    $errors[] = 'Ошибка при загрузке данных профиля';
}

// Установка заголовков безопасности
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - Young Forever</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .profile-page {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 120px 20px 40px;
            background-color: #ffffff;
        }

        .page-title {
            font-size: 36px;
            color: #000000;
            margin-bottom: 40px;
            font-weight: normal;
        }

        .profile-container {
            width: 100%;
            max-width: 800px;
            background-color: #FFE4E6;
            padding: 40px;
            border-radius: 30px;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .avatar-container {
            width: 100px;
            height: 100px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #000000;
        }

        .username {
            font-size: 32px;
            color: #000000;
            font-weight: normal;
        }

        .saved-articles-title {
            font-size: 24px;
            color: #000000;
            margin-top: 20px;
            font-weight: normal;
        }

        .articles-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .article-link {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 25px;
            text-align: center;
            text-decoration: none;
            color: #000000;
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .article-link:hover {
            transform: scale(1.02);
        }

        .no-articles {
            text-align: center;
            color: #666;
            font-size: 18px;
            padding: 20px;
        }

        footer {
            margin-top: auto;
            text-align: center;
            padding: 20px;
            color: #000000;
        }

        @media (max-width: 768px) {
            .articles-grid {
                grid-template-columns: 1fr;
            }

            .profile-container {
                padding: 20px;
            }

            .page-title {
                font-size: 28px;
            }

            .username {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <a href="index.php" class="logo">Young Forever</a>
            <nav>
                <ul>
                    <li class="large-text"><a href="index.php">Главная</a></li>
                    <li class="large-text"><a href="skincare.php">Уход за кожей</a></li>
                    <li class="large-text"><a href="haircare.php">Уход за волосами</a></li>
                    <li class="large-text"><a href="nailcare.php">Уход за ногтями</a></li>
                    <li class="large-text"><a href="bodycare.php">Уход за телом</a></li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <a href="logout.php" class="auth-link">Выйти</a>
            </div>
        </div>
    </header>

    <div class="profile-page">
        <h1 class="page-title">Личный кабинет</h1>
        
        <div class="profile-container">
            <div class="profile-header">
                <div class="avatar-container"><?php echo safeOutput($avatarLetter); ?></div>
                <div class="username"><?php echo safeOutput($user['username']); ?></div>
            </div>

            <div class="saved-articles">
                <h2 class="saved-articles-title">Сохраненные статьи:</h2>
                <?php if (!empty($savedArticles)): ?>
                    <div class="articles-grid">
                        <?php foreach ($savedArticles as $article): ?>
                            <a href="article.php?id=<?php echo $article['id']; ?>" class="article-link">
                                <?php echo safeOutput($article['title']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-articles">У вас пока нет сохраненных статей</div>
                <?php endif; ?>
            </div>
        </div>

        <footer>
            <p>&copy; 2025 Young Forever. Все права защищены.</p>
        </footer>
    </div>
</body>
</html> 