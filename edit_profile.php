<?php
session_start();

// Проверяем, авторизован ли пользователь как психолог
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'психолог') {
    header('Location: index.php');
    exit;
}

// Получаем данные психолога из сессии
$user_id = $_SESSION['user_id'];

// Подключение к базе данных SQLite
$dsn = 'sqlite:' . dirname(__FILE__) . '/psychologist1.db';
$db_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];
try {
    $pdo = new PDO($dsn, null, null, $db_options);
} catch (PDOException $e) {
    die('Ошибка ' . $e->getMessage());
}

// Извлекаем информацию о психологе
$stmtPsychologist = $pdo->prepare("SELECT * FROM psychologists WHERE id = :user_id");
$stmtPsychologist->execute([':user_id' => $user_id]);
$psychologist = $stmtPsychologist->fetch();

if (!$psychologist) {
    header('Location: index.php');
    exit;
}

// Получаем список ВСЕХ методов
$stmtAllMethods = $pdo->query("SELECT id, name FROM methods ORDER BY name ASC");
$allMethods = $stmtAllMethods->fetchAll();

// Получаем список ВСЕХ запросов
$stmtAllQueries = $pdo->query("SELECT id, text FROM queries ORDER BY text ASC");
$allQueries = $stmtAllQueries->fetchAll();

// Получаем текущие методы, используемые психологом
$stmtUsedMethods = $pdo->prepare("
    SELECT m.id, m.name AS method_name
    FROM methods m
    JOIN psychologist_methods pm ON m.id = pm.method_id
    WHERE pm.psychologist_id = :user_id
");
$stmtUsedMethods->execute([':user_id' => $user_id]);
$usedMethodsIds = array_column($stmtUsedMethods->fetchAll(), 'id');

// Получаем текущие запросы, связанные с психологом
$stmtRelatedQueries = $pdo->prepare("
    SELECT q.id, q.text AS query_text
    FROM queries q
    JOIN psychologist_queries pq ON q.id = pq.query_id
    WHERE pq.psychologist_id = :user_id
");
$stmtRelatedQueries->execute([':user_id' => $user_id]);
$relatedQueryIds = array_column($stmtRelatedQueries->fetchAll(), 'id');

// Обработка отправки формы редактирования
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $family = trim($_POST['family']);
    $fname = trim($_POST['fname']);
    $phone = trim($_POST['phone']);
    $birthDate = trim($_POST['birth_date']);
    $specialty = trim($_POST['specialty']);
    $avgCost = floatval(trim($_POST['avg_cost']));
    $contacts = trim($_POST['contacts']);
    $fullDescription = trim($_POST['full_description']);
    $top5 = trim($_POST['top5']);

    // Массивы выбранных методов и запросов
    $selectedMethodIds = $_POST['methods'] ?? [];
    $selectedQueryIds = $_POST['queries'] ?? [];

    try {
        // Обновляем данные психолога
        $stmtUpdateProfile = $pdo->prepare("
            UPDATE psychologists 
            SET family=:family, fname=:fname, phone=:phone, birth_date=:birth_date, specialty=:specialty, avg_cost=:avg_cost, contacts=:contacts, full_description=:full_description, top5=:top5
            WHERE id=:user_id
        ");
        $stmtUpdateProfile->execute([
            ':family' => $family,
            ':fname' => $fname,
            ':phone' => $phone,
            ':birth_date' => $birthDate,
            ':specialty' => $specialty,
            ':avg_cost' => $avgCost,
            ':contacts' => $contacts,
            ':full_description' => $fullDescription,
            ':top5' => $top5,
            ':user_id' => $user_id
        ]);

        // Чистка старых связей и установка новых для методов
        $pdo->beginTransaction();
        $stmtDeleteOldMethods = $pdo->prepare("DELETE FROM psychologist_methods WHERE psychologist_id = :user_id");
        $stmtDeleteOldMethods->execute([':user_id' => $user_id]);

        foreach ($selectedMethodIds as $methodId) {
            $stmtInsertNewMethod = $pdo->prepare("INSERT INTO psychologist_methods (psychologist_id, method_id) VALUES (:user_id, :method_id)");
            $stmtInsertNewMethod->execute([':user_id' => $user_id, ':method_id' => $methodId]);
        }

        // Чистка старых связей и установка новых для запросов
        $stmtDeleteOldQueries = $pdo->prepare("DELETE FROM psychologist_queries WHERE psychologist_id = :user_id");
        $stmtDeleteOldQueries->execute([':user_id' => $user_id]);

        foreach ($selectedQueryIds as $queryId) {
            $stmtInsertNewQuery = $pdo->prepare("INSERT INTO psychologist_queries (psychologist_id, query_id) VALUES (:user_id, :query_id)");
            $stmtInsertNewQuery->execute([':user_id' => $user_id, ':query_id' => $queryId]);
        }

        $pdo->commit();

        header('Location: psyh.php');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo '<div class="alert alert-danger">Ошибка обновления данных: ' . $e->getMessage() . '</div>';
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование профиля психолога</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
        h1 { margin-bottom: 20px; }
        form { max-width: 500px; margin: auto; }
        .form-group { margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Редактирование профиля психолога</h1>
    <form method="POST">
        <div class="form-group">
            <label for="family">Фамилия:</label>
            <input type="text" id="family" name="family" class="form-control" value="<?= $psychologist['family'] ?>" required>
        </div>
        <div class="form-group">
            <label for="fname">Имя:</label>
            <input type="text" id="fname" name="fname" class="form-control" value="<?= $psychologist['fname'] ?>" required>
        </div>
        <div class="form-group">
            <label for="phone">Телефон:</label>
            <input type="tel" id="phone" name="phone" class="form-control" value="<?= $psychologist['phone'] ?>" required>
        </div>
        <div class="form-group">
            <label for="birth_date">Дата рождения:</label>
            <input type="date" id="birth_date" name="birth_date" class="form-control" value="<?= $psychologist['birth_date'] ?>" required>
        </div>
        <div class="form-group">
            <label for="specialty">Специальность:</label>
            <input type="text" id="specialty" name="specialty" class="form-control" value="<?= $psychologist['specialty'] ?>" required>
        </div>
        <div class="form-group">
            <label for="avg_cost">Стоимость консультации:</label>
            <input type="number" step="any" min="0" id="avg_cost" name="avg_cost" class="form-control" value="<?= $psychologist['avg_cost'] ?>" required>
        </div>
        <div class="form-group">
            <label for="contacts">Контакты:</label>
            <textarea id="contacts" name="contacts" class="form-control"><?= $psychologist['contacts'] ?></textarea>
        </div>
        <div class="form-group">
            <label for="full_description">О себе:</label>
            <textarea id="full_description" name="full_description" class="form-control"><?= $psychologist['full_description'] ?></textarea>
        </div>
        <div class="form-group">
            <label for="top5">Топ-5 запросов:</label>
            <textarea id="top5" name="top5" class="form-control"><?= $psychologist['top5'] ?></textarea>
        </div>

        <!-- Чекбоксы для методов -->
        <div class="form-group">
            <label>Методы:</label>
            <?php foreach ($allMethods as $method): ?>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="methods[]" value="<?= $method['id'] ?>" <?= in_array($method['id'], $usedMethodsIds) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($method['name']) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Чекбоксы для запросов -->
        <div class="form-group">
            <label>Запросы:</label>
            <?php foreach ($allQueries as $query): ?>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="queries[]" value="<?= $query['id'] ?>" <?= in_array($query['id'], $relatedQueryIds) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($query['text']) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
    </form>
</div>
</body>
</html>