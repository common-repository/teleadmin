<?php

namespace TeleAdmin\Page;

class ChatsListTable extends WP_List_Table {

    public $current_url;

    /**
     * ChatsListTable constructor.
     *
     * @param $is_ajax bool Indicates whether the page has been called via ajax
     */
    function __construct( $is_ajax ) {
        parent::__construct( $is_ajax, array(
            'singular' => 'chat',
            'plural'   => 'chats',
            'ajax'     => true,
            'screen'   => ChatsPage::$menu_slug
        ) );
    }

    /**
     * @return string Current view: unconfirmed / confirmed / rejected
     */
    function get_current_view() {
        $current = isset( $_GET['confirmation-status'] ) ? $_GET['confirmation-status'] : 'unconfirmed';
        if ( $current != 'unconfirmed' && $current != 'confirmed' && $current != 'rejected' ) {
            $current = 'unconfirmed';
        }

        return $current;
    }

    /**
     * @return array List of possible views
     */
    function get_views() {
        $current = $this->get_current_view();

        return array(
            'unconfirmed' => '<a href="' . remove_query_arg( 'confirmation-status', $this->current_url ) . '" ' . ( $current == 'unconfirmed' ? 'class="current"' : '' ) . '>Unconfirmed</a>',
            'confirmed'   => '<a href="' . add_query_arg( 'confirmation-status', 'confirmed', $this->current_url ) . '" ' . ( $current == 'confirmed' ? 'class="current"' : '' ) . '>Confirmed</a>',
            'rejected'    => '<a href="' . add_query_arg( 'confirmation-status', 'rejected', $this->current_url ) . '" ' . ( $current == 'rejected' ? 'class="current"' : '' ) . '>Rejected</a>'
        );
    }

    /**
     * @param $item array Chat object array
     *
     * @return string Title and row actions HTML
     */
    function column_title( $item ) {
        $title = '<strong>' . ( $item['type'] == 'private' ? '@' . $item['username'] : $item['title'] ) . '</strong>';

        $row_actions = array();
        if ( $this->get_current_view() != 'confirmed' ) {
            $row_actions['confirm'] = '<a style="color: #006505;" href="' . add_query_arg( array(
                    '_wpnonce' => wp_create_nonce( 'bulk-chats' ),
                    'action'   => 'teleadmin-chat-confirm',
                    'chat[]'   => $item['chat_id']
                ), remove_query_arg( 'page', $this->current_url ) ) . '">Confirm</a>';
        }
        if ( $this->get_current_view() != 'rejected' ) {
            $row_actions['reject'] = '<a style="color: #a00;" href="' . add_query_arg( array(
                    '_wpnonce' => wp_create_nonce( 'bulk-chats' ),
                    'action'   => 'teleadmin-chat-reject',
                    'chat[]'   => $item['chat_id']
                ), remove_query_arg( 'page', $this->current_url ) ) . '">Reject</a>';
        }
        $row_actions['delete'] = '<a style="color: #a00;" href="' . add_query_arg( array(
                '_wpnonce' => wp_create_nonce( 'bulk-chats' ),
                'action'   => 'teleadmin-chat-delete',
                'chat[]'   => $item['chat_id']
            ), remove_query_arg( 'page', $this->current_url ) ) . '">Delete</a>';

        return $title . $this->row_actions( $row_actions, $this->get_current_view() == 'unconfirmed' );
    }

    /**
     * @param $item array Chat object array
     *
     * @return string Username HTML
     */
    function column_bot_username( $item ) {
        return '@' . $item['bot_username'];
    }

    /**
     * @param $item array Chat object array
     *
     * @return mixed Telegram ID HTML
     */
    function column_telegram_id( $item ) {
        return $item['telegram_id'];
    }

    /**
     * @param object $item Chat object array
     *
     * @return string Checkbox HTML
     */
    function column_cb( $item ) {
        return '<input type="checkbox" name="chat[]" value="' . $item['chat_id'] . '" />';
    }

    /**
     * @return array List of columns
     */
    function get_columns() {
        return array(
            'cb'           => '<input type="checkbox" />',
            'title'        => 'Username or Group Title',
            'bot_username' => 'Bot Username',
            'telegram_id'  => 'Telegram Chat ID'
        );
    }

    /**
     * @return array List of sortable columns
     */
    function get_sortable_columns() {
        return array(
            'title'        => array( 'title', false ),
            'bot_username' => array( 'bot_username', false ),
            'telegram_id'  => array( 'telegram_id', false )
        );
    }

    /**
     * @return array List of bulk actions
     */
    function get_bulk_actions() {
        $bulk_actions = array();
        if ( $this->get_current_view() != 'confirmed' ) {
            $bulk_actions['teleadmin-chat-confirm'] = 'Accept';
        }
        if ( $this->get_current_view() != 'rejected' ) {
            $bulk_actions['teleadmin-chat-reject'] = 'Reject';
        }
        $bulk_actions['teleadmin-chat-delete'] = 'Delete';

        return $bulk_actions;
    }

    /**
     * Prints HTML if no item is found.
     */
    function no_items() {
        if ( $this->get_current_view() == 'unconfirmed' ) {
            echo '<span class="spinner is-active" style="float:none;"></span> Waiting for chats...';
        } else {
            echo 'No TeleAdmin Chats found.';
        }
    }

    /**
     * Fetches table items.
     */
    function prepare_items() {
        global $wpdb;

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
            'title'
        );

        $current_view = $this->get_current_view();

        $per_page     = $this->get_items_per_page( 'edit_chats_per_page' );
        $current_page = $this->get_pagenum();
        $count        = $wpdb->get_var( "
			SELECT COUNT(*)
			FROM teleadmin_chats
			WHERE confirmed " . ( $current_view == 'unconfirmed' ? ' IS NULL' : ( $current_view == 'confirmed' ? '= 1' : '= 0' ) ) . "
		" );

        $orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array(
            'title',
            'bot_username',
            'telegram_id'
        ) ) ? $_GET['orderby'] : 'chat_id';
        if ( $orderby == 'title' ) {
            $orderby = "IF(type='private', teleadmin_chats.username, title)";
        }
        $order = isset( $_GET['order'] ) && in_array( $_GET['order'], array( 'asc', 'desc' ) ) ? $_GET['order'] : 'asc';

        $this->items = $wpdb->get_results( $wpdb->prepare( "
			SELECT chat_id, telegram_id, type, title, teleadmin_chats.username AS username, teleadmin_bots.username AS bot_username
			FROM teleadmin_chats
			LEFT JOIN teleadmin_bots ON (teleadmin_bots.bot_id = teleadmin_chats.bot_id)
			WHERE confirmed " . ( $current_view == 'unconfirmed' ? ' IS NULL' : ( $current_view == 'confirmed' ? '= 1' : '= 0' ) ) . "
			ORDER BY $orderby $order
			LIMIT %d, %d
		", ( $current_page - 1 ) * $per_page, $current_page * $per_page - 1 ), ARRAY_A );

        $this->set_pagination_args( array(
            'total_items' => $count,
            'per_page'    => $per_page,
            'total_pages' => ceil( $count / $per_page )
        ) );
    }

    /**
     * Modifies the display function such that it includes the javascript file and the hidden field 'last-known-chat-id'
     * which is used for checking whether the table should be updated via ajax.
     */
    function display() {
        global $wpdb;
        if ( $this->get_current_view() == 'unconfirmed' ) {
            wp_enqueue_script( 'teleadmin_unconfirmed_refresh_javascript' );
            $autoIncrement = $wpdb->get_var( "
				SELECT COALESCE(MAX(chat_id), -1)
				FROM teleadmin_chats
			" );
            echo '<input id="last-known-chat-id" name="last-known-chat-id" type="hidden" value="' . $autoIncrement . '" />';
        }
        parent::display();
    }

    /**
     * Prints the table for ajax use and dies.
     */
    function ajax_response() {
        $this->display();
        wp_die();
    }

}
