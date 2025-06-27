<?php
// index.php
session_start();

$dbfile = __DIR__ . '/psychologist1.db';

try {
    $pdo = new PDO("sqlite:$dbfile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS temp_results (
        user_id INTEGER NOT NULL,
        diag_id INTEGER NOT NULL,
        question_number INTEGER NOT NULL,
        answer_chosen INTEGER NOT NULL,
        scale TEXT NOT NULL,
        points INTEGER NOT NULL,
        PRIMARY KEY(user_id, diag_id, question_number)
    );
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS final_results (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        diag_id INTEGER NOT NULL,
        result_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

} catch (PDOException $e) {
    die('Ошибка БД: ' . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
$user_id = $_SESSION['user_id'];

// Диагностики и данные вопросов/ответов (не изменены)
$diagnostics = [
    1 => [
        'photo'=> 'IMG/dig1.png',
        'title' => 'Экспресс-тест на самооценку',
        'theme' => 'Самооценка',
        'description' => 'Экспресс-диагностика самооценки.',
        'time' => '7 мин',
        'questions_count' => 15,
        'questions' => [
            1 => [
                'text' => ' Мне кажется, что я делаю свою работу хуже, чем остальные.',
                'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            2 => [
                'text' => 'Я боюсь выглядеть глупо.',
                    'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            3 => [
                'text' => 'Мне кажется, что окружающие смотрят на меня осуждающе.',
                      'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],

    4 => [
                'text' => 'Я беспокоюсь за своё будущее.',
                'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            5 => [
                'text' => 'Думаю, окружающие меня люди гораздо привлекательнее, чем я сам.',
                    'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            6 => [
                'text' => 'Как жаль, что многие не понимают меня.',
                      'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],




                7 => [
                'text' => 'Чувствую, что не умею свободно общаться с людьми, стесняюсь, не знаю, что сказать.',
                'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            8 => [
                'text' => 'Временами я чувствую себя никому не нужным.',
                    'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            9 => [
                'text' => 'Чувствую себя скованным.',
                      'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],




                10 => [
                'text' => 'Мне кажется, что со мной должна случиться какая-нибудь неприятность.',
                'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            11 => [
                'text' => 'Меня волнует мысль о том, как люди относятся ко мне, что обо мне подумают.',
                    'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            12 => [
                'text' => 'Я чувствую, что люди говорят обо мне за моей спиной.',
                      'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],




                13 => [
                'text' => 'Я не чувствую себя в безопасности.',
                'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            14 => [
                'text' => 'Мне не с кем поделиться своими мыслями.',
                    'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
            15 => [
                'text' => 'Когда я узнаю об успехах кого-нибудь из знакомых,я ощущаю это как собственное поражение.',
                      'answers' => [
                    1 => ['text' => 'Очень часто', 'points' => 4, 'scale' => 'A'],
                    2 => ['text' => 'Часто', 'points' => 3, 'scale' => 'A'],
                    3 => ['text' => 'Иногда', 'points' => 2, 'scale' => 'A'],
                    4 => ['text' => 'Редко', 'points' => 1, 'scale' => 'A'],
                    5 => ['text' => 'Никогда', 'points' => 0, 'scale' => 'A'],
                ],
            ],
        ],
        'interpretations' => [
            'A' => [
                ['min' => 0, 'max' => 10, 'text' => 'Завышенный уровень самооценки'],
                ['min' => 11, 'max' => 29, 'text' => 'Адекватный, нормативный уровень реалистичной оценки своих возможностей.'],
                ['min' => 30, 'max' => 10, 'text' => 'Заниженный уровень самооценки'],
            ],
        ],
    ],
    2 => ['photo'=> 'IMG/dig1.png',
        'title' => 'Диагностика уровня стресса',
        'theme' => 'Стресс',
        'description' => 'Тест для определения уровня стресса',
        'time' => '10 мин',
        'questions_count' => 20,
        'questions' => [
            1 => [
                'text' => 'Легко ли вы раздражаетесь даже из-за мелочей?',
                'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            2 => [
                'text' => 'Нервничаете ли, если приходится чего-либо ждать?',
                  'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            3 => [
                'text' => ' Краснеете ли, когда испытываете неловкость?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            4 => [
                'text' => 'Можете ли в раздражении обидеть кого-нибудь?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            5 => [
                'text' => 'Выносите ли критику или она выводит вас из себя?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
             6 => [
                'text' => 'Если вас толкнут в автобусе, постараетесь ли вы ответить обидчику тем же
или скажете что-то обидное; при управлении автомобилем часто жмете на
клаксон?',
                'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            7 => [
                'text' => 'Всегда ли все ваше время заполнено какой-либо деятельностью?',
                  'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            8 => [
                'text' => 'Свойственна ли вам пунктуальность: часто ли вы опаздываете?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            9 => [
                'text' => 'Умеете ли вы выслушивать других: всегда перебиваете их, дополняете
высказывания?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            10 => [
                'text' => 'Страдаете ли отсутствием аппетита?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
             11 => [
                'text' => ' Часто испытываете беспричинное беспокойство?',
                'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            12 => [
                'text' => ' Плохо чувствуете себя по утрам, кружится ли у вас голова?',
                  'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            13 => [
                'text' => 'Испытываете ли постоянную усталость, легко ли «отключаетесь»?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            14 => [
                'text' => 'Даже после продолжительного сна не чувствуете ли себя «разбитым»?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            15 => [
                'text' => 'Считаете ли вы, что у вас что-то не в порядке с сердцем?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
             16 => [
                'text' => 'Страдаете ли от болей в спине или шее?',
                'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            17 => [
                'text' => 'Часто ли барабаните пальцами по столу, а сидя — покачиваете ногой?',
                  'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            18 => [
                'text' => 'Мечтаете ли вы о признании, хотите ли, чтобы вас хвалили за то, что вы
делаете?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            19 => [
                'text' => 'Считаете ли вы себя лучше многих других, но никто этого не замечает?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
            20 => [
                'text' => 'Находитесь ли вы на диете? Стремитесь ли изменить свой вес?',
                 'answers' => [
                    1 => ['text' => 'Почти никогда', 'points' => 1, 'scale' => 'А'],
                    2 => ['text' => 'Редко', 'points' => 2, 'scale' => 'А'],
                    3 => ['text' => 'Часто', 'points' => 3, 'scale' => 'А'],
                    4 => ['text' => 'Почти всегда ', 'points' => 4, 'scale' => 'А'],
                ],
            ],
        ],
        'interpretations' => [
            'А' => [
                ['min' => 0, 'max' => 30, 'text' => 'Интерпретация X низкий'],
                ['min' => 31, 'max' => 45, 'text' => 'Интерпретация X средний'],
                ['min' => 46, 'max' => 60, 'text' => 'Интерпретация X высокий'],
                ['min' => 61, 'max' => 80, 'text' => 'Интерпретация X высокий'],
            ],
        ],
    ],
];

// Обработка AJAX по action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'start_test') {
        $diag_id = (int)($_POST['diag_id'] ?? 0);
        if (!$diag_id || !isset($diagnostics[$diag_id])) {
            echo json_encode(['error' => 'Неверная диагностика']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM temp_results WHERE user_id = ? AND diag_id = ?");
        $stmt->execute([$user_id, $diag_id]);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'save_answer') {
        $diag_id = (int)($_POST['diag_id'] ?? 0);
        $question_number = (int)($_POST['question_number'] ?? 0);
        $answer_chosen = (int)($_POST['answer_chosen'] ?? 0);
        if (!$diag_id || !$question_number || !$answer_chosen ||
            !isset($diagnostics[$diag_id]) ||
            !isset($diagnostics[$diag_id]['questions'][$question_number]) ||
            !isset($diagnostics[$diag_id]['questions'][$question_number]['answers'][$answer_chosen])) {
            echo json_encode(['error' => 'Неверные данные ответа']);
            exit;
        }
        $answer_data = $diagnostics[$diag_id]['questions'][$question_number]['answers'][$answer_chosen];
        $points = $answer_data['points'];
        $scale = $answer_data['scale'];
        $stmt = $pdo->prepare("
            INSERT INTO temp_results (user_id, diag_id, question_number, answer_chosen, scale, points)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT(user_id, diag_id, question_number) DO UPDATE SET
                answer_chosen = excluded.answer_chosen,
                scale = excluded.scale,
                points = excluded.points
        ");
        $stmt->execute([$user_id, $diag_id, $question_number, $answer_chosen, $scale, $points]);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'finalize_test') {
        $diag_id = (int)($_POST['diag_id'] ?? 0);
        if (!$diag_id || !isset($diagnostics[$diag_id])) {
            echo json_encode(['error' => 'Неверная диагностика']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT scale, points FROM temp_results WHERE user_id = ? AND diag_id = ?");
        $stmt->execute([$user_id, $diag_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) < $diagnostics[$diag_id]['questions_count']) {
            echo json_encode(['error' => 'Не все вопросы отвечены']);
            exit;
        }
        $scaleSum = [];
        foreach ($rows as $row) {
            $scaleSum[$row['scale']] = ($scaleSum[$row['scale']] ?? 0) + $row['points'];
        }
        $ints = [];
        foreach ($scaleSum as $scale => $totalPoints) {
            $interpretations = $diagnostics[$diag_id]['interpretations'][$scale] ?? [];
            $matched = 'Интерпретация не найдена';
            foreach ($interpretations as $range) {
                if ($totalPoints >= $range['min'] && $totalPoints <= $range['max']) {
                    $matched = $range['text'];
                    break;
                }
            }
            $ints[] = "Шкала $scale: $matched (баллы: $totalPoints)";
        }
        $result_text = implode(". ", $ints);
        $stmt = $pdo->prepare("INSERT INTO final_results (user_id, diag_id, result_text) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $diag_id, $result_text]);
        $stmt = $pdo->prepare("DELETE FROM temp_results WHERE user_id = ? AND diag_id = ?");
        $stmt->execute([$user_id, $diag_id]);
        echo json_encode(['success' => true, 'result_text' => $result_text]);
        exit;
    }
    if ($action === 'fetch_last_result') {
        $diag_id = (int)($_POST['diag_id'] ?? 0);
        if (!$diag_id || !isset($diagnostics[$diag_id])) {
            echo json_encode(['error' => 'Неверная диагностика']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT result_text, created_at FROM final_results WHERE user_id = ? AND diag_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id, $diag_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['error' => 'Результат не найден']);
            exit;
        }
        echo json_encode(['success' => true, 'result_text' => $row['result_text'], 'created_at' => $row['created_at']]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8" />
<title>Диагностики с результатами</title>
<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    font-family: Arial, sans-serif;
}
.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 400px;
    position: relative;
    border-radius: 5px;
}
.close {
    color: #aaa;
    position: absolute;
    right: 12px;
    top: 8px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close:hover, .close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}
button {
    background-color: #4CAF50;
    color: white;
    border: none;
    padding: 8px 16px;
    margin-top: 10px;
    cursor: pointer;
    border-radius: 3px;
}
button:disabled {
    background-color: gray;
    cursor: default;
}
.answer-option {
    padding: 5px;
    margin: 4px 0;
    cursor: pointer;
    border: 1px solid #ddd;
    border-radius: 3px;
}
.answer-option.selected {
    background-color: #d0eac7;
    border-color: #4CAF50;
}
.container {
    max-width: 650px;
    margin: 20px auto;
    font-family: Arial, sans-serif;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
}
.diag-block {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ccc;
}
.diag-block img{
    max-height:30vh;
    width: auto;
}
.result-button-row {
    margin-top: 10px;
}
.result-button-row button {
    margin-left: 10px;
    background-color: #2196F3;
}
</style>
</head>
<body>

<section class="container">

<?php
// подгружаем последний результат для каждого диагноза у пользователя
$last_results = [];
foreach ($diagnostics as $diag_id => $_) {
    $stmt = $pdo->prepare("SELECT result_text, created_at FROM final_results WHERE user_id = ? AND diag_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id, $diag_id]);
    $last_results[$diag_id] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>

<?php foreach ($diagnostics as $diag_id => $diag): ?>
<div class="diag-block" data-diag-id="<?= $diag_id ?>">
    <p><img src="<?= htmlspecialchars($diag['photo']); ?>"/></p>
    <p><strong><?= htmlspecialchars($diag['title']) ?></strong></p>
    <p><b>Тема:</b> <?= htmlspecialchars($diag['theme']) ?></p>
    <p><b>Описание:</b> <?= htmlspecialchars($diag['description']) ?></p>
    <p><b>Время:</b> <?= htmlspecialchars($diag['time']) ?></p>
    <p><b>Вопросов:</b> <?= $diag['questions_count'] ?></p>
    
    <button class="start-test-btn" data-diag-id="<?= $diag_id ?>">Пройти</button>
    <?php if (!empty($last_results[$diag_id])): ?>
        <button class="show-result-btn" data-diag-id="<?= $diag_id ?>">Мои результаты</button>
    <?php endif; ?>
</div>

<!-- Модальные окна для данной диагностики -->
<div id="modal-<?= $diag_id ?>-instruction" class="modal">
    <span class="close">&times;</span>
    <div class="modal-content">
        <h1>Инструкция</h1><br/>
        <p>Добро пожаловать в диагностику «<?= htmlspecialchars($diag['title']) ?>».</p>
        <p>Прочитайте внимательно инструкции и нажмите кнопку "Начать", чтобы приступить.</p>
        <button class="instruction-start-btn" data-diag-id="<?= $diag_id ?>">Начать</button>
    </div>
</div>

<?php for ($q = 1; $q <= $diag['questions_count']; $q++): 
    $question = $diag['questions'][$q];
?>
<div id="modal-<?= $diag_id ?>-question-<?= $q ?>" class="modal">
    <span class="close">&times;</span>
    <div class="modal-content">
        <h1>Вопрос <?= $q ?></h1><br/>
        <p><strong><?= htmlspecialchars($question['text']) ?></strong></p>
        <?php foreach ($question['answers'] as $ans_num => $ans_data): ?>
            <p class="answer-option" data-answer="<?= $ans_num ?>"><?= htmlspecialchars($ans_data['text']) ?></p>
        <?php endforeach; ?>
        <button class="next-btn" data-diag-id="<?= $diag_id ?>" data-question-number="<?= $q ?>" disabled>
            <?= ($q === $diag['questions_count']) ? 'Результат' : 'Далее' ?>
        </button>
    </div>
</div>
<?php endfor; ?>

<div id="modal-<?= $diag_id ?>-result" class="modal">
    <span class="close">&times;</span>
    <div class="modal-content result-modal-content">
        <h1>Результат</h1><br/>
        <p class="result-text">Ваш результат ...</p>
        <button class="other-tests-btn">Другие диагностики</button>
    </div>
</div>
<?php endforeach; ?>



<a href="client.php">Вернуться в личный кабинет</a>

</section>

<script>
(() => {
    const diagnostics = <?= json_encode($diagnostics, JSON_UNESCAPED_UNICODE) ?>;
    const userId = <?= json_encode($user_id) ?>;

    function openModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'block';
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'none';
    }
    function clearSelection(modal) {
        if (!modal) return;
        modal.querySelectorAll('.answer-option.selected').forEach(el => el.classList.remove('selected'));
        const btn = modal.querySelector('button.next-btn');
        if (btn) btn.disabled = true;
    }
    function closeAllModals() {
        document.querySelectorAll('.modal').forEach(m => closeModal(m.id));
    }

    // Кнопки Пройти
    document.querySelectorAll('.start-test-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const diagId = btn.getAttribute('data-diag-id');
            openModal(`modal-${diagId}-instruction`);

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({'action':'start_test', 'diag_id':diagId})
            }).then(r => r.json()).then(res => {
                if (!res.success) alert('Ошибка инициализации теста');
            });
        });
    });
    // Кнопки Начать
    document.querySelectorAll('.instruction-start-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const diagId = btn.getAttribute('data-diag-id');
            closeModal(`modal-${diagId}-instruction`);
            openModal(`modal-${diagId}-question-1`);
        });
    });

    //Выбор вариантов и кнопка Далее/Результат
    document.querySelectorAll('[id^="modal-"][id*="-question-"]').forEach(modal => {
        const answerParagraphs = modal.querySelectorAll('.answer-option');
        const nextBtn = modal.querySelector('button.next-btn');
        let selectedAnswer = null;

        answerParagraphs.forEach(p => {
            p.addEventListener('click', () => {
                answerParagraphs.forEach(item => item.classList.remove('selected'));
                p.classList.add('selected');
                selectedAnswer = p.getAttribute('data-answer');
                nextBtn.disabled = false;
            });
        });

        nextBtn.addEventListener('click', () => {
            if (!selectedAnswer) return;

            const diagId = nextBtn.getAttribute('data-diag-id');
            const questionNumber = parseInt(nextBtn.getAttribute('data-question-number'), 10);

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    'action': 'save_answer',
                    'diag_id': diagId,
                    'question_number': questionNumber,
                    'answer_chosen': selectedAnswer
                })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Ошибка сохранения ответа: ' + (data.error || 'Unknown'));
                    return;
                }
                closeModal(modal.id);
                clearSelection(modal);
                selectedAnswer = null;
                if (questionNumber < diagnostics[diagId].questions_count) {
                    openModal(`modal-${diagId}-question-${questionNumber + 1}`);
                } else {
                    fetch('', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            'action': 'finalize_test',
                            'diag_id': diagId,
                        })
                    })
                    .then(r => r.json())
                    .then(resultData => {
                        if (!resultData.success) {
                            alert('Ошибка получения результата: ' + (resultData.error || ""));
                            return;
                        }
                        const modalResult = document.getElementById(`modal-${diagId}-result`);
                        modalResult.querySelector('.result-text').textContent = resultData.result_text;

                        // Добавить/обновить кнопку "Мои результаты" рядом с кнопкой "Пройти"
                        updateResultButtonVisibility(diagId, true);

                        openModal(`modal-${diagId}-result`);
                    });
                }
            });
        });
    });

    // Кнопка "Другие диагностики" - закрывающая все окна
    document.querySelectorAll('.other-tests-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            closeAllModals();
        });
    });

    // Новое: кнопки "Мои результаты"
    document.querySelectorAll('.show-result-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const diagId = btn.getAttribute('data-diag-id');
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    'action': 'fetch_last_result',
                    'diag_id': diagId,
                })
            }).then(r => r.json())
            .then(data => {
                if (data.success) {
                    const modalResult = document.getElementById(`modal-${diagId}-result`);
                    modalResult.querySelector('.result-text').textContent = data.result_text + "\n\n(Последнее прохождение: " + data.created_at + ")";
                    closeAllModals();
                    openModal(`modal-${diagId}-result`);
                } else {
                    alert('Результат не найден.');
                }
            });
        });
    });

    /** Вспомогательная функция для показа/скрытия кнопки "Мои результаты" */
    function updateResultButtonVisibility(diagId, visible) {
        // Ищем контейнер диагностик
        const container = document.querySelector(`.diag-block[data-diag-id="${diagId}"]`);
        if (!container) return;

        let btn = container.querySelector('.show-result-btn');
        if (visible) {
            if (!btn) {
                btn = document.createElement('button');
                btn.className = 'show-result-btn';
                btn.setAttribute('data-diag-id', diagId);
                btn.textContent = 'Мои результаты';
                btn.style.backgroundColor = '#2196F3';
                btn.style.color = 'white';
                btn.style.marginLeft = '10px';
                btn.addEventListener('click', () => {
                    fetch('', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            'action': 'fetch_last_result',
                            'diag_id': diagId,
                        })
                    }).then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const modalResult = document.getElementById(`modal-${diagId}-result`);
                            modalResult.querySelector('.result-text').textContent = data.result_text + "\n\n(Последнее прохождение: " + data.created_at + ")";
                            closeAllModals();
                            openModal(`modal-${diagId}-result`);
                        } else {
                            alert('Результат не найден.');
                        }
                    });
                });
                container.querySelector('.start-test-btn').after(btn);
            }
        } else if (btn) {
            btn.remove();
        }
    }

    // Закрытие окна если клик вне контента
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
            clearSelection(event.target);
        }
    };

})();
</script>

</body>
</html>