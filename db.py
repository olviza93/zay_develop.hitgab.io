import sqlite3

# Подключаемся к базе данных (если файл не существует, он будет создан)
conn = sqlite3.connect('psychologist1.db')
cur = conn.cursor()

# 🔥 Создание таблиц

# Таблица пользователей
cur.execute('''
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    avatar_path TEXT DEFAULT NULL;
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
            
)
''')

# Таблица психологов (добавляем поле status)
cur.execute('''
CREATE TABLE IF NOT EXISTS psychologists (
    user_id INTEGER PRIMARY KEY,
    status TEXT CHECK(status IN ('admin', 'user')),
    FOREIGN KEY(user_id) REFERENCES users(id)
)
''')

# Таблица вопросов
cur.execute('''
CREATE TABLE IF NOT EXISTS test_questions (
    question_id INTEGER PRIMARY KEY AUTOINCREMENT,
    diagnostic_id INTEGER NOT NULL,
    question_text TEXT NOT NULL,
    scale TEXT NOT NULL,
    FOREIGN KEY(diagnostic_id) REFERENCES diagnostics(diagnostic_id)
)
''')

# Таблица вариантов ответов
cur.execute('''
CREATE TABLE IF NOT EXISTS answers_options (
    option_id INTEGER PRIMARY KEY AUTOINCREMENT,
    question_id INTEGER NOT NULL,
    option_text TEXT NOT NULL,
    points INTEGER NOT NULL,
    FOREIGN KEY(question_id) REFERENCES test_questions(question_id)
)
''')

# Таблица диагностик
cur.execute('''
CREATE TABLE IF NOT EXISTS diagnostics (
    diagnostic_id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    questions_count INTEGER,
    duration_minutes INTEGER
)
''')

# Таблица итоговых результатов
cur.execute('''
CREATE TABLE IF NOT EXISTS final_results (
    result_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    diagnostic_id INTEGER NOT NULL,
    raw_result_json TEXT,
    qualitative_result_json TEXT,
    passed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(diagnostic_id) REFERENCES diagnostics(diagnostic_id)
)
''')

# Таблица временных ответов
cur.execute('''
CREATE TABLE IF NOT EXISTS temp_answers (
    user_id INTEGER,
    diagnostic_id INTEGER,
    question_id INTEGER,
    option_id INTEGER,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(diagnostic_id) REFERENCES diagnostics(diagnostic_id),
    FOREIGN KEY(question_id) REFERENCES test_questions(question_id),
    FOREIGN KEY(option_id) REFERENCES answers_options(option_id),
    PRIMARY KEY(user_id, diagnostic_id, question_id)
)
''')

# 🔥 Пример данных для демо-версии

# 1. Добавляем первую диагностику
cur.execute("""
    INSERT INTO diagnostics (title, description, questions_count, duration_minutes)
    VALUES ('Диагностика уровня тревоги', 'Оценка тревожности.', 3, 5)
""")

# 2. Добавляем вопросы и их шкалы для первой диагностики
cur.execute("INSERT INTO test_questions (diagnostic_id, question_text, scale) VALUES (1, 'Частота чувства беспокойства', 'anxiety')")
cur.execute("INSERT INTO test_questions (diagnostic_id, question_text, scale) VALUES (1, 'Напряженность при выполнении дел', 'stress')")
cur.execute("INSERT INTO test_questions (diagnostic_id, question_text, scale) VALUES (1, 'Уровень утомления после напряженного дня', 'fatigue')")

# 3. Добавляем варианты ответов для первого вопроса первой диагностики
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (1, 'Очень редко', 1)")
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (1, 'Иногда', 2)")
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (1, 'Часто', 3)")
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (1, 'Практически всегда', 4)")

# 4. Добавляем варианты ответов для второго вопроса первой диагностики
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (2, 'Практически никогда', 1)")
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (2, 'Редко', 2)")
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (2, 'Иногда', 3)")
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (2, 'Почти всегда', 4)")

# 5. Добавляем варианты ответов для третьего вопроса первой диагностики
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (3, 'Практически никогда', 1)")
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (3, 'Редко', 2)")
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (3, 'Иногда', 3)")
cur.execute("INSERT INTO answers_options (question_id, option_text, points) VALUES (3, 'Почти всегда', 4)")

# Коммитим изменения и закрываем соединение
conn.commit()
conn.close()