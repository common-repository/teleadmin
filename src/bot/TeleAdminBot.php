<?php

namespace TeleAdmin\Bot;

use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Telegram;
use TeleAdmin\Command\CommandDirectory;
use function TeleAdmin\teleadmin_error_log_file;

class TeleAdminBot {

    /**
     * @var object
     */
    private $bot = null;

    /**
     * @var Telegram
     */
    private $telegram = null;

    /**
     * TeleAdminBot constructor.
     *
     * @param $bot object|int|string
     *
     * @throws TelegramException
     */
    public function __construct( $bot ) {
        if ( is_int( $bot ) || is_string( $bot ) ) {
            global $wpdb;
            $this->bot = $wpdb->get_row( $wpdb->prepare( "
                SELECT *
                FROM teleadmin_bots
                WHERE bot_id = %d OR token = %s
            ", $bot, $bot ) );
        } else {
            $this->bot = $bot;
        }
        if ( $this->bot === null ) {
            throw new TelegramException( "Unknown bot." );
        }
        $this->telegram = new Telegram( $this->bot->token, $this->bot->username );
        $this->telegram->addCommandsPath( CommandDirectory::$directory );
    }

    /**
     * @return object
     */
    public function getBot() {
        return $this->bot;
    }

    /**
     * @return bool
     * @throws TelegramException
     */
    public function invokeWebhook() {
        return $this->telegram->handle();
    }

    /**
     * @param $last_update_id int
     *
     * @return int
     * @throws BadResponseTelegramException
     * @throws TelegramException
     */
    public function handleUpdatesManually( $last_update_id ) {
        $response = $this->call( 'getUpdates', [
            'offset' => $last_update_id + 1,
            'limit'  => 5,
            'timout' => 15
        ] );
        $results  = $response->getResult();
        error_log( print_r( $results, true ) . "\n", 3, teleadmin_error_log_file() );

        $max_update_id = - 1;
        foreach ( $results as $result ) {
            $this->telegram->processUpdate( $result );
            $max_update_id = max( $max_update_id, intval( $result->getUpdateId() ) );
        }

        return $max_update_id;
    }

    /**
     * @return ServerResponse
     * @throws TelegramException
     */
    public function emptyResponse() {
        return $this->telegram->emptyResponse();
    }

    /**
     * @param $data array
     *
     * @return ServerResponse
     * @throws TelegramException
     * @throws BadResponseTelegramException
     */
    public function sendMessage( $data ) {
        return $this->ensureChatCall( 'sendMessage', $data );
    }

    /**
     * @param $data array
     *
     * @return ServerResponse
     * @throws TelegramException
     * @throws BadResponseTelegramException
     */
    public function editMessageText( $data ) {
        return $this->ensureChatCall( 'editMessageText', $data );
    }

    /**
     * @param $data array
     *
     * @return ServerResponse
     * @throws BadResponseTelegramException
     * @throws TelegramException
     */
    public function deleteMessage( $data ) {
        return $this->ensureChatCall( 'deleteMessage', $data );
    }

    /**
     * @param $data array
     *
     * @return ServerResponse
     * @throws TelegramException
     * @throws BadResponseTelegramException
     */
    public function answerCallbackQuery( $data ) {
        return $this->call( 'answerCallbackQuery', $data );
    }

    /**
     * @param $data
     *
     * @return ServerResponse
     * @throws BadResponseTelegramException
     * @throws TelegramException
     */
    public function setWebhook( $data ) {
        return $this->call( 'setWebhook', $data );
    }

    /**
     * @return ServerResponse
     * @throws BadResponseTelegramException
     * @throws TelegramException
     */
    public function deleteWebhook() {
        return $this->call( 'deleteWebhook', [] );
    }

    /**
     * @return ServerResponse
     * @throws BadResponseTelegramException
     * @throws TelegramException
     */
    public function getWebhookInfo() {
        return $this->call( 'getWebhookInfo', [] );
    }

    /**
     * @param $name string
     * @param $data array
     *
     * @return ServerResponse
     * @throws TelegramException
     * @throws BadResponseTelegramException
     */
    private function ensureChatCall( $name, $data ) {
        try {
            return $this->call( $name, $data );
        } catch ( BadResponseTelegramException $e ) {
            if ( $e->getResponse()->getErrorCode() == 403 ) {
                // 403 Forbidden usually means the bot is no longer in the group, therefore we delete the chat
                $chat_id = $data['chat_id'];

                global $wpdb;
                $wpdb->query( $wpdb->prepare( "
                DELETE
                FROM teleadmin_messages
                WHERE chat_id = (SELECT chat_id FROM teleadmin_chats WHERE bot_id = %d AND teleadmin_chats.telegram_id = %d)
            ", $this->bot->bot_id, $chat_id ) );
                $wpdb->delete( 'teleadmin_chats', array( 'bot_id' => $this->bot->bot_id, 'telegram_id' => $chat_id ) );
            }
            throw $e;
        }
    }

    /**
     * @param $name string
     * @param $data array
     *
     * @return ServerResponse
     * @throws TelegramException
     * @throws BadResponseTelegramException
     */
    private function call( $name, $data ) {
        if ( $this->telegram !== null ) {
            $response = $this->telegram->send( $name, $data );
            if ( ! $response->isOk() && $response->getErrorCode() == 429 ) {
                // 429 means there are too many requests, try again...
                sleep( 1 );

                return $this->call( $name, $data );
            } else {
                if ( ! $response->isOk() && $response->getErrorCode() == 401 ) {
                    // 401 means we are not authorized, usually the auth key is not valid
                    global $wpdb;
                    $wpdb->update( 'teleadmin_bots', array( 'invalid' => 1 ), array( 'bot_id' => $this->bot->bot_id ), '%d', '%d' );
                } else if ( $this->bot->invalid ) {
                    // Somehow the bot token works again...
                    global $wpdb;
                    $wpdb->update( 'teleadmin_bots', array( 'invalid' => 0 ), array( 'bot_id' => $this->bot->bot_id ), '%d', '%d' );
                }
                if ( ! $response->isOk() ) {
                    throw new BadResponseTelegramException( $response );
                }

                return $response;
            }
        } else {
            return null;
        }
    }

}