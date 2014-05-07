<?php
/*
Plugin Name: iCal Feeds
Plugin URI: http://maxime.sh/ical-feeds
Description: Generate a customizable iCal feed of your present and future blog posts.
Author: Maxime VALETTE
Author URI: http://maxime.sh
Version: 1.1
*/

define('ICALFEEDS_TEXTDOMAIN', 'icalfeeds');

if (function_exists('load_plugin_textdomain')) {
	load_plugin_textdomain(ICALFEEDS_TEXTDOMAIN, false, dirname(plugin_basename(__FILE__)).'/languages' );
}

add_action('admin_menu', 'icalfeeds_config_page');

function icalfeeds_config_page() {

	if (function_exists('add_submenu_page')) {

        add_submenu_page('options-general.php',
            __('iCal Feeds', ICALFEEDS_TEXTDOMAIN),
            __('iCal Feeds', ICALFEEDS_TEXTDOMAIN),
            'manage_options', __FILE__, 'icalfeeds_conf');

    }

}

function icalfeeds_conf() {

	$options = get_option('icalfeeds');

    if (!isset($options['icalfeeds_minutes'])) $options['icalfeeds_minutes'] = 60;	
    if (!isset($options['icalfeeds_secret'])) $options['icalfeeds_secret'] = 'changeme';
    if (!isset($options['icalfeeds_senable'])) $options['icalfeeds_senable'] = 0;

	$updated = false;

	if (isset($_POST['submit'])) {

		check_admin_referer('icalfeeds', 'icalfeeds-admin');

        if (isset($_POST['icalfeeds_minutes'])) {
            $icalfeeds_minutes = (int) $_POST['icalfeeds_minutes'];
        } else {
            $icalfeeds_minutes = 60;
        }

        if (isset($_POST['icalfeeds_secret'])) {
            $icalfeeds_secret = $_POST['icalfeeds_secret'];
        } else {
            $icalfeeds_secret = 'changeme';
        }

        if (isset($_POST['icalfeeds_senable'])) {
            $icalfeeds_senable = $_POST['icalfeeds_senable'];
        } else {
            $icalfeeds_senable = 0;
        }

		$options['icalfeeds_minutes'] = $icalfeeds_minutes;
		$options['icalfeeds_secret'] = $icalfeeds_secret;
        $options['icalfeeds_senable'] = $icalfeeds_senable;

		update_option('icalfeeds', $options);

		$updated = true;

	}

    echo '<div class="wrap">';

    if ($updated) {

        echo '<div id="message" class="updated fade"><p>';
        _e('Configuration updated.', ICALFEEDS_TEXTDOMAIN);
        echo '</p></div>';

    }

    $timezone = get_option('timezone_string');

    if (empty($timezone)) {

        echo '<div id="message" class="error"><p>';
        _e('You have to define your current timezone (specify a city) in', ICALFEEDS_TEXTDOMAIN);
        echo ' <a href="options-general.php">'.__('Settings > General', ICALFEEDS_TEXTDOMAIN).'</a>';
        echo ".</p></div>";

    }

    echo '<h2>'.__('iCal Feeds Configuration', ICALFEEDS_TEXTDOMAIN).'</h2>';

    echo '<p>'.__('', ICALFEEDS_TEXTDOMAIN).'</p>';

    echo '<form action="'.admin_url('options-general.php?page=ical-feeds/ical-feeds.php').'" method="post" id="feeds-conf">';

    echo '<h3>'.__('Advanced Options', ICALFEEDS_TEXTDOMAIN).'</h3>';

    echo '<p><input id="icalfeeds_senable" name="icalfeeds_senable" type="checkbox" value="1"';
    if ($options['icalfeeds_senable'] == 1) echo ' checked';
    echo '/> <label for="icalfeeds_senable">'.__('Enable a secret parameter to view future posts.', ICALFEEDS_TEXTDOMAIN).'</label></p>';

    echo '<h3><label for="icalfeeds_secret">'.__('Secret parameter value:', ICALFEEDS_TEXTDOMAIN).'</label></h3>';
    echo '<p><input type="text" id="icalfeeds_secret" name="icalfeeds_secret" value="'.$options['icalfeeds_secret'].'" style="width: 200px;" /></p>';

    echo '<h3><label for="icalfeeds_minutes">'.__('Time interval per post:', ICALFEEDS_TEXTDOMAIN).'</label></h3>';
    echo '<p><input type="text" id="icalfeeds_minutes" name="icalfeeds_minutes" value="'.$options['icalfeeds_minutes'].'" style="width: 50px; text-align: center;" /> '.__('minutes', ICALFEEDS_TEXTDOMAIN).'</p>';

    echo '<p class="submit" style="text-align: left">';
    wp_nonce_field('icalfeeds', 'icalfeeds-admin');
    echo '<input type="submit" name="submit" value="'.__('Save', ICALFEEDS_TEXTDOMAIN).' &raquo;" /></p></form>';

    echo '<h2>'.__('Main iCal feeds', ICALFEEDS_TEXTDOMAIN).'</h2>';

    echo '<p>'.__('You can use the below addresses to add in your iCal software:', ICALFEEDS_TEXTDOMAIN).'</p>';

    echo '<ul>';

    echo '<li><a href="'.site_url().'/?ical" target="_blank">'.site_url().'/?ical</a> — '.__('Public iCal feed', ICALFEEDS_TEXTDOMAIN).'</li>';

    if ($options['icalfeeds_senable'] == '1') {
        echo '<li><a href="'.site_url().'/?ical='.$options['icalfeeds_secret'].'" target="_blank">'.site_url().'/?ical='.$options['icalfeeds_secret'].'</a> — '.__('Private iCal feed', ICALFEEDS_TEXTDOMAIN).'</li>';
    }

    echo '</ul>';

    echo '<h2>'.__('Categories iCal feeds', ICALFEEDS_TEXTDOMAIN).'</h2>';

    echo '<ul>';

    $categories = get_categories();

    foreach ($categories as $category) {

        echo '<li><a href="'.site_url().'/?ical&category='.$category->category_nicename.'" target="_blank">'.site_url().'/?ical&category='.$category->category_nicename.'</a> — '.__('Public iCal feed for', ICALFEEDS_TEXTDOMAIN).' '.$category->cat_name.'</li>';

    }

    echo '</ul>';

    echo '</div>';

}

function icalfeeds_feed() {

    global $wpdb;

    $options = get_option('icalfeeds');
    if (!isset($options['icalfeeds_minutes'])) $options['icalfeeds_minutes'] = 60;	

    if ($_GET['category']) {

        $categories = get_categories();
        $category_id = false;

        foreach ($categories as $category) {

            if ($_GET['category'] == $category->category_nicename) {

                $category_id = $category->cat_ID;
                break;

            }

        }

        if (!$category_id) {

            $category_id = 0;

        }

    }

    if (is_numeric($_GET['limit'])) {

        $limit = 'LIMIT ' . $_GET['limit'];

    }

    if ($_REQUEST['ical'] == $options['icalfeeds_secret']) {

        $postCond = "post_status = 'publish' OR post_status = 'future'";

    } else {

        $postCond = "post_status = 'publish'";

    }

    // Get posts

    if ($_GET['category']) {

        $posts = $wpdb->get_results("SELECT $wpdb->posts.ID, UNIX_TIMESTAMP(post_date) AS post_date, post_title
            FROM $wpdb->posts
            LEFT JOIN $wpdb->post2cat ON ($wpdb->post2cat.post_id = $wpdb->posts.ID)
            WHERE (".$postCond.") AND post_type = 'post' AND $wpdb->post2cat.category_id = $category_id
            ORDER BY post_date DESC $limit");

    } else {

        $posts = $wpdb->get_results("SELECT ID, post_content, UNIX_TIMESTAMP(post_date) AS post_date, post_title
            FROM $wpdb->posts
            WHERE (".$postCond.") AND post_type = 'post'
            ORDER BY post_date DESC $limit");

    }

    $events = null;

    foreach ($posts as $post) {

        $start_time = date('Ymd\THis', $post->post_date);
        $end_time = date('Ymd\THis', $post->post_date + ($options['icalfeeds_minutes'] * 60));
        $summary = $post->post_title;
        $permalink = get_permalink($post->ID);
        $timezone = get_option('timezone_string');

        $events .= <<<EVENT
BEGIN:VEVENT
DTSTART;TZID=$timezone:$start_time
DTEND;TZID=$timezone:$end_time
SUMMARY:$summary
URL;VALUE=URI:$permalink
END:VEVENT

EVENT;

    }

    $blog_name = get_bloginfo('name');
    $blog_url = get_bloginfo('home');

    header('Content-type: text/calendar');
    header('Content-Disposition: attachment; filename="blog_posts.ics"');

    $content = <<<CONTENT
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//$blog_name//NONSGML v1.0//EN
X-WR-CALNAME:{$blog_name}
X-ORIGINAL-URL:{$blog_url}
X-WR-CALDESC:Blog posts from {$blog_name}
CALSCALE:GREGORIAN
METHOD:PUBLISH
{$events}END:VCALENDAR
CONTENT;

    echo $content;

    exit;

}

// Init or not

if (isset($_REQUEST['ical'])) {

    add_action('init', 'icalfeeds_feed');

}