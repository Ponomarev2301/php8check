<?php
/**
 * php8check - проверка сайта на 1С-Битрикс или MODX перед переходом на PHP 8.
 *
 * Один файл, без установки. Ничего не меняет на сайте и никуда не отправляет данные.
 * В браузере: переименуйте файл в php8check-<случайные символы>.php, положите в корень сайта и откройте.
 * В консоли: php php8check.php /путь/к/сайту [8.0|8.1|8.2|8.3]
 *
 * Это статический анализ: он находит частые проблемы, но не все, и может ошибаться.
 * Окончательная проверка - только на тестовой копии сайта.
 *
 * Лицензия MIT.
 */

define('P8C_VERSION', '1.0.0');
define('P8C_MAX_FILES', 30000);
define('P8C_MAX_FILE_SIZE', 1048576);
define('P8C_MAX_SHOWN', 3000);
define('P8C_HELP_URL', 'https://kwork.ru/website-repair/55321956/perevedu-sayt-na-php-8-bez-polomok-s-proverkoy-na-testovoy-kopii');

@set_time_limit(600);
@ini_set('memory_limit', '512M');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');

    if (!preg_match('/^php8check-[A-Za-z0-9]{12,}\.php$/', basename(__FILE__))) {
        p8c_page_rename();
        exit;
    }

    if (isset($_POST['p8c_delete'])) {
        $ok = @unlink(__FILE__);
        p8c_page_deleted($ok);
        exit;
    }
}

$root = $isCli ? (isset($argv[1]) ? $argv[1] : getcwd()) : dirname(__FILE__);
$root = rtrim(str_replace('\\', '/', realpath($root)), '/');
$target = $isCli ? (isset($argv[2]) ? $argv[2] : '8.2') : (isset($_GET['target']) ? $_GET['target'] : '8.2');
if (!in_array($target, array('8.2', '8.3', '8.4', '8.5'), true)) {
    $target = '8.2';
}

$report = p8c_run($root, $target);

if ($isCli) {
    p8c_render_cli($report);
} else {
    p8c_render_html($report);
}

/* ===================== Правила ===================== */

/**
 * Удалённые и устаревшие функции.
 * since - версия PHP, где функция удалена (critical) или объявлена устаревшей (warning).
 *
 * @return array
 */
function p8c_function_rules()
{
    $r = array();
    $removed70 = array('ereg', 'eregi', 'ereg_replace', 'eregi_replace', 'split', 'spliti', 'sql_regcase',
        'call_user_method', 'call_user_method_array', 'set_magic_quotes_runtime', 'magic_quotes_runtime',
        'set_socket_blocking', 'datefmt_set_timezone_id', 'imagepsbbox', 'imagepsencodefont',
        'imagepsextendfont', 'imagepsfreefont', 'imagepsloadfont', 'imagepsslantfont', 'imagepstext');
    foreach ($removed70 as $f) {
        $r[$f] = array('critical', '7.0', 'Функция удалена ещё в PHP 7.0. Вызов приведёт к фатальной ошибке.');
    }
    $r['mysql_*'] = array('critical', '7.0', 'Расширение mysql удалено в PHP 7.0. Нужно переписать на mysqli, PDO или API движка (например, $DB / Application::getConnection() в Битриксе, $modx->query() в MODX).');
    $r['mcrypt_*'] = array('critical', '7.2', 'Расширение mcrypt удалено в PHP 7.2. Нужна замена на openssl_* или sodium_*.');

    $removed80 = array(
        'each' => 'Функция each() удалена. Обычно заменяется на foreach.',
        'create_function' => 'Функция create_function() удалена. Нужна замена на анонимную функцию function () { ... }.',
        'money_format' => 'Функция money_format() удалена. Замена - number_format() или NumberFormatter.',
        'get_magic_quotes_gpc' => 'Функция get_magic_quotes_gpc() удалена. Обычно вызов можно просто убрать (всегда возвращала false).',
        'get_magic_quotes_runtime' => 'Функция get_magic_quotes_runtime() удалена. Обычно вызов можно просто убрать.',
        'ezmlm_hash' => 'Функция удалена в PHP 8.0.',
        'restore_include_path' => 'Функция удалена. Замена - ini_restore(\'include_path\').',
        'hebrevc' => 'Функция удалена в PHP 8.0.',
        'convert_cyr_string' => 'Функция convert_cyr_string() удалена. Замена - mb_convert_encoding() или iconv().',
        'fgetss' => 'Функция fgetss() удалена. Замена - fgets() + strip_tags().',
        'gzgetss' => 'Функция удалена в PHP 8.0.',
        'image2wbmp' => 'Функция удалена. Замена - imagewbmp().',
        'read_exif_data' => 'Функция удалена. Замена - exif_read_data().',
        'is_real' => 'Функция is_real() удалена. Замена - is_float().',
        'ldap_sort' => 'Функция удалена в PHP 8.0.',
        'ldap_control_paged_result' => 'Функция удалена в PHP 8.0.',
        'ldap_control_paged_result_response' => 'Функция удалена в PHP 8.0.',
        'mbereg' => 'Устаревший псевдоним удалён. Замена - mb_ereg().',
        'mberegi' => 'Устаревший псевдоним удалён. Замена - mb_eregi().',
        'mbereg_replace' => 'Устаревший псевдоним удалён. Замена - mb_ereg_replace().',
        'mberegi_replace' => 'Устаревший псевдоним удалён. Замена - mb_eregi_replace().',
        'mbsplit' => 'Устаревший псевдоним удалён. Замена - mb_split().',
        'mbereg_match' => 'Устаревший псевдоним удалён. Замена - mb_ereg_match().',
        'mbregex_encoding' => 'Устаревший псевдоним удалён. Замена - mb_regex_encoding().',
        'jpeg2wbmp' => 'Функция удалена в PHP 8.0.',
        'png2wbmp' => 'Функция удалена в PHP 8.0.',
        'gmp_random' => 'Функция удалена. Замена - gmp_random_bits() или gmp_random_range().',
    );
    foreach ($removed80 as $f => $msg) {
        $r[$f] = array('critical', '8.0', $msg . ' На PHP 8 - фатальная ошибка.');
    }

    $dep = array(
        'strftime' => array('8.1', 'strftime() устарела с PHP 8.1 и будет удалена. Замена - date() или IntlDateFormatter.'),
        'gmstrftime' => array('8.1', 'gmstrftime() устарела с PHP 8.1. Замена - gmdate() или IntlDateFormatter.'),
        'date_sunrise' => array('8.1', 'Устарела с PHP 8.1. Замена - date_sun_info().'),
        'date_sunset' => array('8.1', 'Устарела с PHP 8.1. Замена - date_sun_info().'),
        'mhash' => array('8.1', 'mhash устарела с PHP 8.1. Замена - hash().'),
        'odbc_result_all' => array('8.1', 'Устарела с PHP 8.1.'),
        'utf8_encode' => array('8.2', 'utf8_encode() устарела с PHP 8.2. Замена - mb_convert_encoding($s, \'UTF-8\', \'ISO-8859-1\').'),
        'utf8_decode' => array('8.2', 'utf8_decode() устарела с PHP 8.2. Замена - mb_convert_encoding().'),
        'mb_strimwidth' => null,
        'assert_options' => array('8.3', 'assert_options() устарела с PHP 8.3.'),
        'mysqli_ping' => array('8.4', 'mysqli_ping() устарела с PHP 8.4.'),
        'mysqli_kill' => array('8.4', 'mysqli_kill() устарела с PHP 8.4.'),
        'lcg_value' => array('8.4', 'lcg_value() устарела с PHP 8.4. Замена - random_int() или Random\\Randomizer.'),
        'mysqli_refresh' => array('8.4', 'mysqli_refresh() устарела с PHP 8.4.'),
        'xml_set_object' => array('8.4', 'xml_set_object() устарела с PHP 8.4. Замена - передавать в обработчики callable с объектом.'),
        'curl_close' => array('8.5', 'curl_close() ничего не делает с PHP 8.0 и устарела в 8.5. Вызов можно удалить.'),
        'curl_share_close' => array('8.5', 'curl_share_close() ничего не делает с PHP 8.0 и устарела в 8.5. Вызов можно удалить.'),
        'imagedestroy' => array('8.5', 'imagedestroy() ничего не делает с PHP 8.0 и устарела в 8.5. Вызов можно удалить.'),
        'finfo_close' => array('8.5', 'finfo_close() устарела в PHP 8.5. Вызов можно удалить.'),
        'xml_parser_free' => array('8.5', 'xml_parser_free() ничего не делает с PHP 8.0 и устарела в 8.5. Вызов можно удалить.'),
    );
    foreach ($dep as $f => $d) {
        if ($d !== null) {
            $r[$f] = array('warning', $d[0], $d[1] . ' Пока работает, но выдаёт предупреждения в лог.');
        }
    }
    return $r;
}

/* ===================== Запуск ===================== */

/**
 * Полный прогон: движок, окружение, код.
 *
 * @param string $root
 * @param string $target
 * @return array
 */
function p8c_run($root, $target)
{
    $report = array(
        'root' => $root,
        'target' => $target,
        'php' => PHP_VERSION,
        'engine' => null,
        'engineVersion' => null,
        'engineNotes' => array(),
        'env' => array(),
        'findings' => array(),
        'encrypted' => array(),
        'declared' => array(),
        'files' => 0,
        'skippedBig' => 0,
        'limitReached' => false,
        'started' => microtime(true),
        'dbNote' => null,
    );

    if (is_file($root . '/bitrix/modules/main/include.php') || is_dir($root . '/bitrix/php_interface')) {
        $report['engine'] = 'bitrix';
        p8c_bitrix_engine($report);
        p8c_bitrix_env($report);
        p8c_db_version($report, p8c_bitrix_db_config($root));
        p8c_scan_dir($report, $root, $root, 'p8c_bitrix_area');
    } elseif (is_file($root . '/config.core.php')) {
        $report['engine'] = 'modx';
        $core = p8c_modx_core_path($root);
        p8c_modx_engine($report, $core);
        p8c_common_env($report);
        p8c_scan_dir($report, $root, $root, 'p8c_modx_area');
        if ($core && strpos($core, $root . '/') !== 0) {
            p8c_scan_dir($report, $core, $core, 'p8c_modx_core_area');
        }
        p8c_modx_db($report, $core);
    } else {
        $report['engine'] = 'other';
        p8c_common_env($report);
        p8c_scan_dir($report, $root, $root, 'p8c_generic_area');
    }

    p8c_apply_polyfills($report);
    $report['elapsed'] = round(microtime(true) - $report['started'], 1);
    return $report;
}

/* ===================== 1С-Битрикс ===================== */

/**
 * Версия ядра и совместимость с целевой версией PHP.
 *
 * @param array $report
 */
function p8c_bitrix_engine(&$report)
{
    $file = $report['root'] . '/bitrix/modules/main/classes/general/version.php';
    $ver = null;
    if (is_file($file) && preg_match('/SM_VERSION["\']\s*,\s*["\']([\d.]+)/', file_get_contents($file), $m)) {
        $ver = $m[1];
    }
    $report['engineVersion'] = $ver;

    if ($ver === null) {
        $report['engineNotes'][] = array('warning', 'Не удалось определить версию главного модуля. Посмотрите её в админке: Настройки > Обновление платформы.');
        return;
    }
    if (version_compare($ver, '20.100.0', '<')) {
        $report['engineNotes'][] = array('critical', 'Версия главного модуля ' . $ver . ' не поддерживает PHP 8 (поддержка появилась в 20.100.0). Сначала нужно обновить ядро на текущем PHP, и только потом переключать версию PHP. Для обновления нужна активная лицензия.');
    } elseif (version_compare($report['target'], '8.1', '>=') && version_compare($ver, '23.0.0', '<')) {
        $report['engineNotes'][] = array('warning', 'Версия главного модуля ' . $ver . ' поддерживает PHP 8.0, но для PHP ' . $report['target'] . ' по нашим данным нужна более свежая версия ядра (ориентир - 23.x и выше). Сверьтесь с таблицей совместимости 1С-Битрикс и обновите ядро перед переходом.');
    } else {
        $report['engineNotes'][] = array('ok', 'Версия главного модуля ' . $ver . ' по нашим данным совместима с PHP ' . $report['target'] . '. Перед переходом всё равно установите все доступные обновления.');
    }
    $report['engineNotes'][] = array('info', 'По данным 1С-Битрикс: с 01.02.2026 поддержка продуктов на PHP ниже 8.2 ограничена, рекомендуется PHP 8.4 и выше; с 01.09.2026 - на MySQL ниже 8.0, рекомендуется MySQL 8.4 и выше. Порядок, который советует 1С-Битрикс: сначала обновить MySQL, затем продукт, затем PHP.');
}

/**
 * Настройки окружения Битрикса, критичные для PHP 8.
 *
 * @param array $report
 */
function p8c_bitrix_env(&$report)
{
    $root = $report['root'];
    p8c_common_env($report);

    $settings = is_file($root . '/bitrix/.settings.php') ? file_get_contents($root . '/bitrix/.settings.php') : '';
    $dbconn = is_file($root . '/bitrix/php_interface/dbconn.php') ? file_get_contents($root . '/bitrix/php_interface/dbconn.php') : '';

    if ($settings !== '' && preg_match('/MysqlConnection[\'"]/', $settings) && !preg_match('/MysqliConnection/', $settings)) {
        $report['env'][] = array('critical', 'Подключение к базе через устаревшее расширение mysql', 'В /bitrix/.settings.php указан класс MysqlConnection. Расширения mysql в PHP 7 и 8 нет - сайт не откроется. Нужно перейти на MysqliConnection и добавить define("BX_USE_MYSQLI", true); в dbconn.php.');
    }
    if ($dbconn !== '' && !preg_match('/BX_USE_MYSQLI[\'"]\s*,\s*true/i', $dbconn) && !preg_match('/MysqliConnection/', $settings)) {
        $report['env'][] = array('warning', 'Не найдена настройка BX_USE_MYSQLI', 'В dbconn.php нет define("BX_USE_MYSQLI", true). На старых версиях ядра без неё используется расширение mysql, которого в PHP 8 нет.');
    }

    $utf = preg_match('/BX_UTF[\'"]\s*,\s*true/i', $dbconn);
    if ($dbconn !== '' && !$utf) {
        $report['env'][] = array('warning', 'Сайт в кодировке windows-1251', 'На PHP 8 сайт в windows-1251 работать может, но новые версии ядра 1С-Битрикс рассчитаны на UTF-8. Уточните это до обновления ядра: перевод на UTF-8 - отдельная работа.');
    }

    $ht = is_file($root . '/.htaccess') ? file_get_contents($root . '/.htaccess') : '';
    if (preg_match('/mbstring\.func_overload\s+[1-7]/i', $ht)) {
        $report['env'][] = array('critical', 'mbstring.func_overload в .htaccess', 'В .htaccess включена настройка mbstring.func_overload. В PHP 8 её нет: ядро старых версий в UTF-8 без неё работает неправильно, а сама строка может дать ошибку 500. Нужна конвертация сайта на работу без func_overload (есть инструкция 1С-Битрикс).');
    }
}

/**
 * Раздел отчёта для файла сайта на Битриксе. null - файл не проверяем.
 *
 * @param string $rel
 * @return string|null
 */
function p8c_bitrix_area($rel)
{
    $skip = array('bitrix/components/bitrix/', 'bitrix/js/', 'bitrix/css/', 'bitrix/images/', 'bitrix/themes/',
        'bitrix/panel/', 'bitrix/wizards/bitrix/', 'bitrix/gadgets/bitrix/', 'bitrix/cache/', 'bitrix/managed_cache/',
        'bitrix/stack_cache/', 'bitrix/html_pages/', 'bitrix/backup/', 'bitrix/tmp/', 'bitrix/updates/',
        'bitrix/admin/', 'bitrix/services/', 'bitrix/activities/bitrix/', 'bitrix/blocks/bitrix/', 'bitrix/tools/',
        'bitrix/otp/', 'bitrix/fonts/', 'upload/', 'bitrix/catalog_export/');
    foreach ($skip as $s) {
        if (strpos($rel, $s) === 0) {
            return null;
        }
    }
    if (preg_match('#^bitrix/modules/([^/]+)/#', $rel, $m)) {
        if (strpos($m[1], '.') === false) {
            return null;
        }
        return 'Сторонний модуль ' . $m[1];
    }
    if (strpos($rel, 'local/templates/') === 0 || strpos($rel, 'bitrix/templates/') === 0) {
        return 'Шаблоны сайта';
    }
    if (strpos($rel, 'local/modules/') === 0) {
        return 'Свои модули (/local/modules)';
    }
    if (strpos($rel, 'local/components/') === 0 || preg_match('#^bitrix/components/[^/]+/#', $rel)) {
        return 'Свои и сторонние компоненты';
    }
    if (strpos($rel, 'local/') === 0) {
        return 'Свой код (/local)';
    }
    if (strpos($rel, 'bitrix/php_interface/') === 0) {
        return 'init.php и обработчики событий';
    }
    if (strpos($rel, 'bitrix/') === 0) {
        return 'Прочие файлы в /bitrix';
    }
    return 'Публичная часть сайта';
}

/* ===================== MODX ===================== */

/**
 * Путь к ядру MODX из config.core.php.
 *
 * @param string $root
 * @return string|null
 */
function p8c_modx_core_path($root)
{
    $txt = file_get_contents($root . '/config.core.php');
    if (preg_match('/MODX_CORE_PATH[\'"]\s*,\s*[\'"]([^\'"]+)/', $txt, $m)) {
        $p = realpath($m[1]);
        return $p ? rtrim(str_replace('\\', '/', $p), '/') : null;
    }
    if (is_dir($root . '/core')) {
        return $root . '/core';
    }
    return null;
}

/**
 * Версия MODX и совместимость.
 *
 * @param array $report
 * @param string|null $core
 */
function p8c_modx_engine(&$report, $core)
{
    $ver = null;
    if ($core && is_file($core . '/docs/version.inc.php')) {
        $txt = file_get_contents($core . '/docs/version.inc.php');
        $parts = array();
        foreach (array('version', 'major_version', 'minor_version') as $k) {
            if (preg_match('/\[[\'"]' . $k . '[\'"]\]\s*=\s*[\'"]?(\d+)/', $txt, $m)) {
                $parts[] = $m[1];
            }
        }
        if (count($parts) === 3) {
            $ver = implode('.', $parts);
        }
    }
    $report['engineVersion'] = $ver;

    if ($ver === null) {
        $report['engineNotes'][] = array('warning', 'Не удалось определить версию MODX. Посмотрите её в админке (в шапке или в отчёте о системе).');
        return;
    }
    if (version_compare($ver, '2.8.0', '<')) {
        $report['engineNotes'][] = array('critical', 'MODX ' . $ver . ' не рассчитан на PHP 8. Сначала обновите MODX до 2.8.x (лучше до последней 2.8) на текущем PHP, затем обновите дополнения и только потом переключайте PHP.');
    } elseif (version_compare($ver, '3.0.0', '<') && version_compare($report['target'], '8.1', '>=')) {
        $report['engineNotes'][] = array('warning', 'MODX ' . $ver . ': ветка 2.8 работает на PHP 8, но для ' . $report['target'] . ' по нашим данным лучше последняя версия 2.8.x - в ней больше исправлений под новые версии PHP.');
    } else {
        $report['engineNotes'][] = array('ok', 'MODX ' . $ver . ' по нашим данным совместим с PHP ' . $report['target'] . '.');
    }
    $report['engineNotes'][] = array('info', 'Дополнения (miniShop2, pdoTools, Formit и др.) обновляйте через Менеджер пакетов до перехода - многие проблемы ниже уже исправлены в их новых версиях.');
}

/**
 * Сниппеты и плагины, которые хранятся в базе MODX.
 *
 * @param array $report
 * @param string|null $core
 */
function p8c_modx_db(&$report, $core)
{
    if (!$core) {
        return;
    }
    $key = defined('MODX_CONFIG_KEY') ? MODX_CONFIG_KEY : 'config';
    $cfgFile = $core . '/config/' . $key . '.inc.php';
    if (!is_file($cfgFile) || !class_exists('PDO')) {
        $report['dbNote'] = 'Сниппеты и плагины из базы не проверены: не найден файл настроек или нет PDO.';
        return;
    }
    $cfg = p8c_modx_read_config($cfgFile);
    if (!$cfg) {
        $report['dbNote'] = 'Сниппеты и плагины из базы не проверены: не удалось прочитать настройки подключения.';
        return;
    }
    p8c_db_version($report, $cfg);
    try {
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $pfx = $cfg['prefix'];
        $sets = array(
            array('site_snippets', 'snippet', 'Сниппеты в базе'),
            array('site_plugins', 'plugincode', 'Плагины в базе'),
        );
        foreach ($sets as $s) {
            $st = $pdo->query('SELECT id, name, `' . $s[1] . '` AS code FROM `' . $pfx . $s[0] . '`');
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $code = (string)$row['code'];
                if (trim($code) === '') {
                    continue;
                }
                if (stripos(ltrim($code), '<?php') !== 0) {
                    $code = "<?php\n" . $code;
                }
                p8c_scan_code($report, $code, $s[2], $s[2] === 'Сниппеты в базе' ? 'сниппет «' . $row['name'] . '» (id ' . $row['id'] . ')' : 'плагин «' . $row['name'] . '» (id ' . $row['id'] . ')', -1);
            }
        }
    } catch (Exception $e) {
        $report['dbNote'] = 'Сниппеты и плагины из базы не проверены: не удалось подключиться к базе.';
    }
}

/**
 * Настройки подключения к базе из /bitrix/.settings.php.
 *
 * @param string $root
 * @return array|null
 */
function p8c_bitrix_db_config($root)
{
    $file = $root . '/bitrix/.settings.php';
    if (!is_file($file)) {
        return null;
    }
    ob_start();
    $cfg = @include $file;
    ob_end_clean();
    if (!is_array($cfg) || !isset($cfg['connections']['value']['default'])) {
        return null;
    }
    $c = $cfg['connections']['value']['default'];
    if (empty($c['login'])) {
        return null;
    }
    $host = isset($c['host']) ? (string)$c['host'] : 'localhost';
    $dsn = 'mysql:dbname=' . (isset($c['database']) ? $c['database'] : '');
    if (preg_match('#^(.*?):(/.+)$#', $host, $m) || strpos($host, '/') === 0) {
        $dsn .= ';unix_socket=' . (isset($m[2]) ? $m[2] : $host);
    } elseif (preg_match('/^(.+):(\d+)$/', $host, $m)) {
        $dsn .= ';host=' . $m[1] . ';port=' . $m[2];
    } else {
        $dsn .= ';host=' . $host;
    }
    return array('dsn' => $dsn, 'user' => $c['login'], 'pass' => isset($c['password']) ? $c['password'] : '', 'prefix' => '');
}

/**
 * Версия MySQL/MariaDB: только читает VERSION(), ничего не меняет.
 *
 * @param array $report
 * @param array|null $cfg
 */
function p8c_db_version(&$report, $cfg)
{
    if (!$cfg || !class_exists('PDO')) {
        $report['env'][] = array('info', 'Версия базы данных не определена', 'Не удалось прочитать настройки подключения. Версию MySQL можно посмотреть в панели хостинга.');
        return;
    }
    try {
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5));
        $ver = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    } catch (Exception $e) {
        $report['env'][] = array('info', 'Версия базы данных не определена', 'Не удалось подключиться к базе. Версию MySQL можно посмотреть в панели хостинга.');
        return;
    }
    preg_match('/^(\d+\.\d+(\.\d+)?)/', $ver, $m);
    $num = isset($m[1]) ? $m[1] : '0';
    if (stripos($ver, 'mariadb') !== false) {
        $report['env'][] = array('info', 'База данных: MariaDB ' . $num, 'Требования движка к версии MariaDB уточните в документации. При переходе на новую версию PHP база обычно не мешает, но её тоже стоит держать актуальной.');
        return;
    }
    if ($report['engine'] === 'bitrix') {
        if (version_compare($num, '8.0', '<')) {
            $report['env'][] = array('warning', 'MySQL ' . $num, 'С 01.09.2026 1С-Битрикс ограничила поддержку продуктов на MySQL ниже 8.0 и рекомендует 8.4 и выше. По порядку 1С-Битрикс MySQL обновляют первым, до продукта и PHP. Обычно это делает поддержка хостинга.');
        } elseif (version_compare($num, '8.4', '<')) {
            $report['env'][] = array('info', 'MySQL ' . $num, 'Версия поддерживается. 1С-Битрикс рекомендует MySQL 8.4 и выше.');
        } else {
            $report['env'][] = array('ok', 'MySQL ' . $num, 'Версия соответствует рекомендации 1С-Битрикс.');
        }
    } else {
        $report['env'][] = array('info', 'MySQL ' . $num, 'Версия базы данных для справки.');
    }
}

/**
 * Читает настройки подключения к базе из конфига MODX без вывода и без побочных эффектов.
 *
 * @param string $file
 * @return array|null
 */
function p8c_modx_read_config($file)
{
    $database_dsn = $database_user = $database_password = $table_prefix = null;
    $database_type = $database_server = $dbase = $database_connection_charset = null;
    ob_start();
    $ok = @include $file;
    ob_end_clean();
    if (!$ok || !$database_user) {
        return null;
    }
    if (!$database_dsn) {
        $database_dsn = 'mysql:host=' . $database_server . ';dbname=' . trim($dbase, '`') . ';charset=' . ($database_connection_charset ? $database_connection_charset : 'utf8');
    }
    return array('dsn' => $database_dsn, 'user' => $database_user, 'pass' => $database_password, 'prefix' => (string)$table_prefix);
}

/**
 * Раздел отчёта для файла в корне сайта на MODX.
 *
 * @param string $rel
 * @return string|null
 */
function p8c_modx_area($rel)
{
    if (strpos($rel, 'core/') === 0) {
        return p8c_modx_core_area(substr($rel, 5));
    }
    foreach (array('manager/', 'connectors/', 'setup/', 'assets/cache/') as $s) {
        if (strpos($rel, $s) === 0) {
            return null;
        }
    }
    if (preg_match('#^assets/components/([^/]+)/#', $rel, $m)) {
        return 'Дополнение ' . $m[1];
    }
    return 'Прочий код сайта';
}

/**
 * Раздел отчёта для файла внутри папки core MODX.
 *
 * @param string $rel путь относительно core/
 * @return string|null
 */
function p8c_modx_core_area($rel)
{
    foreach (array('cache/', 'model/', 'xpdo/', 'src/', 'vendor/', 'lexicon/', 'docs/', 'packages/', 'config/', 'export/', 'import/', 'error/', 'include/') as $s) {
        if (strpos($rel, $s) === 0) {
            return null;
        }
    }
    if (preg_match('#^components/([^/]+)/#', $rel, $m)) {
        return 'Дополнение ' . $m[1];
    }
    if (strpos($rel, 'elements/') === 0) {
        return 'Статичные элементы (core/elements)';
    }
    return 'Прочие файлы в core';
}

/**
 * Раздел отчёта для сайта без распознанного движка.
 *
 * @param string $rel
 * @return string
 */
function p8c_generic_area($rel)
{
    $parts = explode('/', $rel);
    return count($parts) > 1 ? 'Папка /' . $parts[0] : 'Корень сайта';
}

/* ===================== Окружение ===================== */

/**
 * Общие настройки PHP, важные для перехода.
 *
 * @param array $report
 */
function p8c_common_env(&$report)
{
    $fo = (int)ini_get('mbstring.func_overload');
    if ($fo > 0) {
        $report['env'][] = array('critical', 'Включена настройка mbstring.func_overload', 'Сейчас mbstring.func_overload = ' . $fo . '. В PHP 8 этой настройки нет: код, который на неё рассчитывает (strlen, substr и т.п. для UTF-8), начнёт работать неправильно.');
    }
    if (version_compare(PHP_VERSION, '7.0.0', '<')) {
        $report['env'][] = array('warning', 'Сейчас PHP ' . PHP_VERSION, 'Переход с PHP 5 сразу на 8 - большой шаг. Надёжнее через промежуточную версию 7.4: сначала добиться стабильной работы на ней.');
    }
    if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
        $report['env'][] = array('info', 'Скрипт запущен на PHP ' . PHP_VERSION, 'Сайт уже работает на PHP 8. Отчёт ниже покажет, что может мешать переходу на ' . $report['target'] . ' и что уже выдаёт предупреждения.');
    }
}

/* ===================== Обход файлов ===================== */

/**
 * Рекурсивно проверяет php-файлы.
 *
 * @param array $report
 * @param string $dir
 * @param string $base от этой папки считаются относительные пути
 * @param callable $areaFn
 */
function p8c_scan_dir(&$report, $dir, $base, $areaFn)
{
    $self = realpath(__FILE__);
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                function ($cur) {
                    $n = $cur->getFilename();
                    if ($cur->isDir()) {
                        return !in_array($n, array('.git', '.svn', 'node_modules', 'vendor', '.idea'), true) && !$cur->isLink();
                    }
                    return (bool)preg_match('/\.(php|phtml|inc)$/i', $n);
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($it as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (realpath($path) === $self) {
                continue;
            }
            $rel = ltrim(substr($path, strlen($base)), '/');
            $area = call_user_func($areaFn, $rel);
            if ($area === null) {
                continue;
            }
            if ($report['files'] >= P8C_MAX_FILES) {
                $report['limitReached'] = true;
                return;
            }
            if ($file->getSize() > P8C_MAX_FILE_SIZE) {
                $report['skippedBig']++;
                continue;
            }
            $code = @file_get_contents($path);
            if ($code === false) {
                continue;
            }
            $report['files']++;
            if (p8c_is_encrypted($code)) {
                $report['encrypted'][] = array($area, $rel);
                continue;
            }
            p8c_scan_code($report, $code, $area, $rel, 0);
        }
    } catch (Exception $e) {
        $report['env'][] = array('warning', 'Не все папки удалось прочитать', $e->getMessage());
    }
}

/**
 * Зашифрованный код (ionCube, SourceGuardian) проверить нельзя.
 *
 * @param string $code
 * @return bool
 */
function p8c_is_encrypted($code)
{
    $head = substr($code, 0, 2048);
    return stripos($head, 'ioncube') !== false || stripos($head, 'sg_load(') !== false || stripos($head, 'SourceGuardian') !== false;
}

/* ===================== Анализ кода ===================== */

/**
 * Разбирает код на токены и ищет проблемы.
 *
 * @param array $report
 * @param string $code
 * @param string $area
 * @param string $where файл или описание элемента
 * @param int $lineShift поправка номеров строк (для кода из базы)
 */
function p8c_scan_code(&$report, $code, $area, $where, $lineShift)
{
    static $rules = null;
    if ($rules === null) {
        $rules = p8c_function_rules();
    }
    $tokens = @token_get_all($code);
    if (!$tokens) {
        return;
    }
    $lines = null;
    $n = count($tokens);
    $sig = array();
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
            continue;
        }
        $sig[] = $i;
    }
    $cnt = count($sig);
    $tNameFq = defined('T_NAME_FULLY_QUALIFIED') ? T_NAME_FULLY_QUALIFIED : -1;
    $classStack = array();
    $depth = 0;
    $add = function ($sev, $since, $line, $title, $msg) use (&$report, &$lines, $code, $area, $where, $lineShift) {
        if ($sev === 'warning' && version_compare($report['target'], $since, '<')) {
            return -1;
        }
        if ($lines === null) {
            $lines = explode("\n", $code);
        }
        $src = isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
        if (function_exists('mb_substr')) {
            $src = mb_substr($src, 0, 180, 'UTF-8');
        } else {
            $src = substr($src, 0, 180);
        }
        $report['findings'][] = array(
            'sev' => $sev, 'since' => $since, 'area' => $area, 'where' => $where,
            'line' => $line + $lineShift, 'title' => $title, 'msg' => $msg, 'src' => $src, 'fn' => null,
        );
        return count($report['findings']) - 1;
    };

    for ($k = 0; $k < $cnt; $k++) {
        $i = $sig[$k];
        $t = $tokens[$i];
        $prev = $k > 0 ? $tokens[$sig[$k - 1]] : null;
        $next = $k + 1 < $cnt ? $tokens[$sig[$k + 1]] : null;

        if ($t === '{' || (is_array($t) && in_array($t[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
            $depth++;
        } elseif ($t === '}') {
            $depth--;
            if ($classStack && $depth === $classStack[count($classStack) - 1]['depth']) {
                $cls = array_pop($classStack);
                if ($cls['php4'] !== null && !$cls['construct']) {
                    $add('critical', '8.0', $cls['php4'], 'Конструктор в стиле PHP 4', 'Метод ' . $cls['name'] . '() с именем класса больше не считается конструктором: в PHP 8 он не вызовется при new ' . $cls['name'] . '(), объект останется ненастроенным. Нужно переименовать метод в __construct().');
                }
                if (in_array('serializable', $cls['impl'], true) && !$cls['serialize']) {
                    $add('warning', '8.1', $cls['line'], 'Интерфейс Serializable без __serialize()', 'С PHP 8.1 классы с Serializable без методов __serialize()/__unserialize() выдают предупреждение. Нужно добавить эти методы.');
                }
            }
        }

        if ($t === '`' && version_compare($report['target'], '8.5', '>=')) {
            $ln = 1;
            for ($j = $i; $j >= 0; $j--) {
                if (is_array($tokens[$j])) {
                    $ln = $tokens[$j][2] + substr_count($tokens[$j][1], "\n");
                    break;
                }
            }
            $add('warning', '8.5', $ln, 'Обратные кавычки `команда`', 'Запуск команд через обратные кавычки устарел в PHP 8.5. Замена - shell_exec().');
            for ($k++; $k < $cnt && $tokens[$sig[$k]] !== '`'; $k++) {
            }
            continue;
        }

        if ($t === ']' && $next === '{' && $sig[$k + 1] === $i + 1) {
            $ln = 1;
            for ($j = $i; $j >= 0; $j--) {
                if (is_array($tokens[$j])) {
                    $ln = $tokens[$j][2] + substr_count($tokens[$j][1], "\n");
                    break;
                }
            }
            $add('critical', '8.0', $ln, 'Фигурные скобки вместо квадратных: $var[...]{...}', 'Синтаксис $arr[\'key\']{0} удалён в PHP 8 - файл перестанет работать целиком (ошибка разбора). Замена - $arr[\'key\'][0].');
            continue;
        }

        if (!is_array($t)) {
            continue;
        }
        $line = $t[2];

        // Объявления классов - для конструкторов PHP 4 и интерфейсов с типами возврата
        if ($t[0] === T_CLASS && is_array($next) && $next[0] === T_STRING && !($prev && is_array($prev) && $prev[0] === T_DOUBLE_COLON)) {
            $isNs = preg_match('/^\s*namespace\s+[A-Za-z_\\\\]/m', $code);
            $impl = array();
            for ($j = $k + 2; $j < $cnt && $tokens[$sig[$j]] !== '{'; $j++) {
                $tj = $tokens[$sig[$j]];
                if (is_array($tj) && $tj[0] !== T_EXTENDS && $tj[0] !== T_IMPLEMENTS) {
                    $parts = explode('\\', $tj[1]);
                    $impl[] = strtolower(end($parts));
                }
            }
            $classStack[] = array('name' => $next[1], 'depth' => $depth, 'php4' => null, 'construct' => false, 'ns' => $isNs,
                'impl' => $impl, 'line' => $line, 'serialize' => false);
            continue;
        }
        if ($t[0] === T_FUNCTION || (defined('T_FN') && $t[0] === T_FN)) {
            p8c_check_params($tokens, $sig, $k, $add);
        }
        if ($t[0] === T_FUNCTION && is_array($next) && $next[0] === T_STRING) {
            $fname = strtolower($next[1]);
            if ($classStack) {
                $top = &$classStack[count($classStack) - 1];
                if ($depth === $top['depth'] + 1) {
                    if ($fname === '__construct') {
                        $top['construct'] = true;
                    } elseif ($fname === strtolower($top['name']) && !$top['ns']) {
                        $top['php4'] = $next[2];
                    } elseif ($fname === '__serialize') {
                        $top['serialize'] = true;
                    }
                    p8c_check_return_type($tokens, $sig, $k, $next[1], $top['impl'], $add);
                }
                unset($top);
            } else {
                $report['declared'][$fname] = $where;
                if ($fname === '__autoload') {
                    $add('critical', '8.0', $next[2], 'Функция __autoload()', 'Автозагрузка через __autoload() удалена в PHP 8: классы перестанут подключаться. Нужна замена на spl_autoload_register().');
                }
            }
            continue;
        }

        // Удалённые приведения типов
        if ($t[0] === T_DOUBLE_CAST && stripos($t[1], 'real') !== false) {
            $add('critical', '8.0', $line, 'Приведение (real)', 'Приведение (real) удалено в PHP 8 - ошибка разбора файла. Замена - (float).');
            continue;
        }
        if (defined('T_UNSET_CAST') && $t[0] === T_UNSET_CAST) {
            $add('critical', '8.0', $line, 'Приведение (unset)', 'Приведение (unset) удалено в PHP 8 - ошибка разбора файла. Замена - присвоить null.');
            continue;
        }

        // Доступ к символам строки или элементам массива через фигурные скобки: $s{0}
        $isProp = $t[0] === T_STRING && $prev && is_array($prev) && $prev[0] === T_OBJECT_OPERATOR;
        if (($t[0] === T_VARIABLE || $isProp) && $next === '{' && $sig[$k + 1] === $i + 1) {
            $add('critical', '8.0', $line, 'Фигурные скобки вместо квадратных: $var{...}', 'Синтаксис $str{0} удалён в PHP 8 - файл перестанет работать целиком (ошибка разбора). Замена - $str[0].');
            continue;
        }

        // Устаревшие константы и кодировки
        if ($t[0] === T_STRING && $next !== '(' && in_array($t[1], array('FILTER_SANITIZE_STRING', 'FILTER_SANITIZE_STRIPPED'), true)) {
            $add('warning', '8.1', $line, $t[1], 'Фильтр ' . $t[1] . ' устарел с PHP 8.1. Замена - htmlspecialchars() или strip_tags() по ситуации.');
            continue;
        }
        if ($t[0] === T_CONSTANT_ENCAPSED_STRING && strcasecmp(trim($t[1], '\'"'), 'HTML-ENTITIES') === 0) {
            $add('warning', '8.2', $line, 'Кодировка HTML-ENTITIES в mbstring', 'Кодировка HTML-ENTITIES в функциях mb_* устарела с PHP 8.2 (часто встречается при загрузке HTML в DOMDocument). Замена - htmlentities() / mb_encode_numericentity().');
            continue;
        }

        // Неканонические приведения типов (8.5)
        if (version_compare($report['target'], '8.5', '>=') && in_array($t[0], array(T_INT_CAST, T_DOUBLE_CAST, T_BOOL_CAST, T_STRING_CAST), true)
            && preg_match('/\(\s*(integer|double|boolean|binary)\s*\)/i', $t[1], $m)) {
            $canon = array('integer' => 'int', 'double' => 'float', 'boolean' => 'bool', 'binary' => 'string');
            $add('warning', '8.5', $line, 'Приведение (' . strtolower($m[1]) . ')', 'Длинные названия приведений типов устарели в PHP 8.5. Замена - (' . $canon[strtolower($m[1])] . ').');
            continue;
        }

        // ${var} внутри строк
        if ($t[0] === T_DOLLAR_OPEN_CURLY_BRACES && version_compare($report['target'], '8.2', '>=')) {
            $add('warning', '8.2', $line, 'Подстановка "${var}" в строке', 'Запись "${var}" в строках устарела с PHP 8.2. Замена - "{$var}".');
            continue;
        }

        // $HTTP_RAW_POST_DATA
        if ($t[0] === T_VARIABLE && $t[1] === '$HTTP_RAW_POST_DATA') {
            $add('critical', '7.0', $line, '$HTTP_RAW_POST_DATA', 'Переменная удалена ещё в PHP 7.0 и всегда пуста. Замена - file_get_contents(\'php://input\').');
            continue;
        }

        // Вызовы функций
        $isName = $t[0] === T_STRING || $t[0] === $tNameFq;
        if (!$isName || $next !== '(') {
            continue;
        }
        if ($prev && is_array($prev) && in_array($prev[0], array(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST), true)) {
            continue;
        }
        if (defined('T_NULLSAFE_OBJECT_OPERATOR') && $prev && is_array($prev) && $prev[0] === T_NULLSAFE_OBJECT_OPERATOR) {
            continue;
        }
        if ($prev && is_array($prev) && $prev[0] === T_NS_SEPARATOR) {
            $pp = $k > 1 ? $tokens[$sig[$k - 2]] : null;
            if ($pp && is_array($pp) && $pp[0] === T_STRING) {
                continue;
            }
        }
        $name = strtolower(ltrim($t[1], '\\'));
        if (strpos($name, '\\') !== false) {
            continue;
        }

        $rule = null;
        if (isset($rules[$name])) {
            $rule = $rules[$name];
        } elseif (strpos($name, 'mysql_') === 0) {
            $rule = $rules['mysql_*'];
        } elseif (strpos($name, 'mcrypt_') === 0) {
            $rule = $rules['mcrypt_*'];
        }
        if ($rule) {
            if ($rule[0] === 'warning' && version_compare($report['target'], $rule[1], '<')) {
                continue;
            }
            $idx = $add($rule[0], $rule[1], $line, $name . '()', $rule[2]);
            if ($idx >= 0) {
                $report['findings'][$idx]['fn'] = $name;
            }
            continue;
        }

        if (($name === 'get_class' || $name === 'get_parent_class') && $k + 2 < $cnt && $tokens[$sig[$k + 2]] === ')') {
            $add('warning', '8.3', $line, $name . '() без аргумента', 'Вызов ' . $name . '() без аргумента устарел с PHP 8.3. Замена - ' . ($name === 'get_class' ? 'self::class или static::class' : 'parent::class') . '.');
            continue;
        }

        if ($name === 'define' || $name === 'implode' || $name === 'join') {
            $args = p8c_call_args($tokens, $sig, $k + 1);
            if ($name === 'define' && count($args) === 3 && strtolower(trim($args[2])) === 'true') {
                $add('warning', '8.0', $line, 'define(..., ..., true)', 'Константы без учёта регистра убраны в PHP 8: третий параметр true игнорируется. Если где-то константа написана в другом регистре, она станет неопределённой.');
            } elseif ($name !== 'define' && count($args) === 2 && preg_match('/^\$/', trim($args[0])) && preg_match('/^([\'"]).*\1$/s', trim($args[1]))) {
                $add('critical', '8.0', $line, $name . '($массив, \'разделитель\')', 'Старый порядок аргументов (массив, затем разделитель) удалён в PHP 8 - фатальная ошибка. Нужно поменять аргументы местами: ' . $name . '(\'разделитель\', $массив).');
            }
        }
    }
}

/**
 * Индекс в $sig открывающей скобки параметров после function/fn, или -1.
 *
 * @param array $tokens
 * @param array $sig
 * @param int $k индекс function/fn в $sig
 * @return int
 */
function p8c_params_open($tokens, $sig, $k)
{
    $cnt = count($sig);
    for ($j = $k + 1; $j < $cnt && $j <= $k + 3; $j++) {
        if ($tokens[$sig[$j]] === '(') {
            return $j;
        }
    }
    return -1;
}

/**
 * Неявно nullable параметры: Type $x = null без ? (устарело в PHP 8.4).
 *
 * @param array $tokens
 * @param array $sig
 * @param int $k индекс function/fn в $sig
 * @param callable $add
 */
function p8c_check_params($tokens, $sig, $k, $add)
{
    $open = p8c_params_open($tokens, $sig, $k);
    if ($open < 0) {
        return;
    }
    $cnt = count($sig);
    $level = 0;
    $seg = array();
    $skipTypes = array(T_WHITESPACE);
    foreach (array('T_PUBLIC', 'T_PROTECTED', 'T_PRIVATE', 'T_READONLY', 'T_ELLIPSIS') as $c) {
        if (defined($c)) {
            $skipTypes[] = constant($c);
        }
    }
    for ($j = $open; $j < $cnt; $j++) {
        $t = $tokens[$sig[$j]];
        $s = is_array($t) ? $t[1] : $t;
        if ($s === '(' || $s === '[') {
            $level++;
            if ($level === 1) {
                continue;
            }
        } elseif ($s === ')' || $s === ']') {
            $level--;
        }
        if (($level === 0) || ($s === ',' && $level === 1)) {
            $varPos = -1;
            foreach ($seg as $p => $st) {
                if (is_array($st) && $st[0] === T_VARIABLE) {
                    $varPos = $p;
                    break;
                }
            }
            if ($varPos > 0 && isset($seg[$varPos + 1], $seg[$varPos + 2]) && $seg[$varPos + 1] === '='
                && is_array($seg[$varPos + 2]) && strtolower($seg[$varPos + 2][1]) === 'null' && !isset($seg[$varPos + 3])) {
                $type = '';
                for ($p = 0; $p < $varPos; $p++) {
                    $st = $seg[$p];
                    if (is_array($st) && in_array($st[0], $skipTypes, true)) {
                        continue;
                    }
                    if ((is_array($st) ? $st[1] : $st) === '&') {
                        continue;
                    }
                    $type .= is_array($st) ? $st[1] : $st;
                }
                $lt = strtolower($type);
                if ($type !== '' && strpos($type, '?') === false && !preg_match('/(^|\|)(null|mixed)(\||$)/', $lt)) {
                    $add('warning', '8.4', $seg[$varPos][2], 'Неявный nullable-тип: ' . $type . ' ' . $seg[$varPos][1] . ' = null', 'С PHP 8.4 такая запись устарела и пишет предупреждение в лог при каждой загрузке файла. Замена - ?' . $type . ' ' . $seg[$varPos][1] . ' = null.');
                }
            }
            $seg = array();
            if ($level === 0) {
                return;
            }
            continue;
        }
        $seg[] = $t;
    }
}

/**
 * Методы ArrayAccess, Iterator, Countable и др. без типа возврата (предупреждения с PHP 8.1).
 *
 * @param array $tokens
 * @param array $sig
 * @param int $k индекс function в $sig
 * @param string $fname
 * @param array $impl интерфейсы класса (в нижнем регистре)
 * @param callable $add
 */
function p8c_check_return_type($tokens, $sig, $k, $fname, $impl, $add)
{
    $map = array(
        'arrayaccess' => array('offsetget', 'offsetset', 'offsetexists', 'offsetunset'),
        'iterator' => array('current', 'key', 'next', 'rewind', 'valid'),
        'iteratoraggregate' => array('getiterator'),
        'countable' => array('count'),
        'jsonserializable' => array('jsonserialize'),
    );
    $hit = false;
    foreach ($impl as $i) {
        if (isset($map[$i]) && in_array(strtolower($fname), $map[$i], true)) {
            $hit = true;
            break;
        }
    }
    if (!$hit) {
        return;
    }
    $open = p8c_params_open($tokens, $sig, $k);
    if ($open < 0) {
        return;
    }
    $cnt = count($sig);
    $level = 0;
    for ($j = $open; $j < $cnt; $j++) {
        $s = $tokens[$sig[$j]];
        if ($s === '(') {
            $level++;
        } elseif ($s === ')') {
            $level--;
            if ($level === 0) {
                if ($j + 1 < $cnt && $tokens[$sig[$j + 1]] === ':') {
                    return;
                }
                break;
            }
        }
    }
    for ($j = $sig[$k] - 1; $j >= 0 && $j >= $sig[$k] - 40; $j--) {
        $t = $tokens[$j];
        if ($t === ';' || $t === '{' || $t === '}') {
            break;
        }
        if (is_array($t) && stripos($t[1], 'ReturnTypeWillChange') !== false) {
            return;
        }
    }
    $add('warning', '8.1', $tokens[$sig[$k]][2], 'Метод ' . $fname . '() без типа возврата', 'С PHP 8.1 метод интерфейса без указанного типа возврата выдаёт предупреждение (Return type ... should be compatible). Нужно добавить тип возврата или атрибут #[\ReturnTypeWillChange].');
}

/**
 * Аргументы вызова верхнего уровня как строки. $k - индекс '(' в $sig.
 *
 * @param array $tokens
 * @param array $sig
 * @param int $k
 * @return array
 */
function p8c_call_args($tokens, $sig, $k)
{
    $args = array();
    $cur = '';
    $level = 0;
    $cnt = count($sig);
    for ($j = $k; $j < $cnt; $j++) {
        $t = $tokens[$sig[$j]];
        $s = is_array($t) ? $t[1] : $t;
        if ($s === '(' || $s === '[' || $s === '{') {
            $level++;
            if ($level === 1) {
                continue;
            }
        } elseif ($s === ')' || $s === ']' || $s === '}') {
            $level--;
            if ($level === 0) {
                $args[] = $cur;
                return $args;
            }
        } elseif ($s === ',' && $level === 1) {
            $args[] = $cur;
            $cur = '';
            continue;
        }
        $cur .= $s;
        if ($j - $k > 400) {
            break;
        }
    }
    return array();
}

/**
 * Если на сайте есть своя замена удалённой функции (полифилл), понижаем находки до «к сведению».
 *
 * @param array $report
 */
function p8c_apply_polyfills(&$report)
{
    foreach ($report['findings'] as &$f) {
        if ($f['fn'] !== null && isset($report['declared'][$f['fn']])) {
            $f['sev'] = 'info';
            $f['msg'] = 'На сайте найдена своя функция ' . $f['fn'] . '() (' . $report['declared'][$f['fn']] . ') - похоже на замену удалённой функции. Проверьте, что она подключается раньше вызовов и работает корректно.';
        }
    }
    unset($f);
}

/* ===================== Вывод ===================== */

/**
 * Итоговые счётчики и вердикт.
 *
 * @param array $report
 * @return array
 */
function p8c_summary($report)
{
    $c = array('critical' => 0, 'warning' => 0, 'info' => 0);
    foreach ($report['findings'] as $f) {
        $c[$f['sev']]++;
    }
    $envCrit = 0;
    foreach ($report['env'] as $e) {
        if ($e[0] === 'critical') {
            $envCrit++;
        }
    }
    $engCrit = 0;
    foreach ($report['engineNotes'] as $e) {
        if ($e[0] === 'critical') {
            $engCrit++;
        }
    }
    $total = $c['critical'] + $envCrit + $engCrit;
    if ($total > 0) {
        $v = array('bad', 'Сейчас переключать PHP рискованно: найдено критичных проблем - ' . $total . '. Сначала их нужно исправить на тестовой копии.');
    } elseif ($c['warning'] > 0) {
        $v = array('mid', 'Критичных проблем не найдено, но есть предупреждения. Переход возможен после проверки на тестовой копии.');
    } else {
        $v = array('good', 'Явных проблем не найдено. Это хороший знак, но не гарантия: проверьте сайт на тестовой копии с PHP ' . $report['target'] . '.');
    }
    return array('counts' => $c, 'verdict' => $v);
}

/**
 * Текст предупреждения о границах проверки.
 *
 * @return array
 */
function p8c_disclaimer()
{
    return array(
        'Это не панацея. Скрипт читает код, но не запускает его, поэтому может ошибаться в обе стороны: пропустить проблему или показать то, что на деле не сломается.',
        'Он не видит ошибок, которые проявляются только во время работы: null в строковых функциях, динамические свойства объектов, несовпадение типов, особенности сторонних библиотек.',
        'Зашифрованные файлы (ionCube и подобные) проверить нельзя - их нужно обновлять у разработчика модуля.',
        'Окончательная проверка - только на тестовой копии сайта с новой версией PHP: пройти каталог, корзину, оформление заказа, формы, личный кабинет, админку и обмены.',
        'Скрипт ничего не меняет на сайте. Используете его на свой риск, автор не отвечает за последствия решений, принятых по отчёту.',
    );
}

/**
 * Вывод в консоль.
 *
 * @param array $report
 */
function p8c_render_cli($report)
{
    $s = p8c_summary($report);
    $eng = array('bitrix' => '1С-Битрикс', 'modx' => 'MODX', 'other' => 'не распознан');
    echo "php8check " . P8C_VERSION . "\n";
    echo "Папка: {$report['root']}\nДвижок: {$eng[$report['engine']]} " . ($report['engineVersion'] ?: '') . "\n";
    echo "PHP сейчас: {$report['php']}, цель: {$report['target']}\nФайлов проверено: {$report['files']} за {$report['elapsed']} с\n\n";
    echo "ИТОГ: {$s['verdict'][1]}\n";
    echo "Критичных: {$s['counts']['critical']}, предупреждений: {$s['counts']['warning']}, к сведению: {$s['counts']['info']}\n\n";
    foreach ($report['engineNotes'] as $e) {
        echo "[{$e[0]}] {$e[1]}\n";
    }
    foreach ($report['env'] as $e) {
        echo "[{$e[0]}] {$e[1]}: {$e[2]}\n";
    }
    echo "\n";
    $order = array('critical' => 0, 'warning' => 1, 'info' => 2);
    $f = $report['findings'];
    usort($f, function ($a, $b) use ($order) {
        return $order[$a['sev']] - $order[$b['sev']] ?: strcmp($a['area'], $b['area']) ?: strcmp($a['where'], $b['where']) ?: $a['line'] - $b['line'];
    });
    foreach ($f as $x) {
        echo "[{$x['sev']}] {$x['area']} | {$x['where']}:{$x['line']} | {$x['title']}\n    {$x['src']}\n";
    }
    foreach ($report['encrypted'] as $e) {
        echo "[encrypted] {$e[0]} | {$e[1]}\n";
    }
    if ($report['dbNote']) {
        echo "\n{$report['dbNote']}\n";
    }
    echo "\nВажно:\n - " . implode("\n - ", p8c_disclaimer()) . "\n";
}

/**
 * HTML-отчёт.
 *
 * @param array $report
 */
function p8c_render_html($report)
{
    $s = p8c_summary($report);
    $h = function ($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    };
    $eng = array('bitrix' => '1С-Битрикс', 'modx' => 'MODX', 'other' => 'не распознан');
    $sevName = array('critical' => 'Сломается', 'warning' => 'Предупреждение', 'info' => 'К сведению', 'ok' => 'В порядке');

    $groups = array();
    foreach ($report['findings'] as $f) {
        $groups[$f['area']][$f['where']][] = $f;
    }
    ksort($groups);

    p8c_html_head('Отчёт php8check');
    ?>
    <header class="top">
        <h1>Готов ли сайт к PHP <?= $h($report['target']) ?></h1>
        <p class="sub">Движок: <b><?= $h($eng[$report['engine']]) ?><?= $report['engineVersion'] ? ' ' . $h($report['engineVersion']) : '' ?></b> · PHP сейчас: <b><?= $h($report['php']) ?></b> · Проверено файлов: <b><?= (int)$report['files'] ?></b> за <?= $h($report['elapsed']) ?> с</p>
        <form class="targets" method="get">
            Проверить для версии:
            <?php foreach (array('8.2', '8.3', '8.4', '8.5') as $v): ?>
                <button name="target" value="<?= $v ?>" class="<?= $v === $report['target'] ? 'on' : '' ?>"><?= $v ?></button>
            <?php endforeach; ?>
        </form>
    </header>

    <div class="note">
        <b>Важно: это не панацея.</b> Скрипт читает код, но не запускает его, поэтому может ошибаться в обе стороны. Подробнее - внизу отчёта.
    </div>

    <section class="verdict v-<?= $s['verdict'][0] ?>">
        <p><?= $h($s['verdict'][1]) ?></p>
        <div class="counts">
            <span class="c-critical"><?= $s['counts']['critical'] ?> сломается</span>
            <span class="c-warning"><?= $s['counts']['warning'] ?> предупреждений</span>
            <span class="c-info"><?= $s['counts']['info'] ?> к сведению</span>
        </div>
    </section>

    <section>
        <h2>Движок и окружение</h2>
        <ul class="list">
            <?php foreach ($report['engineNotes'] as $e): ?>
                <li class="s-<?= $e[0] ?>"><span class="tag"><?= $h($sevName[$e[0]]) ?></span> <?= $h($e[1]) ?></li>
            <?php endforeach; ?>
            <?php foreach ($report['env'] as $e): ?>
                <li class="s-<?= $e[0] ?>"><span class="tag"><?= $h($sevName[$e[0]]) ?></span> <b><?= $h($e[1]) ?>.</b> <?= $h($e[2]) ?></li>
            <?php endforeach; ?>
            <?php if (!$report['env'] && !$report['engineNotes']): ?>
                <li class="s-ok">Замечаний к окружению нет.</li>
            <?php endif; ?>
        </ul>
    </section>

    <section>
        <h2>Код сайта</h2>
        <?php if (!$groups): ?>
            <p class="muted">В проверенном коде проблем не найдено.</p>
        <?php endif; ?>
        <?php $shown = 0; foreach ($groups as $area => $files):
            $ac = array('critical' => 0, 'warning' => 0, 'info' => 0);
            foreach ($files as $list) { foreach ($list as $f) { $ac[$f['sev']]++; } }
            ?>
            <details class="area" <?= $ac['critical'] ? 'open' : '' ?>>
                <summary><span class="aname"><?= $h($area) ?></span>
                    <?php foreach ($ac as $k => $v): if ($v): ?><span class="pill c-<?= $k ?>"><?= $v ?></span><?php endif; endforeach; ?>
                </summary>
                <?php if (strpos($area, 'Сторонний модуль') === 0 || strpos($area, 'Дополнение') === 0): ?>
                    <p class="hint">Это код стороннего разработчика. Сначала обновите его до последней версии - часто проблемы там уже исправлены. Правки вручную пропадут при следующем обновлении.</p>
                <?php endif; ?>
                <?php foreach ($files as $where => $list): ?>
                    <div class="file">
                        <div class="fname"><?= $h($where) ?></div>
                        <?php foreach ($list as $f): if ($shown++ >= P8C_MAX_SHOWN) { break 3; } ?>
                            <div class="f s-<?= $f['sev'] ?>">
                                <div class="fhead"><span class="tag"><?= $h($sevName[$f['sev']]) ?></span> строка <?= (int)$f['line'] ?> · <b><?= $h($f['title']) ?></b></div>
                                <?php if ($f['src'] !== ''): ?><pre><?= $h($f['src']) ?></pre><?php endif; ?>
                                <div class="fmsg"><?= $h($f['msg']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </details>
        <?php endforeach; ?>
        <?php if ($shown > P8C_MAX_SHOWN): ?>
            <p class="muted">Показаны первые <?= P8C_MAX_SHOWN ?> находок. Полный список - в консоли: php <?= $h(basename(__FILE__)) ?> /путь/к/сайту</p>
        <?php endif; ?>

        <?php if ($report['encrypted']): ?>
            <details class="area">
                <summary><span class="aname">Зашифрованные файлы - не проверены</span><span class="pill c-info"><?= count($report['encrypted']) ?></span></summary>
                <p class="hint">Код закрыт (ionCube и подобные). Узнайте у разработчика модуля, поддерживает ли его текущая версия PHP <?= $h($report['target']) ?>, - обычно нужен и другой загрузчик ionCube под новую версию PHP.</p>
                <?php foreach ($report['encrypted'] as $e): ?><div class="fname"><?= $h($e[0]) ?>: <?= $h($e[1]) ?></div><?php endforeach; ?>
            </details>
        <?php endif; ?>

        <?php if ($report['dbNote'] || $report['skippedBig'] || $report['limitReached']): ?>
            <ul class="list small">
                <?php if ($report['dbNote']): ?><li class="s-info"><?= $h($report['dbNote']) ?></li><?php endif; ?>
                <?php if ($report['skippedBig']): ?><li class="s-info">Пропущено больших файлов (больше 1 МБ): <?= (int)$report['skippedBig'] ?>.</li><?php endif; ?>
                <?php if ($report['limitReached']): ?><li class="s-warning">Достигнут лимит в <?= P8C_MAX_FILES ?> файлов - проверено не всё.</li><?php endif; ?>
                <li class="s-info">Не проверялись: ядро движка, папки vendor и node_modules, кеш и загруженные файлы.</li>
            </ul>
        <?php endif; ?>
    </section>

    <section class="disclaimer">
        <h2>Что важно знать об этой проверке</h2>
        <ul>
            <?php foreach (p8c_disclaimer() as $d): ?><li><?= $h($d) ?></li><?php endforeach; ?>
        </ul>
    </section>

    <section class="help">
        <h2>Как переходить безопасно</h2>
        <ol>
            <li>Сделайте полную копию сайта и базы.</li>
            <li>Разверните тестовую копию на поддомене и включите на ней PHP <?= $h($report['target']) ?>.</li>
            <li>Обновите ядро и сторонние модули/дополнения, исправьте найденное.</li>
            <li>Пройдите ключевые сценарии и посмотрите лог ошибок PHP.</li>
            <li>Только после этого переключайте версию PHP на рабочем сайте.</li>
        </ol>
        <p>Не хотите разбираться сами? Переведу сайт на PHP 8 на тестовой копии, без риска для рабочего сайта: <a href="<?= $h(P8C_HELP_URL) ?>" target="_blank" rel="noopener">заказать на Kwork</a>.</p>
    </section>

    <form method="post" class="del" onsubmit="return confirm('Удалить файл проверки с сервера?')">
        <button name="p8c_delete" value="1">Удалить этот скрипт с сервера</button>
        <span class="muted">Не оставляйте файл проверки на сайте после использования.</span>
    </form>

    <footer class="muted">php8check <?= P8C_VERSION ?> · отчёт сформирован <?= date('d.m.Y H:i') ?></footer>
    <?php
    p8c_html_foot();
}

/**
 * Страница с просьбой переименовать файл.
 */
function p8c_page_rename()
{
    $suggest = 'php8check-' . substr(str_shuffle(str_repeat('abcdefghijkmnpqrstuvwxyz23456789', 3)), 0, 16) . '.php';
    p8c_html_head('php8check: переименуйте файл');
    ?>
    <section class="verdict v-mid">
        <h1>Сначала переименуйте файл</h1>
        <p>Чтобы отчёт о сайте не смог открыть посторонний, скрипт работает, только если в имени файла есть случайные символы. Переименуйте файл, например, так:</p>
        <pre><?= htmlspecialchars($suggest, ENT_QUOTES, 'UTF-8') ?></pre>
        <p>и откройте его по новому адресу. После проверки удалите файл.</p>
    </section>
    <?php
    p8c_html_foot();
}

/**
 * Страница после удаления.
 *
 * @param bool $ok
 */
function p8c_page_deleted($ok)
{
    p8c_html_head('php8check');
    ?>
    <section class="verdict <?= $ok ? 'v-good' : 'v-bad' ?>">
        <p><?= $ok ? 'Файл проверки удалён с сервера.' : 'Не удалось удалить файл - удалите его вручную через FTP или панель хостинга.' ?></p>
    </section>
    <?php
    p8c_html_foot();
}

/**
 * Начало HTML-страницы со стилями.
 *
 * @param string $title
 */
function p8c_html_head($title)
{
    ?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<style>
:root{--bg:#f6f5f2;--card:#fff;--fg:#1d1d1b;--muted:#6b6a66;--line:#e3e1db;--crit:#b3261e;--crit-bg:#fbeceb;--warn:#8a5a00;--warn-bg:#fdf3e1;--info:#2f5d8a;--info-bg:#eaf1f8;--ok:#2e6b3a;--ok-bg:#e9f4ec}
@media (prefers-color-scheme:dark){:root{--bg:#171715;--card:#21211f;--fg:#ecebe6;--muted:#a19f98;--line:#33322f;--crit:#ff8a80;--crit-bg:#3a1f1d;--warn:#f0c060;--warn-bg:#352a14;--info:#8fbbe6;--info-bg:#1c2a38;--ok:#8fd19e;--ok-bg:#1c3022}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.55 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;padding:24px max(16px,env(safe-area-inset-left)) 48px}
body>*{max-width:960px;margin-left:auto;margin-right:auto}
h1{font-size:26px;margin:0 0 6px;line-height:1.2}
h2{font-size:18px;margin:28px 0 10px}
.sub,.muted{color:var(--muted)}
.small{font-size:13px}
.targets{margin-top:10px;color:var(--muted);font-size:13px;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.targets button{border:1px solid var(--line);background:var(--card);color:var(--fg);border-radius:6px;padding:3px 10px;cursor:pointer;font:inherit}
.targets button.on{background:var(--fg);color:var(--bg);border-color:var(--fg)}
.note{margin-top:16px;padding:10px 14px;border-left:3px solid var(--warn);background:var(--warn-bg);border-radius:4px;font-size:14px}
.verdict{margin-top:16px;padding:16px 18px;border-radius:8px;background:var(--card);border:1px solid var(--line)}
.verdict p{margin:0 0 10px;font-size:16px}
.v-bad{border-left:5px solid var(--crit)}.v-mid{border-left:5px solid var(--warn)}.v-good{border-left:5px solid var(--ok)}
.counts{display:flex;flex-wrap:wrap;gap:8px}
.counts span,.pill{padding:2px 10px;border-radius:999px;font-size:13px;font-weight:600}
.c-critical{background:var(--crit-bg);color:var(--crit)}.c-warning{background:var(--warn-bg);color:var(--warn)}.c-info{background:var(--info-bg);color:var(--info)}
.list{list-style:none;padding:0;margin:0;display:grid;gap:8px}
.list li{background:var(--card);border:1px solid var(--line);border-radius:6px;padding:10px 12px}
.tag{display:inline-block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;margin-right:6px}
.s-critical .tag{color:var(--crit)}.s-warning .tag{color:var(--warn)}.s-info .tag{color:var(--info)}.s-ok .tag{color:var(--ok)}
.area{background:var(--card);border:1px solid var(--line);border-radius:8px;margin-bottom:10px;padding:0 14px}
.area summary{cursor:pointer;padding:12px 0;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.aname{font-weight:600;margin-right:auto}
.hint{font-size:13px;color:var(--muted);margin:0 0 10px}
.file{border-top:1px solid var(--line);padding:10px 0}
.fname{font-family:ui-monospace,Consolas,monospace;font-size:13px;word-break:break-all;margin-bottom:6px}
.f{padding:8px 10px;border-radius:6px;margin:6px 0}
.f.s-critical{background:var(--crit-bg)}.f.s-warning{background:var(--warn-bg)}.f.s-info{background:var(--info-bg)}
.fhead{font-size:14px}
pre{margin:6px 0;padding:6px 8px;background:var(--bg);border-radius:4px;overflow-x:auto;font-size:12.5px;white-space:pre}
.fmsg{font-size:14px}
.disclaimer ul,.help ol{padding-left:20px}
.disclaimer{background:var(--card);border:1px solid var(--line);border-radius:8px;padding:2px 18px 10px;margin-top:28px}
.help a{color:var(--info)}
.del{margin-top:24px;display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.del button{background:var(--crit);color:#fff;border:0;border-radius:6px;padding:8px 14px;font:inherit;cursor:pointer}
footer{margin-top:24px;font-size:12px}
</style>
</head>
<body>
<?php
}

/**
 * Конец HTML-страницы.
 */
function p8c_html_foot()
{
    echo "\n</body>\n</html>";
}
