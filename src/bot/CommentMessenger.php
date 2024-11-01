<?php

namespace TeleAdmin\Bot;

use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Exception\TelegramException;

class CommentMessenger extends Messenger {

    protected function get_text( $comment, $deleted ) {
        $post = get_post( $comment->comment_post_ID );
        $type = $comment->comment_type;

        $status = $comment->comment_approved;
        if ( $status === 'trash' ) {
            $statusText = 'Trash';
        } else if ( $status === 'spam' ) {
            $statusText = 'Spam';
        } else if ( $status === '1' || $status === 'approved' ) {
            $statusText = 'Approved';
        } else if ( $status === '0' || $status === 'hold' ) {
            $statusText = 'Unapproved';
        } else if ( $status === 'post-trashed' ) {
            $statusText = 'Corresponding post trashed';
        } else {
            $statusText = 'Unknown: ' . $status;
        }
        if ( $deleted ) {
            $statusText = 'Permanently Deleted';
        }

        $part1 = $type === 'review' ? 'The following review has been posted by <strong>' : 'The following comment has been posted by <strong>';
        $part2 = '</strong> on ' . ( $type === 'review' ? 'product' : 'page' ) . ' <a href="' . $this->telegram_escape( get_permalink( $post->ID ) ) . '">';
        $part3 = '</a>:' . "\n\n";
        $part4 = "\n\n" . ( $type === 'review' ? 'Rating: ' . $this->get_telegram_rating( $comment ) . "\n" : '' ) .
                 'Comment status: <strong>' . $this->telegram_escape( $statusText ) . '</strong>';

        $author       = $this->telegram_escape( get_comment_author( $comment ) );
        $short_author = mb_substr( $author, 0, max( 1, 4000 - mb_strlen( $part1 . $part2 . $part3 . $part4 ) ) );
        if ( $short_author !== $author ) {
            $short_author .= '...';
        }

        $post_title       = $this->telegram_escape( strip_tags( html_entity_decode( get_the_title( $post ) ) ) );
        $short_post_title = mb_substr( $post_title, 0, max( 1, 4000 - mb_strlen( $part1 . $part2 . $part3 . $part4 . $short_author ) ) );
        if ( $short_post_title !== $post_title ) {
            $short_post_title .= '...';
        }

        $content       = $this->telegram_escape( strip_tags( get_comment_text( $comment ) ) );
        $short_content = mb_substr( $content, 0, max( 1, 4000 - mb_strlen( $part1 . $part2 . $part3 . $part4 . $short_author . $short_post_title ) ) );
        if ( $short_content !== $content ) {
            $short_content .= '...';
        }

        return $part1 . $short_author . $part2 . $short_post_title . $part3 . $short_content . $part4;
    }

    /**
     * @param object $comment
     *
     * @return InlineKeyboard
     * @throws TelegramException
     */
    protected function get_reply_markup( $comment ) {
        $status   = $comment->comment_approved;
        $keyboard = new InlineKeyboard( [] );
        if ( $status === 'trash' ) {
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Restore', 'callback_data' => 'restore' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Spam', 'callback_data' => 'spam' ] ) );
        } else if ( $status === 'spam' ) {
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Not Spam', 'callback_data' => 'notspam' ] ) );
        } else if ( $status === '1' || $status === 'approved' ) {
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Unapprove', 'callback_data' => 'unapprove' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Spam', 'callback_data' => 'spam' ] ),
                new InlineKeyboardButton( [ 'text' => 'Trash', 'callback_data' => 'trash' ] ) );
        } else if ( $status === '0' || $status === 'hold' ) {
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Approve', 'callback_data' => 'approve' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Spam', 'callback_data' => 'spam' ] ),
                new InlineKeyboardButton( [ 'text' => 'Trash', 'callback_data' => 'trash' ] ) );
        }

        return $keyboard;
    }

    protected function get_name() {
        return 'comment';
    }

    protected function get_id( $object ) {
        return $object->comment_ID;
    }

    private function get_telegram_rating( $comment ) {
        $rating = max( 0, min( 5, intval( get_comment_meta( $comment->comment_ID, 'rating', true ) ) ) );

        return str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
    }

    protected function should_send_message( $comment ) {
        $type = get_comment_type( $comment );

        return $type === 'comment' || $type === 'review';
    }

    protected function get_object( $object_id ) {
        return get_comment( $object_id );
    }

    protected function get_most_recent() {
        global $wpdb;
        $comment_id = $wpdb->get_var( "
	        SELECT comment_ID
	        FROM {$wpdb->comments}
	        WHERE comment_type IN ('', 'comment', 'review')
            ORDER BY comment_ID DESC
            LIMIT 1
	    ");
        if ( $comment_id === null ) {
            return null;
        }

        return get_comment( $comment_id );
    }

    protected function get_previous( $comment_id ) {
        global $wpdb;
        $next_comment_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT comment_ID
            FROM {$wpdb->comments}
	        WHERE comment_type IN ('', 'comment', 'review')
            AND comment_ID < %d
            ORDER BY comment_ID DESC
            LIMIT 1
        ", $comment_id ) );
        if ( $next_comment_id === null ) {
            return null;
        }

        return get_comment( $next_comment_id );
    }

    protected function get_next( $comment_id ) {
        global $wpdb;
        $comment_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT comment_ID
            FROM {$wpdb->comments}
	        WHERE comment_type IN ('', 'comment', 'review')
            AND comment_ID > %d
            ORDER BY comment_ID ASC
            LIMIT 1
        ", $comment_id ) );
        if ( $comment_id === null ) {
            return null;
        }

        return get_comment( $comment_id );
    }

}
