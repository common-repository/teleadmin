<?php

namespace TeleAdmin\Bot;

use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Exception\TelegramException;
use function TeleAdmin\teleadmin_error_log_file;

abstract class Messenger {

    private static $max_messages_of_same_object = 5;

    /**
     * @return string Unique type name
     */
    protected abstract function get_name();

    /**
     * @param $object_id int
     *
     * @return mixed Object corresponding to id
     */
    protected abstract function get_object( $object_id );

    /**
     * @param $object mixed
     *
     * @return int Id corresponding to object
     */
    protected abstract function get_id( $object );

    /**
     * @param $object mixed
     * @param $deleted bool
     *
     * @return string Message text for object
     */
    protected abstract function get_text( $object, $deleted );

    /**
     * @param $object mixed
     *
     * @return InlineKeyboard Keyboard for message
     */
    protected abstract function get_reply_markup( $object );

    /**
     * @param $object mixed
     *
     * @return bool Should send (not edit) message for given object
     */
    protected abstract function should_send_message( $object );

    /**
     * @return object Most recent object
     */
    protected abstract function get_most_recent();

    /**
     * @param $object_id int
     *
     * @return object Most recent previous object
     */
    protected abstract function get_previous( $object_id );

    /**
     * @param $object_id int
     *
     * @return object Oldest next object
     */
    protected abstract function get_next( $object_id );

    /**
     * @param $object_id int
     */
    public function manual_update( $object_id ) {
        $object = $this->get_object( $object_id );
        $this->update_messages( $object, $object === null );
    }

    /**
     * @param $object object|null Object for which to send/edit messages
     * @param $deleted bool
     */
    public function update_messages( $object, $deleted ) {
        global $wpdb;

        $object_id = $this->get_id( $object );

        $bots = $wpdb->get_results( "
				SELECT *
				FROM teleadmin_bots
			" );
        foreach ( $bots as $bot ) {
            try {
                $telegram = new TeleAdminBot( $bot );

                $messages = $wpdb->get_results( $wpdb->prepare( "
					SELECT *
					FROM teleadmin_messages
					WHERE teleadmin_messages.type = %s
				    AND object_id = %d
					AND (SELECT bot_id FROM teleadmin_chats WHERE teleadmin_chats.chat_id = teleadmin_messages.chat_id) = %d
                    ORDER BY message_id DESC
				", $this->get_name(), $object_id, $bot->bot_id ) );

                if ( empty( $messages ) ) {
                    if ( $object !== null && $this->should_send_message( $object ) ) {
                        $chats = $wpdb->get_results( $wpdb->prepare( "
							SELECT *
							FROM teleadmin_chats
							WHERE bot_id = %d
							AND confirmed = 1
						", $bot->bot_id ) );
                        if ( $chats !== null ) {
                            foreach ( $chats as $chat ) {
                                $this->new_message( $telegram, $chat, $object, $deleted, false );
                            }
                        }
                    }
                } else {
                    foreach ( array_slice( $messages, Messenger::$max_messages_of_same_object ) as $message ) {
                        $chat = $wpdb->get_row( $wpdb->prepare( "
                            SELECT *
                            FROM teleadmin_chats
                            WHERE chat_id = %d
                        ", $message->chat_id ) );
                        if ( $chat !== null ) {
                            $this->delete_message( $telegram, $message, $chat );
                        }
                    }
                    foreach ( array_slice( $messages, 0, Messenger::$max_messages_of_same_object ) as $message ) {
                        $chat = $wpdb->get_row( $wpdb->prepare( "
                            SELECT *
                            FROM teleadmin_chats
                            WHERE chat_id = %d
                        ", $message->chat_id ) );
                        if ( $chat !== null ) {
                            $this->edit_message( $telegram, $message, $chat, $object, $deleted );
                        }
                    }
                }
            } catch ( TelegramException $e ) {
                error_log( $e, 3, teleadmin_error_log_file() );
            }
        }

    }

    /**
     * @param $telegram TeleAdminBot
     * @param $chat object
     */
    public function send_paging_message( $telegram, $chat ) {
        $object = $this->get_most_recent();
        if ( $object === null ) {
            try {
                $telegram->sendMessage( [
                    'chat_id' => $chat->telegram_id,
                    'text'    => 'No ' . $this->get_name() . ' exists on this WordPress website.'
                ] );
            } catch ( TelegramException $e ) {
                error_log( $e, 3, teleadmin_error_log_file() );
            }

            return;
        }
        $this->new_message( $telegram, $chat, $object, false, true );
    }

    /**
     * @param $telegram TeleAdminBot
     * @param $chat object
     * @param $message object
     *
     * @return bool If there was a previous object
     */
    public function previous_paging_message( $telegram, $chat, $message ) {
        $object = $this->get_previous( $message->object_id );
        if ( $object === null ) {
            return false;
        }
        global $wpdb;
        $wpdb->update( 'teleadmin_messages', array( 'object_id' => $this->get_id( $object ) ), array( 'message_id' => $message->message_id ), '%d', '%d' );
        $message->object_id = $this->get_id( $object );
        $this->edit_message( $telegram, $message, $chat, $object, false );

        return true;
    }

    /**
     * @param $telegram TeleAdminBot
     * @param $chat object
     * @param $message object
     *
     * @return bool If there was a next object
     */
    public function next_paging_message( $telegram, $chat, $message ) {
        $object = $this->get_next( $message->object_id );
        if ( $object === null ) {
            return false;
        }
        global $wpdb;
        $wpdb->update( 'teleadmin_messages', array( 'object_id' => $this->get_id( $object ) ), array( 'message_id' => $message->message_id ), '%d', '%d' );
        $message->object_id = $this->get_id( $object );
        $this->edit_message( $telegram, $message, $chat, $object, false );

        return true;
    }

    /**
     * @param $telegram TeleAdminBot
     * @param $chat object
     * @param $message object
     */
    public function show_actions_of_paging_message( $telegram, $chat, $message ) {
        global $wpdb;
        $wpdb->update( 'teleadmin_messages', array( 'is_paging' => false ), array( 'message_id' => $message->message_id ), '%d', '%d' );
        $message->is_paging = false;
        $object             = $this->get_object( $message->object_id );

        if ( $object === null ) {
            try {
                $telegram->editMessageText( [
                    'chat_id'    => $chat->telegram_id,
                    'message_id' => $message->telegram_id,
                    'text'       => 'This ' . $this->get_name() . ' has been deleted'
                ] );
            } catch ( TelegramException $e ) {
                error_log( $e, 3, teleadmin_error_log_file() );
            }
            $wpdb->delete( 'teleadmin_messages', array( 'message_id' => $message->message_id ), '%d' );

            return;
        }
        $this->edit_message( $telegram, $message, $chat, $object, false );
    }

    /**
     * Escape the given string for telegram using HTML parse mode.
     *
     * @param $str string
     *
     * @return string
     */
    public function telegram_escape( $str ) {
        return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $str );
    }

    /**
     * @param $telegram TeleAdminBot
     * @param $chat object
     * @param $object object
     * @param $deleted bool
     * @param $paging bool
     */
    private function new_message( $telegram, $chat, $object, $deleted, $paging ) {
        try {
            $telegram_message = [
                'chat_id'    => $chat->telegram_id,
                'text'       => $this->get_text( $object, $deleted ),
                'parse_mode' => 'HTML'
            ];
            if ( $paging ) {
                $telegram_message['reply_markup'] = $this->get_paging_reply_markup();
            } else if ( ! $deleted ) {
                $telegram_message['reply_markup'] = $this->get_reply_markup( $object );
            }
            $response   = $telegram->sendMessage( $telegram_message );
            $message_id = $response->getResult()->getMessageId();

            global $wpdb;
            $wpdb->insert( 'teleadmin_messages', array(
                'chat_id'     => $chat->chat_id,
                'telegram_id' => $message_id,
                'type'        => $this->get_name(),
                'object_id'   => $this->get_id( $object ),
                'is_paging'   => $paging
            ), array( '%d', '%d', '%s', '%d', '%d' ) );
        } catch ( TelegramException $e ) {
            error_log( $e, 3, teleadmin_error_log_file() );
        }
    }

    /**
     * @param $telegram TeleAdminBot
     * @param $message object
     * @param $chat object|null
     * @param $object object
     * @param $deleted bool
     */
    private function edit_message( $telegram, $message, $chat, $object, $deleted ) {
        try {
            $telegram_message = [
                'chat_id'    => $chat->telegram_id,
                'message_id' => $message->telegram_id,
                'text'       => $object === null ? 'This ' . $this->get_name() . ' has been deleted.' : $this->get_text( $object, $deleted ),
                'parse_mode' => 'HTML'
            ];
            if ( $message->is_paging ) {
                $telegram_message['reply_markup'] = $this->get_paging_reply_markup();
            } else if ( ! $deleted ) {
                $telegram_message['reply_markup'] = $this->get_reply_markup( $object );
            }
            try {
                $telegram->editMessageText( $telegram_message );
            } catch ( BadResponseTelegramException $e ) {
                if ( $e->isMessageNotFoundError() ) {
                    global $wpdb;
                    $wpdb->delete( 'teleadmin_messages', array( 'message_id' => $message->message_id ), '%d' );
                    throw $e;
                } else {
                    throw $e;
                }
            }
        } catch ( TelegramException $e ) {
            error_log( $e, 3, teleadmin_error_log_file() );
        }
    }

    /**
     * @param $telegram TeleAdminBot
     * @param $message object
     * @param $chat object
     */
    private function delete_message( $telegram, $message, $chat ) {
        try {
            $success = false;
            try {
                $telegram->deleteMessage( [
                    'chat_id'    => $chat->telegram_id,
                    'message_id' => $message->telegram_id
                ] );
                $success = true;
            } catch ( BadResponseTelegramException $e ) {
                try {
                    $telegram->editMessageText( [
                        'chat_id'    => $chat->telegram_id,
                        'message_id' => $message->telegram_id,
                        'text'       => 'This message is outdated.'
                    ] );
                    $success = true;
                } catch ( BadResponseTelegramException $e ) {
                    if ( $e->isMessageNotFoundError() ) {
                        $success = true;
                    }
                }
            }

            if ( $success ) {
                global $wpdb;
                $wpdb->delete( 'teleadmin_messages', array( 'message_id' => $message->message_id ), '%d' );
            }
        } catch ( TelegramException $e ) {
            error_log( $e, 3, teleadmin_error_log_file() );
        }
    }

    /**
     * @return InlineKeyboard
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function get_paging_reply_markup() {
        $keyboard = new InlineKeyboard( [] );
        $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Previous', 'callback_data' => 'previous' ] ),
            new InlineKeyboardButton( [ 'text' => 'Next', 'callback_data' => 'next' ] ) );
        $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Show Actions', 'callback_data' => 'showactions' ] ) );

        return $keyboard;
    }

}
