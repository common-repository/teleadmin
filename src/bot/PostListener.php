<?php

namespace TeleAdmin\Bot;

/**
 * Class PostListener
 * Sends/edits messages on post updates.
 *
 * @package TeleAdmin\Bot
 */
class PostListener {

    public function __construct() {
        add_action( 'publish_post', array( $this, 'post_posted' ) );
        add_action( 'pending_post', array( $this, 'post_posted' ) );
        add_action( 'draft_post', array( $this, 'post_posted' ) );
        add_action( 'future_post', array( $this, 'post_posted' ) );
        add_action( 'private_post', array( $this, 'post_posted' ) );
        add_action( 'trash_post', array( $this, 'post_posted' ) );
        add_action( 'before_delete_post', array( $this, 'post_deleted' ) );
    }

    /**
     * @param $post_id int
     */
    public function post_posted( $post_id ) {
        ( new PostMessenger() )->update_messages( get_post( $post_id ), false );
    }

    /**
     * @param $post_id int
     */
    public function post_deleted( $post_id ) {
        ( new PostMessenger() )->update_messages( get_post( $post_id ), true );
    }

}