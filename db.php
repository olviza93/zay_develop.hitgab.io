<?php
// Настройка подключения к SQLite
$dbfile = __DIR__ . '/psychologist1.db';

try {
    $pdo = new PDO("sqlite:$dbfile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Создание таблицы пользователей
    $createUsersTableSql = <<<'EOD'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    first_name TEXT,
    last_name TEXT,
    patronymic TEXT,
    birth_date DATE,
    phone_number TEXT,
    telegram_id INTEGER DEFAULT NULL,
    telegram_nickname TEXT DEFAULT NULL,
    experience BOOLEAN DEFAULT FALSE,
    mental_illness BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
EOD;

    // Таблица психологов
    $createPsychologistsTableSql = <<<'EOD'
CREATE TABLE IF NOT EXISTS psychologists (
    user_id INTEGER PRIMARY KEY,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
EOD;

    $pdo->exec($createUsersTableSql);
    $pdo->exec($createPsychologistsTableSql);
} catch (PDOException $e) {
    die('Подключение не удалось: ' . $e->getMessage());
}