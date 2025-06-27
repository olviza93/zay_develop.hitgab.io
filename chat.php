<?php
session_start();

// Путь к базе данных
$dbfile = __DIR__ . '/psychologist1.db';

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    die('Пожалуйста, авторизуйтесь как клиент.');
}

// Получаем ID пользователя из сессии
$user_id = (int)$_SESSION['user_id'];

try {
    // Подключение к базе данных SQLite
    $pdo = new PDO("sqlite:" . $dbfile);
    // Установка режима обработки ошибок PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Отключение эмуляции подготовленных запросов
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Добавление столбца 'status' в таблицу 'psycholog'
    $pdo->exec("ALTER TABLE psycholog ADD COLUMN status TEXT DEFAULT 'us'");

    // Создание таблицы 'chat_dialogs', если ее нет
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_dialogs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_id INTEGER NOT NULL,
            psychologist_id INTEGER DEFAULT NULL,
            status TEXT DEFAULT 'waiting',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Создание таблицы 'chat_messages', если ее нет
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            dialog_id INTEGER NOT NULL,
            sender_type TEXT NOT NULL,
            sender_id INTEGER NOT NULL,
            message TEXT,
            file_path TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(dialog_id) REFERENCES chat_dialogs(id)
        )
    ");
} catch (PDOException $e) {
    // Обработка ошибок подключения к базе данных
    die("Ошибка БД: " . $e->getMessage());
}

// Функция для получения активного диалога клиента
function getActiveDialog($pdo, $user_id) {
    // Подготовка SQL-запроса
    $stmt = $pdo->prepare("
        SELECT * FROM chat_dialogs
        WHERE client_id = ? AND status IN ('waiting','active')
        ORDER BY created_at DESC
        LIMIT 1
    ");
    // Выполнение запроса с параметром user_id
    $stmt->execute([$user_id]);
    // Возвращаем ассоциативный массив с данными диалога
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// --- Обработка AJAX запросов ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // Создать новый диалог (в случае если нет активного)
    if ($action === 'start_dialog') {
        $dialog = getActiveDialog($pdo, $user_id);
        if ($dialog) {
            echo json_encode(['success'=>true, 'dialog_id'=>$dialog['id']]);
            exit;
        }
        // Создаем новый диалог без psychologist_id
        $stmt = $pdo->prepare("INSERT INTO chat_dialogs (client_id) VALUES (?)");
        $stmt->execute([$user_id]);
        echo json_encode(['success'=>true, 'dialog_id'=>$pdo->lastInsertId()]);
        exit;
    }

    // Отправка сообщения (текст и/или файл)
    if ($action === 'send_message') {
        $dialog_id = (int)($_POST['dialog_id']??0);
        $message = trim($_POST['message'] ?? '');
        if (!$dialog_id) {
            echo json_encode(['error'=>'Неверный ID диалога']);
            exit;
        }

        // Проверяем, что диалог принадлежит клиенту и активен
        $stmt = $pdo->prepare("SELECT * FROM chat_dialogs WHERE id = ? AND client_id = ? AND status IN ('waiting','active')");
        $stmt->execute([$dialog_id, $user_id]);
        $dialog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dialog) {
            echo json_encode(['error'=>'Диалог не найден или завершён']);
            exit;
        }

        $file_path = null;
        if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $allowed = [
                'application/pdf',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'audio/mpeg', 'audio/ogg', 'audio/wav', 'video/mp4', 'video/x-msvideo', 'video/webm'
            ];
            $file = $_FILES['file'];
            if (!in_array($file['type'], $allowed)) {
                echo json_encode(['error' => 'Недопустимый формат файла']);
                exit;
            }
            // Генерируем уникальное имя
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = uniqid('client' . $user_id . '_') . '.' . $ext;
            $fullpath = $uploadDir . $newName;
            if (!move_uploaded_file($file['tmp_name'], $fullpath)) {
                echo json_encode(['error' => 'Ошибка загрузки файла']);
                exit;
            }
            // Сохраняем путь, относительный к сайту
            $file_path = 'IMG/profil/doc/' . $newName;
        }

        if ($message === '' && $file_path === null) {
            echo json_encode(['error' => 'Пустое сообщение']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (dialog_id, sender_type, sender_id, message, file_path) 
            VALUES (?, 'client', ?, ?, ?)
        ");
        $stmt->execute([$dialog_id, $user_id, $message, $file_path]);

        echo json_encode(['success'=>true]);
        exit;
    }

    // Получение сообщений диалога клиента (для обновления чата)
    if ($action === 'get_messages') {
        $dialog_id = (int)($_POST['dialog_id'] ?? 0);
        if (!$dialog_id) {
            echo json_encode(['error'=>'Неверный ID диалога']);
            exit;
        }
        // Проверка что диалог принадлежит клиенту
        $stmt = $pdo->prepare("SELECT * FROM chat_dialogs WHERE id = ? AND client_id = ?");
        $stmt->execute([$dialog_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error'=>'Диалог не найден']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE dialog_id = ? ORDER BY created_at ASC");
        $stmt->execute([$dialog_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true, 'messages'=>$messages]);
        exit;
    }

    // Завершить диалог клиентом
    if ($action === 'end_dialog') {
        $dialog_id = (int)($_POST['dialog_id'] ?? 0);
        if (!$dialog_id) {
            echo json_encode(['error'=>'Неверный ID диалога']);
            exit;
        }
        // Проверяем право клиента завершать
        $stmt = $pdo->prepare("SELECT * FROM chat_dialogs WHERE id = ? AND client_id = ? AND status IN ('active','waiting')");
        $stmt->execute([$dialog_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error'=>'Диалог недоступен для завершения']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE chat_dialogs SET status = 'finished' WHERE id = ?");
        $stmt->execute([$dialog_id]);
        echo json_encode(['success'=>true]);
        exit;
    }
    
    echo json_encode(['error'=>'Неизвестное действие']);
    exit;
}

// --- Получаем текущий активный или ждём начала новый диалог ---
$activeDialog = getActiveDialog($pdo, $user_id);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Чат клиента</title>
<style>
body { font-family: Arial,sans-serif; margin: 20px; }
.chat-window {
    border: 1px solid #ccc;
    max-width: 700px;
    min-height: 300px;
    padding: 10px;
    overflow-y: auto;
    background: #f9f9f9;
    margin-bottom: 10px;
}
.message {
    margin-bottom: 12px;
}
.message.client {
    text-align: right;
}
.message .text {
    display: inline-block;
    background-color: #e2ffc7;
    padding: 8px 12px;
    border-radius: 15px;
    max-width: 80%;
}
.message.psychologist .text {
    background-color: #d0d8ff;
}
.message .file-link {
    display: block;
    margin-top: 5px;
}
#send-form {
    max-width: 700px;
}
#send-form textarea {
    width: 100%;
    height: 80px;
    resize: vertical;
}
</style>
</head>
<body>

<h2>Чат с психологом</h2>

<div id="chat-section">
    <?php if (!$activeDialog): ?>
        <p>У вас нет активного диалога с психологом.</p>
        <button id="btn-start-dialog">Начать новый диалог</button>
    <?php else: ?>
        <div>Статус диалога: <strong><?= htmlspecialchars($activeDialog['status']) ?></strong></div>
        <div class="chat-window" id="chat-window"></div>
        <?php if ($activeDialog['status'] !== 'finished'): ?>
        <form id="send-form" enctype="multipart/form-data">
            <textarea name="message" placeholder="Введите сообщение..."></textarea><br>
            <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,audio/*,video/*"><br>
            <input type="hidden" name="dialog_id" value="<?= (int)$activeDialog['id'] ?>">
            <button type="submit">Отправить</button>
            <button type="button" id="btn-end-dialog" style="background:#e74c3c;margin-left:10px;">Завершить диалог</button>
        </form>
        <?php else: ?>
            <p>Диалог завершён.</p>
            <button id="btn-start-dialog">Начать новый диалог</button>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(() => {
    const userId = <?= json_encode($user_id) ?>;
    let dialogId = <?= $activeDialog ? (int)$activeDialog['id'] : 'null' ?>;
    const chatWindow = document.getElementById('chat-window');

    function renderMessages(messages) {
        chatWindow.innerHTML = '';
        messages.forEach(msg => {
            const div = document.createElement('div');
            div.classList.add('message');
            div.classList.add(msg.sender_type === 'client' ? 'client' : 'psychologist');
            const textDiv = document.createElement('div');
            textDiv.className = 'text';
            textDiv.textContent = msg.message || '';
            div.appendChild(textDiv);
            if (msg.file_path) {
                const a = document.createElement('a');
                a.href = msg.file_path;
                a.target = '_blank';
                a.className = 'file-link';
                a.textContent = 'Прикрепленный файл';
                div.appendChild(a);
            }
            chatWindow.appendChild(div);
        });
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    async function fetchMessages(){
        if (!dialogId) return;
        const formData = new FormData();
        formData.append('action', 'get_messages');
        formData.append('dialog_id', dialogId);
        const res = await fetch('', { method: 'POST', body: formData });
        const data = await res.json();
        if(data.success){
            renderMessages(data.messages);
        }
    }

    // Для старта нового диалога
    const btnStartDialog = document.getElementById('btn-start-dialog');
    if(btnStartDialog){
        btnStartDialog.addEventListener('click', async () => {
            const formData = new FormData();
            formData.append('action','start_dialog');
            const res = await fetch('',{method:'POST',body:formData});
            const data = await res.json();
            if(data.success){
                dialogId = data.dialog_id;
                location.reload();
            } else {
                alert(data.error || 'Ошибка старта диалога');
            }
        });
    }

    // Отправка сообщения
    const sendForm = document.getElementById('send-form');
    if(sendForm){
        sendForm.addEventListener('submit', async e => {
            e.preventDefault();
            if(!dialogId){
                alert('Диалог не выбран');
                return;
            }
            const formData = new FormData(sendForm);
            formData.append('action','send_message');
            const res = await fetch('', {method:'POST', body:formData});
            const data = await res.json();
            if(data.success){
                sendForm.message.value = '';
                sendForm.file.value = '';
                fetchMessages();
            } else {
                alert(data.error || 'Ошибка отправки');
            }
        });

        // Кнопка "Завершить диалог"
        const btnEndDialog = document.getElementById('btn-end-dialog');
        btnEndDialog?.addEventListener('click', async () => {
            if(!dialogId) return alert('Диалог не найден');
            if(!confirm('Закончить диалог?')) return;
            const formData = new FormData();
            formData.append('action','end_dialog');
            formData.append('dialog_id', dialogId);
            const res = await fetch('',{method:'POST', body:formData});
            const data = await res.json();
            if(data.success){
                alert('Диалог завершен');
                location.reload();
            } else {
                alert(data.error || 'Ошибка завершения');
            }
        });
    }

    if(dialogId){
        fetchMessages();
        setInterval(fetchMessages, 3000);
    }
})();
</script>

</body>
</html>