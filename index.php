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
$sqlQuery = "SELECT DISTINCT p.* FROM psychologists p {$sqlWhereClause} ORDER BY p.ray DESC LIMIT 20";

// Получение специалистов с учетом фильтров и пагинации
$specialists = fetchSpecialistsWithMethods($pdo, $sqlQuery, $params);
$pageSize = 4;
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

<body style="width: 100vw;  margin:0;">
 <section class="background-section" style="max-width: 100vw; overflow-X:hidden;">
        <div style="width: 100vw;">
        <h1 class="scroll-text1" >
            <p  >Представьте своё будущее,</p> 
            <p>в котором Вы просыпаетесь каждое утро</p> 
            <p>полной сил и вдохновения, </p>
            <p>зная, что впереди ждёт новый удивительный день.</p>
            <p>Ваши отношения наполнены гармонией</p>  
            <p>и взаимопониманием,</p>
            <p>карьера приносит радость и удовлетворение,</p> 
            <p>а внутренний мир сияет спокойствием и уверенностью.</p> 

<p>Это будущее возможно — вместе с нами!</p></h1></div>
    </section>
    
    <script>
   document.addEventListener("DOMContentLoaded", function() {
    const scrollText = document.querySelector('.scroll-text1');

    window.addEventListener('scroll', () => {
        let scrollPosition = window.pageYOffset || document.documentElement.scrollTop;

        // Начало анимации на отметке 70px, полное появление к 200px
        if(scrollPosition >= 18 && scrollPosition <= 200) {
            // Расчёт непрозрачности между 70 и 200 пикселями
            scrollText.style.opacity = ((scrollPosition - 18) / (200 - 18));
        } else if(scrollPosition > 200){
            // Полностью показать текст после 100px
            scrollText.style.opacity = 1;
        } else {
            // Скрыть текст перед началом области активации
            scrollText.style.opacity = 0;
        }
    });
});
</script>





<section id="first" class="content" >
    <div class="overlay">
      <div class="reg1"><div><p class="button4"><a class="button5" href="login.php">Войти</a></p> <p class="button4"><a class="button5" href="signup.php">Регистрация</a></p></div> </div>  
       <div class="logo">
             <img src="IMG/logo.png" alt="лого" >
        </div>
         <br>
         <div class="heading">
             <h1 class="title1">PSY-WOMAN</h1>
            <p class="title2 ">Женщины для Женщин</p>
            <p   class="button3 ><button " onclick="scrollToNextBlock()"><img src="IMG/галочка.png" alt="рука" ></button></p><br>
         </div> 
    </div>
        
</section>

<section id="about"  class="center"> 
    <div class="about">
        <div>
            <br><div class="about"><img src="IMG/рука.svg" alt="рука" ></div>
         <br>
            <div class="about fontt1">
                <p class="fontt1" > Наша основная концепция — стремление создать пространство доверия, безопасности и принятия, где каждая женщина сможет раскрыть себя и обрести внутреннюю гармонию.
                Мы верим, что каждая женщина заслуживает поддержки и понимания от профессионала, способного глубоко прочувствовать её внутренний мир. Наша платформа создана специально женщинами-психологами именно для вас — наших любимых клиенток. Начните с нами жизнь с чистого листа!
                </p>
            </div>
        </div>
    </div>
</section> 


<section id="specialists">
    <h1 class="card3">Наши специалисты</h1><br>
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
    <div class="center">
        <div class="center1">
            <?php foreach ($paginatedSpecialists as $specialist): ?>
                
                <div>
                  <span class="card height" >
                 <div ><div class="center">
                        <div class="center"><img src="<?php echo htmlspecialchars($specialist['photo']); ?>" alt="<?php echo htmlspecialchars($specialist['id']); ?>"/></div>
                    </div> <h2 class="card1 center" style="text-align: center;"><?php echo htmlspecialchars($specialist['family']); ?> <?php echo htmlspecialchars($specialist['fname']); ?></h2>
                    <p class="card2" style="color: #a71300; padding-left: 2vw; font-weight: bold; display:flex; justify-content: center;"><strong class="red"></strong> <?php echo htmlspecialchars($specialist['specialty']); ?></p>
                    <p class="card2 " style="padding-left: 2vw;"><strong class="red">Метод:</strong> <?php echo htmlspecialchars($specialist['methods'] ?? ''); ?></p>
                    <p class="card2 " style="padding-left: 2vw;"><strong class="red">Топ-5 запросов:</strong> <?php echo htmlspecialchars($specialist['top5']); ?></p>
                    <p class="card2" style="padding-left: 2vw;"><a href="#" class="learn-more-link red" data-id="<?php echo $specialist['id']; ?>">Узнать о психологе</a></p></div>
                      
                    
                          <div class="buttons-container center" >
                       <div style=" margin-bottom: 30px;">
                          <a href="<?php echo htmlspecialchars($specialist['contacts']); ?>" class="button2">Подробнее →</a>
                        <a href="#" class="button">Записаться→</a> 
                       </div> 
                    </div> 
                     
                </span>  
                </div>
                
        <?php endforeach; ?>
       </div>
           </div>     <!-- Пагинация -->
            
              
            <br>
            
            <div class="center">
                
                  <nav class="nav" ><div style="display:flex; gap:15px" >
             
                <div><?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>#specialists"   style="text-decoration: none;" class="pagination-link <?php echo ($i == $currentPage) ? 'active' : ''; ?> red"><strong ><?php echo $i; ?></a>
                <?php endfor; ?>   </div>
                   <div > <a href="specialists.php"  class="button">Все специалисты</a></div> 
            </div>
            
            
  
         
            
            
            </nav> 
            </div>
      
    
            
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


       <section style="padding-bottom: 3vh;" id="action"><br>
    <h1 class="card3">НАШИ МЕРОПРИЯТИЯ</h1>
            <div class="carousel-container1">
        
       
        

            <div class="articles-carousel1">     <button id="prev1"><</button>

                <article class="article1" data-modal-id="modal-01" style="  height: 75vh; font-size: 16px; gap:0px;   line-height:16px;">
                     <div >
                        <div class="image"><img src="IMG/м1.png" ></div>
                       
                            <h3>Психотрансформация</h3>
                           <p class="bold"> <strong style="color:#a71300;">Организатор:</strong> ПсихоПара</p>

<p><strong class="bold" style="color:#a71300;">Формат:</strong> Онлайн</p>

<p><strong class="bold" style="color:#a71300;">Тип мероприятия:</strong> Марафон</p>

<p><strong class="bold" style="color:#a71300;">Дата проведения: </strong>01.07.2025  - 14.07.2025 </p>

<p>Краткий психотерапевтический курс в Telegram-чате на 14 дней. Всего за две недели участники научатся лучше понимать свои чувства, потребности и желания, обретут уверенность в собственных силах и получат инструменты управления стрессовыми ситуациями.
    </p>
                     <button class="learn-more-link btn">Подробнее</button>       
                    </div> 
                </article> 

            </div>
            <button id="next1">></button>
        </div>
    
    
    <!-- Модальные окна для каждой статьи -->
    
    <div id="modal-01" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
     <h2>Психотрансформация</h2><br>
  <p>Это интенсивный практический онлайн-курс, направленный на глубокое осознание себя, развитие эмоциональной устойчивости и личностный рост. Всего за две недели участники научатся лучше понимать свои чувства, потребности и желания, обретут уверенность в собственных силах и получат инструменты управления стрессовыми ситуациями.
<p><br>
<p class="red bold">Для кого этот курс?</p>
<p>Курс предназначен для тех, кто хочет изменить свою жизнь, разобраться в причинах тревожности, стресса и неуверенности, обрести внутренний баланс и гармонию. Курс также подойдет людям, испытывающим трудности в отношениях с собой и окружающими, стремящимся повысить качество жизни и осознанность своего существования.
</p><br>
<p class="red bold">Что включает курс?</p>
<p>Программа состоит из ежедневных заданий и материалов, распределенных на 14 дней. Каждый день участник получает новое задание и доступ к полезным материалам, видеоматериалам и упражнениям, способствующим трансформации личности.
</p><br>
<p class="red bold">Основные темы курса:</p>
<p>1. Самопознание: Изучение внутреннего мира, потребностей и желаний.</p>
<p>2. Эмоциональный интеллект: Освоение инструментов распознавания и управления эмоциями.</p>
<p>3. Устойчивость к стрессу: Практики снятия напряжения и повышения психологической выносливости.</p>
<p>4. Коммуникация и отношения: Навык эффективного общения и выстраивания гармоничных отношений.</p>
<p>5. Личная эффективность: Управление временем, постановка целей и мотивация.</p>
<p>6. Работа над самооценкой: Повышение уверенности в себе и принятие собственной уникальности.</p>
<p>7. Трансформация негативных установок: Преодоление ограничивающих убеждений и стереотипов.</p>
<br>
<p class="red bold">Каждый день участникам предлагается новый материал, включающий:</p>
<p>- Теоретические уроки,</p>
<p>- Упражнения и задания для самостоятельной проработки,</p>
<p>- Личные рекомендации от психологов,</p>
<p>- Поддержка группы единомышленников в чате Telegram.</p>

<p>Все материалы легко воспринимаются и просты в освоении даже новичкам.</p>
<br>
<p class="red bold">Преимущества участия:</p>
<p>- Получение глубоких инсайтов о своей личности и жизненных целях.</p>
<p>- Возможность переосмыслить жизненный опыт и избавиться от ограничений прошлого.</p>
<p>- Развитие внутренней силы и способности справляться с трудностями.</p>
<p>- Улучшение качества жизни и повышение уровня удовлетворённости.</p>
<p>- Доступ к эксклюзивным материалам и поддержке экспертов-психологов.</p>
<br>
<p>По завершении курса участники смогут почувствовать внутреннюю свободу, повысится уровень счастья и благополучия, улучшится восприятие себя и окружающих, появится ясность в отношении личных ценностей и целей.
</p><br>
<p>Приглашаем вас присоединиться к курсу и начать путешествие к лучшей версии самого себя!</p>
           
             </div>
    </div>
   
</div>

 <div class="center" > <a href="meropriyatiya.html"  class="button">Все мероприятия</a></div> 
</section>

<section class="center cent" style="  padding-top:2vh; padding-bottom:2vh; background-color: #faf9f5; " id="form">
<div class="form"
    <div><p class="form1 bold">Записаться на консультацию</p><br>
<p class="form">Вы готовы сделать первый шаг навстречу себе и своей гармонии? Запишитесь на первую встречу прямо сейчас!</p>

<p class="form">Вы можете выбрать удобный вам формат консультации:</p>
<p class="form">— Онлайн-встреча через защищённый видеочат</p>
<p class="form">— Личная встреча в нашем уютном кабинете</p>

<p class="form">Чтобы записаться, выберите понравившегося психолога и удобное время. Если возникнут вопросы, наши консультанты оперативно свяжутся с вами.</p>

<p class="form">Ваш комфорт и безопасность важны для нас! Ждём вас на первой встрече, которая станет началом вашего пути к внутренней свободе и счастью.</p>

<p class="form">Заполните форму или свяжитесь с нами любым удобным способом. Ваше будущее начинается здесь и сейчас!</p>
</div>


</div>
    <div> 
        <div class="form">  
               
         <form id="feedbackForm" class="form">
            <label for="name">Имя:</label><br>
            <p><input type="text" id="name" name="name" placeholder="Введите ваше имя" required style="padding-left:1vw; padding-right:1vw"></p><br>
            
            <label for="phone">Номер телефона:</label><br>
            <p><input type="tel" id="phone" name="phone" placeholder="Введите ваш номер телефона" required style="padding-left:1vw; padding-right:1vw"></p><br>

            <label for="spec">ФИО специалиста:</label><br>
            <p><input type="text" id="spec" name="email" placeholder="ФИО специалиста" required style="padding-left:1vw; padding-right:1vw"></p><br>
            
            <label for="services">Выберите услугу:</label><br>
                <select style=" max-width: 80vw; " id="service" name="service" required>
                    <option value="">-- Выберите услугу --</option>
                    <option value="создание_сайта"> Индивидуальная консультация очно </option>
 <option value="создание_сайта">Индивидуальная консультация онлайн </option>
 <option value="создание_сайта">Групповая психотерапия (10 встреч)</option>
 <option value="создание_сайта">Психологическая игры</option>
                </select><br><br>
            <label for="message">Ваши пожелания:</label><br>
            <textarea style=" max-width: 80vw; padding-left:1vw; padding-right:1vw" id="message" name="message" rows="4" cols="30" placeholder="Запрос, день, время и т.д" required ></textarea><br><br>
            
            <button type="submit" class="button">Отправить</button>
        </form></div>
     
       


        <script>
document.addEventListener('DOMContentLoaded', function() {
    var modalTrigger = document.querySelector('[data-modal-id="form"]');
    var modal = document.getElementById('form');
    var closeButton = modal.querySelector('.close');

    modalTrigger.addEventListener('click', function(event) {
        event.preventDefault(); // Отменяем переход по ссылке
        modal.style.display = 'block'; // Показываем модальное окно
    });

    closeButton.addEventListener('click', function() {
        modal.style.display = 'none'; // Скрываем модальное окно
    });

    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.style.display = 'none'; // Скрываем модальное окно при клике вне его
        }
    });
});

            document.getElementById('feedbackForm').addEventListener('submit', function(event) {
                event.preventDefault();
                
                const name = document.getElementById('name').value;
                const spec = document.getElementById('spec').value;
                 const phone = document.getElementById('phone').value;
                  const services = document.getElementById('services').value;
                const message = document.getElementById('message').value;
    
                // Токен вашего бота
                const botToken = '6567131076:AAHTT21yuexvbjH_NEd1qccrFBEpfDP_j0I';
                // ID чата или канал, куда будут отправляться сообщения
                const chatId = '680625533';
    
                const url = `https://api.telegram.org/bot${botToken}/sendMessage`;
    
                const data = {
                    chat_id: chatId,
                    text: `Имя: ${name}\nТелефон:\n${phone}\nspec: ${spec}\nуслуга: ${services}\nСообщение:\n${message}`
                };
    
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                }).then(response => {
                    if (response.ok) {
                        alert('Сообщение успешно отправлено!');
                    } else {
                        alert('Произошла ошибка при отправке сообщения.');
                    }
                }).catch(error => {
                    console.error('Ошибка:', error);
                    alert('Произошла ошибка при отправке сообщения.');
                });
            });
        </script>
    <div >
    </div></div>
</section>

<section style="padding-bottom: 2vh;">
<h1 class="card3" id="blog">Читай, вдохновляйся, живи</h1>
<div class="carousel-container">
            <div class="articles-carousel">     <button id="prev"><</button>

                <article class="article" data-modal-id="modal-1">
                    <div> 
                        <div class="image"><img src="IMG/ст1.png" ></div>
                            <div>
                                <p class="bold"> Как выбрать своего идеального психолога: чек-лист для будущей комфортной терапии.</p>
                       <p><strong class="red bold"> Тематика: </strong>Выбор психолога</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                </article> 
    
                <article class="article" data-modal-id="modal-2">
                  <div> 
                        <div class="image"><img src="IMG/ст2.png" ></div>
                            <div>
                                <p class="bold"> Психология отношений: почему важно говорить о чувствах?</p>
                       <p><strong class="red bold"> Тематика: </strong>Психология отношений</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                 </article>
    
                <article class="article" data-modal-id="modal-3"> 
                <div> 
                        <div class="image"><img src="IMG/ст3.png" ></div>
                            <div>
                                <p class="bold"> Самопринятие: путь к внутренней гармонии</p>
                       <p><strong class="red bold"> Тематика: </strong>Самооценка</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div>
                    </article>

                     <article class="article" data-modal-id="modal-4">
                    <div> 
                        <div class="image"><img src="IMG/ст4.png" ></div>
                            <div>
                                <p class="bold"> Что значит женская сила и как её развивать?</p>
                       <p><strong class="red bold"> Тематика: </strong>Женская энергия</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div>  
                </article> 
    
                <article class="article" data-modal-id="modal-5">
                   <div> 
                        <div class="image"><img src="IMG/ст5.jpeg" ></div>
                            <div>
                                <p class="bold"> Мотивация и цель: как преодолеть кризис мотивации?</p>
                       <p><strong class="red bold"> Тематика: </strong>Мотивация</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
            </article>
    
                <article class="article" data-modal-id="modal-6"> 
                 <div> 
                        <div class="image"><img src="IMG/ст6.png" ></div>
                            <div>
                                <p class="bold"> Страх одиночества: причины возникновения и способы преодоления</p>
                       <p><strong class="red bold"> Тематика: </strong>Одиночество</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>

                     <article class="article" data-modal-id="modal-7"> 
                 <div> 
                        <div class="image"><img src="IMG/ст7.png" ></div>
                            <div>
                                <p class="bold"> Токсичные отношения: признаки и последствия</p>
                       <p><strong class="red bold"> Тематика: </strong>Психология отношений</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>

                     <article class="article" data-modal-id="modal-8"> 
                 <div> 
                        <div class="image"><img src="IMG/ст8.png" ></div>
                            <div>
                                <p class="bold"> Истощённость и выгорание: профилактика и восстановление ресурсов</p>
                       <p><strong class="red bold"> Тематика: </strong>Профессиональное выгорание</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>

                     <article class="article" data-modal-id="modal-9"> 
                 <div> 
                        <div class="image"><img src="IMG/ст9.png" ></div>
                            <div>
                                <p class="bold"> Управление эмоциями: как контролировать гнев и раздражение?</p>
                       <p><strong class="red bold"> Тематика: </strong>Эмоциональный интеллект</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>

                     <article class="article" data-modal-id="modal-10"> 
                 <div> 
                        <div class="image"><img src="IMG/ст10.png" ></div>
                            <div>
                                <p class="bold"> Тайм-менеджмент для работающих мам: секреты эффективности</p>
                       <p><strong class="red bold"> Тематика: </strong> Материнство</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>

                     <article class="article" data-modal-id="modal-11"> 
                 <div> 
                        <div class="image"><img src="IMG/ст11.png" ></div>
                            <div>
                                <p class="bold"> Ценность личной границы: как установить и защитить свои границы?</p>
                       <p><strong class="red bold"> Тематика: </strong>Личные границы</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>
                      
                    <article class="article" data-modal-id="modal-12"> 
                 <div> 
                        <div class="image"><img src="IMG/ст12.png" ></div>
                            <div>
                                <p class="bold"> Личностный рост: технологии развития потенциала и раскрытия талантов</p>
                       <p><strong class="red bold"> Тематика: </strong>Личностный рост</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>

                     <article class="article" data-modal-id="modal-13"> 
                 <div> 
                        <div class="image"><img src="IMG/ст13.png" ></div>
                            <div>
                                <p class="bold">Работа над собой: маленькие шаги к большим изменениям</p>
                       <p><strong class="red bold"> Тематика: </strong>Личностный рост</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>

                     <article class="article" data-modal-id="modal-14"> 
                 <div> 
                        <div class="image"><img src="IMG/ст14.png" ></div>
                            <div>
                                <p class="bold"> Роль хобби в нашей жизни: зачем заниматься любимым делом?</p>
                       <p><strong class="red bold"> Тематика: </strong>Хобби и творчество</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>

                     <article class="article" data-modal-id="modal-15"> 
                 <div> 
                        <div class="image"><img src="IMG/ст15.png" ></div>
                            <div>
                                <p class="bold"> Успех приходит изнутри: почему важна внутренняя гармония?</p>
                       <p><strong class="red bold"> Тематика: </strong>Личностный рост</p>
                       <p><strong class="red bold"> Автор:</strong> Psy-Woman
                            </div>
                            <button type="submit" class="button">Читать</button>
                    </div> 
                    </article>



            </div>
            <button id="next">></button>
        </div>
     <div class="center" > <a href="blog.html"  class="button2 center" >Читать еще</a></div> 
    
    <!-- Модальные окна для каждой статьи -->
    
    <div id="modal-1" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    <h2>Как выбрать своего идеального психолога: чек-лист для будущей комфортной терапии.  </h2><br>
  <p>Правильный выбор психолога – залог успеха и комфорта в работе над собственными проблемами. Процесс поиска подходящего специалиста может оказаться непростым, поэтому предлагаем полезный чек-лист, который поможет упростить задачу.</p>
<br>
<p class="red bold">Критерии выбора психолога:</p> <br>

<p class="red bold">1. Образование и квалификация</p>
<p>Проверьте наличие высшего образования и государственных дипломов. Обратите внимание на дополнительные курсы и сертификаты повышения квалификации, подтверждающие профессионализм.</p>
<br>
<p class="red bold"> 2. Визитка и блог</p>
<p>Познакомьтесь с описанием психолога и его статьями. Посмотрите, совпадают ли Ваши мысли и идеалы. Это поможет заранее понять компетентен ли психолог в Вашем вопросе и комфортно ли будет применять знания специалиста в собственной жизни.</p>
<br>
<p class="red bold">3. Специализация</p>
<p>Важно заранее ознакомиться с направлением специализации психолога. К примеру, некоторые профессионалы занимаются детскими вопросами, другие предпочитают супружеские пары, третьи сосредоточены на индивидуальных консультациях.</p>
<br>
<p class="red bold">4. Методы работы</p>
<p>Существуют различные подходы: когнитивно-поведенческий, гештальт, арт-терапия и прочие. Желательно выяснить предпочтения психолога и сопоставить их со своими целями и особенностями восприятия.</p>
<br>
<p class="red bold">5. География и доступность</p>
<p>Уточните местоположение кабинета психолога. Возможно, удобнее рассмотреть вариант удаленных консультаций по видеосвязи.</p>
<br>
<p class="red bold">6. Цены и условия оплаты</p>
<p>Определите подходящий диапазон стоимости и уточняйте возможные формы оплаты. </p>
<br>
<p class="red bold">7. Интуиция и эмпатия</p>
<p>Самое главное – ваша интуиция. Во время первой встречи прислушайтесь к ощущениям: комфортно ли вам общаться, чувствуете ли вы доверие и понимание со стороны психолога.</p>
<p>И помните, сообщество *Psy-Woman* объединяет специалистов, соответствующих всем указанным критериям. Здесь вы точно найдете идеального психолога, готового поддержать вас на пути к внутренним изменениям и улучшению качества жизни.
</p>
           
             </div>
    </div>



    <div id="modal-2" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2> Психология отношений: почему важно говорить о чувствах?</h2><br>
  <p>Открытое выражение чувств помогает строить крепкие и здоровые отношения, улучшать коммуникацию и предотвращать недопонимания. Тем не менее многим женщинам трудно открыто высказывать свои мысли и переживания. Это мешает созданию глубокой связи и может приводить к накоплению обид и недовольства.
  </p><br>
<p class="red bold">Почему важно уметь выражать свои чувства?</p><br>

<p>1. Укрепление близости. Когда партнеры делятся мыслями и чувствами, это создает близкое и доверительное пространство, позволяя глубже понять друг друга.
</p>
<p>2. Решение конфликтов. Открытый разговор позволяет выявлять причины разногласий и искать компромиссные решения. Скрытые эмоции приводят к разрастанию конфликта.
</p>
<p>3. Повышение удовлетворенности отношениями. Способность открыто говорить о желаниях и предпочтениях увеличивает шанс удовлетворения потребностей обоих партнеров.
</p>
<p>4. Развитие эмоционального интеллекта. Осознанное обсуждение чувств развивает способность понимать и управлять ими, что полезно как в отношениях, так и в повседневной жизни.
</p><br>
<p class="red bold">Как научиться говорить о чувствах?
</p><br>
<p>1. Практикуйте честность. Будьте откровенными и прямыми, говорите честно о своих переживаниях и желаниях.
</p>
<p>2. Используйте технику “Я-сообщений”. Говорите от первого лица («Я чувствую…», «Для меня важно…»), чтобы партнер воспринимал сообщение спокойно и конструктивно.
</p>
<p>3. Регулярное общение. Установите привычку регулярного обмена мнениями и впечатлениями. Это сделает разговоры более легкими и понятными.
</p>
<p>4. Будьте терпеливы. Умение открыто говорить о чувствах развивается постепенно. Не бойтесь ошибок и неудач.
</p><br>
<p>Открытое выражение чувств – ключ к здоровым отношениям. Оно укрепляет привязанность, уменьшает количество конфликтов и дарит обоим партнерам глубокое чувство понимания и любви.
</p><br>
<p>Если вам тяжело выразить свои чувства самостоятельно, не стесняйтесь обратиться за поддержкой к профессионалам. Специалисты нашего сообщества готовы сопровождать вас на пути освоения искусства открытого общения и помогут построить глубокие и прочные отношения.
</p>
           
               
             </div>
    </div>
    <div id="modal-3" class="modal">
        <span class="close">×</span>
        <div class="modal-content1">
            <h1> Самопринятие: путь к внутренней гармонии</h1> <br>
           <p>Самопринятие — основа психоэмоционального здоровья и залог полноценной жизни. Принять себя такой, какая есть, означает признать собственную ценность, несмотря на недостатки и несовершенства. Из-за постоянного давления общества и навязанных стандартов многие женщины сталкиваются с низкой самооценкой и чувством неудовлетворенности собой. Однако научиться любить и принимать себя — это возможно и достижимо.
<br>
<p class="red bold">Зачем нужно самопринятие?</p><br>

<p>1. Эмоциональная стабильность. Люди, способные принять себя, испытывают меньше стресса и тревоги, так как перестают сравнивать себя с окружающими.
</p>
<p>2. Повышенная самооценка. Самопринятие формирует положительное отношение к себе, что ведет к уверенности в собственных силах и возможностях.
</p>
<p>3. Качественная жизнь. Без осуждения самого себя проще ставить реалистичные цели и стремиться к исполнению желаний.
</p>
<p>4. Улучшенные отношения. Женщина, которая принимает себя, способна искренне поддерживать близких и уважительно относиться к другим.
</p><br>
<p class="red bold">Способы достижения самопринятия:</p><br>

<p>    1. Осознание достоинств и недостатков. Составьте список положительных качеств и достижений, которыми гордитесь. Признавайте свои слабые стороны и работайте над ними без осуждений.
</p>
<p>2. Прощение и принятие прошлого. Перестаньте винить себя за прошлые неудачи и ошибки. Прощение освобождает от груза вины и открывает дорогу к развитию.</p>

<p>3. Создание поддерживающей среды. Окружите себя людьми, которые поддерживают и принимают вас такой, какая вы есть. Общение с теми, кто критикует и осуждает, отрицательно сказывается на восприятии себя.</p>

<p>4. Фокус на собственном опыте. Живите настоящим моментом, наслаждайтесь маленькими радостями и развивайте благодарность за то, что имеете.</p>

<p>5. Уход за телом и разумом. Заботьтесь о своем физическом и ментальном состоянии. Занимайтесь спортом, медитацией, расслабляющими процедурами.</p>
<br>
<p>Помните, путь к самопринятию — постепенный процесс, требующий терпения и постоянной заботы о себе. Совершенствование внутреннего мира непременно приведет к гармонизации внешних обстоятельств и подарит вам состояние покоя и радости.</p>
<br>
<p>Если самостоятельные попытки самопринятия вызывают трудности, рекомендуем обращаться за помощью к опытным психологам. Наши специалисты помогут пройти этот путь бережно и мягко, предоставив необходимые знания и инструменты для полного принятия себя.</p>

          

            </div>
    </div>
   

    <div id="modal-4" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
           <h2>Что значит женская сила и как её развивать? </h2><br>
 <p>Женская энергия уникальна и многогранна. Она проявляется в мягкости, креативности, интуитивности и решительности. Умение владеть и развивать эту силу значительно обогащает жизнь, придает уверенности и помогает добиваться желаемого.
</p><br>
<p class="red bold">Особенности женской энергии:</p><br>
<p>1. Женская природа склонна к творческому мышлению и созидательству. Эта черта помогает создавать уют, красоту и гармонию вокруг себя.</p>

<p>2. Женщины чаще склонны проявлять сочувствие и оказывать эмоциональную поддержку близким. Эти качества формируют прочные социальные связи и улучшают отношения.
</p>
<p>3. Современная женщина совмещает карьеру, семью, воспитание детей и уход за домом. Гармоничное распределение ролей и обязанностей наполняет жизнь смыслом и добавляет сил.
</p>
<p>4. После сложных ситуаций женщины демонстрируют удивительную способность восстановиться и вновь встать на ноги. Такое свойство незаменимо в условиях современного ритма жизни.
</p><br>
<p class="red bold">Как развивать женскую силу?</p><br>

<p>1. Занятия танцами, йогой, плаванием способствуют укреплению тела и раскрытию внутренней энергии.</p>

<p>2. Медитация, чтение книг, посещение культурных мероприятий развивают духовность и расширяют кругозор.</p>

<p>3. Прогулки на свежем воздухе, путешествия и пребывание рядом с природой очищают сознание и заряжают положительной энергией.</p>

<p>4. Поддерживающее окружение оказывает огромное влияние на формирование женской силы. Близкие друзья и подруги помогают обмениваться энергией и ресурсами.
</p><br>
<p>Научившись гармонично сочетать женские качества и навыки, вы почувствуете увеличение уровня жизненной энергии и общее улучшение самочувствия. Работая над развитием своей внутренней силы, вы становитесь источником вдохновения и поддержки для окружающих.
</p><br>
<p>Если возникают сложности в осознании и применении своих уникальных способностей, наша команда готова прийти на помощь. Консультации психологов нашего сообщества помогут выявить внутренние препятствия и подсказать правильные шаги для раскрытия вашей женской силы.
</p>
            
        </div>
    </div>


 <div id="modal-5" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2>  Мотивация и цель: как преодолеть кризис мотивации? </h2><br>
 <p>Потеря интереса к жизни, снижение энтузиазма и нежелание действовать знакомы многим. Такие периоды называют кризисом мотивации. Они случаются периодически, но не обязательно означают конец всего хорошего. Главное — вовремя заметить признаки кризиса и предпринять меры для восстановления утраченной энергии.
 </p><br>
<p class="red bold">Признаки кризиса мотивации:</p>

<p>1. Отсутствие желания начинать новые дела.</p>
<p>2. Потеря интереса к ранее приятным занятиям.</p>
<p>3. Постоянное чувство лени и апатии.</p>
<p>4. Нарушение сна и аппетита.</p>
<p>5. Негативные установки и критика самой себя.</p><br>

<p class="red bold">Что провоцирует потерю мотивации?</p>

<p>- Переутомление и хронический стресс.</p>
<p>- Неправильно поставленные цели или завышенные ожидания.</p>
<p>- Недостаточная поддержка и признание со стороны окружающих.</p>
<p>- Сложности в личной жизни или на работе.</p>
<p>- Физическое переутомление или болезнь.</p><br>

<p class="red bold">Как восстановить мотивацию?</p>

<p>1. Пересмотрите свои приоритеты и цели. Возможно, текущие стремления перестали соответствовать вашим внутренним убеждениям.</p>

<p>2. Возьмите паузу, отдохните физически и морально. Путешествия, прогулки на природе, спорт и хобби помогут перезагрузиться.</p>

<p>3. Попробуйте сменить обстановку, заняться новыми видами активности, внести разнообразие в повседневную рутину.</p>

<p>4. Четкая формулировка задач и постановка промежуточных этапов создают ясность и структуру действий.</p>

<p>5. Обратитесь за поддержкой к родным и друзьям. Совместные беседы и поддержка облегчают возвращение к активной жизни.</p>
<br>
<p>При правильном подходе кризис мотивации можно превратить в стартовую площадку для нового этапа жизни, полного энергии и энтузиазма.</p>
<br>
<p>Если вам сложно вернуться к нормальной жизни самостоятельно, специалисты нашего сообщества будут рады поделиться рекомендациями и поддержкой. Вместе мы найдем путь обратно к состоянию счастья и энергичности.</p>

            
        </div>
    </div>


 <div id="modal-6" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2> Страх одиночества: причины возникновения и способы преодоления  </h2><br>
 <p>Страх остаться одной преследует многих женщин. Человек боится изоляции, социальной отстраненности и утраты значимых контактов. Хотя подобные опасения вполне естественны, излишнее беспокойство способно стать препятствием для нормального функционирования и полноценной жизни.
 </p><br>
<p class="red bold">Причины появления страха одиночества:</p>

<p>1. Ранние травмы детства (развод родителей, недостаток родительской любви).</p>
<p>2. Длительный разрыв отношений или расставание.</p>
<p>3. Чрезмерная зависимость от одобрения окружающих.</p>
<p>4. Низкий уровень самооценки и неуверенность в себе.</p>
<p>5. Давление общественного мнения ("одиночество плохо").</p><br>

<p class="red bold">Последствия хронического страха одиночества:</p>

<p>- Повышенный уровень тревожности и депрессии.</p>
<p>- Проблемы с формированием качественных социальных связей.</p>
<p>- Потребность находиться в отношениях независимо от их характера.</p>
<p>- Замкнутость и социальная изоляция.</p><br>

<p >При возникновении чувств, связанных со страхом одиночества попробуйте использовать методику "Картинка будущего". Эта методика основана на создании позитивного представления о будущем, где одиночество не воспринимается как угроза.
</p><br>
<p>1. Найдите тихое место, закройте глаза и постарайтесь расслабиться. Глубоко дышите, снимая напряжение.</p>
<p>2. Мысленно перенеситесь в ближайшее будущее, где обстоятельства сложились таким образом, что вы временно остаетесь одна. Постарайтесь увидеть себя в этом положении ясно и отчетливо.
</p>
  <p>  3. Какие чувства появляются при представлении себя в таком сценарии? Запишите их и проанализируйте. Какие из этих реакций вызваны страхом, а какие реальной ситуацией?
  </p>
 <p>   4. Представьте себе позитивные аспекты временной изоляции. Может быть, вы проведёте время с пользой для души и тела, займетесь хобби, встретитесь с друзьями или отправитесь путешествовать?
 </p>
 <p>   5. Продолжайте представлять приятные моменты, создавайте яркую картину того, каким интересным и насыщенным может быть одиночество. Фиксируйте возникающие положительные образы и ассоциации.
 </p>
<p>    6. Медленно возвращайтесь в реальность, сохраняя приятное впечатление от проделанной работы. Сделайте медленный вдох, выдох и завершите упражнение.
</p><br>
<p>Выполнение этой техники позволит снять напряжение и пересмотреть негативное восприятие одиночества. Со временем страхи ослабнут, уступив место уверенности и готовности воспринимать любые жизненные обстоятельства.
</p><br>
<p>Если самостоятельно справиться со страхом одиночества до конца не получается, обратитесь за поддержкой к профессиональным психологам нашего сообщества. Они проведут вас через весь процесс деликатно и грамотно, помогая избавиться от страхов и открыться новому опыту.
</p>
            
        </div>
    </div>

<div id="modal-7" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2>  Токсичные отношения: признаки и последствия</h2><br>
 
    <p>        Находиться в токсичных отношениях крайне опасно для физического и психического здоровья. Такая связь подавляет, лишает свободы и снижает самооценку. Несмотря на распространенное заблуждение, что в любых отношениях бывают трудности, важно отличать временные ссоры от систематически разрушающих поведение партнера.
    </p><br>
<p class="red bold">Признаки токсичных отношений:</p>

<p>1. Постоянная критика и унижение.</p>
<p>2. Контроль поведения и ограничение свободного передвижения.</p>
<p>3. Недоверие и постоянные подозрения.</p>
<p>4. Игнорирование интересов и потребностей другой стороны.</p>
<p>5. Частые вспышки агрессии и раздражительности.</p>
<br>
<p class="red bold">Последствия пребывания в токсичной среде:</p>

<p>- Ухудшение самооценки и развитие комплексов неполноценности.</p>
<p>- Появление депрессивных состояний и повышенной тревожности.</p>
<p>- Утрата смысла жизни и отказ от личных амбиций.</p>
<p>- Накопление физической усталости и болезней.</p>
<br>
<p class="red bold">Как освободиться от токсичных отношений?</p>

<p>1.Первым шагом должно стать признание факта нахождения в токсичном пространстве.</p>

<p>2. Проконсультируйтесь с близкими людьми или профессиональными психологами. Объективная оценка постороннего взгляда поможет понять масштабы проблемы.
</p>
<p>3. Ограничьте контакты с токсичным партнером, исключите совместные планы и откажитесь от совместной жизни.
</p>
<p>4. Придерживайтесь продуманного плана ухода. Уменьшайте частоту встреч и взаимодействий, найдите временное убежище, соберите вещи.
</p>
<p>5. Посещайте индивидуальные или групповые психотерапевтические консультации, направленные на восстановление самооценки и личной силы.
</p><br>
<p>Свобода от токсичных отношений принесет долгожданное облегчение и позволит взглянуть на жизнь совершенно иначе. Ваше благополучие — самое важное, ради которого стоит приложить усилия.
</p>
        </div>
    </div>

    <div id="modal-8" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2>  Истощённость и выгорание: профилактика и восстановление ресурсов </h2><br>
 
 <p> Профессиональное выгорание и физическое истощение становятся частыми спутниками современной жизни. Высокий темп работы, постоянная занятость и постоянное давление внешней среды нередко приводят к снижению работоспособности, упадку настроения и ухудшению общего самочувствия. Поняв симптомы и факторы риска, можно предупредить серьезные последствия и вернуть жизненную энергию.
 </p><br>
<p class="red bold">Симптомы выгорания и истощения:</p>

<p>1. Усталость и хроническая усталость.</p>
<p>2. Неспособность концентрироваться и ухудшение памяти.</p>
<p>3. Сонливость днем и бессонница ночью.</p>
<p>4. Апатия и равнодушие к окружающим событиям.</p>
<p>5. Рост числа заболеваний простудного и вирусного происхождения.</p>
<br>
<p class="red bold">Факторы риска:</p>

<p>1. Высокая рабочая нагрузка и нехватка отдыха.</p>
<p>2. Монотонная деятельность и однообразие.</p>
<p>3. Несоответствие ожиданий результатам труда.</p>
<p>4. Трудности в управлении личным временем.</p>
<p>5. Отсутствие социальной поддержки и признания заслуг.</p>
<br>
<p>Справляться с этими состояниями можно не только традиционными способами вроде смены обстановки или физических нагрузок, но и с помощью особых подходов, направленных непосредственно на тело и подсознание.
</p><br>
<p class="red bold">Телесно-ориентированный подход акцентирует внимание на взаимосвязи между физическим состоянием и психическими реакциями. Одна из простых и эффективных методик — техника дыхания, снимающая мышечное напряжение и восстанавливающая энергетический баланс.
</p>
<p>1. Расслабляйтесь удобно сидя или лежа, закрыв глаза и стараясь дышать медленно и ровно.</p>
<p>2. Сделайте глубокий вдох животом, задерживая дыхание на несколько секунд.</p>
<p>3. Затем плавно выдохните, отпуская напряжение из мышц рук, плеч, шеи и спины.</p>
<p>4. Продолжайте цикл дыхания, пока не почувствуете легкость и расслабленность.</p>
<br>
<p>Эта простая техника поможет оперативно устранить физическое перенапряжение и уменьшить уровень стресса.</p>
<br>
<p class="red bold">Еще один способ при работе с эмоциями - арт-терапевтические методики. Арт-терапия — это мягкий инструмент, позволяющий выразить эмоции через рисунок, минуя интеллектуальную цензуру сознания.
</p>
    <p>1. Возьмите лист бумаги и карандаши/краски.</p>
<p>2. Закройте глаза и сосредоточьтесь на своих ощущениях, представляя собственное внутреннее состояние.</p>
<p>3. Начните рисовать линии, фигуры или цвета, отражающие ваше самочувствие в настоящий момент.</p>
<p>4. Посмотрите на созданный рисунок и попытайтесь интерпретировать его символы и знаки.</p>
<p>5. Запишите возникшие идеи и наблюдения, обсудите их с самим собой или терапевтом.</p>
<br>
<p>Метод рисования состояния помогает визуально материализовать бессознательные процессы, делая возможным их сознательное изменение.</p>
<br>
<p>Использование этих двух мощных методик поможет вам своевременно диагностировать признаки утомляемости и выгорания, давая возможность быстро восстановить внутренние ресурсы и вернуть эмоциональное равновесие.
</p><br>
<p>А если хочется глубже проработать проблему и найти заново то, что будет приносить радость в профессиональной деятельности, то наши специалисты-психотерапевты готовы провести вас через этот процесс профессионально и бережно. Восстановление вашего внутреннего ресурса — наша главная задача!
</p>
            
        </div>
    </div>

 <div id="modal-9" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2> Управление эмоциями: как контролировать гнев и раздражение?  </h2><br>
 <p>Жизнь полна неожиданных поворотов, и порой внешние обстоятельства провоцируют появление раздражения и агрессивных импульсов. Если неконтролируемый всплеск эмоций приводит к конфликтам и портит настроение, важно овладеть инструментами управления своими чувствами.
 </p><br>
<p class="red bold">Почему важно уметь контролировать эмоции?</p>

<p>1. Улучшается качество коммуникации с окружающими.</p>
<p>2. Укрепляется психическое здоровье и снижается уровень стресса.</p>
<p>3. Повышается производительность и концентрация на делах.</p>
<p>4. Снижаются риски хронических заболеваний, связанных с постоянным напряжением.</p>
<br>
<p class="red bold">Когда нахлынула волна гнева, попробуйте следующее:</p>

<p>1. Сделайте глубокий вдох через нос, считая до четырех.</p>
<p>2. Задержите дыхание на четыре секунды.</p>
<p>3. Медленно выдохните ртом, снова считая до четырех.</p>
<p>4. Повторите цикл несколько раз, концентрируя внимание на дыхании.</p>

<p>Такая техника успокаивает нервную систему и возвращает контроль над сознанием.</p>
<br>
<p class="red bold">Когда неприятная ситуация вызывает бурю негодования, остановитесь на мгновение и отступите назад.</p>

<p>1. Оставьте помещение или выйдите прогуляться.</p>
<p>2. Займитесь чем-нибудь нейтральным: выпейте воды, послушайте музыку.</p>
<p>3. Вернувшись, оцените ситуацию заново.</p>

<p>Подобная пауза позволит трезво проанализировать происходящее и избрать адекватную стратегию реагирования.</p>
<br>
<p class="red bold">Если чувство раздражения оказалось очень сильным, то вы можете выразить его на бумаге.</p>

<p>1. Опишите событие, вызвавшее агрессию.</p>
<p>2. Затем выразите словами свое недовольство и сформулируйте потребности.</p>
<p>3. Продумайте аргументы, которые могли бы убедить оппонента пойти на диалог.</p>
<br>
<p>Такой прием поможет структурировать мысли и подготовиться к конструктивной беседе.</p>
<br>
<p>Каждый человек испытывает приступы раздражения и возмущения, и научиться контролировать эмоции — один из важнейших навыков для поддержания здоровой и счастливой жизни. Овладев простыми, но эффективными стратегиями управления гневом, вы приобретете независимость от неприятных моментов и сохраните эмоциональное равновесие.
</p><br>
<p>Если самостоятельно освоить искусство управления эмоциями сложно, приглашаем вас обратиться к психологам нашего сообщества. Индивидуальные консультации помогут изучить специфику вашей эмоциональной структуры и предложат конкретные инструменты стабилизации эмоционального фона.
</p>
            
        </div>
    </div>

 <div id="modal-10" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2>  Тайм-менеджмент для работающих мам: секреты эффективности </h2><br>
 <p>Сегодня многие современные женщины совмещают карьерные амбиции с воспитанием детей и ведением домашнего хозяйства. Организация времени становится ключевым фактором успешного совмещения ролей. Несколько проверенных способов тайм-менеджмента помогут выстроить эффективный график и минимизировать стресс от нехватки часов в сутках.
 </p>
<p class="red bold">Главные принципы тайм-менеджмента для работающих мам:</p>

<p>1. Составьте чёткий план на неделю вперед. Выделите главные задачи и распределите их равномерно по дням. Ставьте реальные сроки выполнения, учитывая нагрузку и особенности семейного графика.
</p>
<p>2. Маленькие перерывы между основными занятиями — идеальный повод заняться полезными мелочами. Например, утром перед завтраком можно записать заметки, вечером после укладывания ребёнка уделить время чтению важной литературы.
</p>
<p>3. Постарайтесь делегировать или автоматизировать бытовые обязанности. Заведите привычку готовить еду заранее, заказывать доставку продуктов, привлекать помощников для уборки дома.
</p>
<p>4. Семейные обязательства могут казаться дополнительной нагрузкой, но правильное распределение обязанностей превращает их в источник дополнительного ресурса. Поручите детям несложные задания, попросите мужа помогать с домашним хозяйством.
</p>
<p>5. Работающей маме необходим полноценный сон и регулярный отдых. Обязательно выделяйте хотя бы полчаса ежедневно на любимые занятия: прогулку, занятие фитнесом, просмотр фильма.
</p>
<p>Следуя перечисленным правилам, каждая мама сможет сбалансировать рабочие нагрузки и домашние хлопоты, оставив достаточно времени на себя и любимую семью.
</p>
<p>Если внедрение системы планирования вызывает трудности, наши психологи готовы проконсультировать вас по вопросам организации времени и повысить вашу продуктивность в любом режиме жизни. Помните, эффективное распоряжение временем обеспечит возможность полноценно реализоваться в карьере и семье!
</p>
            
        </div>
    </div>

 <div id="modal-11" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2> Ценность личной границы: как установить и защитить свои границы?  </h2><br>
<p> Личная граница — невидимая линия, отделяющая ваше пространство от внешнего влияния. Она определяет, насколько близко вы допускаете людей к себе, какие требования принимаете и какие действия считаете приемлемыми. Устанавливая и защищая личные границы, вы защищаетесь от манипуляций, контролируете своё время и энергию, повышаете качество жизни.
</p><br>
<p class="red bold">Почему важно иметь личные границы?</p>

<p>1. Сохраняется целостность личности и уверенность в себе.</p>
<p>2. Снижается риск попадания в токсичные отношения.</p>
<p>3. Повышается уровень комфорта и эмоционального благополучия.</p>
<p>4. Появляется свобода следовать собственным интересам и целям.</p>
<br>
<p class="red bold">Как установить и защитить личные границы?</p>

<p>1. Зафиксируйте в сознании, что для вас допустимо, а что нет. Четкое понимание собственных ограничений служит фундаментом будущей защиты.
</p>
<p>2. Говорите четко и прямо, обозначая свои пожелания и ограничения. Формулировки типа "Мне некомфортно..." или "Я предпочитаю..." помогают установить границу.
</p>
<p>3. Умейте отказываться от предложений, противоречащих вашим интересам. Сказанное "Нет" не должно сопровождаться оправданиями или объяснениями.
</p>
<p>4. Последовательно отстаивайте установленные границы, не поддаваясь уговорам и манипулированию. Если однажды сказали "Нет", придерживайтесь принятого решения.
</p>
<p>5. Постоянно обращайте внимание на сигналы своего тела и разума. Если чувствуете дискомфорт, это сигнал, что надо пересмотреть текущие договоренности.
</p><br>
<p>Установка и поддержание личных границ — это серьёзная работа, которая требует времени и терпения. Благодаря этому процессу вы станете увереннее, свободнее и счастливее.
</p><br>
<p>Если вы испытываете трудности с определением и защитой своих границ, профессиональная помощь психолога окажет значительную поддержку. Эксперты нашего сообщества владеют специальными техниками, которые помогут качественно закрепить границы и навсегда исключить неприятные вторжения извне.
</p>
            
        </div>
    </div>

     <div id="modal-12" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2> Личностный рост: технологии развития потенциала и раскрытия талантов  </h2><br>
 
           <p>Каждый человек обладает уникальным набором способностей и возможностей, которые могут быть развиты и использованы для достижения успехов в жизни. Однако многие люди игнорируют или недооценивают свои таланты, оставаясь в зоне комфорта и ограничивая себя рамками существующих представлений. Преодолевая сомнения и пробуя новое, можно раскрыть огромный потенциал, заложенный природой.
           </p><br>
<p class="red bold">Что даёт личностный рост?</p>

<p>1. Увеличение уверенности в себе и своих силах.</p>
<p>2. Возможность постановки больших целей и их достижения.</p>
<p>3. Повышение качества жизни и уровня удовлетворённости.</p>
<p>4. Более высокий социальный статус и престиж.</p>
<br>
<p class="red bold">Техники для личностного роста:</p>

<p>1. Анализируйте свои достоинства и недостатки, определите точки роста и места, нуждающиеся в коррекции. Применяйте дневник наблюдений, тесты и опросники для углубленного изучения себя.
</p>
<p>2. Ставьте достижимые, измеримые, актуальные и ограниченные по срокам цели. Конкретные задачи гораздо эффективнее абстрактных мечтаний.
</p>
<p>3. Регулярные эксперименты с новыми знаниями и умениями развивают мозг и увеличивают пластичность мышления. Пробуйте разные виды спорта, изучайте иностранные языки, осваивайте творческие дисциплины.
</p>
<p>4. Сознательно формируйте полезные привычки, такие как ранний подъём, регулярные физические упражнения, здоровое питание. Постепенное введение изменений создаёт устойчивую основу для дальнейшей трансформации.
</p>
<p>5. Активно запрашивайте оценку своих действий у друзей, коллег и наставников. Их взгляд со стороны поможет скорректировать направление движения и усовершенствовать тактику действий. Но не забывайте, что принятие оценки окружающих не должна расходиться с вашими целями, убеждениями и сопровождаться дискомфортом. Используя этот инструмент оценки соблюдайте свои личные границы.
</p><br>
<p>Реализовав данные рекомендации, вы приблизитесь к раскрытию своего внутреннего потенциала и сумеете достигнуть желаемого результата.
</p><br>
<p>Если вам нужны дополнительные советы и сопровождение на пути к личностному росту, специалисты нашего сообщества окажут всестороннюю поддержку. Профессиональные психологи помогут выявить скрытые таланты и разработать пошаговый план их реализации.
</p>
            
        </div>
    </div>


 <div id="modal-13" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2>  Работа над собой: маленькие шаги к большим изменениям </h2><br>
<p>“Я хочу меняться, но не знаю, с чего начать…” </p> <br>
<p>Это знакомое чувство переживают миллионы людей, стремящихся улучшить свою жизнь, но застревающих на старте. Причина проста: глобальные перемены воспринимаются как нечто грандиозное и неподъемное. Однако вся мудрость заключается в одном — большие победы начинаются с малых шагов.
</p><br>
<p class="red bold">Почему важен постепенный подход?</p>

<p>— Маленькие шаги снижают порог входа и уменьшают сопротивление мозга.</p>
<p>— Легче отслеживать прогресс и замечать позитивные сдвиги.</p>
<p>— Минимизируется риск возвратиться к старым привычкам.</p>
<br>
<p class="red bold">Примеры небольших изменений:</p>

<p>1. Привычка чтения перед сном</p>
<p>Даже пять минут ежедневного чтения полезнейшей книги — маленький шаг, приводящий к огромному эффекту. Через месяц вы прочитаете целую книгу, улучшите мышление и обогатите словарный запас.
</p>
<p>2. Утро без телефона</p>
<p>Замечали, как гаджет захватывает утро, отвлекая от полезных дел? Поставьте телефон на беззвучный режим и посвятите первые минуты дня полезным ритуалам: зарядке, молитве, медитации, танцу.
</p>
<p>3. Один комплимент себе в день</p>
<p>Ежедневно хвалите себя за мелочи. Так вы постепенно увеличите самооценку и веру в собственные силы.</p>

<p>4. Минималистичный порядок</p>
<p>Начинайте с малого: каждое утро заправляйте кровать, мойте посуду сразу после еды, складывайте одежду. Порядок снаружи рождает порядок внутри.
</p>
<p>5. Прогулка пешком</p>
<p>Просто ходьба — доступный и легкий способ держать тело в тонусе. Начните с десяти минут в день и увеличивайте продолжительность постепенно.</p>
<br>
<p class="red bold">Ключевое правило успешных изменений: последовательность важнее скорости.</p>
<br>
<p>Главная идея заключается в следующем: делайте чуть-чуть каждый день, не ожидая быстрых результатов. Результат придет незаметно, потому что мелкие изменения накапливаются и со временем трансформируют вашу жизнь радикальным образом.
</p><br>
<p>Если вам нужна дополнительная поддержка в разработке индивидуального плана маленьких шагов, психологи нашего сообщества помогут вам сориентироваться и подобрать оптимальный набор решений. Начните сегодня и двигайтесь вперед небольшими шажочками, приближаясь к лучшей версии себя!
</p>
            
        </div>
    </div>



     <div id="modal-14" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2> Роль хобби в нашей жизни: зачем заниматься любимым делом?  </h2><br>
 <p>
            Хобби — важная составляющая человеческой жизни, способствующая восстановлению энергоресурсов, эмоциональной разгрузке и повышению уровня счастья. Время, посвящённое любимым занятиям, снимает стресс, стимулирует творческий потенциал и поддерживает интерес к жизни.
</p><br>
<p class="red bold">Польза хобби для здоровья и благополучия:</p>

<p>1. Эмоциональная разгрузка. Любимое дело отвлечёт от текущих трудностей и снизит уровень стресса.</p>
<p>2. Расширение кругозора. Новые впечатления, полученные через хобби, обогащают внутренний мир и добавляют яркие краски жизни.</p>
<p>3. Позитивное воздействие на здоровье. Творческие занятия оказывают благоприятное влияние на сердечно-сосудистую систему, иммунитет и мозговую активность.
</p>
    <p>4. Возможность социального взаимодействия. Клубы по интересам, выставки и фестивали дают возможность завести новые знакомства и расширить круг общения.
    </p><br>
<p class="red bold">Популярные виды хобби и их польза:</p>

<p>- Живопись и рисование. Помогают выразить эмоции и развить эстетическое восприятие.</p>
<p>- Музыкальные инструменты. Усиливают память, координацию движений и способность к концентрации.</p>
<p>- Спорт и физическая активность. Тренировка выносливости, укрепление иммунитета и улучшение настроения.</p>
<p>- Коллекционирование. Доставляет удовольствие от приобретения редких предметов и создания коллекций.</p>
<p>- Путешествия. Расширяют кругозор, обогащают культурный багаж и освежают сознание.</p>
<br>
<p>Включите хобби в свою жизнь, и вы почувствуете, как оно придаст ей ярких красок и привнесёт свежие впечатления, став важным источником позитива и вдохновения.
</p><br>
<p>Если вам сложно определиться с хобби или хочется понять, какое занятие подойдёт именно вам, проконсультируйтесь с нашими психологами. Мы поможем выбрать подходящее увлечение, соответствующее вашим интересам и целям.
</p>
        </div>
    </div>

 <div id="modal-15" class="modal">
        <span class="close">×</span>
        <div class="modal-content">
    
    
           <h2>  Успех приходит изнутри: почему важна внутренняя гармония?  </h2><br>
 
<p>            Внешний успех — карьера, деньги, известность — ничто без внутренней гармонии и счастья. Настоящее благополучие достигается путем глубокой работы над собой, раскрытия внутреннего потенциала и укрепления веры в собственные силы. Только находясь в согласии с собой, человек способен испытывать подлинное удовлетворение и счастье.
</p><br>
<p class="red bold">Почему внутренняя гармония необходима для успеха?</p>

<p>1. Снижение уровня стресса. Жизнь в гармонии позволяет преодолевать неприятности с меньшими усилиями.</p>
<p>2. Рост производительности. Наличие внутренней стабильности положительно отражается на рабочих результатах.</p>
<p>3. Качество межличностных отношений. Когда человек находится в мире с собой, ему проще выстраивать теплые и добрые отношения с окружающими.</p>
<p>4. Лучшее принятие решений. Ясность мыслей и уравновешенность позволяют совершать взвешенные поступки.</p>
<br>
<p class="red bold">Средства достижения внутренней гармонии:</p>

<p>1. Медитация и йога. Практики, направленные на успокоение ума и снятие напряженности.</p>
<p>2. Искусство и творчество. Рисование, музыка, танцы помогают выразить внутренние переживания и освободить душу.</p>
<p>3. Чтение и самообразование. Интеллектуальное развитие поднимает общий уровень осведомленности и способствует формированию целостного мировоззрения.
</p>
    <p>4. Связь с природой. Регулярные прогулки на свежем воздухе способствуют восстановлению сил и снятию накопившегося напряжения.</p>
<br>
<p>Поменяв фокус с внешних показателей успеха на внутреннее благополучие, вы достигнете устойчивого положения и откроетесь для настоящего счастья и радости.
</p><br>
<p>Если вы хотите ускорить движение к внутренней гармонии, обратитесь за помощью к нашим специалистам. </p>


        </div>
    </div>
</section>  

<section class="center" style="  background-color: #faf9f5; padding-bottom: 2vh;" id="contact">
  <div>
 <div   class="about">  
    <div class="logo1">
                    <h1 class="title2 red" >СЛЕДИТЕ ЗА НАМИ В СОЦСЕТЯХ</h1>

                     <img src="IMG/logo.png" alt="logo" >

                    <p class="bold"><strong>Psy-Woman</strong></p> 
                   
                    <p><b class="bold" >e-mail: </b><a href="mailto:psy.woman@outlook.com" style="color: #000;">            </a></p>
    </div>
</div>



                    
                    <div class="media">
                        <a href="https://t.me/psy_womans" target="_blank" title="Telegram">
                            <img src="IMG/телега.png" alt="Telegram Icon">
                        </a>
                        <a href="https://" target="_blank" title="dzen">
                            <img src="IMG/Дзен.jpg" alt="dzen Icon">
                        </a>
                        <a href="/https://vk.com/psy_womans" target="_blank" title="VK">
                            <img src="IMG/vk.png" alt="VK Icon"><br><p></p>
                        </a>
                    </div></div><br><p></p>
                 
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
         
         
        <div class="column1">
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

  

<div>
     <div class="dropdown-btn" >

   
    
    <button onclick="toggleDropdown()"><div>
                                          <p>-</p>
                                          <p>-</p>
                                          <p>-</p>
                                        </div>
        </button>
    <div id="dropdown-menu" class="dropdown-content">
        <ul>
            <p><a href="#about">О нас</a></p>
             <p><a href="#specialists">Специалисты</a></p>
            <p><a href="#action">Мероприятия</a></p> 
             <p><a href="#form">Онлайн-запись</a></p>
             <p><a href="#blog">Блог</a></p>
            <p><a href="#contact">Контакты</a></p>
          
        </ul>
    </div>
</div> 
</div>



<div class="scroll-top-btn">
    <button onclick="scrollToTop()">↑<i class="fa-solid fa-arrow-up"></i></button>
</div>
   
    <script>



function toggleDropdown() {
    const dropdownMenu = document.getElementById("dropdown-menu");
    dropdownMenu.classList.toggle("show");
}

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

// Закрытие выпадающего списка при нажатии вне области кнопки
window.onclick = function(event) {
    if (!event.target.matches('.dropdown-btn button')) {
        const dropdownMenus = document.getElementsByClassName("dropdown-content");
        for (let i = 0; i < dropdownMenus.length; i++) {
            let openDropdown = dropdownMenus[i];
            if (openDropdown.classList.contains('show')) {
                openDropdown.classList.remove('show');
            }
        }
    }
};








    function scrollToNextBlock() {
        const currentBlock = document.getElementById("first");
        const nextBlock = document.getElementById("about");
    
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

document.getElementById('accept-button2').addEventListener('click', function() {
    document.getElementById('offer-modal1').style.display = 'none';
});
document.getElementById('accept-button3').addEventListener('click', function() {
    document.getElementById('offer-modal2').style.display = 'none';
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

window.addEventListener('DOMContentLoaded', (event) => {
    const carouselContainer = document.querySelector('.articles-carousel1');
    const articles = document.querySelectorAll('.article1');
    const prevButton = document.getElementById('prev1');
    const nextButton = document.getElementById('next1');
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
