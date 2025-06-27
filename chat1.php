<?php
// chat1.php — Личный кабинет психолога для общения с клиентами

session_start();

$dbfile = __DIR__ . '/psychologist1.db'; // Путь к файлу БД
$uploadDir = __DIR__ . '/IMG/profil/doc/'; // Папка для загрузок
 
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Создание папки, если её нет
}

// Проверка авторизации психолога
if (!isset($_SESSION['user_id'])) {
    die('Пожалуйста, авторизуйтесь как психолог.'); // Прекращение работы, если не авторизован
}

$psychologist_id = (int)$_SESSION['user_id']; // ID психолога из сессии



    // Создание таблиц, если их нет
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS chat_dialogs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER NOT NULL,
        psychologist_id INTEGER DEFAULT NULL,
        status TEXT DEFAULT 'waiting',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

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
    )");

    // Добавление столбца 'status' в таблицу 'psychologists', если его нет
    try {
        $pdo->exec("ALTER TABLE psychologists ADD COLUMN status TEXT DEFAULT 'us'");
    } catch (PDOException $e) {
        // Игнорируем, если столбец уже существует
    }

    // Добавление столбца 'user_id' в таблицу 'psychologists', если его нет
    try {
        $pdo->exec("ALTER TABLE psychologists ADD COLUMN user_id INTEGER");
    } catch (PDOException $e) {
        // Игнорируем, если столбец уже существует
    }


} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage()); // Вывод ошибки БД и прекращение работы
}

// Проверка, что пользователь является психологом с нужными полномочиями
$stmt = $pdo->prepare("SELECT status FROM psychologists WHERE user_id = ?");
$stmt->execute([$psychologist_id]); // Выполнение запроса с параметром
$psy_row = $stmt->fetch(PDO::FETCH_ASSOC); // Получение результата в виде ассоциативного массива

if (!$psy_row) {
    die("Вы не психолог или не авторизованы."); // Прекращение работы, если пользователь не психолог или не авторизован
}

// Можно добавить проверку статуса $psy_row['status'] === 'admin' или возможность менять для разных полномочий

// Можно добавить проверку статуса $psy_row['status'] === 'admin' или возможность менять для разных полномочий

// --- Обработка AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // Получение новых безответных сообщений (waiting диалоги без psychologist_id)
    if ($action === 'get_waiting_messages') {
        // Получаем сообщения в диалогах с status='waiting' и psychologist_id IS NULL
        $stmt = $pdo->prepare("
            SELECT cd.id AS dialog_id, cd.client_id, cd.status,
                   cm.id AS message_id, cm.message, cm.file_path, cm.created_at
            FROM chat_dialogs cd
            JOIN chat_messages cm ON cm.dialog_id = cd.id
            WHERE cd.status = 'waiting' AND cd.psychologist_id IS NULL
            ORDER BY cm.created_at ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true, 'messages'=>$rows]);
        exit;
    }

    // Психолог нажимает "Ответить" на выбранный диалог => закрепляем за ним диалог
    if ($action === 'take_dialog') {
        $dialog_id = (int)($_POST['dialog_id'] ?? 0);
        if (!$dialog_id) {
            echo json_encode(['error'=>'Неверный ID диалога']);
            exit;
        }
        // Проверяем что диалог свободен и waiting
        $stmt = $pdo->prepare("SELECT psychologist_id, status FROM chat_dialogs WHERE id = ?");
        $stmt->execute([$dialog_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['error'=>'Диалог не найден']);
            exit;
        }
        if ($row['psychologist_id'] !== null || $row['status'] !== 'waiting') {
            echo json_encode(['error'=>'Диалог уже взят']);
            exit;
        }
        // Закрепляем за психологом и меняем статус на active
        $stmt = $pdo->prepare("UPDATE chat_dialogs SET psychologist_id = ?, status = 'active' WHERE id = ?");
        $stmt->execute([$psychologist_id, $dialog_id]);
        echo json_encode(['success'=>true]);
        exit;
    }

    // Получение диалогов психолога (status active или waiting, где психолог закреплен)
    if ($action === 'get_my_dialogs') {
        $stmt = $pdo->prepare("
            SELECT cd.id, cd.client_id, cd.status, u.username, u.first_name, u.last_name
            FROM chat_dialogs cd
            JOIN users u ON u.id = cd.client_id
            WHERE cd.psychologist_id = ? AND cd.status IN ('active','waiting')
            ORDER BY cd.created_at DESC
        ");
        $stmt->execute([$psychologist_id]);
        $dialogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true, 'dialogs'=>$dialogs]);
        exit;
    }

    // Получение сообщений для заданного диалога психолога
    if ($action === 'get_dialog_messages') {
        $dialog_id = (int)($_POST['dialog_id'] ?? 0);
        if (!$dialog_id) {
            echo json_encode(['error'=>'Неверный ID диалога']);
            exit;
        }
        // Проверка что диалог принадлежит психологу
        $stmt = $pdo->prepare("SELECT * FROM chat_dialogs WHERE id = ? AND psychologist_id = ?");
        $stmt->execute([$dialog_id, $psychologist_id]);
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

    // Отправка сообщения психолога
    if ($action === 'send_message') {
        $dialog_id = (int)($_POST['dialog_id']??0);
        $message = trim($_POST['message'] ?? '');
        if (!$dialog_id) {
            echo json_encode(['error'=>'Неверный ID диалога']);
            exit;
        }
        // Проверка диалога за психологом и статус active
        $stmt = $pdo->prepare("SELECT * FROM chat_dialogs WHERE id = ? AND psychologist_id = ? AND status = 'active'");
        $stmt->execute([$dialog_id, $psychologist_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error'=>'Диалог не доступен для сообщений']);
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
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = uniqid('psych' . $psychologist_id . '_') . '.' . $ext;
            $fullpath = $uploadDir . $newName;
            if (!move_uploaded_file($file['tmp_name'], $fullpath)) {
                echo json_encode(['error' => 'Ошибка загрузки файла']);
                exit;
            }
            $file_path = 'IMG/profil/doc/' . $newName;
        }

        if ($message === '' && $file_path === null) {
            echo json_encode(['error' => 'Пустое сообщение']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (dialog_id, sender_type, sender_id, message, file_path)
            VALUES (?, 'psychologist', ?, ?, ?)
        ");
        $stmt->execute([$dialog_id, $psychologist_id, $message, $file_path]);

        echo json_encode(['success'=>true]);
        exit;
    }

    // Завершение диалога психологом
    if ($action === 'end_dialog') {
        $dialog_id = (int)($_POST['dialog_id'] ?? 0);
        if (!$dialog_id) {
            echo json_encode(['error'=>'Неверный ID диалога']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM chat_dialogs WHERE id = ? AND psychologist_id = ? AND status IN ('active','waiting')");
        $stmt->execute([$dialog_id, $psychologist_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error'=>'Диалог недоступен']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE chat_dialogs SET status = 'finished' WHERE id = ?");
        $stmt->execute([$dialog_id]);
        echo json_encode(['success'=>true]);
        exit;
    }

    // Поиск клиента по ID и открытие нового диалога
    if ($action === 'find_client') {
        $client_id = (int)($_POST['client_id'] ?? 0);
        if (!$client_id) {
            echo json_encode(['error'=>'Введите корректный ID клиента']);
            exit;
        }
        // Проверка что клиент существует
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$client_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error'=>'Клиент не найден']);
            exit;
        }

        // Проверяем есть ли уже открытый диалог с этим клиентом у данного психолога в статусах active|waiting
        $stmt = $pdo->prepare("
            SELECT id FROM chat_dialogs 
            WHERE client_id = ? AND psychologist_id = ? AND status IN ('active','waiting')
            LIMIT 1
        ");
        $stmt->execute([$client_id, $psychologist_id]);
        $existing_dialog = $stmt->fetchColumn();
        if ($existing_dialog) {
            echo json_encode(['success'=>true, 'dialog_id'=>$existing_dialog]);
            exit;
        }

        // Создаем диалог, сразу закрепляем за психологом и сразу активный
        $stmt = $pdo->prepare("
            INSERT INTO chat_dialogs (client_id, psychologist_id, status) VALUES (?, ?, 'active')
        ");
        $stmt->execute([$client_id, $psychologist_id]);
        $dialog_id = $pdo->lastInsertId();

        echo json_encode(['success'=>true, 'dialog_id'=>$dialog_id]);
        exit;
    }

    echo json_encode(['error'=>'Неизвестное действие']);
    exit;
}

// --- Отрисовка страницы ---

?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Чат психолога</title>
<style>
body { font-family: Arial,sans-serif; margin: 20px; }
.container {
    max-width: 900px;
    margin: 0 auto;
    display: flex;
    gap: 20px;
}
.left-panel {
    width: 300px;
    border: 1px solid #ccc;
    padding: 10px;
    box-sizing: border-box;
}
.right-panel {
    flex-grow: 1;
    border: 1px solid #ccc;
    padding: 10px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}
.chat-window {
    flex-grow: 1;
    overflow-y: auto;
    border: 1px solid #aaa;
    background: #f9f9f9;
    padding: 10px;
    margin-bottom: 10px;
}
.message {
    margin-bottom: 12px;
}
.message.client {
    background: #d0ffd6;
    padding: 6px 10px;
    border-radius: 10px;
    max-width: 75%;
}
.message.psychologist {
    background: #d0d8ff;
    padding: 6px 10px;
    border-radius: 10px;
    max-width: 75%;
    margin-left: auto;
}
.message .file-link {
    display: block;
    margin-top: 5px;
    color: blue;
    text-decoration: underline;
}
.dialog-item {
    cursor: pointer;
    border: 1px solid #aaa;
    padding: 8px;
    border-radius: 5px;
    margin-bottom: 8px;
}
.dialog-item.active {
    background-color: #d0eaff;
}
.status-waiting {
    color: orange;
    font-weight: bold;
}
.status-active {
    color: green;
    font-weight: bold;
}
.status-finished {
    color: gray;
    font-weight: normal;
}
#send-form textarea {
    width: 100%;
    height: 80px;
    resize: vertical;
}
#search-client-form {
    margin-bottom: 15px;
}
</style>
</head>
<body>

<h2>Чат психолога</h2>
<div class="container">
    <div class="left-panel">
        <form id="search-client-form">
            <label>Поиск клиента по ID:</label><br>
            <input type="number" id="client-id-input" min="1" required>
            <button type="submit">Начать диалог</button>
        </form>
        <h3>Мои активные диалоги</h3>
        <div id="my-dialogs"></div>
        <h3>Новые запросы клиентов</h3>
        <div id="waiting-messages"></div>
    </div>
    <div class="right-panel">
        <h3>Диалог: <span id="dialog-client-name">Нет выбранного диалога</span></h3>
        <div class="chat-window" id="chat-window"></div>
        <form id="send-form" enctype="multipart/form-data" style="display:none;">
            <textarea name="message" placeholder="Введите сообщение..."></textarea><br>
            <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,audio/*,video/*"><br>
            <input type="hidden" name="dialog_id" value="">
            <button type="submit">Отправить</button>
            <button type="button" id="btn-end-dialog" style="background:#e74c3c;margin-left:10px;">Завершить диалог</button>
        </form>
    </div>
</div>

<script>
(() => {
    let psychologistId = <?= $psychologist_id ?>;
    let currentDialogId = null;
    let dialogs = [];
    let waitingMessages = [];

    const myDialogsDiv = document.getElementById('my-dialogs');
    const waitingMessagesDiv = document.getElementById('waiting-messages');
    const chatWindow = document.getElementById('chat-window');
    const dialogClientNameSpan = document.getElementById('dialog-client-name');
    const sendForm = document.getElementById('send-form');
    const clientIdInput = document.getElementById('client-id-input');
    const searchForm = document.getElementById('search-client-form');
    const btnEndDialog = document.getElementById('btn-end-dialog');
    const dialogIdInput = sendForm.querySelector('[name=dialog_id]');

    // Функция отрисовки списка диалогов
    function renderDialogs() {
        myDialogsDiv.innerHTML = '';
        dialogs.forEach(d => {
            const div = document.createElement('div');
            div.className = 'dialog-item';
            if (currentDialogId == d.id) div.classList.add('active');

            div.innerHTML = `<strong>${d.first_name || ''} ${d.last_name || ''} (ID: ${d.client_id})</strong><br>
                <span class="${statusClass(d.status)}">${d.status}</span>`;

            div.addEventListener('click', () => selectDialog(d.id));

            myDialogsDiv.appendChild(div);
        });
    }
    function statusClass(status) {
        switch (status) {
            case 'waiting': return 'status-waiting';
            case 'active': return 'status-active';
            case 'finished': return 'status-finished';
            default: return '';
        }
    }

    // Функция отрисовки новых запросов (сообщения в диалогах waiting и без психолога)
    function renderWaitingMessages() {
        waitingMessagesDiv.innerHTML = '';
        if(waitingMessages.length === 0){
            waitingMessagesDiv.textContent = 'Нет новых запросов.';
            return;
        }
        waitingMessages.forEach(msg => {
            const div = document.createElement('div');
            div.className = 'dialog-item';
            div.innerHTML = `<strong>Клиент ID: ${msg.client_id}</strong><br>${msg.message ? msg.message.substring(0, 40) : 'Файл'}<br>
            <button data-dialog="${msg.dialog_id}">Взять в работу</button>`;
            div.querySelector('button').addEventListener('click', () => {
                takeDialog(msg.dialog_id);
            });
            waitingMessagesDiv.appendChild(div);
        });
    }

    // Выбор диалога
    async function selectDialog(dialog_id) {
        currentDialogId = dialog_id;
        const dialog = dialogs.find(d=>d.id == dialog_id);
        if (!dialog) return alert('Диалог не найден');

        dialogClientNameSpan.textContent = (dialog.first_name || '') + ' ' + (dialog.last_name || '') + ' (ID:' + dialog.client_id + ')';
        dialogIdInput.value = dialog_id;
        sendForm.style.display = (dialog.status === 'finished') ? 'none' : 'block';

        renderDialogs();

        await fetchDialogMessages(dialog_id);
    }

    async function fetchMyDialogs() {
        let formData = new FormData();
        formData.append('action','get_my_dialogs');
        const res = await fetch('',{method:'POST',body:formData});
        const data = await res.json();
        if(data.success){
            dialogs = data.dialogs;
            renderDialogs();
            // Если выбран диалог, обновим у него сообщения
            if(currentDialogId){
                let dialogExists = dialogs.some(d=>d.id == currentDialogId);
                if(dialogExists){
                    await fetchDialogMessages(currentDialogId);
                } else {
                    currentDialogId = null;
                    chatWindow.innerHTML = '';
                    dialogClientNameSpan.textContent = 'Нет выбранного диалога';
                    sendForm.style.display = 'none';
                }
            }
        }
    }

    // Получение сообщений для текущего диалога
    async function fetchDialogMessages(dialog_id) {
        let formData = new FormData();
        formData.append('action','get_dialog_messages');
        formData.append('dialog_id', dialog_id);
        const res = await fetch('', {method:'POST', body:formData});
        const data = await res.json();
        if(data.success){
            renderMessages(data.messages);
        } else {
            chatWindow.innerHTML = 'Не удалось загрузить сообщения';
        }
    }

    function renderMessages(messages) {
        chatWindow.innerHTML = '';
        messages.forEach(msg => {
            const div = document.createElement('div');
            div.classList.add('message');
            div.classList.add(msg.sender_type === 'client' ? 'client' : 'psychologist');
            div.textContent = msg.message || '';
            if(msg.file_path){
                const a = document.createElement('a');
                a.href = msg.file_path;
                a.target = '_blank';
                a.textContent = 'Прикрепленный файл';
                a.className = 'file-link';
                div.appendChild(document.createElement('br'));
                div.appendChild(a);
            }
            chatWindow.appendChild(div);
        });
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    // Взять диалог в работу (закрепить за психологом)
    async function takeDialog(dialog_id){
        let formData = new FormData();
        formData.append('action','take_dialog');
        formData.append('dialog_id', dialog_id);
        const res = await fetch('',{method:'POST', body: formData});
        const data = await res.json();
        if(data.success){
            alert('Вы взяли диалог в работу');
            await refreshAll();
            selectDialog(dialog_id);
        } else {
            alert(data.error || 'Ошибка при взятии диалога');
        }
    }

    // Поиск клиента и открытие диалога
    searchForm.addEventListener('submit', async e => {
        e.preventDefault();
        const clientId = clientIdInput.value.trim();
        if(clientId === '') return alert('Введите ID клиента');
        let formData = new FormData();
        formData.append('action', 'find_client');
        formData.append('client_id', clientId);
        const res = await fetch('',{method:'POST',body:formData});
        const data = await res.json();
        if(data.success){
            await refreshAll();
            selectDialog(data.dialog_id);
        } else {
            alert(data.error || 'Ошибка поиска клиента');
        }
    });

    // Отправка сообщения психолога
    sendForm.addEventListener('submit', async e => {
        e.preventDefault();
        if (!currentDialogId) {
            alert('Выберите диалог');
            return;
        }
        let formData = new FormData(sendForm);
        formData.append('action', 'send_message');
        const res = await fetch('', {method:'POST', body:formData});
        const data = await res.json();
        if(data.success){
            sendForm.message.value = '';
            sendForm.file.value = '';
            await fetchDialogMessages(currentDialogId);
        } else {
            alert(data.error || 'Ошибка отправки');
        }
    });

    // Завершение диалога
    btnEndDialog.addEventListener('click', async () => {
        if(!currentDialogId) return alert('Диалог не выбран');
        if(!confirm('Закончить диалог?')) return;
        let formData = new FormData();
        formData.append('action', 'end_dialog');
        formData.append('dialog_id', currentDialogId);
        const res = await fetch('', {method:'POST', body:formData});
        const data = await res.json();
        if(data.success){
            alert('Диалог завершен');
            sendForm.style.display = 'none';
            await refreshAll();
            currentDialogId = null;
            chatWindow.innerHTML = '';
            dialogClientNameSpan.textContent = 'Нет выбранного диалога';
        } else {
            alert(data.error || 'Ошибка завершения');
        }
    });

    // Получаем новые сообщения от клиентов, ожидающих ответа
    async function fetchWaitingMessages() {
        let formData = new FormData();
        formData.append('action', 'get_waiting_messages');
        const res = await fetch('', {method:'POST', body: formData});
        const data = await res.json();
        if(data.success){
            waitingMessages = data.messages;
            renderWaitingMessages();
        }
    }

    async function refreshAll(){
        await fetchMyDialogs();
        await fetchWaitingMessages();
    }

    // Обновлять автоматически
    setInterval(refreshAll, 5000);
    refreshAll();

})();
</script>

</body>
</html>