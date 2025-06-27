<?php
session_start();
require_once 'db.php';
// Добавление столбца 'user_id' в таблицу 'psychologists', если его нет
    try {
        $pdo->exec("ALTER TABLE psychologists ADD COLUMN user_id INTEGER");
    } catch (PDOException $e) {
        // Игнорируем, если столбец уже существует
    }
     // Добавление столбца 'parol' в таблицу 'psycholog', если его нет
    try {
        $pdo->exec("ALTER TABLE psychologists ADD COLUMN password INTEGER");
    } catch (PDOException $e) {
        // Игнорируем, если столбец уже существует
    }
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $phoneNumber = trim($_POST['user_id']);
    $password = trim($_POST['password']);

    try {
        // Хешируем пароль перед сохранением
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Добавляем нового пользователя
        $stmt = $pdo->prepare("INSERT INTO psychologists (user_id, password, ) VALUES (:user_id, :password)");
        $stmt->execute([
            ':user_id' => $phoneNumber,     
            ':password' => $hashedPassword,
        ]);

        // Сразу авторизуем пользователя
        $userId = $pdo->lastInsertId(); // получаем последний вставленный id
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $phoneNumber;
        $_SESSION['role'] = 'user'; // по умолчанию считаем всех клиентами

        // Перенаправляем на личный кабинет клиента
        header('Location: chat1.php');
        exit;
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Ошибка регистрации: ' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <style>
        /* Ваши CSS-стили */
    </style>
</head>
<body>
<h1>Регистрация</h1>
<form method="POST">

    <label for="phone_number">Телефон (логин):</label>
    <input type="tel" id="phone_number" name="phone_number" required><br>
    <label for="password">Пароль:</label>
    <input type="password" id="password" name="password" required><br>
    
    <button type="submit">Зарегистрироваться</button>
</form>
</body>
</html>