<?php

namespace TeleAdmin\Bot;


use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Exception\TelegramException;

class PostMessenger extends Messenger {

    protected function get_text( $post, $deleted ) {
        $status = get_post_status( $post->ID );

        if ( $status === 'publish' ) {
            $statusText = 'Published';
        } else if ( $status === 'pending' ) {
            $statusText = 'Pending Review';
        } else if ( $status === 'draft' ) {
            $statusText = 'Draft';
        } else if ( $status === 'future' ) {
            $statusText = 'Future';
        } else if ( $status === 'private' ) {
            $statusText = 'Published Privately';
        } else if ( $status === 'trash' ) {
            $statusText = 'Trash';
        } else {
            $statusText = 'Unknown: ' . $status;
        }
        if ( $deleted ) {
            $statusText = 'Permanently Deleted';
        }

        $part1 = 'The following post <a href="' . admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) . '">';
        $part2 = '</a> has been written:' . "\n\n";
        $part3 = "\n\n" . 'Post status: <strong>' . $this->telegram_escape( $statusText ) . '</strong>';

        $title       = $this->telegram_escape( strip_tags( html_entity_decode( get_the_title( $post ) ) ) );
        $short_title = mb_substr( $title, 0, max( 1, 4000 - mb_strlen( $part1 . $part2 . $part3 ) ) );
        if ( $short_title !== $title ) {
            $short_title .= '...';
        }

        $content       = $this->telegram_escape( strip_tags( html_entity_decode( apply_filters( 'the_content', $post->post_content ) ) ) );
        $short_content = mb_substr( $content, 0, max( 1, 4000 - mb_strlen( $part1 . $part2 . $part3 . $short_title ) ) );
        if ( $short_content !== $content ) {
            $short_content .= '...';
        }

        return $part1 . $short_title . $part2 . $short_content . $part3;
    }

    /**
     * @param $post
     *
     * @return InlineKeyboard
     * @throws TelegramException
     */
    protected function get_reply_markup( $post ) {
        $status   = get_post_status( $post->ID );
        $keyboard = new InlineKeyboard( [] );

        if ( $status === 'publish' ) {
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Make Draft', 'callback_data' => 'draft' ] ),
                new InlineKeyboardButton( [ 'text' => 'Needs Review', 'callback_data' => 'pending' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Make Private', 'callback_data' => 'private' ] ),
                new InlineKeyboardButton( [ 'text' => 'Trash', 'callback_data' => 'trash' ] ) );
        } else if ( $status === 'pending' ) {
            $keyboard->addRow( new InlineKeyboardButton( [
                'text'          => 'Publish Privately',
                'callback_data' => 'private'
            ] ),
                new InlineKeyboardButton( [ 'text' => 'Publish', 'callback_data' => 'publish' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Make Draft', 'callback_data' => 'draft' ] ),
                new InlineKeyboardButton( [ 'text' => 'Trash', 'callback_data' => 'trash' ] ) );
        } else if ( $status === 'draft' ) {
            $keyboard->addRow( new InlineKeyboardButton( [
                'text'          => 'Publish Privately',
                'callback_data' => 'private'
            ] ),
                new InlineKeyboardButton( [ 'text' => 'Publish', 'callback_data' => 'publish' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Needs Review', 'callback_data' => 'pending' ] ),
                new InlineKeyboardButton( [ 'text' => 'Trash', 'callback_data' => 'trash' ] ) );
        } else if ( $status === 'future' ) {
            $keyboard->addRow( new InlineKeyboardButton( [
                'text'          => 'Publish Privately',
                'callback_data' => 'private'
            ] ),
                new InlineKeyboardButton( [ 'text' => 'Publish Now', 'callback_data' => 'publish' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Make Draft', 'callback_data' => 'draft' ] ),
                new InlineKeyboardButton( [ 'text' => 'Needs Review', 'callback_data' => 'pending' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Trash', 'callback_data' => 'trash' ] ) );
        } else if ( $status === 'private' ) {
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Make Draft', 'callback_data' => 'draft' ] ),
                new InlineKeyboardButton( [ 'text' => 'Publish', 'callback_data' => 'publish' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Needs Review', 'callback_data' => 'pending' ] ),
                new InlineKeyboardButton( [ 'text' => 'Trash', 'callback_data' => 'trash' ] ) );
        } else if ( $status === 'trash' ) {
            $keyboard->addRow( new InlineKeyboardButton( [
                'text'          => 'Restore From Trash',
                'callback_data' => 'restore'
            ] ) );
        }

        return $keyboard;
    }

    protected function get_name() {
        return 'post';
    }

    protected function get_id( $object ) {
        return $object->ID;
    }

    protected function should_send_message( $post ) {
        return get_post_type( $post ) === 'post';
    }

    protected function get_object( $object_id ) {
        return get_post( $object_id );
    }

    protected function get_most_recent() {
        global $wpdb;
        $post_id = $wpdb->get_var( "
	        SELECT ID
	        FROM {$wpdb->posts}
	        WHERE post_type = 'post'
	        AND post_status IN ('publish', 'pending', 'draft', 'future', 'private', 'trash')
            ORDER BY ID DESC
            LIMIT 1
	    " );
        if ( $post_id === null ) {
            return null;
        }

        return get_post( $post_id );
    }

    protected function get_previous( $post_id ) {
        global $wpdb;
        $next_post_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT ID
            FROM {$wpdb->posts}
	        WHERE post_type = 'post'
	        AND post_status IN ('publish', 'pending', 'draft', 'future', 'private', 'trash')
            AND ID < %d
            ORDER BY ID DESC
            LIMIT 1
        ", $post_id ) );
        if ( $next_post_id === null ) {
            return null;
        }

        return get_post( $next_post_id );
    }

    protected function get_next( $post_id ) {
        global $wpdb;
        $next_post_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT ID
            FROM {$wpdb->posts}
	        WHERE post_type = 'post'
	        AND post_status IN ('publish', 'pending', 'draft', 'future', 'private', 'trash')
            AND ID > %d
            ORDER BY ID ASC
            LIMIT 1
        ", $post_id ) );
        if ( $next_post_id === null ) {
            return null;
        }

        return get_post( $next_post_id );
    }

}
