<?php
/**
 * @package SE_Robots_log
 * @version 0.0.1
 */

/*
Plugin Name: SE Robots log
Version: 0.0.1
Plugin URI: https://sayri.work/
Description: SE Robots Log - a plugin for collecting statistics on search engine bot visits to the site.
Author: SAYri
Author URI: https://sayri.work/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Copyright 2026 sayri.work
*/

/*
Copyright 2026 sayri.work (email: sayri.ua@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$sebotslogs_version = '0.0.1';
$sebotslogs_se_req_date = current_time('mysql');
$sebotslogs_se_name = '';
$sebotslogs_se_bot = '';
$sebotslogs_se_url = '';

define('SEBOTSLOGS_LOGS_LIFETIME_DEFAULT', 90); // default lifetime of log records in DB
define('SEBOTSLOGS_URLS_LIFETIME_DEFAULT', 2); // default lifetime of 'urls' records in DB
define('SEBOTSLOGS_URLS_INTO_DB_DEFAULT', 0); // logging requested URLs is OFF by default
$sebotslogs_urls_into_db = 0;
$day = 86400; // 24*60*60 seconds

function sebotslogs_options_page() {

    global $wpdb;
    global $day;
    global $sebotslogs_version;    
    global $sebotslogs_urls_into_db;

    // If Options Form sent data, then apply form changes
    if (isset($_POST['sebotslogs_options_save_btn'])) {
        if( function_exists('current_user_can') && !current_user_can('manage_options') )
            die( 'Who are You?' );

        if( function_exists ('check_admin_referer') ){
            check_admin_referer('sebotslogs_options_form_9156155873');
        }

        $sebotslogs_logs_lifetime = $_POST['sebotslogs_logs_lifetime'];

        if( $_POST['sebotslogs_urls_into_db'] == 'on' ) {
            $sebotslogs_urls_into_db = 1;
        }else{
            $sebotslogs_urls_into_db = 0;
        }

        update_option('sebotslogs_logs_lifetime', $sebotslogs_logs_lifetime);
        update_option('sebotslogs_urls_into_db', $sebotslogs_urls_into_db);

		// Truncate ALL table(s)
		if( $_POST['sebotslogs_reset_chb'] == 'on' ) {
            sebotslogs_delete_records_db();
        } else {
			// Truncate table `sebotslogs_urls` only
			if( $_POST['sebotslogs_urls_delete_chb'] == 'on' ) {
				sebotslogs_urls_delete_db();
			}
		}

    }

    echo '<div class="wrap">';
    echo '<h2>SE Robots log</h2>';
    echo '<h3>Плагин сбора статистики посещений блога роботами поисковых систем <a href="http://www.yandex.ru" target="_blank">Yandex</a>, <a href="http://www.google.com" target="_blank">Google</a>, <a href="http://mail.ru" target="_blank">Mail.ru</a></h3>';
    echo 'Версия: ' . $sebotslogs_version . '<br/>Автор плагина <a href="http://4remind.ru" target="_blank">4remind.ru</a><br/><br/>';
    echo '<b>Текущая дата и время сервера: ' . current_time( 'mysql' ) . '</b><br/>';
    echo '<h3 style="margin:1em 0 -2em 0;">Статистика:</h3>';
	echo '<table cellspacing="0" cellpadding="4" border="1">';
	echo '<tr style="font-weight:bold;text-align:center;background-color:#dddddd;"><td>ПС</td><td>Сегодня</td><td>Вчера</td><td>7 дней</td><td>30 дней</td><td>90 дней</td><td>Всего</td><td>Последнее посещение</td></tr>';	

	$sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Yandex"';
    $se_total_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Yandex" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
    $se_today_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Yandex" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 2*$day ) . '" AND `date` <= "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
    $se_yesterday_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Yandex" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 7*$day) . '"';
    $se_lastweek_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Yandex" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 30*$day) . '"';
    $se_lastmonth_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Yandex" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 90*$day) . '"';
    $se_last3month_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT `date` FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Yandex" ORDER BY `id` DESC LIMIT 1';
    $se_last_datatime_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
	
    echo '<tr style="text-align:center;"><td><b><a href="http://webmaster.yandex.ru/sites/" target="_blank">Yandex</a></b></td><td>' .
			'<span style="cursor:pointer;text-decoration:underline;font-weight:bold;" onclick="if (document.getElementById(\'yandex_urls\').style.display != \'\') { document.getElementById(\'yandex_urls\').style.display = \'\';} else { document.getElementById(\'yandex_urls\').style.display = \'none\';}" >&nbsp;' .
			$se_today_count . '&nbsp;</span></td><td>' .
			'<span style="cursor:pointer;text-decoration:underline;font-weight:bold;" onclick="if (document.getElementById(\'yandex2_urls\').style.display != \'\') { document.getElementById(\'yandex2_urls\').style.display = \'\';} else { document.getElementById(\'yandex2_urls\').style.display = \'none\';}" >&nbsp;' .
			$se_yesterday_count . '&nbsp;</span></td><td>' .
			$se_lastweek_count . '</td><td>' .
			$se_lastmonth_count . '</td><td>' .
			$se_last3month_count . '</td><td>' .
			$se_total_count . '</td><td>' .
			$se_last_datatime_count . '</td></tr><br/>';

    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Google"';
    $se_total_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Google" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
    $se_today_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Google" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 2*$day ) . '" AND `date` <= "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
    $se_yesterday_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Google" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 7*$day ) . '"';
    $se_lastweek_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Google" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 30*$day ) . '"';
    $se_lastmonth_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Google" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 90*$day ) . '"';
    $se_last3month_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT `date` FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Google" ORDER BY `id` DESC LIMIT 1';
    $se_last_datatime_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));

    echo '<tr style="text-align:center;"><td><b><a href="https://www.google.com/webmasters/tools/" target="_blank">Google</a></b></td><td>' .
			'<span style="cursor:pointer;text-decoration:underline;font-weight:bold;" onclick="if (document.getElementById(\'google_urls\').style.display != \'\') { document.getElementById(\'google_urls\').style.display = \'\';} else { document.getElementById(\'google_urls\').style.display = \'none\';}" >&nbsp;' .
			$se_today_count . '&nbsp;</span></td><td>' .
			'<span style="cursor:pointer;text-decoration:underline;font-weight:bold;" onclick="if (document.getElementById(\'google2_urls\').style.display != \'\') { document.getElementById(\'google2_urls\').style.display = \'\';} else { document.getElementById(\'google2_urls\').style.display = \'none\';}" >&nbsp;' .
			$se_yesterday_count . '&nbsp;</span></td><td>' .
			$se_lastweek_count . '</td><td>' .
			$se_lastmonth_count . '</td><td>' .
			$se_last3month_count . '</td><td>' .
			$se_total_count . '</td><td>' .
			$se_last_datatime_count . '</td></tr><br/>';

    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Mail.ru"';
    $se_total_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Mail.ru" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
    $se_today_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Mail.ru" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 2*$day ) . '" AND `date` <= "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
    $se_yesterday_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Mail.ru" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 7*$day ) . '"';
    $se_lastweek_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Mail.ru" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 30*$day ) . '"';
    $se_lastmonth_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Mail.ru" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 90*$day ) . '"';
    $se_last3month_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));
    $sql = 'SELECT `date` FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `name`="Mail.ru" ORDER BY `id` DESC LIMIT 1';
    $se_last_datatime_count = $wpdb->get_var( $wpdb->prepare( $sql, null ));

	echo '<tr style="text-align:center;"><td><b><a href="http://webmaster.mail.ru/" target="_blank">Mail.ru</a></b></td><td>' .
			'<span style="cursor:pointer;text-decoration:underline;font-weight:bold;" onclick="if (document.getElementById(\'mailru_urls\').style.display != \'\') { document.getElementById(\'mailru_urls\').style.display = \'\';} else { document.getElementById(\'mailru_urls\').style.display = \'none\';}" >&nbsp;' .
			$se_today_count . '&nbsp;</span></td><td>' .
			'<span style="cursor:pointer;text-decoration:underline;font-weight:bold;" onclick="if (document.getElementById(\'mailru2_urls\').style.display != \'\') { document.getElementById(\'mailru2_urls\').style.display = \'\';} else { document.getElementById(\'mailru2_urls\').style.display = \'none\';}" >&nbsp;' .
			$se_yesterday_count . '&nbsp;</span></td><td>' .
			$se_lastweek_count . '</td><td>' .
			$se_lastmonth_count . '</td><td>' .
			$se_last3month_count . '</td><td>' .
			$se_total_count . '</td><td>' .
			$se_last_datatime_count . '</td></tr><br/>';

    echo '</table><br/>';

    echo "Максимальный срок давности записей счетчиков = " . get_option('sebotslogs_logs_lifetime') . " дней<br/><br/>";

    // shows table status
	/*
	$sql = "SHOW TABLE STATUS LIKE '" . $wpdb->prefix."sebotslogs_urls" . "'";
	$table_state = $wpdb->get_results( $wpdb->prepare( $sql, null ), OBJECT );
	foreach ( $table_state as $tbl_state ) {
		$table_length = ($tbl_state->Data_length + $tbl_state->Index_length)/1024;
		echo 'Размер таблицы в БД со ссылками запросов от поисковых ботов ~ ' . round( $table_length, 0) . ' Кб ' . '<br /><br />';
	}
    echo '<br />';
	*/

	sebotslogs_refresh_options_page();

	// Yandex URLs from Today
	$sql = 'SELECT * FROM `' . $wpdb->prefix."sebotslogs_urls" . '` WHERE `name`="Yandex" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
	$sebotslogs_last_urls_requested = $wpdb->get_results( $wpdb->prepare( $sql, null ), OBJECT );

	echo '<div><div id="yandex_urls" style="display:none;padding:10px;margin:0px 20px 20px 0px; border:1px #aaa solid;"><span>';
	echo '<b>Yandex (сегодня):&nbsp;&nbsp;&nbsp;</b><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'yandex_urls\').style.display != \'\') { document.getElementById(\'yandex_urls\').style.display = \'\';} else { document.getElementById(\'yandex_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/><br/>';
		foreach ( $sebotslogs_last_urls_requested as $urls_requested ) {
			if ( $urls_requested->name == "Yandex" ) {
				echo $urls_requested->date . ' |  <a target="_blank" href="http://' . $urls_requested->url . '">http://' . $urls_requested->url . '</a><br/>';
			}
		}
	echo '<br/><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'yandex_urls\').style.display != \'\') { document.getElementById(\'yandex_urls\').style.display = \'\';} else { document.getElementById(\'yandex_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/>';
	echo "<br/></span></div></div>";

	// Yandex URLs from Yesterday
	$sql = 'SELECT * FROM `' . $wpdb->prefix."sebotslogs_urls" . '` WHERE `name`="Yandex" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 2*$day ) . '" AND `date` <= "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
	$sebotslogs_last_urls_requested = $wpdb->get_results( $wpdb->prepare( $sql, null ), OBJECT );

	echo '<div><div id="yandex2_urls" style="display:none;padding:10px;margin:0px 20px 20px 0px; border:1px #aaa solid;"><span>';
	echo '<b>Yandex (вчера):&nbsp;&nbsp;&nbsp;</b><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'yandex2_urls\').style.display != \'\') { document.getElementById(\'yandex2_urls\').style.display = \'\';} else { document.getElementById(\'yandex2_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/><br/>';
		foreach ( $sebotslogs_last_urls_requested as $urls_requested ) {
			if ( $urls_requested->name == "Yandex" ) {
				echo $urls_requested->date . ' |  <a target="_blank" href="http://' . $urls_requested->url . '">http://' . $urls_requested->url . '</a><br/>';
			}
		}
	echo '<br/><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'yandex2_urls\').style.display != \'\') { document.getElementById(\'yandex2_urls\').style.display = \'\';} else { document.getElementById(\'yandex2_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/>';
	echo "<br/></span></div></div>";

	// Google URLs from Today
	$sql = 'SELECT * FROM `' . $wpdb->prefix."sebotslogs_urls" . '` WHERE `name`="Google" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
	$sebotslogs_last_urls_requested = $wpdb->get_results( $wpdb->prepare( $sql, null ), OBJECT );

	echo '<div><div id="google_urls" style="display:none;padding:10px;margin:0px 20px 20px 0px; border:1px #aaa solid;"><span>';
	echo '<b>Google (сегодня):&nbsp;&nbsp;&nbsp;</b><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'google_urls\').style.display != \'\') { document.getElementById(\'google_urls\').style.display = \'\';} else { document.getElementById(\'google_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/><br/>';
		foreach ( $sebotslogs_last_urls_requested as $urls_requested ) {
			if ( $urls_requested->name == "Google" ) {
				echo $urls_requested->date . ' |  <a target="_blank" href="http://' . $urls_requested->url . '">http://' . $urls_requested->url . '</a><br/>';
			}
		}
	echo '<br/><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'google_urls\').style.display != \'\') { document.getElementById(\'google_urls\').style.display = \'\';} else { document.getElementById(\'google_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/>';
	echo "<br/></span></div></div>";

	// Google URLs from Yesterday
	$sql = 'SELECT * FROM `' . $wpdb->prefix."sebotslogs_urls" . '` WHERE `name`="Google" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 2*$day ) . '" AND `date` <= "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
	$sebotslogs_last_urls_requested = $wpdb->get_results( $wpdb->prepare( $sql, null ), OBJECT );

	echo '<div><div id="google2_urls" style="display:none;padding:10px;margin:0px 20px 20px 0px; border:1px #aaa solid;"><span>';
	echo '<b>Google (вчера):&nbsp;&nbsp;&nbsp;</b><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'google2_urls\').style.display != \'\') { document.getElementById(\'google2_urls\').style.display = \'\';} else { document.getElementById(\'google2_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/><br/>';
		foreach ( $sebotslogs_last_urls_requested as $urls_requested ) {
			if ( $urls_requested->name == "Google" ) {
				echo $urls_requested->date . ' |  <a target="_blank" href="http://' . $urls_requested->url . '">http://' . $urls_requested->url . '</a><br/>';
			}
		}
	echo '<br/><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'google2_urls\').style.display != \'\') { document.getElementById(\'google2_urls\').style.display = \'\';} else { document.getElementById(\'google2_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/>';
	echo "<br/></span></div></div>";

	// Mail.ru URLs from Today
	$sql = 'SELECT * FROM `' . $wpdb->prefix."sebotslogs_urls" . '` WHERE `name`="Mail.ru" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
	$sebotslogs_last_urls_requested = $wpdb->get_results( $wpdb->prepare( $sql, null ), OBJECT );

	echo '<div><div id="mailru_urls" style="display:none;padding:10px;margin:0px 20px 20px 0px; border:1px #aaa solid;"><span>';
	echo '<b>Mail.ru (сегодня):&nbsp;&nbsp;&nbsp;</b><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'mailru_urls\').style.display != \'\') { document.getElementById(\'mailru_urls\').style.display = \'\';} else { document.getElementById(\'mailru_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/><br/>';
		foreach ( $sebotslogs_last_urls_requested as $urls_requested ) {
			if ( $urls_requested->name == "Mail.ru" ) {
				echo $urls_requested->date . ' |  <a target="_blank" href="http://' . $urls_requested->url . '">http://' . $urls_requested->url . '</a><br/>';
			}
		}
	echo '<br/><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'mailru_urls\').style.display != \'\') { document.getElementById(\'mailru_urls\').style.display = \'\';} else { document.getElementById(\'mailru_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/>';
	echo "<br/></span></div></div>";

	// Mail.ru URLs from Yesterday
	$sql = 'SELECT * FROM `' . $wpdb->prefix."sebotslogs_urls" . '` WHERE `name`="Mail.ru" AND `date` > "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 2*$day ) . '" AND `date` <= "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day ) . '"';
	$sebotslogs_last_urls_requested = $wpdb->get_results( $wpdb->prepare( $sql, null ), OBJECT );

	echo '<div><div id="mailru2_urls" style="display:none;padding:10px;margin:0px 20px 20px 0px; border:1px #aaa solid;"><span>';
	echo '<b>Mail.ru (вчера):&nbsp;&nbsp;&nbsp;</b><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'mailru2_urls\').style.display != \'\') { document.getElementById(\'mailru2_urls\').style.display = \'\';} else { document.getElementById(\'mailru2_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/><br/>';
		foreach ( $sebotslogs_last_urls_requested as $urls_requested ) {
			if ( $urls_requested->name == "Mail.ru" ) {
				echo $urls_requested->date . ' |  <a target="_blank" href="http://' . $urls_requested->url . '">http://' . $urls_requested->url . '</a><br/>';
			}
		}
	echo '<br/><span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="if (document.getElementById(\'mailru2_urls\').style.display != \'\') { document.getElementById(\'mailru2_urls\').style.display = \'\';} else { document.getElementById(\'mailru2_urls\').style.display = \'none\';}" >[X] Закрыть</span><br/>';
	echo "<br/></span></div></div>";

    echo <<<OPTIONSTOP
	<div>
		<div>
			<input type="button" value="Настройки" style="width:120px;font-weight:bold;cursor:pointer;background-color: inherit; border: 1px solid #000;" onclick="if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerText = ''; this.value = 'Скрыть настройки'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerText = ''; this.value = 'Настройки'; }"/>
		</div>
		<div>
			<div style="display: none; padding: 10px; margin-right: 20px; border: 1px #aaa solid;">
				<span>
OPTIONSTOP;

    echo "<h3>Настройки плагина SE Robots log</h3>";

// Save Options
    sebotslogs_save_options_form();

    echo <<<OPTIONSBOTTOM
				</span>
			</div>
		</div>
	</div><br />
OPTIONSBOTTOM;

    echo <<<HELP1
	<div>
		<div>
			<input type="button" value="Справка" style="width:120px;font-weight:bold;cursor:pointer;background-color: inherit; border: 1px solid #000;" onclick="if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerText = ''; this.value = 'Скрыть справку'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerText = ''; this.value = 'Справка'; }"/>
		</div>
		<div>
			<div style="display: none; padding: 10px; margin-right: 20px; border: 1px #aaa solid;">
				<span>
					<ol>
						<li>При активации плагина в базе данных создаются таблицы «вашпрефикс_sebotslogs_se» и «вашпрефикс_sebotslogs_urls», и используется они только для работы плагина «SE Robots log».<br /></li>
						<li>При деактивации плагина созданные таблицы не удаляются из базы данных, и все данные в них сохранятся.<br /></li>
						<li>Таблицы будут удалены из базы данных, если Вы удалите плагин соответствующей командой из консоли WordPress.<br /></li>
						<li>НЕ рекомендуется удалять плагин вручную, т.е. методом ручного удаления каталога плагина и его файлов, так как в этом случае в базе данных останутся таблицы «вашпрефикс_sebotslogs_se» и «вашпрефикс_sebotslogs_urls».<br /></li>
						<li>Записи в таблице «вашпрефикс_sebotslogs_urls» хранятся максимум 2 дня, т.е. все записи, срок давности которых больше 2 дней, удаляются автоматически.</li>
						<li>Во избежание переполнения Базы Данных записи в таблице «вашпрефикс_sebotslogs_se» хранятся максимум 90 дней (по умолчанию), т.е. все записи, срок давности которых больше 90 дней (значение по умолчанию), удаляются автоматически.</li>
					</ol>
				</span>
			</div>
		</div>
	</div>
HELP1;

    $sql = 'DELETE FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `date` < "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day * get_option('sebotslogs_logs_lifetime')) . '"';
    $wpdb->query( $sql );

    unset( $sql, $se_today_count, $se_yesterday_count, $se_lastweek_count, $se_lastmonth_count, $se_total_count );
}

/**
 * Refresh plugin options page
 *
 */
function sebotslogs_refresh_options_page() {

	echo '<div>
				<input type="button" value="Обновить" style="width:120px;font-weight:bold;cursor:pointer;background-color: inherit; border: 1px solid #000;" onclick="window.location.reload();"/><br/><br/>
			</div>';

}

/**
 * Settings Form
 *
 */
function sebotslogs_save_options_form() {

    // Options Form
    if (strpos($_SERVER['REQUEST_URI'], 'sebotslogs-dashboard')) {
        echo "<form name='sebotslogs_options' method='post' action='" . $_SERVER['PHP_SELF'] . "?page=sebotslogs-dashboard&amp;updated=true'>";
    }else{
        echo "<form name='sebotslogs_options' method='post' action='" . $_SERVER['PHP_SELF'] . "?page=sebotslogs&amp;updated=true'>";
    }

    if (function_exists ('wp_nonce_field') ) {
        wp_nonce_field('sebotslogs_options_form_9156155873');
    }

    echo "<table>
			<tr>
				<td style='text-align:right;'>Максимальный срок давности записей счетчиков: <br/>(дней) </td>
				<td style='width:80px;'><input style='text-align:center;width:80px;' type='text' name='sebotslogs_logs_lifetime' value='" . get_option('sebotslogs_logs_lifetime') . "'/></td>
				<td><i>Записи логов счетчиков, давность которых превышает указанное количество дней, удаляются из Базы Данных.</i></td>
			</tr>
            <tr>
				<td style='text-align:right;'><br/>Логировать ссылки страниц: </td>
				<td style='width:80px;text-align:center;'><br/><input type='checkbox' name='sebotslogs_urls_into_db' ";
					if( get_option('sebotslogs_urls_into_db') ) echo ' checked="checked" ';
						echo " />
				</td>
				<td><br/><i>Отметьте, если хотите сохранять ссылки посещенных поисковыми ботами страниц в Базе Данных.</i></td>
			</tr>
			<tr>
				<td style='text-align:right;color:blue;'><br/>Удалить все ссылки: </td>
				<td style='width:80px;text-align:center;'><br/><input type='checkbox' name='sebotslogs_urls_delete_chb' /></td>
				<td style='color:blue;'><br/><i>Отметьте, если хотите удалить все ссылки посещенных поисковыми ботами страниц из Базы Данных.</i></td>
			</tr>
			<tr>
				<td style='text-align:right;color:red;'><br/>Удалить все записи плагина: </td>
				<td style='width:80px;text-align:center;'><br/><input type='checkbox' name='sebotslogs_reset_chb' /></td>
				<td style='color:red;'><br/><i>Отметьте, если хотите удалить все записи плагина из Базы Данных.</i></td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td style='text-align:center;'><br/><input style='width:80px;cursor:pointer;' type='submit' name='sebotslogs_options_save_btn' value='Применить' style='width:140px; height:25px' /></td>
				<td>&nbsp;</td>
			</tr>
		</table>";
    echo "</form></div>";
}

/**
 * Delete URLs from DB table `sebotslogs_urls`
 *
 */
function sebotslogs_urls_delete_db() {

    global $wpdb;

    $sql = 'TRUNCATE TABLE `' . $wpdb->prefix."sebotslogs_urls" . '`';
    $wpdb->query( $sql );

}

/**
 * Deletes all logs records from DB.
 *
 */
function sebotslogs_delete_records_db() {

    global $wpdb;

    $sql = 'TRUNCATE TABLE `' . $wpdb->prefix."sebotslogs_se" . '`';
    $wpdb->query( $sql );

    $sql = 'TRUNCATE TABLE `' . $wpdb->prefix."sebotslogs_urls" . '`';
    $wpdb->query( $sql );

}

/**
 * Add log record(s) into DB.
 *
 */
function sebotslogs_add_se_db() {

    global $sebotslogs_se_req_date;
    global $sebotslogs_se_name;
    global $sebotslogs_se_bot;
    global $sebotslogs_se_url;
    global $sebotslogs_urls_into_db;
    global $wpdb;
    global $day;

    $table_se = $wpdb->prefix."sebotslogs_se";
    $table_urls = $wpdb->prefix."sebotslogs_urls";

    $sebotslogs_se_req_date = current_time('mysql');

    $wpdb->insert(
        $table_se,
        array( 'name' => $sebotslogs_se_name, 'bot' => $sebotslogs_se_bot, 'date' => $sebotslogs_se_req_date ),
        array( '%s', '%s', '%s' )
    );

    if( get_option('sebotslogs_urls_into_db') ){
        $sebotslogs_se_url = $_SERVER['SERVER_NAME'] . $sebotslogs_se_url;

		// delete all records from 'sebotslogs_urls' where date older than 2 days (SEBOTSLOGS_URLS_LIFETIME_DEFAULT = 2)
		$sql_del_old_urls = 'DELETE FROM `' . $table_urls . '` WHERE `date` < "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day*SEBOTSLOGS_URLS_LIFETIME_DEFAULT) . '"';
		$wpdb->query( $sql_del_old_urls );
		unset( $sql_del_old_urls );

		// insert a new URL into DB
        $wpdb->insert(
            $table_urls,
            array( 'name' => $sebotslogs_se_name, 'bot' => $sebotslogs_se_bot, 'date' => $sebotslogs_se_req_date, 'url' => $sebotslogs_se_url ),
            array( '%s', '%s', '%s', '%s' )
        );

    }

	// random deletion and optimizing DB tables
    $randomval = mt_rand( 0, 10 );
    if( $randomval > 7 ) {
        $sql_rand = 'DELETE FROM `' . $table_se . '` WHERE `date` < "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day*get_option('sebotslogs_logs_lifetime')) . '"';
        $wpdb->query( $sql_rand );
		$sql_rand = 'OPTIMIZE TABLE $table_se, $table_urls';
		$wpdb->query( $sql_rand );
        unset( $sql_rand );
    }

    unset( $randomval );
}

/**
 * Check if an incoming request received from any supported SE-bot, and if so, then call sebotslogs_add_se_db();
 *
 */
function sebotslogs_check_se_robots() {
    
    global $sebotslogs_se_name; 
    global $sebotslogs_se_url; 
    global $sebotslogs_urls_into_db; 
	$is_bot = FALSE;
	
    if( isset( $_SERVER['HTTP_USER_AGENT'] ) && isset( $_SERVER['REQUEST_URI'] )) {
        
		$s_useragent = $_SERVER['HTTP_USER_AGENT'];

        if(( stripos( $s_useragent, " yandex" )) && 
		( stripos( $s_useragent, " +http://yandex.com/bots" )) ) { 

            $is_bot = TRUE;
            $sebotslogs_se_name = "Yandex";
			
        } elseif ( stripos( $s_useragent, " googlebot" )) {

            $is_bot = TRUE;
            $sebotslogs_se_name = "Google";

        } elseif ( stripos( $s_useragent, " Mail.RU_Bot" ) && stripos( $s_useragent, " +http://go.mail.ru/help/robots" )) {

            $is_bot = TRUE;
            $sebotslogs_se_name = "Mail.ru";

        }

        if( $is_bot ) {

            if( get_option('sebotslogs_urls_into_db') )
                $sebotslogs_se_url = $_SERVER['REQUEST_URI'];

            sebotslogs_add_se_db();

        }

        unset( $is_bot, $s_useragent );
    }
}

/**
 * Run SE Robots log
 *
 */
function sebotslogs_run() {
    sebotslogs_check_se_robots();
}

/**
 * Adds an additional links (plugin_row_meta) on the plugins meta page.
 * Note: This add_filter function must be in this file or it does not work correctly, requires plugin_basename and file match
 *
 * @param mixed $links
 * @param mixed $file
 */
function sebotslogs_register_meta_links($links, $file) {
    $base = plugin_basename(__FILE__);

    if ($file == $base) {
        //$links[] = '<a href="options-general.php?page=sebotslogs" title="Настройки SE Robots log">' . __('Settings','sebotslogs') . '</a>';
        $links[] = '<a href="options-general.php?page=sebotslogs" title="Настройки SE Robots log">Настройки</a>';
    }
    return $links;
}

/**
 * Adds an additional links (plugin_row_meta) on the plugins action page.
 * Note: This add_filter function must be in this file or it does not work correctly, requires plugin_basename and file match
 *
 * @param mixed $links
 * @param mixed $file
 */
function sebotslogs_register_action_links($links, $file) {
    $base = plugin_basename(__FILE__);

    if ($file == $base) {
        $links[] = '<a href="options-general.php?page=sebotslogs" title="Настройки SE Robots log">Настройки</a>';
    }
    return $links;
}

/**
 * This plugin activation
 * Note: creating plugin's DB tables if they are NOT exist
 *
 */
function sebotslogs_activation() {

    global $wpdb;

    $table_se = $wpdb->prefix."sebotslogs_se";
    $table_urls = $wpdb->prefix."sebotslogs_urls";

    // Bot's ids table
    $sql = "CREATE TABLE IF NOT EXISTS `".$table_se."` (
			`id` BIGINT(14) UNSIGNED NOT NULL AUTO_INCREMENT,
			`name` varchar(64) NOT NULL,
			`bot` varchar(64) NOT NULL,
			`date` datetime NOT NULL,
			PRIMARY KEY (`id`)
		) DEFAULT CHARSET=utf8;";

    $wpdb->query( $sql );

    // Bot's URL requests table
    $sql = "CREATE TABLE IF NOT EXISTS `".$table_urls."` (
			`id` BIGINT(14) UNSIGNED NOT NULL AUTO_INCREMENT,
			`name` varchar(64) NOT NULL,
			`bot` varchar(64) NOT NULL,
			`date` datetime NOT NULL,
			`url` text NOT NULL,
			PRIMARY KEY (`id`)
		) DEFAULT CHARSET=utf8;";

    $wpdb->query( $sql );
	
	// Add Default Options    
	add_option('sebotslogs_logs_lifetime', SEBOTSLOGS_LOGS_LIFETIME_DEFAULT); // lifetime of log's records in DB (in days)
    add_option('sebotslogs_urls_into_db', SEBOTSLOGS_URLS_INTO_DB_DEFAULT); // save URLs of requested pages into DB
}

/**
 * This plugin deactivation
 *
 */
function sebotslogs_deactivation() {
	// to do...
}

/**
 * Remove this plugin's options & DB tables on plugin uninstall
 *
 */
function sebotslogs_uninstall() {

    global $wpdb;

    // delete plugin's tables from DB on uninstall
    $table_se = $wpdb->prefix."sebotslogs_se";
    $table_urls = $wpdb->prefix."sebotslogs_urls";

    $sql = "DROP TABLE `" . $table_se . "`;";
    $wpdb->query( $sql );

    $sql = "DROP TABLE `" . $table_urls . "`;";
    $wpdb->query( $sql );

    delete_option('sebotslogs_logs_lifetime'); // delete option 'sebotslogs_logs_lifetime' on plugin uninstall
    delete_option('sebotslogs_urls_into_db'); // delete option 'sebotslogs_urls_into_db' on plugin uninstall

}

/**
 * Registration hooks for this plugin
 *
 */
register_activation_hook( __FILE__, 'sebotslogs_activation' );
register_deactivation_hook( __FILE__, 'sebotslogs_deactivation' );
register_uninstall_hook( __FILE__, 'sebotslogs_uninstall' );

/**
 * Add <SE Robots log> into the dashboard menu
 *
 */
function sebotslogs_add_dashboard_menu() {
    add_dashboard_page('Статистика SE Robots log', 'SE Robots log', 'administrator', 'sebotslogs-dashboard', 'sebotslogs_options_page');
}

/**
 * Add <SE Robots log> into the admin pages
 *
 */
function sebotslogs_add_admin_pages() {
    add_options_page( 'Статистика SE Robots log', 'SE Robots log', 'administrator', 'sebotslogs', 'sebotslogs_options_page' );
}

/**
 * Add this plugin's Filters(s) & Actions(s)
 *
 */
add_filter( 'plugin_row_meta','sebotslogs_register_meta_links',10,2); // adds "Settings" link to the plugin row meta page
add_filter( 'plugin_action_links', 'sebotslogs_register_action_links',10,2); // adds "Settings" link to the plugin action page
add_action( 'admin_menu', 'sebotslogs_add_dashboard_menu' );
add_action( 'admin_menu', 'sebotslogs_add_admin_pages' );
add_action( 'init', 'sebotslogs_run' );

?>