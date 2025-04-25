<?php
require_once 'config.php';
require_once 'database.php';

// Проверка авторизации
if (!checkAuth()) {
    header('Location: login.php');
    exit;
}

// Проверка авторизации для header'а
$isLoggedIn = checkAuth();
$username = $isLoggedIn ? ($_SESSION['username'] ?? '') : '';

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

// Получение сохраненных статей
try {
    $stmt = $db->prepare("
        SELECT 
            a.*,
            u.username,
            COUNT(DISTINCT al.id) as likes_count,
            COUNT(DISTINCT ac.id) as comments_count,
            GROUP_CONCAT(DISTINCT ap.photo_path) as photos,
            sa.created_at as saved_at
        FROM saved_articles sa
        JOIN articles a ON sa.article_id = a.id
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN article_likes al ON a.id = al.article_id
        LEFT JOIN article_comments ac ON a.id = ac.article_id
        LEFT JOIN article_photos ap ON a.id = ap.article_id
        WHERE sa.user_id = ?
        GROUP BY a.id, a.title, a.content, a.category, a.user_id, a.created_at, a.updated_at, u.username, sa.created_at
        ORDER BY sa.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $saved_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Отладочная информация
    if (empty($saved_articles)) {
        error_log("No saved articles found for user ID: " . $_SESSION['user_id']);
    } else {
        error_log("Found " . count($saved_articles) . " saved articles for user ID: " . $_SESSION['user_id']);
    }
} catch (Exception $e) {
    error_log("Error loading saved articles: " . $e->getMessage());
    $error = 'Ошибка при загрузке сохраненных статей: ' . $e->getMessage();
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
        /* Фиксированная шапка сайта */
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: white;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Основные стили */
        body {
            padding-top: 80px; /* Отступ под фиксированную шапку */
        }

        /* Контейнер для галереи изображений */
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

        /* Стили для навигации */
        nav ul {
            display: flex;
            gap: 20px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        nav ul li a {
            text-decoration: none;
            color: #000000;
            font-size: 1.2em;
            transition: color 0.3s ease;
        }

        nav ul li a:hover {
            color: #fbdde2;
        }

        /* Стили для кнопок авторизации */
        .auth-buttons {
            display: flex;
            gap: 15px;
        }

        .auth-link {
            text-decoration: none;
            color: #000000;
            padding: 8px 15px;
            border-radius: 20px;
            transition: background-color 0.3s ease;
        }

        .auth-link:hover {
            background-color: #fbdde2;
        }

        /* Стили для иконки профиля */
        .profile-icon {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #000000;
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

        .saved-articles {
            margin-top: 40px;
        }

        .saved-articles h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .no-articles {
            text-align: center;
            color: #666;
            font-style: italic;
        }

        .articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .article-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .article-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .article-content {
            padding: 20px;
        }

        .article-title {
            font-size: 1.3em;
            color: #333;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .article-meta {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
        }

        .article-excerpt {
            color: #444;
            line-height: 1.5;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .article-stats {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
        }

        .article-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .read-more {
            display: inline-block;
            padding: 8px 20px;
            background: #fbdde2;
            color: #333;
            text-decoration: none;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .read-more:hover {
            background: #f9c4cc;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
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
            
            <div class="add-article-button" style="padding-left: 40px;">
                <a href="upload_article.php" style="
                    text-decoration: none;
                    color: #000000;
                    font-size: 24px;
                    transition: color 0.3s ease;
                ">Добавить статью</a>
            </div>

            <div class="saved-articles">
                <h2>Сохраненные статьи</h2>
                <?php if (empty($saved_articles)): ?>
                    <p class="no-articles">У вас пока нет сохраненных статей.</p>
                <?php else: ?>
                    <div class="articles-grid">
                        <?php foreach ($saved_articles as $article): ?>
                            <article class="article-card">
                                <?php 
                                $photos = !empty($article['photos']) ? explode(',', $article['photos']) : [];
                                $mainPhoto = !empty($photos[0]) ? $photos[0] : 'default-article.jpg';
                                ?>
                                <img src="articles/<?php echo safeOutput($article['username']); ?>_<?php echo $article['id']; ?>/<?php echo safeOutput($mainPhoto); ?>" 
                                     alt="<?php echo safeOutput($article['title']); ?>" 
                                     class="article-image"
                                     onerror="this.src='default-article.jpg'">
                                
                                <div class="article-content">
                                    <h3 class="article-title">
                                        <?php echo safeOutput($article['title']); ?>
                                    </h3>
                                    
                                    <div class="article-meta">
                                        Автор: <?php echo safeOutput($article['username']); ?> | 
                                        Сохранено: <?php echo date('d.m.Y', strtotime($article['saved_at'])); ?>
                                    </div>
                                    
                                    <p class="article-excerpt">
                                        <?php 
                                        $excerpt = strip_tags($article['content']);
                                        echo safeOutput(substr($excerpt, 0, 150)) . '...'; 
                                        ?>
                                    </p>
                                    
                                    <div class="article-stats">
                                        <span class="article-stat">
                                            <span class="like-icon">❤</span>
                                            <?php echo $article['likes_count']; ?>
                                        </span>
                                        <span class="article-stat">
                                            <span class="comment-icon">💬</span>
                                            <?php echo $article['comments_count']; ?>
                                        </span>
                                    </div>
                                    
                                    <a href="view_article.php?id=<?php echo $article['id']; ?>" class="read-more">
                                        Читать далее
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <footer>
            <p>&copy; 2025 Young Forever. Все права защищены.</p>
        </footer>
    </div>
</body>
</html> 