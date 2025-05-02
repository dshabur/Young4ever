<?php
require_once 'config.php';
require_once 'database.php';

// Установка заголовков безопасности
setSecurityHeaders();

// Проверка авторизации
if (!checkAuth()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Создание директории для статей, если она не существует
$articlesDir = __DIR__ . '/articles';
if (!file_exists($articlesDir)) {
    mkdir($articlesDir, 0777, true);
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Проверка CSRF токена
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Ошибка безопасности. Пожалуйста, попробуйте еще раз.');
        }

        // Получение и валидация данных
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'];

        if (empty($title) || empty($category) || empty($content)) {
            throw new Exception('Все поля должны быть заполнены.');
        }

        if (strlen($title) > 255) {
            throw new Exception('Название статьи слишком длинное.');
        }

        // Подключение к базе данных
        $db = Database::getInstance();
        
        // Подготовка и выполнение запроса
        $stmt = $db->prepare("
            INSERT INTO articles (title, category, content, user_id, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$title, $category, $content, $user_id]);
        $article_id = $db->lastInsertId();

        // Создание директории для фотографий статьи
        $articleDir = $articlesDir . '/' . $username . '_' . $article_id;
        if (!file_exists($articleDir)) {
            mkdir($articleDir, 0777, true);
        }

        // Обработка загруженных фотографий
        if (!empty($_FILES['photos']['name'][0])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB

            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileType = $_FILES['photos']['type'][$key];
                    $fileSize = $_FILES['photos']['size'][$key];

                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception('Недопустимый тип файла. Разрешены только JPG, PNG и GIF.');
                    }

                    if ($fileSize > $maxFileSize) {
                        throw new Exception('Размер файла превышает 5MB.');
                    }

                    // Генерация уникального имени файла
                    $fileExtension = pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION);
                    $fileName = uniqid('photo_') . '.' . $fileExtension;
                    $filePath = $articleDir . '/' . $fileName;

                    if (move_uploaded_file($tmp_name, $filePath)) {
                        // Сохранение информации о фото в базе данных
                        $stmt = $db->prepare("
                            INSERT INTO article_photos (article_id, photo_path) 
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$article_id, $fileName]);
                    } else {
                        throw new Exception('Ошибка при загрузке файла. Пожалуйста, попробуйте еще раз.');
                    }
                } else {
                    $errorMessage = match($_FILES['photos']['error'][$key]) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает допустимый предел.',
                        UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично.',
                        UPLOAD_ERR_NO_FILE => 'Файл не был загружен.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка.',
                        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
                        UPLOAD_ERR_EXTENSION => 'Загрузка файла была остановлена расширением.',
                        default => 'Произошла неизвестная ошибка при загрузке файла.'
                    };
                    throw new Exception($errorMessage);
                }
            }
        }
        
        $success = 'Статья успешно опубликована!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
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
    <title>Загрузка статьи - Young Forever</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: #fff; /* Исправлено: белый фон */
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        header {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: white;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            height: 80px; /* Совпадает с другими страницами */
            display: flex;
            align-items: center;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
            width: 100%;
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
        nav ul li a:hover, nav ul li a.active {
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
            transition: background 0.3s;
        }
        .auth-link:hover {
            background: #fbdde2;
        }
        .profile-icon {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #000;
            padding: 8px 15px;
            border-radius: 20px;
            transition: background 0.3s;
        }
        .profile-icon:hover {
            background: #fbdde2;
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
        .upload-container {
            max-width: 800px;
            margin: 100px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
            font-size: 1.1em;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #fbdde2;
            outline: none;
            box-shadow: 0 0 8px rgba(251, 221, 226, 0.6);
        }

        textarea.form-control {
            min-height: 300px;
            resize: vertical;
        }

        select.form-control {
            background-color: white;
            cursor: pointer;
        }

        .photo-upload {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .photo-upload:hover {
            border-color: #fbdde2;
            background-color: rgba(251, 221, 226, 0.1);
        }

        .photo-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .photo-preview img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid #fbdde2;
        }

        .btn-submit {
            background-color: #fbdde2;
            color: #333;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: #f9c4cc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(251, 221, 226, 0.4);
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

        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.2em;
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

    <div class="upload-container">
        <h1>Загрузка новой статьи</h1>
        
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

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label class="form-label" for="title">Название статьи</label>
                <input type="text" id="title" name="title" class="form-control" required 
                       value="<?php echo safeOutput($_POST['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="category">Категория</label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">Выберите категорию</option>
                    <option value="skincare" <?php echo ($_POST['category'] ?? '') === 'skincare' ? 'selected' : ''; ?>>Уход за кожей</option>
                    <option value="haircare" <?php echo ($_POST['category'] ?? '') === 'haircare' ? 'selected' : ''; ?>>Уход за волосами</option>
                    <option value="nailcare" <?php echo ($_POST['category'] ?? '') === 'nailcare' ? 'selected' : ''; ?>>Уход за ногтями</option>
                    <option value="bodycare" <?php echo ($_POST['category'] ?? '') === 'bodycare' ? 'selected' : ''; ?>>Уход за телом</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="content">Содержание статьи</label>
                <textarea id="content" name="content" class="form-control" required><?php echo safeOutput($_POST['content'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Фотографии к статье</label>
                <div class="photo-upload">
                    <input type="file" name="photos[]" id="photos" multiple accept="image/*" style="display: none;">
                    <label for="photos" style="cursor: pointer;">
                        <div>Перетащите фотографии сюда или нажмите для выбора</div>
                        <div style="font-size: 0.9em; color: #666; margin-top: 5px;">
                            (Максимальный размер файла: 5MB. Разрешены форматы: JPG, PNG, GIF)
                        </div>
                    </label>
                    <div class="photo-preview" id="photoPreview"></div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Опубликовать статью</button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Автоматическое сохранение черновика
        const form = document.querySelector('form');
        const titleInput = document.getElementById('title');
        const categorySelect = document.getElementById('category');
        const contentTextarea = document.getElementById('content');
        const photoInput = document.getElementById('photos');
        const photoPreview = document.getElementById('photoPreview');

        // Загрузка черновика при открытии страницы
        const draft = JSON.parse(localStorage.getItem('articleDraft') || '{}');
        if (draft.title) titleInput.value = draft.title;
        if (draft.category) categorySelect.value = draft.category;
        if (draft.content) contentTextarea.value = draft.content;

        // Сохранение черновика при изменении полей
        function saveDraft() {
            const draft = {
                title: titleInput.value,
                category: categorySelect.value,
                content: contentTextarea.value
            };
            localStorage.setItem('articleDraft', JSON.stringify(draft));
        }

        titleInput.addEventListener('input', saveDraft);
        categorySelect.addEventListener('change', saveDraft);
        contentTextarea.addEventListener('input', saveDraft);

        // Очистка черновика при успешной отправке формы
        form.addEventListener('submit', function() {
            localStorage.removeItem('articleDraft');
        });

        // Предпросмотр фотографий
        photoInput.addEventListener('change', function() {
            photoPreview.innerHTML = '';
            Array.from(this.files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        photoPreview.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                }
            });
        });

        // Drag and drop для фотографий
        const photoUpload = document.querySelector('.photo-upload');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            photoUpload.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            photoUpload.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            photoUpload.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            photoUpload.classList.add('highlight');
        }

        function unhighlight(e) {
            photoUpload.classList.remove('highlight');
        }

        photoUpload.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            photoInput.files = files;
            photoInput.dispatchEvent(new Event('change'));
        }
    });
    </script>
</body>
</html>