<?php
require_once 'config.php';
require_once 'database.php';

// Установка заголовков безопасности
setSecurityHeaders();

// Проверка авторизации
$isLoggedIn = checkAuth();
$username = $isLoggedIn ? ($_SESSION['username'] ?? '') : '';

// Получение статей категории "Уход за ногтями"
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT a.*, u.username, 
               COUNT(DISTINCT al.id) as likes_count,
               COUNT(DISTINCT ac.id) as comments_count,
               GROUP_CONCAT(DISTINCT ap.photo_path) as photos
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN article_likes al ON a.id = al.article_id
        LEFT JOIN article_comments ac ON a.id = ac.article_id
        LEFT JOIN article_photos ap ON a.id = ap.article_id
        WHERE a.category = 'nailcare'
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Ошибка при загрузке статей: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Уход за ногтями - Young Forever</title>
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
            transition: color 0.3s;
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
            transition: background-color 0.3s;
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
            transition: background-color 0.3s;
        }
        .profile-icon:hover {
            background-color: #fbdde2;
        }
        .profile-icon img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            transition: transform 0.3s;
        }
        .profile-icon:hover img {
            transform: scale(1.1);
        }
        .articles-container { max-width: 1200px; margin: 100px auto; padding: 20px;}
        .articles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; margin-top: 40px;}
        .article-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: all 0.3s;}
        .article-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.15);}
        .article-image { width: 100%; height: 200px; object-fit: cover;}
        .article-content { padding: 20px;}
        .article-title { font-size: 1.5em; color: #333; margin-bottom: 10px; line-height: 1.3;}
        .article-meta { color: #666; font-size: 0.9em; margin-bottom: 15px;}
        .article-excerpt { color: #444; line-height: 1.5; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;}
        .article-stats { display: flex; gap: 15px; color: #666; font-size: 0.9em;}
        .article-stat { display: flex; align-items: center; gap: 5px;}
        .read-more { display: inline-block; padding: 8px 20px; background: #fbdde2; color: #333; text-decoration: none; border-radius: 20px; transition: all 0.3s; margin-top: 15px;}
        .read-more:hover { background: #f9c4cc; transform: translateY(-2px);}
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; text-align: center;}
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .category-header { background: white; padding: 40px 0; margin-bottom: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
        .category-header h1 { margin: 0; color: #333; font-size: 2.5em; text-align: center;}
        .category-header p { margin: 20px auto 0; max-width: 800px; color: #666; text-align: center; line-height: 1.6;}
        footer {
            margin-top: auto;
            text-align: center ;
            padding: 20px;
            color: #000000;
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
                    <li class="large-text"><a href="nailcare.php" class="active">Уход за ногтями</a></li>
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
    <div class="category-header">
        <h1>Уход за ногтями</h1>
        <p style="text-align: center;">
        Красивые и ухоженные ногти – признак здоровья и аккуратности.  
        </p>
        <ul style="margin-top: 30px; padding-left: 0; text-align: center; list-style: none;">
            <li>Гигиена – регулярно подстригайте и подпиливайте ногти, избегайте грязи под ними.</li>
            <li>Укрепление – используйте лаки с кальцием и витаминами, масло для кутикулы.</li>
            <li>Увлажнение – втирайте крем для рук и ногтей, чтобы предотвратить ломкость.</li>
            <li>Защита – надевайте перчатки при контакте с бытовой химией.</li>
            <li>Отдых от лака – делайте перерывы между покрытиями, чтобы ногти могли отдохнуть.</li>
        </ul>
        <p style="margin-top: 30px; text-align: center;">Совет: Включите в рацион продукты, богатые биотином (яйца, орехи, рыба) – это укрепит ногти.</p>
    </div>
    <div class="articles-container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo safeOutput($error); ?>
            </div>
        <?php endif; ?>
        <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
                <article class="article-card">
                    <?php 
                    $photos = explode(',', $article['photos']);
                    $mainPhoto = !empty($photos[0]) ? $photos[0] : 'default-article.jpg';
                    ?>
                    <img src="articles/<?php echo safeOutput($article['username']); ?>_<?php echo $article['id']; ?>/<?php echo safeOutput($mainPhoto); ?>" 
                         alt="<?php echo safeOutput($article['title']); ?>" 
                         class="article-image">
                    <div class="article-content">
                        <h2 class="article-title">
                            <?php echo safeOutput($article['title']); ?>
                        </h2>
                        <div class="article-meta">
                            Автор: <?php echo safeOutput($article['username']); ?> | 
                            <?php echo date('d.m.Y', strtotime($article['created_at'])); ?>
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
    <footer>
        <p>&copy; 2025 Young Forever. Все права защищены.</p>
    </footer>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.article-card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });
        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            observer.observe(card);
        });
    });
    </script>
</body>
</html>