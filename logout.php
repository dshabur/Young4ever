<?php
require_once 'config.php';

// Проверяем, авторизован ли пользователь
if (checkAuth()) {
    // Вызываем функцию выхода из системы
    logout();
    
    // Перенаправляем на главную страницу
    header('Location: index.php');
    exit;
} else {
    // Если пользователь не авторизован, также перенаправляем на главную
    header('Location: index.php');
    exit;
}
?> 