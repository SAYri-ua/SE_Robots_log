<?php
// Run with: php -d extension=pdo_sqlite tests/sebotslog_test.php
date_default_timezone_set('UTC');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

define('OBJECT', 'OBJECT');
$test_options = array('sebotslogs_logs_lifetime' => 90, 'sebotslogs_urls_into_db' => 0);
function current_time($type) {
    return $type === 'mysql' ? '2026-10-10 12:00:00' : strtotime('2026-10-10 12:00:00');
}
function get_option($name) { return $GLOBALS['test_options'][$name]; }
function register_activation_hook($file, $callback) {}
function register_deactivation_hook($file, $callback) {}
function register_uninstall_hook($file, $callback) {}
function add_filter($hook, $callback, $priority = 10, $args = 1) {}
function add_action($hook, $callback) { $GLOBALS['test_actions'][$hook][] = $callback; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_attr($value); }

// Exercise the plugin's SQL against a real in-memory database.
class SebotslogsTestDatabase {
    public $prefix = 'test_';
    public $db;
    public $read_queries = array();

    public function __construct() {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('CREATE TABLE test_sebotslogs_se (id INTEGER PRIMARY KEY, name TEXT, bot TEXT, date TEXT)');
        $this->db->exec('CREATE TABLE test_sebotslogs_urls (id INTEGER PRIMARY KEY, name TEXT, bot TEXT, date TEXT, url TEXT)');
    }

    public function reset() {
        $this->db->exec('DELETE FROM test_sebotslogs_se');
        $this->db->exec('DELETE FROM test_sebotslogs_urls');
        $this->read_queries = array();
    }

    public function prepare($sql, ...$args) {
        if (substr_count($sql, '%s') !== count($args)) {
            throw new RuntimeException('SQL placeholder count mismatch');
        }
        return preg_replace_callback('/%s/', function () use (&$args) {
            return $this->db->quote(array_shift($args));
        }, $sql);
    }

    public function insert($table, $data, $formats = array()) {
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')';
        return $this->db->prepare($sql)->execute(array_values($data));
    }

    public function query($sql) {
        // SQLite has no MySQL OPTIMIZE TABLE command; cleanup DELETEs still run.
        return strpos($sql, 'OPTIMIZE TABLE') === 0 ? 0 : $this->db->exec($sql);
    }

    public function get_results($sql, $format) {
        $this->read_queries[] = $sql;
        return $this->db->query($sql)->fetchAll(PDO::FETCH_OBJ);
    }

    public function records($table) {
        return $this->db->query('SELECT * FROM ' . $this->prefix . $table . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }
}

$wpdb = new SebotslogsTestDatabase();
require dirname(__DIR__) . '/sebotslog.php';
$checks = 0;
function check($condition, $message) {
    $GLOBALS['checks']++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Independent expectations from the requested User-Agent list.
$expected_bots = array(
    'Google' => array('Googlebot', 'Googlebot-Image', 'Googlebot-Video', 'Storebot-Google'),
    'Bing' => array('bingbot', 'MicrosoftPreview', 'BingVideoPreview'),
    'Yahoo' => array('Yahoo! Slurp', 'Slurp'),
    'DuckDuckGo' => array('DuckDuckBot'),
    'Apple' => array('Applebot'),
    'Baidu' => array('Baiduspider'),
    'Qwant' => array('Qwantbot'),
    'Naver' => array('Yeti'),
    'Mojeek' => array('MojeekBot'),
    'ChatGPT' => array('OAI-SearchBot', 'ChatGPT-User'),
);
check(in_array('sebotslogs_run', $test_actions['init'], true), 'Request logging must run on init');
foreach ($expected_bots as $name => $bots) {
    foreach ($bots as $bot) {
        foreach (array($bot . '/1.0', 'Mozilla/5.0 (compatible; ' . strtolower($bot) . '/1.0)', strtoupper($bot)) as $user_agent) {
            foreach (array(0, 1) as $log_urls) {
                $wpdb->reset();
                $test_options['sebotslogs_urls_into_db'] = $log_urls;
                $_SERVER = array('HTTP_USER_AGENT' => $user_agent, 'REQUEST_URI' => '/article?topic=search&lang=uk', 'SERVER_NAME' => 'example.test');
                sebotslogs_run();
                $records = $wpdb->records('sebotslogs_se');
                check(count($records) === 1, $user_agent . ': log exactly one visit');
                check($records[0]['name'] === $name && $records[0]['bot'] === $bot, $user_agent . ': correct engine and specific bot');
                check($records[0]['date'] === current_time('mysql'), $user_agent . ': WordPress request time');
                $urls = $wpdb->records('sebotslogs_urls');
                check(count($urls) === $log_urls, $user_agent . ': respect URL logging setting');
                if ($log_urls) {
                    check($urls[0]['name'] === $name && $urls[0]['bot'] === $bot && $urls[0]['url'] === 'example.test/article?topic=search&lang=uk', $user_agent . ': correct URL record');
                }
            }
        }
    }
}

foreach (array('Mozilla/5.0', 'google', 'GPTBot/1.0', '') as $user_agent) {
    $wpdb->reset();
    $_SERVER['HTTP_USER_AGENT'] = $user_agent;
    sebotslogs_run();
    check(count($wpdb->records('sebotslogs_se')) === 0 && count($wpdb->records('sebotslogs_urls')) === 0, 'Ignore unsupported User-Agent: ' . $user_agent);
    check($sebotslogs_se_name === '' && $sebotslogs_se_bot === '' && $sebotslogs_se_url === '', 'Do not retain previous request state');
}
foreach (array(array('REQUEST_URI' => '/'), array('HTTP_USER_AGENT' => 'Googlebot')) as $server) {
    $wpdb->reset();
    $_SERVER = $server;
    sebotslogs_run();
    check(count($wpdb->records('sebotslogs_se')) === 0, 'Missing request headers must not create visits');
}
$wpdb->reset();
$test_options['sebotslogs_urls_into_db'] = 0;
$_SERVER = array('HTTP_USER_AGENT' => 'Googlebot-Image bingbot', 'REQUEST_URI' => '/');
sebotslogs_run();
check(count($wpdb->records('sebotslogs_se')) === 1, 'Multiple matches must not duplicate a visit');

// Include exact day boundaries, older visits and historical records with an empty bot.
$wpdb->reset();
$visit_dates = array('2026-10-10 00:00:00', '2026-10-10 12:00:00', '2026-10-09 00:00:00', '2026-10-09 23:59:59');
foreach (array(-6 => '12:00:00', -7 => '23:59:59', -29 => '12:00:00', -30 => '23:59:59', -89 => '12:00:00', -90 => '23:59:59') as $offset => $time) {
    $visit_dates[] = (new DateTimeImmutable('2026-10-10'))->modify($offset . ' days')->format('Y-m-d') . ' ' . $time;
}
foreach (array_keys($expected_bots) as $name) {
    if ($name === 'Mojeek') { continue; }
    $key = strtolower($name);
    foreach ($visit_dates as $visit_date) {
        $wpdb->insert('test_sebotslogs_se', array('name' => $name, 'bot' => '', 'date' => $visit_date));
    }
    foreach (array('today' => '2026-10-10 00:00:00', 'yesterday' => '2026-10-09 23:59:59', 'too-old' => '2026-10-08 23:59:59') as $period => $visit_date) {
        $wpdb->insert('test_sebotslogs_urls', array('name' => $name, 'bot' => '', 'date' => $visit_date, 'url' => 'example.test/' . $key . '/' . $period . '?q=<script>&b=2'));
    }
}
$_SERVER = array('REQUEST_URI' => '/wp-admin/options-general.php?page=sebotslogs', 'PHP_SELF' => '/wp-admin/options-general.php');
$_POST = array();
ob_start();
sebotslogs_options_page();
$html = ob_get_clean();
check(count($wpdb->read_queries) === 2, 'Fetch all engine statistics and URL logs with two read queries');
preg_match_all('~<tr style="text-align:center;">(.*?)</tr>~s', $html, $rows);
check(count($rows[1]) === 11, 'Render all eleven search engines');
foreach (array_keys($expected_bots) as $index => $name) {
    preg_match_all('~<td>(.*?)</td>~s', $rows[1][$index], $columns);
    $values = array_map(function ($value) {
        return trim(str_replace("\xc2\xa0", ' ', html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8')));
    }, $columns[1]);
    $expected = $name === 'Mojeek' ? array($name, '0', '0', '0', '0', '0', '0', '') : array($name, '2', '2', '5', '7', '9', '10', '2026-10-10 12:00:00');
    check($values === $expected, $name . ': correct counters and last visit, including historical data');
    $key = strtolower($name);
    foreach (array('today' => $key . '_urls', 'yesterday' => $key . '2_urls') as $period => $panel_id) {
        check(preg_match('~<div id="' . $panel_id . '"[^>]*><span>(.*?)</span></div></div>~s', $html, $panel) === 1, $name . ': URL panel exists for ' . $period);
        if ($name === 'Mojeek') {
            check(strpos($panel[1], '<a ') === false, 'Empty engine has no URLs');
        } else {
            check(strpos($panel[1], '/' . $key . '/' . $period) !== false, $name . ': correct URL in ' . $period);
            check(substr_count($panel[1], '<a ') === 1, $name . ': no URLs from other engines or days');
        }
    }
}
check(strpos($html, '/too-old') === false, 'Exclude URL records at the previous-day cutoff');
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Escape requested URLs in the dashboard');
preg_match_all('~id="([^"]+)"~', $html, $ids);
check(count($ids[1]) === 22 && count(array_unique($ids[1])) === 22, 'Each URL panel has a unique ID');

$wpdb->reset();
ob_start();
sebotslogs_options_page();
$empty_html = ob_get_clean();
check(substr_count($empty_html, '<tr style="text-align:center;">') === 11, 'Show every engine when the log is empty');
check(substr_count($empty_html, '&nbsp;0&nbsp;') === 22, 'Show zero daily counters when the log is empty');
echo 'PASS: ' . $checks . ' checks across 20 User-Agent identifiers, URL logging and the dashboard.' . PHP_EOL;
