<?php

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use TeleAdmin\Bot\CommandHelper;
use TeleAdmin\Bot\CommentMessenger;
use TeleAdmin\Bot\OrderMessenger;
use TeleAdmin\Bot\PostMessenger;
use function TeleAdmin\teleadmin_woocommerce_is_installed;

/**
 * Callback query command
 */
class CallbackqueryCommand extends SystemCommand {

    /**
     * @var string
     */
    protected $name = 'callbackquery';

    /**
     * @var string
     */
    protected $description = 'Reply to callback query';

    /**
     * Command execute method
     *
     * @return mixed
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute() {
        $callback_query = $this->getCallbackQuery();
        if ( $callback_query->getMessage() === null ) {
            return $this->simpleResponse();
        }
        $message = $callback_query->getMessage();

        $helper = new CommandHelper( $message->getChat(), $this->getTelegram() );
        if ( ! $helper->is_authorized() ) {
            return $this->simpleResponse();
        }
        $telegram = $helper->getTeleAdminBot();

        global $wpdb;
        $message = $wpdb->get_row( $wpdb->prepare( "
			SELECT *
			FROM teleadmin_messages
			WHERE chat_id = %d
			AND telegram_id = %d
		", $helper->getOurChat()->chat_id, $message->getMessageId() ) );
        if ( $message === null ) {
            return $this->simpleResponse();
        }
        $type = $message->type;

        if ( $message->is_paging ) {
            $messenger = null;
            if ( $type === 'comment' ) {
                $messenger = new CommentMessenger();
            } else if ( $type === 'post' ) {
                $messenger = new PostMessenger();
            } else if ( $type === 'order' && teleadmin_woocommerce_is_installed() ) {
                $messenger = new OrderMessenger();
            }

            if ( $messenger !== null ) {
                if ( $callback_query->getData() === 'previous' ) {
                    if ( ! $messenger->previous_paging_message( $telegram, $helper->getOurChat(), $message ) ) {
                        return $this->telegram->answerCallbackQuery( [
                            'callback_query_id' => $callback_query->getId(),
                            'text'              => 'This is already the first ' . $type . '.'
                        ] );
                    }
                } else if ( $callback_query->getData() === 'next' ) {
                    if ( ! $messenger->next_paging_message( $telegram, $helper->getOurChat(), $message ) ) {
                        return $this->telegram->answerCallbackQuery( [
                            'callback_query_id' => $callback_query->getId(),
                            'text'              => 'This is already the last ' . $type . '.'
                        ] );
                    }
                } else if ( $callback_query->getData() === 'showactions' ) {
                    $messenger->show_actions_of_paging_message( $telegram, $helper->getOurChat(), $message );
                }
            }

        } else {

            if ( $type === 'comment' ) {
                $comment_id = $message->object_id;
                $comment    = get_comment( $comment_id );
                if ( $comment !== null ) {
                    $status = $comment->comment_approved;
                    if ( $callback_query->getData() === 'spam' ) {
                        if ( $status === 'trash' || $status === '1' || $status === 'approved' || $status === '0' || $status === 'hold' ) {
                            wp_spam_comment( $comment_id );
                        }
                    } else if ( $callback_query->getData() === 'notspam' ) {
                        if ( $status === 'spam' ) {
                            wp_unspam_comment( $comment_id );
                        }
                    } else if ( $callback_query->getData() === 'trash' ) {
                        if ( $status === '1' || $status === 'approved' || $status === '0' || $status === 'hold' ) {
                            wp_trash_comment( $comment_id );
                        }
                    } else if ( $callback_query->getData() === 'restore' ) {
                        if ( $status === 'trash' ) {
                            wp_untrash_comment( $comment_id );
                        }
                    } else if ( $callback_query->getData() === 'approve' ) {
                        if ( $status === '0' || $status === 'hold' ) {
                            wp_set_comment_status( $comment_id, 'approve' );
                        }
                    } else if ( $callback_query->getData() === 'unapprove' ) {
                        if ( $status === '1' || $status === 'approved' ) {
                            wp_set_comment_status( $comment_id, 'hold' );
                        }
                    }
                }
                ( new CommentMessenger() )->manual_update( $message->object_id );
            }

            if ( $type === 'post' ) {
                $post_id = $message->object_id;
                $status  = get_post_status( $post_id );
                if ( $status !== false ) {
                    if ( $callback_query->getData() === 'trash' ) {
                        if ( $status === 'publish' || $status === 'pending' || $status === 'draft' || $status === 'future' || $status === 'private' ) {
                            wp_trash_post( $post_id );
                        }
                    } else if ( $callback_query->getData() === 'restore' ) {
                        if ( $status === 'trash' ) {
                            wp_untrash_post( $post_id );
                        }
                    } else if ( $callback_query->getData() === 'publish' ) {
                        if ( $status === 'pending' || $status === 'draft' || $status === 'future' || $status === 'private' ) {
                            wp_publish_post( $post_id );
                        }
                    } else if ( $callback_query->getData() === 'private' ) {
                        if ( $status === 'publish' || $status === 'pending' || $status === 'draft' || $status === 'future' ) {
                            wp_update_post( array( 'ID' => $post_id, 'post_status' => 'private' ) );
                        }
                    } else if ( $callback_query->getData() === 'draft' ) {
                        if ( $status === 'publish' || $status === 'pending' || $status === 'future' || $status === 'private' ) {
                            wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
                        }
                    } else if ( $callback_query->getData() === 'pending' ) {
                        if ( $status === 'publish' || $status === 'draft' || $status === 'future' || $status === 'private' ) {
                            wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ) );
                        }
                    }
                }
                ( new PostMessenger() )->manual_update( $message->object_id );
            }

            if ( $type === 'order' && teleadmin_woocommerce_is_installed() ) {
                $order = wc_get_order( $message->object_id );
                if ( $order instanceof \WC_Order ) {
                    $status = $order->get_status();
                    if ( $callback_query->getData() === 'processing' ) {
                        if ( $status === 'pending' || $status === 'on-hold' ) {
                            $order->update_status( 'processing', '', true );
                        }
                    } else if ( $callback_query->getData() === 'completed' ) {
                        if ( $status === 'pending' || $status === 'processing' || $status === 'on-hold' ) {
                            $order->update_status( 'completed', '', true );
                        }
                    } else if ( $callback_query->getData() === 'on-hold' ) {
                        if ( $status === 'pending' ) {
                            $order->update_status( 'on-hold', '', true );
                        }
                    }
                }
                ( new OrderMessenger() )->manual_update( $message->object_id );
            }

        }

        return $this->simpleResponse();
    }

    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     */
    private function simpleResponse() {
        return $this->telegram->answerCallbackQuery( [ 'callback_query_id' => $this->getCallbackQuery()->getId() ] );
    }

}
