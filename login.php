<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = isset($_POST['role']) ? $_POST['role'] : '';

    try {
        if ($role === 'психолог') {
            // Проверка в таблице психологов
            $stmt = $pdo->prepare("SELECT * FROM psychologists WHERE user_id = :username");
            $stmt->execute([':username' => $username]);
            $psychologist = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($psychologist && password_verify($password, $psychologist['password'])) {
                $_SESSION['user_id'] = $psychologist['user_id'];
                $_SESSION['username'] = $username; // Используем введенное имя пользователя
                $_SESSION['role'] = 'психолог';
                header('Location: psyh.php'); // Перенаправление на рабочую область психолога
                exit;
            } else {
                echo '<div class="alert alert-danger">Неверное имя пользователя или пароль для психолога!</div>';
            }
        } else { // Клиент
            // Проверка в таблице клиентов ( напрямую таблицы клиентов нет , поэтому ищем в users)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client && password_verify($password, $client['password'])) {
                $_SESSION['user_id'] = $client['id'];
                $_SESSION['username'] = $client['username'];
                $_SESSION['role'] = 'клиент';
                header('Location: client.php'); // Перенаправление в личный кабинет клиента
                exit;
            } else {
                echo '<div class="alert alert-danger">Неверное имя пользователя или пароль для клиента!</div>';
            }
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Ошибка авторизации: ' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
        form { display: inline-block; padding: 20px; border: 1px solid #ccc; background-color: #f9f9f9; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; }
        button { padding: 10px 20px; cursor: pointer; }
        a { color: blue; text-decoration: none; }
        .alert { margin-top: 10px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Авторизация</h1>
    <form method="POST">
        <div class="form-group">
            <label for="username">Имя пользователя (телефон):</label>
            <input type="text" id="username" name="username" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <div class="form-group">
            <button type="submit" name="role" value="клиент" class="btn btn-primary">Войти как клиент</button>
            <button type="submit" name="role" value="психолог" class="btn btn-secondary">Войти как психолог</button>
        </div>
    </form>
    <p>Новый пользователь? <a href="signup.php">Регистрация</a></p>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
