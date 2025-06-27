import sqlite3

# Подключаемся к базе данных
conn = sqlite3.connect('psychologist1.db')
cur = conn.cursor()

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