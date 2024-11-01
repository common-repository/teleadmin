<?php

namespace TeleAdmin\Bot;

use Longman\TelegramBot\Entities\Chat;
use Longman\TelegramBot\Telegram;
use TeleAdmin\Page\ChatsPage;

class CommandHelper {

    /**
     * @var TeleAdminBot
     */
    private $telegram;

    /**
     * @var Chat
     */
    private $chat;

    /**
     * @var object
     */
    private $ourChat;

    /**
     * @var \Longman\TelegramBot\Entities\ServerResponse
     */
    private $response;

    /**
     * @var bool
     */
    private $is_authorized = false;

    /**
     * @var bool
     */
    private $was_first_message = false;

    /**
     * CommandHelper constructor.
     *
     * @param Chat $chat
     * @param Telegram $telegram
     *
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function __construct( Chat $chat, Telegram $telegram ) {
        $this->chat     = $chat;
        $this->response = $telegram->emptyResponse();
        $this->telegram = new TeleAdminBot( $telegram->getApiKey() );

        global $wpdb;
        $this->ourChat = $wpdb->get_row( $wpdb->prepare( "
			SELECT *
			FROM teleadmin_chats
			WHERE bot_id = %d
			AND telegram_id = %d
		", array( $this->telegram->getBot()->bot_id, $chat->getId() ) ) );

        if ( $this->ourChat === null ) {
            $this->was_first_message = true;

            $wpdb->insert( 'teleadmin_chats', array(
                'telegram_id' => $chat->getId(),
                'bot_id'      => $this->telegram->getBot()->bot_id,
                'type'        => $chat->getType(),
                'title'       => $chat->getTitle(),
                'username'    => $chat->getUsername()
            ), array( '%d', '%d', '%s', '%s', '%s' ) );

            $this->ourChat = $wpdb->get_row( $wpdb->prepare( "
                SELECT *
                FROM teleadmin_chats
                WHERE bot_id = %d
                AND telegram_id = %d
            ", array( $this->telegram->getBot()->bot_id, $chat->getId() ) ) );

            $this->response = $this->telegram->sendMessage( [
                'chat_id'    => $chat->getId(),
                'text'       => 'Welcome to your WordPress administration Telegram Bot.' . "\n\n" .
                                'In order to be able to use this bot, you will need to confirm this chat in your <a href="' . add_query_arg( 'page', ChatsPage::$menu_slug, admin_url() ) . '">WordPress administration interface</a>.',
                'parse_mode' => 'HTML'
            ] );
        } else {
            $this->is_authorized = $this->ourChat->confirmed == 1;
        }
    }

    /**
     * @return bool
     */
    public function is_authorized() {
        return $this->is_authorized;
    }

    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function send_not_authorized_notice_if_not_first_message() {
        if ( $this->was_first_message ) {
            return $this->response;
        }
        $this->response = $this->telegram->sendMessage( array(
            'chat_id'    => $this->chat->getId(),
            'text'       => 'This chat has not been confirmed for the WordPress administration Telegram Bot yet.' . "\n\n" .
                            'In order to be able to use this bot, you will need to confirm this chat in your <a href="' . add_query_arg( 'page', ChatsPage::$menu_slug, admin_url() ) . '">WordPress administration interface</a>.',
            'parse_mode' => 'HTML'
        ) );

        return $this->response;
    }

    /**
     * If the chat partner has a new username, this methods updates the database entry.
     */
    public function update_admin_username() {
        if ( $this->chat->getType() !== 'private' ) {
            return;
        }
        if ( $this->chat->getUsername() !== $this->ourChat->username ) {
            global $wpdb;
            $wpdb->update( 'teleadmin_chats', array(
                'username' => $this->chat->getUsername()
            ), array(
                'chat_id' => $this->ourChat->chat_id
            ), '%s', '%d' );
        }
    }

    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     */
    public function getResponse(): \Longman\TelegramBot\Entities\ServerResponse {
        return $this->response;
    }

    /**
     * @return int
     */
    public function getBotId() {
        return $this->telegram->getBot()->bot_id;
    }

    /**
     * @return object|null
     */
    public function getOurChat() {
        return $this->ourChat;
    }

    /**
     * @return TeleAdminBot
     */
    public function getTeleAdminBot() {
        return $this->telegram;
    }

}
