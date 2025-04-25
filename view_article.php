<?php
require_once 'config.php';
require_once 'database.php';

// Установка заголовков безопасности
setSecurityHeaders();

// Получение ID статьи из URL
$article_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$article_id) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$db = Database::getInstance();

// Обработка добавления комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!checkAuth()) {
        header('Location: login.php');
        exit;
    }

    try {
        if ($_POST['action'] === 'comment') {
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Ошибка безопасности. Пожалуйста, попробуйте еще раз.');
            }

            $comment = trim($_POST['comment'] ?? '');
            if (empty($comment)) {
                throw new Exception('Комментарий не может быть пустым.');
            }

            $stmt = $db->prepare("
                INSERT INTO article_comments (article_id, user_id, comment) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$article_id, $_SESSION['user_id'], $comment]);
            $success = 'Комментарий успешно добавлен!';
        }
        elseif ($_POST['action'] === 'like') {
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Ошибка безопасности. Пожалуйста, попробуйте еще раз.');
            }

            // Проверяем, не лайкал ли уже пользователь
            $stmt = $db->prepare("
                SELECT id FROM article_likes 
                WHERE article_id = ? AND user_id = ?
            ");
            $stmt->execute([$article_id, $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                // Удаляем лайк
                $stmt = $db->prepare("
                    DELETE FROM article_likes 
                    WHERE article_id = ? AND user_id = ?
                ");
                $stmt->execute([$article_id, $_SESSION['user_id']]);
            } else {
                // Добавляем лайк
                $stmt = $db->prepare("
                    INSERT INTO article_likes (article_id, user_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$article_id, $_SESSION['user_id']]);
            }
        }
        elseif ($_POST['action'] === 'save') {
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Ошибка безопасности. Пожалуйста, попробуйте еще раз.');
            }

            // Проверяем, не сохранена ли уже статья
            $stmt = $db->prepare("
                SELECT id FROM saved_articles 
                WHERE article_id = ? AND user_id = ?
            ");
            $stmt->execute([$article_id, $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                // Удаляем из сохраненных
                $stmt = $db->prepare("
                    DELETE FROM saved_articles 
                    WHERE article_id = ? AND user_id = ?
                ");
                $stmt->execute([$article_id, $_SESSION['user_id']]);
            } else {
                // Добавляем в сохраненные
                $stmt = $db->prepare("
                    INSERT INTO saved_articles (article_id, user_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$article_id, $_SESSION['user_id']]);
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получение данных статьи
try {
    $stmt = $db->prepare("
        SELECT a.*, u.username, 
               COUNT(DISTINCT al.id) as likes_count,
               COUNT(DISTINCT ac.id) as comments_count,
               COUNT(DISTINCT sa.id) as saves_count,
               EXISTS(SELECT 1 FROM article_likes WHERE article_id = a.id AND user_id = ?) as user_liked,
               EXISTS(SELECT 1 FROM saved_articles WHERE article_id = a.id AND user_id = ?) as user_saved
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN article_likes al ON a.id = al.article_id
        LEFT JOIN article_comments ac ON a.id = ac.article_id
        LEFT JOIN saved_articles sa ON a.id = sa.article_id
        WHERE a.id = ?
        GROUP BY a.id
    ");
    $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['user_id'] ?? 0, $article_id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        header('Location: index.php');
        exit;
    }

    // Получение фотографий статьи
    $stmt = $db->prepare("SELECT photo_path FROM article_photos WHERE article_id = ?");
    $stmt->execute([$article_id]);
    $photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Получение комментариев
    $stmt = $db->prepare("
        SELECT c.*, u.username 
        FROM article_comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.article_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$article_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = 'Ошибка при загрузке статьи: ' . $e->getMessage();
}

// Генерация CSRF токена
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safeOutput($article['title']); ?> - Young Forever</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .article-container {
            max-width: 800px;
            margin: 100px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .article-header {
            margin-bottom: 30px;
        }

        .article-title {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 10px;
        }

        .article-meta {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 20px;
        }

        .article-content {
            font-size: 1.1em;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .article-photos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .article-photo {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .article-photo:hover {
            transform: scale(1.05);
        }

        .article-actions {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .like-button {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            background: #fbdde2;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .like-button:hover {
            background: #f9c4cc;
        }

        .like-button.liked {
            background: #ff6b81;
            color: white;
        }

        .comments-section {
            margin-top: 40px;
        }

        .comment-form {
            margin-bottom: 30px;
        }

        .comment-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            margin-bottom: 10px;
            resize: vertical;
            min-height: 100px;
        }

        .comment-input:focus {
            border-color: #fbdde2;
            outline: none;
        }

        .comment-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .comment {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
            font-size: 0.9em;
        }

        .comment-content {
            color: #333;
            line-height: 1.5;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .save-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            background: #fbdde2;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 10px;
        }

        .save-button:hover {
            background: #f9c4cc;
            transform: scale(1.05);
        }

        .save-button.saved {
            background: #ffd700;
            color: #333;
        }

        .save-button.disabled {
            background: #f0f0f0;
            color: #999;
            cursor: default;
        }

        .save-icon {
            font-size: 1.2em;
        }

        .save-count {
            font-size: 0.9em;
            font-weight: 500;
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
                <?php if (checkAuth()): ?>
                    <a href="profile.php" class="profile-icon">
                        <?php echo safeOutput($_SESSION['username']); ?>
                        <img src="profile-icon.png" alt="Профиль">
                    </a>
                <?php else: ?>
                    <a href="register.php" class="auth-link">Регистрация</a>
                    <a href="login.php" class="auth-link">Вход</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="article-container">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo safeOutput($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo safeOutput($success); ?>
            </div>
        <?php endif; ?>

        <article>
            <div class="article-header">
                <h1 class="article-title"><?php echo safeOutput($article['title']); ?></h1>
                <div class="article-meta">
                    Автор: <?php echo safeOutput($article['username']); ?> | 
                    Дата публикации: <?php echo date('d.m.Y H:i', strtotime($article['created_at'])); ?>
                </div>
            </div>

            <?php if (!empty($photos)): ?>
                <div class="article-photos">
                    <?php foreach ($photos as $photo): ?>
                        <img src="articles/<?php echo safeOutput($article['username']); ?>_<?php echo $article_id; ?>/<?php echo safeOutput($photo); ?>" 
                             alt="Фото к статье" class="article-photo">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="article-content">
                <?php echo nl2br(safeOutput($article['content'])); ?>
            </div>

            <div class="article-actions">
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="like">
                    <button type="submit" class="like-button <?php echo $article['user_liked'] ? 'liked' : ''; ?>">
                        <span class="like-count"><?php echo $article['likes_count']; ?></span>
                        <span class="like-icon">❤</span>
                    </button>
                </form>

                <?php if (checkAuth()): ?>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="save">
                        <button type="submit" class="save-button <?php echo $article['user_saved'] ? 'saved' : ''; ?>">
                            <span class="save-count"><?php echo $article['saves_count']; ?></span>
                            <span class="save-icon">⭐</span>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="save-button disabled">
                        <span class="save-count"><?php echo $article['saves_count']; ?></span>
                        <span class="save-icon">⭐</span>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <div class="comments-section">
            <h2>Комментарии (<?php echo $article['comments_count']; ?>)</h2>

            <?php if (checkAuth()): ?>
                <form method="POST" action="" class="comment-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="comment">
                    <textarea name="comment" class="comment-input" placeholder="Напишите комментарий..." required></textarea>
                    <button type="submit" class="btn-submit">Отправить комментарий</button>
                </form>
            <?php else: ?>
                <p>Чтобы оставить комментарий, пожалуйста, <a href="login.php">войдите</a> или <a href="register.php">зарегистрируйтесь</a>.</p>
            <?php endif; ?>

            <div class="comment-list">
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-header">
                            <span class="comment-author"><?php echo safeOutput($comment['username']); ?></span>
                            <span class="comment-date"><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></span>
                        </div>
                        <div class="comment-content">
                            <?php echo nl2br(safeOutput($comment['comment'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Обработка лайков без перезагрузки страницы
        const likeForm = document.querySelector('form[action="like"]');
        if (likeForm) {
            likeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newLikeButton = doc.querySelector('.like-button');
                    const newLikeCount = doc.querySelector('.like-count');
                    
                    document.querySelector('.like-button').className = newLikeButton.className;
                    document.querySelector('.like-count').textContent = newLikeCount.textContent;
                });
            });
        }

        // Обработка сохранения без перезагрузки страницы
        const saveForm = document.querySelector('form[action="save"]');
        if (saveForm) {
            saveForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newSaveButton = doc.querySelector('.save-button');
                    const newSaveCount = doc.querySelector('.save-count');
                    
                    document.querySelector('.save-button').className = newSaveButton.className;
                    document.querySelector('.save-count').textContent = newSaveCount.textContent;
                });
            });
        }
    });
    </script>
</body>
</html> 