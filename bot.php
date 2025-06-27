<?php
define('BOT_TOKEN', 'ВАШ_TELEGRAM_BOT_TOKEN');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
define('WEBSITE_LOGIN_URL', 'http://ВАШ_ДОМЕН/login.php');

// Загрузка данных от Telegram API
$data = json_decode(file_get_contents('php://input'), true);

if (!empty($data)) {
    $chatId = $data['message']['from']['id'];              // ID пользователя в Telegram
    $telegramUsername = $data['message']['from']['username']; // Username пользователя
    $command = $data['message']['text'];

    // Если пользователь вводит команду /start
    if ($command === '/start') {
        require_once 'db.php';

        try {
            // Запрашиваем пользователя в базе данных по Telegram ID
            $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = :telegram_id");
            $stmt->execute([':telegram_id' => $chatId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Пользователь найден в базе данных
                sendTelegramMessage($chatId, 'Привет! Ваш аккаунт уже связан с нашим сервисом.');
                
                // Генерируем уникальный URL для авторизации пользователя
                $authUrl = WEBSITE_LOGIN_URL . '?tg_auth_token=' . bin2hex(random_bytes(16)); // Генерация случайного токена
                
                // Сохраняем токен в сессии или временную запись в базе данных для последующей проверки
                session_start(); // Начнём сессию для временного хранения токена
                $_SESSION['tg_auth_token'] = $authUrl;
                
                // Отправляем пользователю ссылку для авторизации
                sendTelegramMessage($chatId, 'Перейдите по ссылке для авторизации на нашем сайте:', ['parse_mode'=>'HTML', 'disable_web_page_preview'=>true], "<a href=\"{$authUrl}\">Авторизоваться на сайте</a>");
            } else {
                // Новый пользователь, которого нет в базе данных
                sendTelegramMessage($chatId, 'Добро пожаловать! Нажмите /start, чтобы связать ваш аккаунт с сайтом Psy-Woman.');
            }
        } catch (PDOException $e) {
            sendTelegramMessage($chatId, 'Ошибка при обработке запроса.');
        }
    }
}

// Функция отправки сообщений в Telegram
function sendTelegramMessage($chatId, $text, array $options = [], string $formatText = '') {
    global $API_URL;
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
    ];
    
    if (!empty($formatText)) {
        $params['parse_mode'] = $formatText;
    }
    
    foreach ($options as $key => $value) {
        $params[$key] = $value;
    }
    
    return file_get_contents($API_URL . 'sendMessage?' . http_build_query($params));
}