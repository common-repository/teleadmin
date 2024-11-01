<?php

namespace TeleAdmin;

use Longman\TelegramBot\Exception\TelegramException;
use TeleAdmin\Bot\BadResponseTelegramException;
use TeleAdmin\Bot\TeleAdminBot;

class BotWebhook {

    public static $ajax_poll_action = 'teleadmin-poll-updates-ajax';
    public static $cron_webhook_check = 'teleadmin-webhook-check-cron';

    /**
     * @param $token string
     *
     * @return string
     */
    public static function getWebhookUrl( $token ) {
        return add_query_arg( array(
            'page'  => 'teleadmin-webhook',
            'token' => $token
        ), get_home_url( null, '/' ) );
    }

    public function __construct() {
        add_action( 'init', array( $this, 'url_check' ) );
        add_action( 'init', array( $this, 'check_polling' ) );
        add_action( 'admin_init', array( $this, 'check_polling' ) );

        add_action( 'wp_ajax_' . BotWebhook::$ajax_poll_action, array( $this, 'poll' ) );
        add_action( 'wp_ajax_nopriv_' . BotWebhook::$ajax_poll_action, array( $this, 'poll' ) );

        add_action( BotWebhook::$cron_webhook_check, array( $this, 'webhook_check' ) );
        if ( ! wp_next_scheduled( BotWebhook::$cron_webhook_check ) ) {
            wp_schedule_event( time(), 'hourly', BotWebhook::$cron_webhook_check );
        }
    }

    /**
     * Called always.
     */
    public function check_polling() {
        global $wpdb;
        $bot_ids = $wpdb->get_col( "
            SELECT bot_id
            FROM teleadmin_bots
            WHERE TIMESTAMPDIFF(SECOND, last_poll, NOW()) > 30
            AND webhook = 0
        " );
        foreach ( $bot_ids as $bot_id ) {
            $this->start_polling_on_bot( $bot_id );
        }
    }

    /**
     * @param $bot_id int
     */
    private function start_polling_on_bot( $bot_id ) {
        global $wpdb;
        $nonce = wp_create_nonce( BotWebhook::$ajax_poll_action );
        $wpdb->query( $wpdb->prepare( "
                UPDATE teleadmin_bots
                SET last_poll = NOW(), poll_nonce = %s
                WHERE bot_id = %d
            ", $nonce, $bot_id ) );
        wp_remote_post( admin_url( 'admin-ajax.php' ), array(
            'timeout'  => 0.01,
            'blocking' => false,
            'body'     => array(
                'action' => BotWebhook::$ajax_poll_action,
                'nonce'  => $nonce,
                'bot'    => $bot_id
            )
        ) );
    }

    public function poll() {
        session_write_close();

        if ( ! isset( $_POST['nonce'] ) ) {
            return;
        }

        if ( ! isset( $_POST['bot'] ) ) {
            return;
        }
        $bot_id = $_POST['bot'];

        global $wpdb;
        $bot = $wpdb->get_row( $wpdb->prepare( "
            SELECT *
            FROM teleadmin_bots
            WHERE bot_id = %d
            AND webhook = 0
            AND poll_nonce = %s
        ", $bot_id, $_POST['nonce'] ) );
        if ( $bot === null ) {
            return;
        }

        $wpdb->query( $wpdb->prepare( "
            UPDATE teleadmin_bots
            SET last_poll = NOW()
            WHERE bot_id = %d
        ", $bot_id ) );

        $callAgain = true;
        try {
            try {
                $telegram       = new TeleAdminBot( $bot );
                $last_update_id = $telegram->handleUpdatesManually( $bot->last_update_id );
                $wpdb->query( $wpdb->prepare( "
                    UPDATE teleadmin_bots
                    SET last_update_id = GREATEST(last_update_id, %d)
                    WHERE bot_id = %d
                ", $last_update_id, $bot_id ) );

            } catch ( BadResponseTelegramException $e ) {
                if ( $e->getResponse()->getErrorCode() === 409 ) {
                    $callAgain = false;// 409 means there is already another instance calling getUpdates
                } else {
                    throw $e;
                }
            }
        } catch ( TelegramException $e ) {
            error_log( $e, 3, teleadmin_error_log_file() );
        }

        if ( $callAgain ) {
            $this->start_polling_on_bot( $bot_id );
        }
    }

    public function url_check() {
        if ( isset( $_GET['page'] ) && $_GET['page'] == 'teleadmin-webhook' ) {
            $this->called();
            die();
        }
    }

    public function called() {
        if ( ! isset( $_GET['token'] ) ) {
            wp_die( "Token needed." );
        }
        $token = $_GET['token'];

        global $wpdb;
        $bot = $wpdb->get_row( $wpdb->prepare( "
			SELECT *
			FROM teleadmin_bots
			WHERE token = %s
		", $token ) );

        if ( $bot === null ) {
            wp_die( 'Unknown token.' );
        }

        try {
            $telegram = new TeleAdminBot( $bot );
            $telegram->invokeWebhook();
        } catch ( TelegramException $e ) {
            error_log( $e, 3, teleadmin_error_log_file() );
        }
        die();
    }

    public function webhook_check() {
        global $wpdb;
        $bots = $wpdb->get_results( "
            SELECT *
            FROM teleadmin_bots
        " );
        foreach ( $bots as $bot ) {
            try {
                $telegram = new TeleAdminBot( $bot );
                $enabled  = true;
                if ( $bot->webhook ) {
                    $enabled = false;
                } else {
                    $response = $telegram->getWebhookInfo();
                    if ( $response->getResult()->getUrl() !== BotWebhook::getWebhookUrl( $bot->token ) ) {
                        $enabled = false;
                    }
                }
                if ( ! $enabled ) {
                    try {
                        $telegram->setWebhook( [
                            'url' => BotWebhook::getWebhookUrl( $bot->token )
                        ] );
                        $enabled = true;
                    } catch ( TelegramException $e ) {
                        error_log( $e, 3, teleadmin_error_log_file() );
                    }
                }
                $wpdb->update( 'teleadmin_bots', array( 'webhook' => $enabled ), array( 'bot_id' => $bot->bot_id ), '%d', '%d' );
            } catch ( TelegramException $e ) {
                error_log( $e, 3, teleadmin_error_log_file() );
            }
        }
    }

}