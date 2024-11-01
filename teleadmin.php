<?php

/*
Plugin Name: TeleAdmin
Plugin URI: https://wordpress.org/plugins/teleadmin/
Description: Administrate your WordPress website on the way using Telegram
Version: 1.0.0
Requires at least: 4.0
Requires PHP: 7.0
Author: Marian Dietz
Author URI: https://profiles.wordpress.org/mariandz/
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
Text Domain: teleadmin
*/

namespace TeleAdmin;

require_once dirname( __FILE__ ) . '/vendor/autoload.php';

use TeleAdmin\Bot\CommentListener;
use TeleAdmin\Bot\OrderListener;
use TeleAdmin\Bot\PostListener;
use TeleAdmin\Bot\UserRegisterListener;
use TeleAdmin\Page\BotsPage;
use TeleAdmin\Page\ChatsPage;

function teleadmin_activation() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $charset_collate = $wpdb->get_charset_collate();

    dbDelta( "CREATE TABLE teleadmin_bots (
			bot_id int(11) AUTO_INCREMENT,
			token tinytext NOT NULL,
			username varchar(64) NOT NULL,
			webhook boolean DEFAULT 0 NOT NULL,
			invalid boolean DEFAULT 0 NOT NULL,
			last_update_id int(11) DEFAULT 0 NOT NULL,
			last_poll datetime DEFAULT '2000-01-01 00:00:00' NOT NULL,
			poll_nonce varchar(128),
			PRIMARY KEY  (bot_id)
		) $charset_collate;" );
    dbDelta( "CREATE TABLE teleadmin_chats (
			chat_id int(11) AUTO_INCREMENT,
			bot_id int(11) NOT NULL,
			telegram_id bigint(20) NOT NULL,
			type text NOT NULL,
			title text,
			username text,
			confirmed boolean,
			PRIMARY KEY  (chat_id)
		) $charset_collate" );
    dbDelta( "CREATE TABLE teleadmin_messages (
	  		message_id int(11) AUTO_INCREMENT,
	  		chat_id int(11) NOT NULL,
	  		telegram_id bigint(20) NOT NULL,
	  		type varchar(16) NOT NULL,
	  		object_id int(11) NOT NULL,
	  		is_paging boolean NOT NULL,
	  		PRIMARY KEY  (message_id)
	) $charset_collate" );

    update_option( 'teleadmin_version', '1.0' );
}

function teleadmin_uninstall() {
    global $wpdb;
    $wpdb->query( "DROP TABLE IF EXISTS teleadmin_bots;" );
    $wpdb->query( "DROP TABLE IF EXISTS teleadmin_chats;" );
    $wpdb->query( "DROP TABLE IF EXISTS teleadmin_messages;" );

    delete_option( 'teleadmin_version' );
    delete_option( 'edit_chats_per_page' );
}

register_activation_hook( __FILE__, '\TeleAdmin\teleadmin_activation' );
register_uninstall_hook( __FILE__, '\TeleAdmin\teleadmin_uninstall' );

function teleadmin_enqueue_scripts() {
    wp_register_style( 'teleadmin_style', plugins_url( 'css/style.css', __FILE__ ) );
    wp_enqueue_style( 'teleadmin_style' );

    wp_register_script( 'teleadmin_bots_javascript', plugins_url( 'js/bots.js', __FILE__ ) );
    wp_register_script( 'teleadmin_unconfirmed_refresh_javascript', plugins_url( 'js/unconfirmed_refresh.js', __FILE__ ) );
}

add_action( 'admin_enqueue_scripts', '\TeleAdmin\teleadmin_enqueue_scripts' );

function teleadmin_error_log_file() {
    return plugin_dir_path( __FILE__ ) . 'error.log';
}

function teleadmin_needed_woocommerce_version() {
    return '3.3'; // because of get_edit_order_url
}

function teleadmin_woocommerce_is_installed() {
    if ( class_exists( 'WooCommerce' ) ) {
        global $woocommerce;
        if ( version_compare( $woocommerce->version, teleadmin_needed_woocommerce_version(), '>=' ) ) {
            return true;
        }
    }

    return false;
}

new ChatsPage();
new BotsPage();
new BotWebhook();
new UserRegisterListener();
new CommentListener();
new PostListener();
add_action( 'plugins_loaded', function () {
    if ( teleadmin_woocommerce_is_installed() ) {
        new OrderListener();
    }
} );

