<?php

namespace TeleAdmin\Page;

use Longman\TelegramBot\Exception\TelegramException;
use TeleAdmin\Bot\TeleAdminBot;
use function TeleAdmin\teleadmin_error_log_file;

class ChatsPage {

    public static $menu_slug = 'teleadmin-chats';
    public static $ajax_unconfirmed_refresh = 'teleadmin-unconfirmed-refresh-ajax';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_entry' ) ); // Add menu entry
        add_action( 'wp_ajax_' . ChatsPage::$ajax_unconfirmed_refresh, array(
            $this,
            'unconfirmed_refresh'
        ) ); // Ajax for auto-reloading table of unconfirmed chats

        // Add (bulk) actions. -1 is needed because the bottom bulk action (action2) can also be used.
        add_action( 'admin_action_teleadmin-chat-confirm', array( $this, 'check_for_action' ) );
        add_action( 'admin_action_teleadmin-chat-reject', array( $this, 'check_for_action' ) );
        add_action( 'admin_action_teleadmin-chat-delete', array( $this, 'check_for_action' ) );
        add_action( 'admin_action_-1', array( $this, 'check_for_action' ) );
    }

    /**
     * Add page to menu.
     */
    public function add_menu_entry() {
        add_menu_page( 'TeleAdmin Chats', 'TeleAdmin', 'manage_options', ChatsPage::$menu_slug, array(
            $this,
            'page_html'
        ) );
        // Override the first submenu entry in order to get another title in the submenu
        add_submenu_page( ChatsPage::$menu_slug, 'TeleAdmin Chats', 'Chats', 'manage_options', ChatsPage::$menu_slug, array(
            $this,
            'page_html'
        ) );
    }

    /**
     * Display page.
     */
    public function page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $bots = $wpdb->get_var( "
            SELECT COUNT(*)
            FROM teleadmin_bots
        " );


        $chats_list_table = new ChatsListTable( false );
        $chats_list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1>TeleAdmin Chats</h1>
            <?php foreach ( array_merge( BotsPage::generate_invalid_bot_notices(), BotsPage::generate_webhook_not_working_notices() ) as $notice ): ?>
                <div class="notice notice-error">
                    <p><?php echo $notice; ?></p>
                </div>
            <?php endforeach; ?>
            <?php if ( $bots == 0 ): ?>
                <div class="notice notice-warning">
                    <p>
                        You need to <a href="<?php menu_page_url( BotsPage::$menu_slug ) ?>">add a Telegram Bot</a>
                        first.
                    </p>
                </div>
            <?php endif; ?>
            <div class="notice notice-info">
                <p>
                    If you <strong>confirm a chat</strong>, the corresponding user / all users in the corresponding
                    group can use the bot for administrating this WordPress website.
                </p>
                <p>
                    If you <strong>reject a chat</strong> and before doing anything, the bot is unusable in that chat.
                    This prevents anyone who is not allowed to administrate the website from using the bot.
                </p>
                <p>
                    If you want to <strong>delete a chat</strong>, you need to remove the bot from the corresponding
                    group / stop the private chat with him first.
                    Afterwards you can use the delete button in this list.
                    Do not delete an unauthorized chat! You should just keep them in the list of rejected chats.
                </p>
                <p>
                    In case you add a bot to a Telegram <strong>group</strong>, make sure that he has permissions to
                    send messages. Otherwise the chat will be deleted automatically from this list.
                </p>
            </div>
            <?php $chats_list_table->views() ?>
            <div id="<?php echo ChatsPage::$ajax_unconfirmed_refresh; ?>"
                 data-nonce="<?php echo wp_create_nonce( ChatsPage::$ajax_unconfirmed_refresh ); ?>"></div>
            <form id="chats-filter" method="get" action="<?php echo remove_query_arg('page', $chats_list_table->current_url); ?>">
                <?php $chats_list_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Ajax for auto-reloading the table of unconfirmed chats.
     */
    public function unconfirmed_refresh() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die();
        }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], ChatsPage::$ajax_unconfirmed_refresh ) ) {
            wp_die( 'Invalid nonce.' );
        }

        if ( ! isset( $_POST['last-known-chat-id'] ) ) {
            wp_die();
        }

        global $wpdb;
        $autoIncrement = $wpdb->get_var( "
				SELECT COALESCE(MAX(chat_id), -1)
				FROM teleadmin_chats
			" );
        if ( $_POST['last-known-chat-id'] == $autoIncrement ) {
            wp_die(); // No new chat
        }

        $chats_list_table = new ChatsListTable( true );
        $chats_list_table->prepare_items();
        $chats_list_table->ajax_response();
        wp_die();
    }

    /**
     * Maybe an action has been executed (i.e. reject or accept).
     */
    public function check_for_action() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Fetch action
        if ( ! isset( $_GET['action'] ) ) {
            return;
        }
        $action = $_GET['action'];
        if ( $action == '-1' ) { // Maybe the bottom bulk action has been used
            if ( ! isset( $_GET['action2'] ) ) {
                return;
            }
            $action = $_GET['action2'];
            if ( $action == '-1' ) {
                wp_redirect( wp_get_referer() );
            }
        }
        if ( $action !== 'teleadmin-chat-confirm' && $action !== 'teleadmin-chat-reject' && $action !== 'teleadmin-chat-delete' ) {
            return;
        }

        // Check nonce
        if ( ! isset( $_GET['_wpnonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'bulk-chats' ) ) {
            wp_die( 'Invalid nonce.' );
        }

        // Check chat variable
        if ( ! isset( $_GET['chat'] ) ) {
            $_GET['chat'] = array();
        }
        $chat_ids = $_GET['chat'];
        if ( ! is_array( $chat_ids ) ) {
            return;
        }

        // Execute action
        if ( $action === 'teleadmin-chat-confirm' ) {
            $this->chat_confirm( $chat_ids, true );
        } else if ( $action === 'teleadmin-chat-reject' ) {
            $this->chat_confirm( $chat_ids, false );
        } else if ( $action === 'teleadmin-chat-delete' ) {
            $this->chat_delete( $chat_ids );
        } else {
            return;
        }
        wp_redirect( wp_get_referer() );
    }

    /**
     * @param $chat_ids array
     * @param $confirm bool 1=confirm, 0=reject
     */
    private function chat_confirm( $chat_ids, $confirm ) {
        global $wpdb;
        foreach ( $chat_ids as $chat_id ) {
            $updated = $wpdb->update( 'teleadmin_chats', array( 'confirmed' => $confirm ), array( 'chat_id' => $chat_id ), array(
                '%s',
                '%d'
            ), array( '%d' ) );
            if ( $updated !== 1 ) {
                continue;
            }
            $chat = $wpdb->get_row( $wpdb->prepare( "
                    SELECT *
                    FROM teleadmin_chats
                    WHERE chat_id = %d
                ", $chat_id ) );
            if ( $chat === null ) {
                continue;
            }

            try {
                $telegram = new TeleAdminBot( $chat->bot_id );
                $telegram->sendMessage( [
                    'chat_id'    => $chat->telegram_id,
                    'text'       => $confirm ? 'This chat has been confirmed. You can now receive new comments, posts and orders. Type /help to get a list of commands.' :
                        'This chat has been rejected. You will not be able to receive new comments, posts and orders. If this was a mistake, you can change the decision in the <a href="' . add_query_arg( 'page', ChatsPage::$menu_slug, admin_url() ) . '">WordPress administration interface</a>.',
                    'parse_mode' => 'HTML'
                ] );
            } catch ( TelegramException $e ) {
                error_log( $e, 3, teleadmin_error_log_file() );
            }
        }
    }

    private function chat_delete( $chat_ids ) {
        global $wpdb;
        foreach ( $chat_ids as $chat_id ) {
            $wpdb->query( $wpdb->prepare( "
                DELETE
                FROM teleadmin_messages
                WHERE chat_id = %d
            ", $chat_id ) );
            $wpdb->delete( 'teleadmin_chats', array( 'chat_id' => $chat_id ), '%d' );
        }
    }

}