<?php
/**
 * Приём заявки с формы и отправка на почту.
 *
 * Кладётся рядом с index.html. Требует PHP 7.0+ и работающую функцию mail()
 * (на большинстве обычных хостингов она включена).
 *
 * Что нужно поправить перед запуском — блок НАСТРОЙКИ ниже.
 */

// ------------------------- НАСТРОЙКИ -------------------------

// Куда слать заявки. Можно несколько через запятую.
$TO = 'ventsay@mail.ru';

// От кого. ДОЛЖЕН быть адресом на вашем домене, иначе письмо уйдёт в спам:
// почтовые сервисы проверяют SPF и не любят чужие адреса в поле From.
$FROM      = 'site@ventsay70.ru';
$FROM_NAME = 'Сайт ВентСай-Томск';

$SUBJECT = 'Заявка с сайта ventsay70.ru';

// Версия политики, с которой согласился пользователь. Меняйте вместе с политикой.
$POLICY_VERSION = '2026-09-10';

// Журнал заявок и согласий. Пустая строка — не вести.
//
// ВНИМАНИЕ: в журнале лежат персональные данные. Лучше указать путь ВЫШЕ
// корня сайта, например '/home/ВАШ_АККАУНТ/leads.log' — тогда файл недоступен
// по прямой ссылке в принципе. Пока путь внутри сайта, доступ закрывает
// .htaccess рядом с этим файлом (работает на Apache; на nginx нужно правило
// в конфиге сервера).
$LOG_FILE = __DIR__ . '/leads.log';

// Не больше стольких заявок с одного IP в час.
$RATE_LIMIT = 5;

// -------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function done() {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Метод не поддерживается', 405);
}

// --- Защита от ботов ---

// Honeypot: поле спрятано от людей, боты его заполняют.
if (!empty($_POST['company'])) {
    done(); // Молча отвечаем «принято», чтобы бот не подбирал обход.
}

// Форма, отправленная быстрее трёх секунд после загрузки, — почти наверняка бот.
$ts = isset($_POST['ts']) ? (int) $_POST['ts'] : 0;
if ($ts > 0 && (time() - intdiv($ts, 1000)) < 3) {
    done();
}

// --- Ограничение частоты ---

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
$bucket = sys_get_temp_dir() . '/lead_' . md5($ip) . '.txt';
$hits = [];
if (is_readable($bucket)) {
    $hits = array_filter(
        explode(',', (string) file_get_contents($bucket)),
        function ($t) { return (int) $t > time() - 3600; }
    );
}
if (count($hits) >= $RATE_LIMIT) {
    fail('Слишком много заявок подряд. Позвоните нам: 8 (3822) 577-002', 429);
}
$hits[] = time();
@file_put_contents($bucket, implode(',', $hits));

// --- Разбор и проверка полей ---

function field($key, $max = 2000) {
    $v = isset($_POST[$key]) ? (string) $_POST[$key] : '';
    $v = trim($v);
    if (function_exists('mb_substr')) {
        $v = mb_substr($v, 0, $max, 'UTF-8');
    } else {
        $v = substr($v, 0, $max);
    }
    // Убираем переводы строк там, где они попадут в заголовки письма.
    return $v;
}

$name    = field('name', 80);
$phone   = field('phone', 40);
$service = field('service', 120);
$comment = field('comment', 2000);
$consent = isset($_POST['consent']) && $_POST['consent'] !== '';

$len = function ($s) {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
};

if ($len($name) < 2)  fail('Укажите имя');
if (preg_match_all('/\d/', $phone) < 10) fail('Проверьте номер телефона');
if (!$consent) fail('Нужно согласие на обработку персональных данных');

// --- Письмо ---

$when = date('d.m.Y H:i:s');

$lines = [
    'Новая заявка с сайта',
    '',
    'Имя:      ' . $name,
    'Телефон:  ' . $phone,
    'Услуга:   ' . ($service !== '' ? $service : 'не выбрана'),
    '',
    'Комментарий:',
    ($comment !== '' ? $comment : '—'),
    '',
    str_repeat('-', 40),
    'Отправлено: ' . $when,
    'IP:         ' . $ip,
    'Страница:   ' . (isset($_SERVER['HTTP_REFERER']) ? preg_replace('/[\r\n]/', '', $_SERVER['HTTP_REFERER']) : '—'),
    'Согласие на обработку персональных данных: получено, версия политики ' . $POLICY_VERSION,
];
$body = implode("\r\n", $lines);

// В заголовках переводы строк недопустимы — иначе можно подставить чужие заголовки.
$clean = function ($s) { return preg_replace('/[\r\n]+/', ' ', $s); };

$headers = implode("\r\n", [
    'From: =?UTF-8?B?' . base64_encode($clean($FROM_NAME)) . '?= <' . $clean($FROM) . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'MIME-Version: 1.0',
    'X-Mailer: ventsay-site',
]);

$subject = '=?UTF-8?B?' . base64_encode($clean($SUBJECT) . ' — ' . $clean($name)) . '?=';

$sent = @mail($clean($TO), $subject, $body, $headers, '-f' . $clean($FROM));

// --- Журнал (в том числе как доказательство согласия по 152-ФЗ) ---

if ($LOG_FILE !== '') {
    $row = [
        date('c'), $ip, $name, $phone, $service,
        str_replace(["\r", "\n"], ' ', $comment),
        'consent=' . $POLICY_VERSION,
        'mail=' . ($sent ? 'ok' : 'fail'),
    ];
    @file_put_contents($LOG_FILE, implode("\t", $row) . "\n", FILE_APPEND | LOCK_EX);
}

if (!$sent) {
    fail('Не удалось отправить заявку. Позвоните нам: 8 (3822) 577-002', 500);
}

done();
