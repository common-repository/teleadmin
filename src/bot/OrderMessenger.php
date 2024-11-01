<?php

namespace TeleAdmin\Bot;

use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;

class OrderMessenger extends Messenger {

    /**
     * @param \WC_Order $order
     * @param $deleted
     *
     * @return string
     */
    protected function get_text( $order, $deleted ) {
        $status = $order->get_status();

        if ( $status === 'pending' ) {
            $statusText = 'Pending Payment';
        } else if ( $status === 'processing' ) {
            $statusText = 'Processing';
        } else if ( $status === 'on-hold' ) {
            $statusText = 'On-hold';
        } else if ( $status === 'completed' ) {
            $statusText = 'Completed';
        } else if ( $status === 'cancelled' ) {
            $statusText = 'Cancelled';
        } else if ( $status === 'refunded' ) {
            $statusText = 'Refunded';
        } else if ( $status === 'failed' ) {
            $statusText = 'Failed';
        } else {
            $statusText = 'Unknown: ' . $status;
        }
        if ( $deleted ) {
            $statusText = 'Permanently Deleted';
        }

        $part1 = '<a href="' . $this->telegram_escape( $order->get_edit_order_url() ) . '">Order #' .
                 $this->telegram_escape( $order->get_order_number() ) . '</a> has been placed by ' .
                 $this->telegram_escape( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . ':' . "\n\n";
        $part2 = "\n" . 'Total: <strong>' . $this->get_price_string( $order->get_total() ) . "</strong>\n\n" .
                 'Status: <strong>' . $this->telegram_escape( $statusText ) . '</strong>';

        $items_text = '';
        $items      = $order->get_items( array( 'line_item', 'fee', 'shipping', 'tax', 'coupon' ) );
        if ( empty( $items ) ) {
            $items_text = 'Empty order' . "\n";
        }
        foreach ( $items as $item ) {
            if ( $item instanceof \WC_Order_Item_Product ) {
                $items_text .= $item->get_name() . ' (' . $item->get_quantity() . 'x) - ' . $this->get_price_string( $item->get_total() ) . "\n";
            } else if ( $item instanceof \WC_Order_Item_Fee ) {
                $items_text .= $item->get_name() . ' - ' . $this->get_price_string( $item->get_total() ) . "\n";
            } else if ( $item instanceof \WC_Order_Item_Shipping ) {
                $items_text .= $item->get_name() . ' - ' . $this->get_price_string( $item->get_total() ) . "\n";
            } else if ( $item instanceof \WC_Order_Item_Tax ) {
                $items_text .= $item->get_name() . ' - ' . $this->get_price_string( $item->get_tax_total() ) . "\n";
            } else if ( $item instanceof \WC_Order_Item_Coupon ) {
                $items_text .= $item->get_name() . ' - ' . $this->get_price_string( $item->get_discount() ) . "\n";
            }
        }

        $short_items_text = mb_substr( $items_text, 0, max( 1, 4000 - mb_strlen( $part1 . $part2 ) ) );
        if ( $short_items_text !== $items_text ) {
            $short_items_text .= '...';
        }

        return $part1 . $short_items_text . $part2;
    }

    private function get_price_string( $price ) {
        return html_entity_decode( get_woocommerce_currency_symbol() ) . $this->telegram_escape( strval( $price ) );
    }

    /**
     * @param \WC_Order $order
     *
     * @return InlineKeyboard
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    protected function get_reply_markup( $order ) {
        $status   = $order->get_status();
        $keyboard = new InlineKeyboard( [] );

        if ( $status === 'pending' ) {
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Processing', 'callback_data' => 'processing' ] ),
                new InlineKeyboardButton( [ 'text' => 'Completed', 'callback_data' => 'completed' ] ) );
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'On-hold', 'callback_data' => 'on-hold' ] ) );
        } else if ( $status === 'processing' ) {
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Completed', 'callback_data' => 'completed' ] ) );
        } else if ( $status === 'on-hold' ) {
            $keyboard->addRow( new InlineKeyboardButton( [ 'text' => 'Processing', 'callback_data' => 'processing' ] ),
                new InlineKeyboardButton( [ 'text' => 'Completed', 'callback_data' => 'completed' ] ) );
        }

        return $keyboard;
    }

    protected function get_name() {
        return 'order';
    }

    /**
     * @param \WC_Order $object
     *
     * @return int
     */
    protected function get_id( $object ) {
        return $object->get_id();
    }

    protected function should_send_message( $object ) {
        return true;
    }

    protected function get_object( $object_id ) {
        $order = wc_get_order( $object_id );
        if ( $order instanceof \WC_Order ) {
            return $order;
        } else {
            return null;
        }
    }

    protected function get_most_recent() {
        global $wpdb;
        $order_id = $wpdb->get_var( "
	        SELECT ID
	        FROM {$wpdb->posts}
	        WHERE post_type = 'shop_order'
	        AND post_status LIKE 'wc-%'
            ORDER BY ID DESC
            LIMIT 1
	    " );
        if ( $order_id === null ) {
            return null;
        }

        return $this->get_object( $order_id );
    }

    protected function get_previous( $order_id ) {
        global $wpdb;
        $next_order_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT ID
            FROM {$wpdb->posts}
	        WHERE post_type = 'shop_order'
	        AND post_status LIKE 'wc-%'
            AND ID < %d
            ORDER BY ID DESC
            LIMIT 1
        ", $order_id ) );
        if ( $next_order_id === null ) {
            return null;
        }

        return $this->get_object( $next_order_id );
    }

    protected function get_next( $order_id ) {
        global $wpdb;
        $next_order_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT ID
            FROM {$wpdb->posts}
	        WHERE post_type = 'shop_order'
	        AND post_status LIKE 'wc-%'
            AND ID > %d
            ORDER BY ID ASC
            LIMIT 1
        ", $order_id ) );
        if ( $next_order_id === null ) {
            return null;
        }

        return $this->get_object( $next_order_id );
    }

}