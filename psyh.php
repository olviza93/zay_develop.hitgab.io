<?php
session_start();
require_once 'db.php';

// Получаем данные текущего пользователя
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'психолог') {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM psychologists WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Психолог не найден.";
    exit;
}


// Путь к папке для аватарок
$uploadDir = 'IMG/profil/psyh';
// Создаем папку, если её нет
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$avatarPath = $uploadDir . $user['id'] . '.jpg';

// Если нет аватарки, используем стандартную
if (!file_exists($avatarPath)) {
    $avatarPath = 'user.jpeg';
}


// Обработка изменения профиля
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newPhone = trim($_POST['phone_number']);
    $newPassword = trim($_POST['password']);

    if (!empty($newPassword)) {
        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE psychologists SET phone_number = :new_phone, password = :new_password WHERE user_id = :user_id");
        $stmt->execute([':new_phone' => $newPhone, ':new_password' => $hashedNewPassword, ':user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE psychologists SET phone_number = :new_phone WHERE user_id = :user_id");
        $stmt->execute([':new_phone' => $newPhone, ':user_id' => $userId]);
    }

    // Обновляем переменную $user
    $stmt = $pdo->prepare("SELECT * FROM psychologists WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo '<script>alert("Профиль успешно обновлен."); window.location.href = "psyh.php";</script>';
}

// Обработка загрузки фото
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png'];
    if (in_array($_FILES['photo']['type'], $allowedTypes)) {
        // Удалять старую аватарку больше не нужно, так как move_uploaded_file перезапишет файл

        // Перемещаем загруженный файл
        move_uploaded_file($_FILES['photo']['tmp_name'], $avatarPath); // Используем $avatarPath, чтобы сохранить файл с нужным именем
        echo '<script>alert("Фотография успешно загружена."); window.location.href = "client.php";</script>';
    } else {
        echo '<script>alert("Некорректный формат файла. Поддерживаемые типы: JPEG, PNG");</script>';
    }
}

// Обработка выхода
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль психолога</title>
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

    <p>Телефон: <?= htmlspecialchars($user['phone_number']) ?></p>
    <p>Email: <?= htmlspecialchars($user['email']) ?></p>
    <p>Специализация: <?= htmlspecialchars($user['specialization']) ?></p>
    <p>Опыт работы: <?= htmlspecialchars($user['experience']) ?> лет</p>


    <h2>Редактировать профиль</h2>
    <form method="post">
        <label for="phone_number">Телефон:</label>
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
