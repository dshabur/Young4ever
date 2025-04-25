

-- Использование базы данных
USE `u3111049_YoungForever`;

-- Создание таблицы пользователей
CREATE TABLE IF NOT EXISTS `users` (
    `id` int NOT NULL AUTO_INCREMENT,
    `username` varchar(255) NOT NULL,
    `password` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `role` enum('user','admin') DEFAULT 'user',
    `is_active` enum('active','banned','pending') DEFAULT 'active',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание таблицы статей
CREATE TABLE IF NOT EXISTS `articles` (
    `id` int NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `category` varchar(50) NOT NULL,
    `content` text NOT NULL,
    `user_id` int NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_articles_category` (`category`),
    KEY `idx_articles_user_id` (`user_id`),
    CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание таблицы фотографий статей
CREATE TABLE IF NOT EXISTS `article_photos` (
    `id` int NOT NULL AUTO_INCREMENT,
    `article_id` int NOT NULL,
    `photo_path` varchar(255) NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_article_photos_article_id` (`article_id`),
    CONSTRAINT `article_photos_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание таблицы лайков
CREATE TABLE IF NOT EXISTS `article_likes` (
    `id` int NOT NULL AUTO_INCREMENT,
    `article_id` int NOT NULL,
    `user_id` int NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_like` (`article_id`,`user_id`),
    KEY `idx_likes_article_id` (`article_id`),
    KEY `idx_likes_user_id` (`user_id`),
    CONSTRAINT `article_likes_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `article_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание таблицы комментариев
CREATE TABLE IF NOT EXISTS `article_comments` (
    `id` int NOT NULL AUTO_INCREMENT,
    `article_id` int NOT NULL,
    `user_id` int NOT NULL,
    `comment` text NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_comments_article_id` (`article_id`),
    KEY `idx_comments_user_id` (`user_id`),
    CONSTRAINT `article_comments_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `article_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание таблицы сохраненных статей
CREATE TABLE IF NOT EXISTS `saved_articles` (
    `id` int NOT NULL AUTO_INCREMENT,
    `user_id` int NOT NULL,
    `article_id` int NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_save` (`user_id`,`article_id`),
    KEY `idx_saved_articles_user_id` (`user_id`),
    KEY `idx_saved_articles_article_id` (`article_id`),
    CONSTRAINT `saved_articles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `saved_articles_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание представления для статей с деталями
CREATE OR REPLACE VIEW `article_with_details` AS
SELECT 
    a.*,
    u.username,
    GROUP_CONCAT(DISTINCT ap.photo_path) as photos,
    COUNT(DISTINCT al.id) as likes_count,
    COUNT(DISTINCT ac.id) as comments_count
FROM articles a
LEFT JOIN users u ON a.user_id = u.id
LEFT JOIN article_photos ap ON a.id = ap.article_id
LEFT JOIN article_likes al ON a.id = al.article_id
LEFT JOIN article_comments ac ON a.id = ac.article_id
GROUP BY a.id;

-- Создание представления для статей с фотографиями
CREATE OR REPLACE VIEW `article_with_photos` AS
SELECT 
    a.*,
    u.username,
    GROUP_CONCAT(ap.photo_path) as photos
FROM articles a
LEFT JOIN users u ON a.user_id = u.id
LEFT JOIN article_photos ap ON a.id = ap.article_id
GROUP BY a.id;

-- Добавление комментариев к таблицам
ALTER TABLE users COMMENT 'Таблица для хранения пользователей';
ALTER TABLE articles COMMENT 'Таблица для хранения статей';
ALTER TABLE article_photos COMMENT 'Таблица для хранения фотографий статей';
ALTER TABLE article_comments COMMENT 'Таблица для хранения комментариев';
ALTER TABLE article_likes COMMENT 'Таблица для хранения лайков';
ALTER TABLE saved_articles COMMENT 'Таблица для хранения сохраненных статей';

-- Создание тестового пользователя (опционально)
INSERT INTO users (username, email, password) VALUES 
('test_user', 'test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE username = username;

-- Создание тестовой статьи (опционально)
INSERT INTO articles (title, category, content, user_id) VALUES 
('Тестовая статья', 'bodycare', 'Это тестовая статья для проверки функционала.', 1)
ON DUPLICATE KEY UPDATE title = title; 