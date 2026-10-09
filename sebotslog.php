<?php
/**
 * @package SE_Robots_log
 * @version 0.1.1
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

$sebotslogs_version = '0.1.1';
$sebotslogs_se_req_date = current_time('mysql');
$sebotslogs_se_name = '';
$sebotslogs_se_bot = '';
$sebotslogs_se_url = '';

define('SEBOTSLOGS_LOGS_LIFETIME_DEFAULT', 90); // default lifetime of log records in DB
define('SEBOTSLOGS_URLS_LIFETIME_DEFAULT', 2); // default lifetime of 'urls' records in DB
define('SEBOTSLOGS_URLS_INTO_DB_DEFAULT', 0); // logging requested URLs is OFF by default
$sebotslogs_urls_into_db = 0;
$day = 86400; // 24*60*60 seconds
$search_engines = [
        'google' => [
            'name' => 'Google',
            'user_agents' => ['Googlebot', 'Googlebot-Image', 'Googlebot-Video', 'Storebot-Google'],
        ],
        'bing' => [
            'name' => 'Bing',
            'user_agents' => ['bingbot', 'MicrosoftPreview', 'BingVideoPreview'],
        ],
        'yahoo' => [
            'name' => 'Yahoo',
            'user_agents' => ['Yahoo! Slurp', 'Slurp'],
        ],
        'duckduckgo' => [
            'name' => 'DuckDuckGo',
            'user_agents' => ['DuckDuckBot'],
        ],
        'apple' => [
            'name' => 'Apple',
            'user_agents' => ['Applebot'],
        ],
        'baidu' => [
            'name' => 'Baidu',
            'user_agents' => ['Baiduspider'],
        ],
        'qwant' => [
            'name' => 'Qwant',
            'user_agents' => ['Qwantbot'],
        ],
        'naver' => [
            'name' => 'Naver',
            'user_agents' => ['Yeti'],
        ],
        'mojeek' => [
            'name' => 'Mojeek',
            'user_agents' => ['MojeekBot'],
        ],
        'chatgpt' => [
            'name' => 'ChatGPT',
            'user_agents' => ['OAI-SearchBot', 'ChatGPT-User'],
		],
    ]; // Search engines and their supported User-Agent identifiers.



function sebotslogs_options_page() {

    global $wpdb;
    global $day;
    global $sebotslogs_version;    
    global $sebotslogs_urls_into_db;
	global $search_engines;

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
    echo '<h3>Plugin for collecting statistics on search engine bot visits to the blog.</h3>';
    echo 'Version: ' . $sebotslogs_version . '<br/>Plugin author <a href="https://sayri.work/" target="_blank">sayri.work</a><br/><br/>';
    echo '<b>Current server date and time: ' . current_time( 'mysql' ) . '</b><br/>';
    echo '<h3 style="margin:1em 0 -2em 0;">Statistics:</h3><br><br>';
	echo '<table cellspacing="0" cellpadding="4" border="1">';
	echo '<tr style="font-weight:bold;text-align:center;background-color:#dddddd;"><td>Search engine</td><td>Today</td><td>Yesterday</td><td>7 days</td><td>30 days</td><td>90 days</td><td>Total</td><td>Last visit</td></tr>';


    $timestamp = current_time('timestamp');
    $today_boundary = date('Y-m-d 23:59:59', $timestamp - $day);
    $yesterday_boundary = date('Y-m-d 23:59:59', $timestamp - 2 * $day);

    // Fetch statistics for all engines in one query.
    $sql = 'SELECT `name`, COUNT(*) AS total_count,
        SUM(`date` > %s) AS today_count,
        SUM(`date` > %s AND `date` <= %s) AS yesterday_count,
        SUM(`date` > %s) AS lastweek_count,
        SUM(`date` > %s) AS lastmonth_count,
        SUM(`date` > %s) AS last3month_count,
        MAX(`date`) AS last_visit
        FROM `' . $wpdb->prefix . 'sebotslogs_se` GROUP BY `name`';
    $statistics = $wpdb->get_results($wpdb->prepare(
        $sql,
        $today_boundary,
        $yesterday_boundary,
        $today_boundary,
        date('Y-m-d 23:59:59', $timestamp - 7 * $day),
        date('Y-m-d 23:59:59', $timestamp - 30 * $day),
        date('Y-m-d 23:59:59', $timestamp - 90 * $day)
    ), OBJECT);
    $engine_statistics = array();
    foreach ((array) $statistics as $statistics_row) {
        $engine_statistics[$statistics_row->name] = $statistics_row;
    }
    $empty_statistics = (object) array(
        'today_count' => 0,
        'yesterday_count' => 0,
        'lastweek_count' => 0,
        'lastmonth_count' => 0,
        'last3month_count' => 0,
        'total_count' => 0,
        'last_visit' => '',
    );

    foreach ($search_engines as $engine_key => $engine) {
        $stats = isset($engine_statistics[$engine['name']]) ? $engine_statistics[$engine['name']] : $empty_statistics;
        echo '<tr style="text-align:center;"><td><b>' . esc_html($engine['name']) . '</b></td>';
        
        foreach (array($engine_key . '_urls' => $stats->today_count, $engine_key . '2_urls' => $stats->yesterday_count) as $panel_id => $count) {
            echo '<td><span style="cursor:pointer;text-decoration:underline;font-weight:bold;" onclick="var panel = document.getElementById(\'' . esc_attr($panel_id) . '\'); panel.style.display = panel.style.display === \'none\' ? \'\' : \'none\';">&nbsp;' . (int) $count . '&nbsp;</span></td>';
        }
        echo '<td>' . (int) $stats->lastweek_count . '</td><td>' .
            (int) $stats->lastmonth_count . '</td><td>' .
            (int) $stats->last3month_count . '</td><td>' .
            (int) $stats->total_count . '</td><td>' .
            esc_html($stats->last_visit) . '</td></tr>';
    }

    echo '</table><br/>';

    echo "Maximum retention period for counter records = " . get_option('sebotslogs_logs_lifetime') . " days<br/><br/>";

	sebotslogs_refresh_options_page();

    // Group the two-day URL log by engine and day.
    $sql = 'SELECT `name`, `date`, `url` FROM `' . $wpdb->prefix . 'sebotslogs_urls` WHERE `date` > %s ORDER BY `id` ASC';
    $sebotslogs_last_urls_requested = $wpdb->get_results($wpdb->prepare($sql, $yesterday_boundary), OBJECT);
    $engine_urls = array();
    foreach ((array) $sebotslogs_last_urls_requested as $urls_requested) {
        $period = $urls_requested->date > $today_boundary ? 'today' : 'yesterday';
        $engine_urls[$urls_requested->name][$period][] = $urls_requested;
    }

    foreach ($search_engines as $engine_key => $engine) {
        foreach (array('today' => $engine_key . '_urls', 'yesterday' => $engine_key . '2_urls') as $period => $panel_id) {
            $close_button = '<span style="color:brown;cursor:pointer;text-decoration:underline;" onclick="document.getElementById(\'' . esc_attr($panel_id) . '\').style.display = \'none\';">[X] Close</span>';
            echo '<div><div id="' . esc_attr($panel_id) . '" style="display:none;padding:10px;margin:0px 20px 20px 0px; border:1px #aaa solid;"><span>';
            echo '<b>' . esc_html($engine['name']) . ' (' . esc_html($period) . '):&nbsp;&nbsp;&nbsp;</b>' . $close_button . '<br/><br/>';
            $urls = isset($engine_urls[$engine['name']][$period]) ? $engine_urls[$engine['name']][$period] : array();
            foreach ($urls as $urls_requested) {
                $url = 'http://' . $urls_requested->url;
                echo esc_html($urls_requested->date) . ' | <a target="_blank" href="' . esc_url($url) . '">' . esc_html($url) . '</a><br/>';
            }
            echo '<br/>' . $close_button . '<br/><br/></span></div></div>';
        }
    }


    echo <<<OPTIONSTOP
	<div>
		<div>
			<input type="button" value="Settings" style="width:120px;font-weight:bold;cursor:pointer;background-color: inherit; border: 1px solid #000;" onclick="if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerText = ''; this.value = 'Hide settings'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerText = ''; this.value = 'Settings'; }"/>
		</div>
		<div>
			<div style="display: none; padding: 10px; margin-right: 20px; border: 1px #aaa solid;">
				<span>
OPTIONSTOP;

    echo "<h3>SE Robots log plugin settings</h3>";

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
			<input type="button" value="Help" style="width:120px;font-weight:bold;cursor:pointer;background-color: inherit; border: 1px solid #000;" onclick="if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerText = ''; this.value = 'Hide help'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerText = ''; this.value = 'Help'; }"/>
		</div>
		<div>
			<div style="display: none; padding: 10px; margin-right: 20px; border: 1px #aaa solid;">
				<span>
					<ol>
						<li>Activating the plugin creates the database tables «yourprefix_sebotslogs_se» and «yourprefix_sebotslogs_urls», used exclusively by the «SE Robots log» plugin.<br /></li>
						<li>Deactivating the plugin leaves the tables in the database and preserves all their data.<br /></li>
						<li>The tables will be removed from the database when you delete the plugin through the WordPress dashboard.<br /></li>
						<li>Removing the plugin directory and its files manually is NOT recommended, because this leaves the tables «yourprefix_sebotslogs_se» and «yourprefix_sebotslogs_urls» in the database.<br /></li>
						<li>Records in the «yourprefix_sebotslogs_urls» table are retained for a maximum of 2 days. Records older than 2 days are deleted automatically.</li>
						<li>To prevent the database from growing too large, records in the «yourprefix_sebotslogs_se» table are retained for a maximum of 90 days by default. Records older than 90 days (the default value) are deleted automatically.</li>
					</ol>
				</span>
			</div>
		</div>
	</div>
HELP1;

    $sql = 'DELETE FROM `' . $wpdb->prefix."sebotslogs_se" . '` WHERE `date` < "' . date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - $day * get_option('sebotslogs_logs_lifetime')) . '"';
    $wpdb->query( $sql );

    unset( $sql );
}

/**
 * Refresh plugin options page
 *
 */
function sebotslogs_refresh_options_page() {

	echo '<div>
				<input type="button" value="Refresh" style="width:120px;font-weight:bold;cursor:pointer;background-color: inherit; border: 1px solid #000;" onclick="window.location.reload();"/><br/><br/>
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
				<td style='text-align:right;'>Maximum retention period for counter records: <br/>(days) </td>
				<td style='width:80px;'><input style='text-align:center;width:80px;' type='text' name='sebotslogs_logs_lifetime' value='" . get_option('sebotslogs_logs_lifetime') . "'/></td>
				<td><i>Counter log records older than the specified number of days are deleted from the database.</i></td>
			</tr>
            <tr>
				<td style='text-align:right;'><br/>Log page URLs: </td>
				<td style='width:80px;text-align:center;'><br/><input type='checkbox' name='sebotslogs_urls_into_db' ";
					if( get_option('sebotslogs_urls_into_db') ) echo ' checked="checked" ';
						echo " />
				</td>
				<td><br/><i>Select this option to save the URLs of pages visited by search engine bots in the database.</i></td>
			</tr>
			<tr>
				<td style='text-align:right;color:blue;'><br/>Delete all URLs: </td>
				<td style='width:80px;text-align:center;'><br/><input type='checkbox' name='sebotslogs_urls_delete_chb' /></td>
				<td style='color:blue;'><br/><i>Select this option to delete all URLs of pages visited by search engine bots from the database.</i></td>
			</tr>
			<tr>
				<td style='text-align:right;color:red;'><br/>Delete all plugin records: </td>
				<td style='width:80px;text-align:center;'><br/><input type='checkbox' name='sebotslogs_reset_chb' /></td>
				<td style='color:red;'><br/><i>Select this option to delete all plugin records from the database.</i></td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td style='text-align:center;'><br/><input style='width:80px;cursor:pointer;' type='submit' name='sebotslogs_options_save_btn' value='Apply' style='width:140px; height:25px' /></td>
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
    global $sebotslogs_se_bot;
    global $sebotslogs_se_url;
	global $search_engines;

    $sebotslogs_se_name = '';
    $sebotslogs_se_bot = '';
    $sebotslogs_se_url = '';

    if (!isset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REQUEST_URI'])) {
        return;
    }

    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    foreach ($search_engines as $engine) {
        $matched_bot = '';
        foreach ($engine['user_agents'] as $bot) {
            // Prefer the specific identifier, e.g. Googlebot-Image over Googlebot.
            if (stripos($user_agent, $bot) !== false && strlen($bot) > strlen($matched_bot)) {
                $matched_bot = $bot;
            }
        }

        if ($matched_bot !== '') {
            $sebotslogs_se_name = $engine['name'];
            $sebotslogs_se_bot = $matched_bot;
            if (get_option('sebotslogs_urls_into_db')) {
                $sebotslogs_se_url = $_SERVER['REQUEST_URI'];
            }
            sebotslogs_add_se_db();
            return;
        }
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
        //$links[] = '<a href="options-general.php?page=sebotslogs" title="SE Robots log settings">' . __('Settings','sebotslogs') . '</a>';
        $links[] = '<a href="options-general.php?page=sebotslogs" title="SE Robots log settings">Settings</a>';
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
        $links[] = '<a href="options-general.php?page=sebotslogs" title="SE Robots log settings">Settings</a>';
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
    add_dashboard_page('SE Robots log statistics', 'SE Robots log', 'administrator', 'sebotslogs-dashboard', 'sebotslogs_options_page');
}

/**
 * Add <SE Robots log> into the admin pages
 *
 */
function sebotslogs_add_admin_pages() {
    add_options_page( 'SE Robots log statistics', 'SE Robots log', 'administrator', 'sebotslogs', 'sebotslogs_options_page' );
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
