<?php

namespace TeleAdmin\Bot;

/**
 * Class CommentListener
 * Sends/edits messages on comment updates.
 *
 * @package TeleAdmin\Bot
 */
class CommentListener {

    public function __construct() {
        add_action( 'comment_post', array( $this, 'comment_posted' ) ); // Posted
        add_action( 'edit_comment', array( $this, 'comment_edited' ) ); // Edited in administration interface
        add_action( 'transition_comment_status', array(
            $this,
            'comment_status_transition'
        ), 10, 3 ); // New status (also deletion)
        add_action( 'trashed_post_comments', array(
            $this,
            'post_comments_trashed'
        ), 10, 2 ); // Trash comments because post has been trashed
        add_action( 'untrashed_post_comments', array(
            $this,
            'post_comments_untrashed'
        ) ); // Untrash comments because post has been untrashed
    }

    /**
     * @param $comment_id int
     */
    public function comment_posted( $comment_id ) {
        ( new CommentMessenger() )->update_messages( get_comment( $comment_id ), false );
    }

    /**
     * @param $comment_id int
     */
    public function comment_edited( $comment_id ) {
        ( new CommentMessenger() )->update_messages( get_comment( $comment_id ), false );
    }

    /**
     * @param $new_status string
     * @param $old_status string
     * @param $comment \WP_Comment
     */
    public function comment_status_transition( $new_status, $old_status, $comment ) {
        ( new CommentMessenger() )->update_messages( $comment, $new_status === 'delete' );
    }

    /**
     * @param $post_id int
     * @param $statuses array Keys are trashed comment ids
     */
    public function post_comments_trashed( $post_id, $statuses ) {
        foreach ( array_keys( $statuses ) as $comment_id ) {
            ( new CommentMessenger() )->update_messages( get_comment( $comment_id ), false );
        }
    }

    /**
     * @param $post_id int
     */
    public function post_comments_untrashed( $post_id ) {
        $comments = get_comments( array( 'post_id' => $post_id ) );
        foreach ( $comments as $comment ) {
            ( new CommentMessenger() )->update_messages( $comment, false );
        }
    }

}