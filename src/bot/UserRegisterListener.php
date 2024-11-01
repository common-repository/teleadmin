<?php

namespace TeleAdmin\Bot;

use Longman\TelegramBot\Exception\TelegramException;
use function TeleAdmin\teleadmin_error_log_file;

class UserRegisterListener {

    public function __construct() {
        add_action( 'user_register', array( $this, 'user_registered' ) );
    }

    public function user_registered( $user_id ) {
        global $wpdb;

        $user = get_user_by( 'id', $user_id );
        if ( $user === null ) {
            return;
        }

        $bots = $wpdb->get_results( "
				SELECT *
				FROM teleadmin_bots
			" );
        foreach ( $bots as $bot ) {
            try {
                $telegram = new TeleAdminBot( $bot );
                $chats    = $wpdb->get_results( $wpdb->prepare( "
                    SELECT chat_id, telegram_id
                    FROM teleadmin_chats
                    WHERE bot_id = %d
                    AND confirmed = 1
                ", $bot->bot_id ) );
                foreach ( $chats as $chat ) {
                    try {
                        $telegram->sendMessage( [
                            'chat_id'    => $chat->telegram_id,
                            'text'       => 'User <a href="' . add_query_arg( 'user_id', $user_id, self_admin_url( 'user-edit.php' ) ) . '">' . $user->user_login . '</a> has registered.',
                            'parse_mode' => 'HTML'
                        ] );
                    } catch ( TelegramException $e ) {
                        error_log( $e, 3, teleadmin_error_log_file() );
                    }
                }
            } catch ( TelegramException $e ) {
                error_log( $e, 3, teleadmin_error_log_file() );
            }
        }
    }

}