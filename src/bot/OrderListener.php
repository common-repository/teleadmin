<?php

namespace TeleAdmin\Bot;

/**
 * Class OrderListener
 * Sends/edits messages on order updates.
 *
 * @package TeleAdmin\Bot
 */
class OrderListener {

    public function __construct() {
        add_action( 'woocommerce_update_order', array( $this, 'update_order' ) );
        add_action( 'woocommerce_delete_order', array( $this, 'delete_order' ) );
    }

    /**
     * @param $order_id int
     */
    public function update_order( $order_id ) {
        $this->update_order_messages( $order_id, false );
    }

    /**
     * @param $order_id int
     */
    public function delete_order( $order_id ) {
        $this->update_order_messages( $order_id, true );
    }

    /**
     * @param $order_id int
     * @param $deleted bool
     */
    private function update_order_messages( $order_id, $deleted ) {
        $type = get_post_type( $order_id );
        if ( $type !== 'shop_order' ) {
            return;
        }
        $order = wc_get_order( $order_id );

        if ( $order instanceof \WC_Order ) {
            ( new OrderMessenger() )->update_messages( $order, $deleted );
        }
    }

}