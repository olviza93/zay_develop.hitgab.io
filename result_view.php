<?php
session_start();
require_once 'db.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Получаем последний результат диагностики
if (isset($_GET['diagnostic_id'])) {
    $diagnosticId = intval($_GET['diagnostic_id']);

    // Берем последнюю диагностику
    $stmt = $pdo->prepare("SELECT result_json FROM final_results WHERE user_id = :user_id AND diagnostic_id = :diagnostic_id ORDER BY result_id DESC LIMIT 1");
    $stmt->execute([':user_id' => $userId, ':diagnostic_id' => $diagnosticId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Декодируем JSON-результаты
        $scales = json_decode($result['result_json'], true);

        // Выводим результаты
        echo '<h2>Итоги диагностики</h2>';
        foreach ($scales as $scale => $score) {
            echo '<p>Шкала ' . ucfirst($scale) . ': ' . $score . ' баллов</p>';
        }
    } else {
        echo '<p>Нет результатов для этой диагностики.</p>';
    }
} else {
    echo '<p>Ошибка: неверный запрос.</p>';
}
?>