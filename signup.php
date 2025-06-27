<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $patronymic = trim($_POST['patronymic']);
    $birthDate = trim($_POST['birth_date']);
    $phoneNumber = trim($_POST['phone_number']);
    $password = trim($_POST['password']);
    $experiencePsychology = isset($_POST['experience_psychology']) && $_POST['experience_psychology'] === 'yes';
    $mentalIllnessHistory = isset($_POST['mental_illness_history']) && $_POST['mental_illness_history'] === 'yes';

    try {
        // Хешируем пароль перед сохранением
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Добавляем нового пользователя
        $stmt = $pdo->prepare("INSERT INTO users (username, password, first_name, last_name, patronymic, birth_date, phone_number, experience, mental_illness) VALUES (:username, :password, :first_name, :last_name, :patronymic, :birth_date, :phone_number, :experience, :mental_illness)");
        $stmt->execute([
            ':username' => $phoneNumber,     
            ':password' => $hashedPassword,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':patronymic' => $patronymic,
            ':birth_date' => $birthDate,
            ':phone_number' => $phoneNumber,
            ':experience' => $experiencePsychology,
            ':mental_illness' => $mentalIllnessHistory
        ]);

        // Сразу авторизуем пользователя
        $userId = $pdo->lastInsertId(); // получаем последний вставленный id
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $phoneNumber;
        $_SESSION['role'] = 'клиент'; // по умолчанию считаем всех клиентами

        // Перенаправляем на личный кабинет клиента
        header('Location: client.php');
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
    <label for="first_name">Имя:</label>
    <input type="text" id="first_name" name="first_name" required><br>
    <label for="last_name">Фамилия:</label>
    <input type="text" id="last_name" name="last_name" required><br>
    <label for="patronymic">Отчество:</label>
    <input type="text" id="patronymic" name="patronymic"><br>
    <label for="birth_date">Дата рождения:</label>
    <input type="date" id="birth_date" name="birth_date" required><br>
    <label for="phone_number">Телефон (логин):</label>
    <input type="tel" id="phone_number" name="phone_number" required><br>
    <label for="password">Пароль:</label>
    <input type="password" id="password" name="password" required><br>
    <fieldset>
        <legend>Был ли опыт посещения психолога?</legend>
        <label><input type="radio" name="experience_psychology" value="yes" required>Да</label>
        <label><input type="radio" name="experience_psychology" value="no" required>Нет</label>
    </fieldset>
    <fieldset>
        <legend>Есть ли психиатрические заболевания?</legend>
        <label><input type="radio" name="mental_illness_history" value="yes" required>Да</label>
        <label><input type="radio" name="mental_illness_history" value="no" required>Нет</label>
    </fieldset>
    <button type="submit">Зарегистрироваться</button>
</form>
</body>
</html>