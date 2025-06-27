<?php
session_start();

// Подключение к базе данных SQLite
$dsn = 'sqlite:' . dirname(__FILE__) . '/psychologist1.db';
$db_options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
try {
    $pdo = new PDO($dsn, null, null, $db_options);
} catch (PDOException $e) {
    die('Ошибка ' . $e->getMessage());
}

// Получение всех методов и запросов из базы данных
$stmtMethods = $pdo->query("SELECT id, name FROM methods ORDER BY name ASC");
$allMethods = $stmtMethods->fetchAll(PDO::FETCH_ASSOC);
$stmtQueries = $pdo->query("SELECT id, text FROM queries ORDER BY text ASC");
$allQueries = $stmtQueries->fetchAll(PDO::FETCH_ASSOC);

// Функция для извлечения специалистов с учетом фильтров
function fetchSpecialistsWithMethods($pdo, $sqlQuery, array $params = []) {
    $stmt = $pdo->prepare($sqlQuery);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $specialists = [];
    foreach ($rows as $row) {
        $psychologist_id = $row['id'];
        $stmtMethods = $pdo->prepare("SELECT m.name AS method_name FROM psychologist_methods pm JOIN methods m ON pm.method_id = m.id WHERE pm.psychologist_id = :psycho_id");
        $stmtMethods->bindValue(':psycho_id', $psychologist_id, PDO::PARAM_INT);
        $stmtMethods->execute();
        $methods = $stmtMethods->fetchAll(PDO::FETCH_COLUMN);
        $row['methods'] = implode(', ', $methods);
        $specialists[] = $row;
    }
    return $specialists;
}

// Обработка POST запроса
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Обработка запроса на очистку фильтров
    if (isset($_POST['clear_filter'])) {
        unset($_SESSION['age_range']);
        unset($_SESSION['method']);
        unset($_SESSION['query']);
        unset($_SESSION['cost_max']);
        header("Location: " . $_SERVER['PHP_SELF'] . '#specialists');
        exit();
    }

    // Сброс и получение значений фильтров из POST
    unset($_SESSION['age_range']);
    unset($_SESSION['method']);
    unset($_SESSION['query']);
    unset($_SESSION['cost_max']);
    $ageRange = isset($_POST['age_range']) ? $_POST['age_range'] : '';
    $methodsNames = isset($_POST['method']) && is_array($_POST['method']) ? $_POST['method'] : [];
    $queriesTexts = isset($_POST['query']) && is_array($_POST['query']) ? $_POST['query'] : [];
    $costMax = isset($_POST['cost_max']) && is_numeric($_POST['cost_max']) ? intval($_POST['cost_max']) : null;
    $costMin = 4000;

    // Сохранение значений фильтров в сессию
    $_SESSION['age_range'] = $ageRange;
    $_SESSION['method'] = $methodsNames;
    $_SESSION['query'] = $queriesTexts;
    $_SESSION['cost_max'] = $costMax;

    // Перенаправление на страницу с якорем
    header("Location: " . $_SERVER['PHP_SELF'] . '#specialists');
    exit();
}

// Получение значений фильтров из сессии
$ageRange = isset($_SESSION['age_range']) ? $_SESSION['age_range'] : '';
$methodsNames = isset($_SESSION['method']) ? $_SESSION['method'] : [];
$queriesTexts = isset($_SESSION['query']) ? $_SESSION['query'] : [];
$costMax = isset($_SESSION['cost_max']) ? $_SESSION['cost_max'] : null;

// Формирование SQL запроса на основе фильтров
$conditions = [];
$params = [];

if (!empty($methodsNames)) {
    $placeholders = implode(',', array_fill(0, count($methodsNames), '?'));
    $conditions[] = 'EXISTS (SELECT 1 FROM psychologist_methods pm JOIN methods m ON pm.method_id = m.id WHERE pm.psychologist_id = p.id AND m.name IN (' . $placeholders . '))';
    $params = array_merge($params, $methodsNames);
}
if (!empty($queriesTexts)) {
    $placeholders = implode(',', array_fill(0, count($queriesTexts), '?'));
    $conditions[] = 'EXISTS (SELECT 1 FROM psychologist_queries pq JOIN queries q ON pq.query_id = q.id WHERE pq.psychologist_id = p.id AND q.text IN (' . $placeholders . '))';
    $params = array_merge($params, $queriesTexts);
}

if ($costMax !== null) {
    $conditions[] = 'p.avg_cost <= :cost_max';
    $params['cost_max'] = $costMax;
}

$sqlWhereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sqlQuery = "SELECT DISTINCT p.* FROM psychologists p {$sqlWhereClause} ORDER BY p.ray DESC ";

// Получение специалистов с учетом фильтров и пагинации
$specialists = fetchSpecialistsWithMethods($pdo, $sqlQuery, $params);
$pageSize = 30;
$totalPages = ceil(count($specialists) / $pageSize);
$currentPage = isset($_GET['page']) ? max(1, min(intval($_GET['page']), $totalPages)) : 1;
$startIndex = ($currentPage - 1) * $pageSize;
$paginatedSpecialists = array_slice($specialists, $startIndex, $pageSize);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="description" content="Психологическая консультация онлайн и очно в 
    г. Москва от профессиональных психологов. Помощь в трудных жизненных ситуациях, поддержка, терапия. Доступные цены, индивидуальный подход.">
    <meta name="keywords" content="психолог, психотерапия, консультация, онлайн, очно в г. Москва, поддержка, стресс, апатия, эмоциональное выгорание, тревога, семейные проблемы, беспокойство, ощущение неопределенности, бессонница, утомление, постоянное напряжение, боли в голове, раздражение, страх, проблемы в отношениях, конфликты, контроль эмоций, доступная цена">
    <title>PSY-WOMAN - Психологические консультации</title>
    <link rel="icon" href="logo.png" type="image/png">
     <link rel="stylesheet" href="style.css?v=8">
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <meta name="robots" content="index, specialists, meropriyatiya, blog, forpsyhlog ">
    <!-- Open Graph метатеги для социальных сетей -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://psy-woman.ru/">
    <meta property="og:title" content="PSY-WOMAN - Психологические консультации">
    <meta property="og:image" content="https://psy-woman.ru/IMG/logo.png">
    <meta property="og:description" content="Получите профессиональную психологическую поддержку онлайн. Консультации от опытных специалистов помогут вам справиться со стрессом, депрессией, семейными проблемами и другими жизненными трудностями.">
    <meta property="og:site_name" content="PSY-WOMAN - Психологические консультации онлайн">
    <!-- Каноническая ссылка -->
    <link rel="canonical" href="https://psy-woman.ru/">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ProfessionalService",
      "name": "PSY-WOMAN - Психологические консультации",
      "url": "https://psy-woman.ru/",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Москва",
        "addressCountry": "Россия"
      },
      "telephone": "+7 (916) 144-95-70",
      "email": "jkj_z@mail.ru",
      "sameAs": [
        "https://t.me/psy_womans",
        "https://vk.com/psy_womans"
      ],
      "description": "Профессиональные психологические консультации онлайн. Поддержка в сложных жизненных ситуациях, индивидуальная терапия, доступные цены.",
      "geo": {
        "@type": "GeoCoordinates",
        "latitude": 55.755826,
        "longitude": 37.6172999
      }
    }</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

  <nav class="navbar">
    <div class="container center2 cent">
       <a href="index.php" ><p class="brand " style="color: #faf9f5;">Psy-Woman</p></a>
        <a href="index.php"><img src="IMG/logo.png" alt="Psy-Woman" class="profile-image"></a>
        <span class="profession ">Женщины для женщин</span>
        
       
        <div class="hamburger-container">
            <button class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            
            <div class="dropdown-menu">
                <a href="index.php">Главная</a>
                <a href="#">Наши специалисты</a>
                <a href="meropriyatiya.html" >Мероприятия</a>
                <a href="blog.html">Блог</a>
            </div>
        </div>

        
        <ul class="menu btn">
            <li><a href="index.php">Главная</a></li>
            <li><a href="#"><strong class="button3">Наши специалисты</strong></a></li>
              <li><a href="meropriyatiya.html" >Мероприятия</a></li>
            <li><a href="blog.html">Блог</a></li>
        </ul>
    </div>
</nav>
<br>

<section id="specialists">
    <h1 class="card3">Наши специалисты:</h1><br>
    <!-- Форма фильтрации -->
   <form id="filter-form" class="center2" method="post">
       <div><div style="color: #a71300; font-weight: bold;">Методы:</div>
        <div style="height: 80px; overflow-y: auto; border: 1px solid #ccc; padding: 5px; margin-bottom: 10px;">
            <?php foreach ($allMethods as $method): ?>
                <label>
                    <input type="checkbox" name="method[]" value="<?php echo htmlspecialchars($method['name']); ?>" <?php echo in_array($method['name'], $methodsNames) ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($method['name']); ?>
                </label><br>
            <?php endforeach; ?>
        </div></div> 
       <div><div style="color: #a71300; font-weight: bold;">Запросы:</div>
        <div style="height: 80px; overflow-y: auto; border: 1px solid #ccc; padding: 5px; margin-bottom: 10px;">
            <?php foreach ($allQueries as $query): ?>
                <label>
                    <input type="checkbox" name="query[]" value="<?php echo htmlspecialchars($query['text']); ?>" <?php echo in_array($query['text'], $queriesTexts) ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($query['text']); ?>
                </label><br>
            <?php endforeach; ?>
        </div></div> 
       <div><label for="cost_max" style="color: #a71300; font-weight: bold;">Максимальная стоимость</label>
        <input type="number" class="input" name="cost_max" min="4000" step="100" placeholder="Например, 5000" style="padding-left:1vw; padding-right:1vw" value="<?php echo $costMax ?? ''; ?>"></div> 
        <!-- Кнопки "Применить" и "Очистить" -->
   
   <div>
        <p><button type="submit" class="button" style="width: 9.5vw;">Применить</button></p>
       <p><button type="submit" name="clear_filter" class="knopka">X Очистить фильтр</button></p> 
   </div>
              
      
        
    </form>



    <!-- Вывод специалистов -->
    <div class="center" style="padding-left: 7vw; padding-right:5vw;">
        <div class="centers" >
            <?php foreach ($paginatedSpecialists as $specialist): ?>
                
                <div>
                  <span class="cards height" >
                
                          <div class="center" >
                        <div class="center"><img src="<?php echo htmlspecialchars($specialist['photo']); ?>" alt="<?php echo htmlspecialchars($specialist['id']); ?>"/></div>
                    </div> <h2 class="card1 center"><?php echo htmlspecialchars($specialist['family']); ?></h2>
                     <h2 class="card1 center" style="text-align:center;"><?php echo htmlspecialchars($specialist['fname']); ?></h2>
                    <p class="card2" style="color: #a71300; padding-left: 2vw; font-weight: bold; display:flex; justify-content: center;"><strong class="red"></strong> <?php echo htmlspecialchars($specialist['specialty']); ?></p>
                    <p class="card2 " style="padding-left: 2vw;"><strong class="red">Метод:</strong> <?php echo htmlspecialchars($specialist['methods'] ?? ''); ?></p>
                    <p class="card2 " style="padding-left: 2vw;"><strong class="red">Топ-5 запросов:</strong> <?php echo htmlspecialchars($specialist['top5']); ?></p>
                    <p class="card2" style="padding-left: 2vw;"><a href="#" class="learn-more-link red" data-id="<?php echo $specialist['id']; ?>">Узнать о психологе</a></p>
                    <div class="buttons-container center">
                        <a href="<?php echo htmlspecialchars($specialist['contacts']); ?>" class="button2">Подробнее →</a>
                        <a href="#" class="button">Записаться→</a>
                    </div>
                     
                </span>  
                </div>
                
              
            <?php endforeach; ?>
       </div>
           </div>     
        <!-- Пагинация -->
              
            <br>
   <!-- Навигационное меню -->
   <div class="center">
<nav class="nav">
    <div style="display: flex; gap: 15px; padding-bottom:10px">
        <div>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>#specialists" class="pagination-link <?php echo ($i == $currentPage) ? 'active' : ''; ?> red"><strong><?php echo $i; ?></strong></a>
            <?php endfor; ?>
        </div>
        <div><a href="index.php" class="button">Главная</a></div>
    </div>
</nav></div>

<!-- Модальное окно для отображения полной информации о психологе -->
 <?php foreach ($specialists as $specialist): ?>
<!-- Модальное окно для каждого специалиста -->
        <div id="myModal<?php echo $specialist['id']; ?>" class="modal">
            <div class="modal-content">
                <span class="close" id="closeModal<?php echo $specialist['id']; ?>">×</span>
                <h2><?php echo htmlspecialchars($specialist['family']); ?> <?php echo htmlspecialchars($specialist['fname']); ?></h2>
                <p><?php echo htmlspecialchars($specialist['full_description']); ?></p>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($specialists as $specialist): ?>
            // Получаем модальное окно и кнопку
            var modal<?php echo $specialist['id']; ?> = document.getElementById("myModal<?php echo $specialist['id']; ?>");
            var btn<?php echo $specialist['id']; ?> = document.querySelector('.learn-more-link[data-id="<?php echo $specialist['id']; ?>"]');
            var span<?php echo $specialist['id']; ?> = document.getElementById("closeModal<?php echo $specialist['id']; ?>");

            //Проверяем, что кнопка существует
            if(btn<?php echo $specialist['id']; ?>){
              // Добавляем обработчик клика.  Важно вешать обработчик именно после того, как элемент найден.
              btn<?php echo $specialist['id']; ?>.addEventListener('click', function(event) {
                  event.preventDefault();
                  modal<?php echo $specialist['id']; ?>.style.display = "block";
              });

              // Закрытие по клику на крестик
              span<?php echo $specialist['id']; ?>.addEventListener('click', function() {
                  modal<?php echo $specialist['id']; ?>.style.display = "none";
              });
            } else {
              console.error("Кнопка с data-id <?php echo $specialist['id']; ?> не найдена."); // Вывод ошибки в консоль, если кнопка не найдена
            }

            // Закрытие по клику вне окна
            window.addEventListener('click', function(event) {
                if (event.target == modal<?php echo $specialist['id']; ?>) {
                    modal<?php echo $specialist['id']; ?>.style.display = "none";
                }
            });

            <?php endforeach; ?>
        });
    </script>
</section>


<section>
    <div class="footer-container">
        <!-- Первый столбец -->
        <div class="column left">
            <span class="open-modal" data-modal-id="policy-modal">Политика конфиденциальности</span><br>
            <span class="open-modal" data-modal-id="offer-modal">Договор оферты</span><br>
             <span class="open-modal" data-modal-id="offer-modal1">Договор c психолом </span><br>
            <div>            <a href="forpsyhlog.html" target="_blank" title="Telegram" class="column left">
                Для психологов</a></div>
            <span>© PSY-WOMAN, 2025</span>
           
        </div>
         
         
        <div class="column left1">
           <p>ИП: Зайцева Евгения Викторовна </p>
           <p>ИНН: 400500402830 </p>
           <p>ОГРН: </p>
            <p><a href="https://t.me/helga_zai" target="_blank" title="Telegram" class="column left">
                Cайт разработан Ольгой Зайцевой</a></p>
        </div>
        
        
        <!-- Второй столбец -->
        <div class="column social-icons">
            <a href="https://t.me/psy_womans" target="_blank" title="Telegram">
                <img src="IMG/телега.png" alt="Telegram Icon">
            </a>
            <a href="https://" target="_blank" title="dzen">
                <img src="IMG/Дзен.jpg" alt="dzen Icon">
            </a>
            <a href="/https://vk.com/psy_womans" target="_blank" title="VK">
                <img src="IMG/vk.png" alt="VK Icon">
            </a>
            <br><br>
            
        </div> 
        
        
        
    </div>

<!-- Модальное окно для политики конфиденциальности -->
<div id="policy-modal" class="modal">
    <span class="close">×</span>
    <div class="modal-content1">
        <h3 class="bold">Политика конфиденциальности</h3><br>
        <button id="accept-button" class="button">Ознакомилась и согласилась</button>
        <button id="downloadButton" class="button2">Скачать документ</button>

    </div>
</div>
<script type="text/javascript"> document.getElementById('downloadButton').addEventListener('click', function() { 
    // Запускаем процесс загрузки 
window.location.href = 'политика_конфиденциальности.docx'; }); </script>


<!-- Модальное окно для договора оферты -->
<div id="offer-modal" class="modal">
    <span class="close">×</span>
    <div class="modal-content1">
        <h3>ДОГОВОР ОФЕРТЫ</h3><br>
<button id="accept-button1" class="button">Ознакомилась и согласилась</button>

    </div>
</div>
<div id="offer-modal1" class="modal">
    <span class="close">×</span>
    <div class="modal-content1">
        <h3>ДОГОВОР с психолагами</h3><br>
<button id="accept-button2">Ознакомилась и согласилась</button>
        <button id="downloadButton1" class="button2">Скачать договор</button>

    </div>
</div>
<script type="text/javascript"> document.getElementById('downloadButton1').addEventListener('click', function() { 
    // Запускаем процесс загрузки 
window.location.href = 'договор_партнер.docx'; }); </script>


<div id="offer-modal2" class="modal">
    <span class="close">×</span>
    <div class="modal-content1">
        <h3>ДОГОВОР с партнерами</h3><br>
<button id="accept-button3">Ознакомилась и согласилась</button>
        <button id="downloadButton2" class="button2">Скачать документ</button>

    </div>
</div>
<script type="text/javascript"> document.getElementById('downloadButton2').addEventListener('click', function() { 
    // Запускаем процесс загрузки 
window.location.href = 'договор_клмент.docx'; }); </script>
</section>


  

<div class="scroll-top-btn">
    <button onclick="scrollToTop()"><i class="fa-solid fa-arrow-up">↑</i></button>
</div>
   



  <script>

// Скроллинг наверх
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Показать кнопку скроллинга наверх при прокрутке вниз
window.addEventListener('scroll', () => {
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    const scrollBtn = document.querySelector('.scroll-top-btn');

    if (scrollPosition > 300) { // Показываем кнопку, если страница прокручена больше чем на 300 пикселей
        scrollBtn.classList.add('show-scroll-top');
    } else {
        scrollBtn.classList.remove('show-scroll-top');
    }
});




    function scrollToNextBlock() {
        const currentBlock = document.getElementById("first");
        const nextBlock = document.getElementById("second");
    
        if(nextBlock && currentBlock) {
            // Плавная прокрутка к следующему блоку
            window.scrollTo({
                top: nextBlock.offsetTop,
                behavior: 'smooth'
            });
        }
    }

document.getElementById('accept-button').addEventListener('click', function() {
    document.getElementById('policy-modal').style.display = 'none';
});

document.getElementById('accept-button1').addEventListener('click', function() {
    document.getElementById('offer-modal').style.display = 'none';
});

document.addEventListener('DOMContentLoaded', function() {
    // Открыть модальное окно
    document.querySelectorAll('.open-modal').forEach(function(el) {
        el.addEventListener('click', function(e) {
            var modalId = this.getAttribute('data-modal-id');
            var modal = document.getElementById(modalId);
            modal.style.display = 'block'; // Показываем модальное окно
        });
    });

    // Закрыть модальное окно
    document.querySelectorAll('.close').forEach(function(el) {
        el.addEventListener('click', function(e) {
            var modal = this.closest('.modal'); // Находим ближайшее модальное окно
            modal.style.display = 'none'; // Скрываем модальное окно
        });
    });

    // Закрыть модальное окно при клике вне контейнера
    window.onclick = function(event) {
        if (event.target.classList.contains('modal') && !event.target.classList.contains('modal-content')) {
            event.target.style.display = 'none'; // Скрываем модальное окно, если клик был вне его содержимого
        }
    };
});

window.addEventListener('DOMContentLoaded', (event) => {
    const carouselContainer = document.querySelector('.articles-carousel');
    const articles = document.querySelectorAll('.article');
    const prevButton = document.getElementById('prev');
    const nextButton = document.getElementById('next');
    let currentIndex = 0;
    const totalArticles = articles.length;

    // Функция для динамического расчета количества видимых статей
    function getVisibleCount() {
        const width = window.innerWidth || document.documentElement.clientWidth;

        if (width <= 600) { // Для экранов шириной до 600px
            return 1;
        } else if (width > 600 && width <= 900) { // Для экранов шириной от 601px до 900px
            return 2;
        } else { // Для экранов шире 901px
            return 3;
        }
    }

    function showArticles(startIndex) {
        for (let i = 0; i < totalArticles; i++) {
            articles[i].style.display = 'none';
        }

        const visibleCount = getVisibleCount(); // Получаем актуальное значение visibleCount
        for (let i = startIndex; i < startIndex + visibleCount; i++) {
            articles[i % totalArticles].style.display = 'flex';
        }
    }

    function moveCarousel(offset) {
        currentIndex = (currentIndex + offset + totalArticles) % totalArticles;
        showArticles(currentIndex);
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }

    showArticles(currentIndex); // Изначально показать первые три статьи

    prevButton.addEventListener('click', () => {
        moveCarousel(-1);
    });

    nextButton.addEventListener('click', () => {
        moveCarousel(1);
    });

    articles.forEach(article => {
        article.addEventListener('click', () => {
            const modalId = article.dataset.modalId;
            if (modalId) {
                openModal(modalId);
            }
        });
    });

    document.querySelectorAll('.close').forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const modalId = e.target.closest('.modal').id;
            closeModal(modalId);
        });
    });

    window.addEventListener('click', (e) => {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    // Обновляем количество видимых статей при изменении размера окна
    window.addEventListener('resize', () => {
        showArticles(currentIndex);
    });
});


// Скроллинг наверх
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Показать кнопку скроллинга наверх при прокрутке вниз
window.addEventListener('scroll', () => {
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    const scrollBtn = document.querySelector('.scroll-top-btn');

    if (scrollPosition > 300) { // Показываем кнопку, если страница прокручена больше чем на 300 пикселей
        scrollBtn.classList.add('show-scroll-top');
    } else {
        scrollBtn.classList.remove('show-scroll-top');
    }
});

window.addEventListener('scroll', function() {
    // Получаем текущую позицию скроллинга
    let scrollPosition = window.scrollY || document.documentElement.scrollTop;
    
    // Максимальная высота первой секции
    let firstSectionHeight = document.querySelector('.first-section').clientHeight;
    
    // Если пользователь еще находится в пределах первой секции
    if (scrollPosition < firstSectionHeight) {
        // Рассчитываем процент прокрутки
        let percentScrolled = Math.min(scrollPosition / firstSectionHeight, 1);
        
        // Устанавливаем непрозрачность черного слоя в зависимости от процента прокрутки
        document.getElementById('blackOverlay').style.backgroundColor = `rgba(0, 0, 0, ${percentScrolled})`;
    } else {
        // Если пользователь вышел за пределы первой секции, устанавливаем полную непрозрачность
        document.getElementById('blackOverlay').style.backgroundColor = 'rgba(0, 0, 0, 1)';
    }
});

    </script>
</body>
</html>
