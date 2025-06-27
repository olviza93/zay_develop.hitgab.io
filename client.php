<?php
session_start();
require_once 'db.php';

// Получаем данные текущего пользователя
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->execute([':user_id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Определение пути к аватарке
$avatarPath = 'uploads/' . $user['id'] . '.jpg';
if (!file_exists($avatarPath)) {
    $avatarPath = 'user.jpeg'; // Стандартная картинка
}

// Обработка изменения профиля
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newPhone = trim($_POST['phone_number']);
    $newPassword = trim($_POST['password']);

    if (!empty($newPassword)) {
        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET phone_number = :new_phone, password = :new_password WHERE id = :user_id");
        $stmt->execute([':new_phone' => $newPhone, ':new_password' => $hashedNewPassword, ':user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET phone_number = :new_phone WHERE id = :user_id");
        $stmt->execute([':new_phone' => $newPhone, ':user_id' => $userId]);
    }

    // Обновляем переменную $user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo '<script>alert("Профиль успешно обновлен."); window.location.href = "profile.php";</script>';
}

// Обработка загрузки фотографии
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png'];
    if (in_array($_FILES['photo']['type'], $allowedTypes)) {
        move_uploaded_file($_FILES['photo']['tmp_name'], $avatarPath);
        echo '<script>alert("Фотография успешно загружена."); window.location.href = "profile.php";</script>';
    } else {
        echo '<script>alert("Некорректный формат файла. Поддерживаемые типы: JPEG, PNG");</script>';
    }
}

// Логика выхода из аккаунта
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль пользователя</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; }
        img.avatar { max-width: 150px; height: auto; border-radius: 50%; object-fit: cover; }
        h2 { margin-top: 20px; }
        p { margin-bottom: 10px; }
        form { margin-top: 20px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; }
        button { padding: 10px 20px; cursor: pointer; }
    </style>
</head>
<body>
    <img src="<?= $avatarPath ?>" alt="Аватарка" class="avatar">
    <form enctype="multipart/form-data" action="" method="post">
        <input type="file" name="photo" accept="image/*" /><br/>
        <button type="submit">Загрузить фото</button>
    </form>

    <h2><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' ' . $user['patronymic']) ?></h2>
    <p>Возраст: <?= floor((time() - strtotime($user['birth_date'])) / (60*60*24*365)) ?> лет</p>

    <?php if ($user['telegram_nickname']): ?>
        <p>Ник в Telegram: <?= htmlspecialchars($user['telegram_nickname']) ?></p>
    <?php else: ?>
        <p><a href="/connect_tg.php">Связать аккаунт с Telegram</a></p>
    <?php endif; ?>

    <h2>Редактировать профиль</h2>
    <form method="post">
        <label for="phone_number">Телефон (логин):</label>
        <input type="text" id="phone_number" name="phone_number" value="<?= htmlspecialchars($user['phone_number']) ?>" required><br>
        <label for="password">Новый пароль (оставьте пустым, если не хотите менять):</label>
        <input type="password" id="password" name="password"><br>
        <button type="submit">Обновить профиль</button>
    </form>

    <hr>
    <a href="profile.php?action=logout">Выйти из аккаунта</a>

    <!-- Новая секция с кнопкой для перехода на диагностику -->
    <section>
        <h2>Психодиагностика</h2>
        <a href="psyhodiagnostic.php" class="btn btn-primary">Психодиагностика</a>
    </section>
</body>
</html>