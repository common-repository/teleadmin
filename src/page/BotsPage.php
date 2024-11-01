<?php

namespace TeleAdmin\Page;

use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Telegram;
use TeleAdmin\BotWebhook;
use function TeleAdmin\teleadmin_error_log_file;

class BotsPage {

    public static $menu_slug = 'teleadmin-bots';
    public static $nonce_add_bot = 'teleadmin-add-bot-nonce';
    public static $nonce_edit_bot = 'teleadmin-edit-bot-nonce';
    public static $nonce_delete_bot = 'teleadmin-delete-bot-nonce';
    public static $ajax_token_validation = 'teleadmin-token-validation-ajax';

    /**
     * @return array
     */
    public static function generate_invalid_bot_notices() {
        $res = array();

        global $wpdb;
        $bots = $wpdb->get_results( "
            SELECT *
            FROM teleadmin_bots
            WHERE invalid = 1
        " );
        foreach ( $bots as $bot ) {
            $res[] = 'TeleAdmin has noticed that the bot @' . $bot->username .
                     ' does not work properly and <strong>cannot send messages</strong> anymore. ' .
                     'Usually the reason is that the Telegram token has been revoked or the bot has been deleted. ' .
                     'Please <a href="' . add_query_arg( 'edit', $bot->bot_id, menu_page_url( BotsPage::$menu_slug, false ) ) .
                     '">edit</a> this bot and fix the issue.';
        }

        return $res;
    }

    public static function generate_webhook_not_working_notices() {
        $res = array();

        global $wpdb;
        $bots = $wpdb->get_results( "
            SELECT *
            FROM teleadmin_bots
            WHERE webhook = 0
        " );
        foreach ( $bots as $bot ) {
            $res[] = 'The webhook of bot @' . $bot->username . ' does not work. ' .
                     'This can have a significant impact on the performance of your website and your bot. ' .
                     'Usually the reason is that your website does not use HTTPS, ' .
                     'which is very bad practice in general.';
        }

        return $res;
    }

    private $error_messages = array();
    private $success_messages = array();

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_entry' ) ); // Add menu entry
        add_action( 'wp_ajax_' . BotsPage::$ajax_token_validation, array(
            $this,
            'token_validation'
        ) ); // Ajax token validation

        add_action( 'admin_action_teleadmin-delete-bot', array( $this, 'delete_bot' ) ); // Delete bot action
    }

    /**
     * Add page to menu.
     */
    public function add_menu_entry() {
        add_submenu_page( ChatsPage::$menu_slug, 'TeleAdmin Bots', 'Bots', 'manage_options', BotsPage::$menu_slug, array(
            $this,
            'page_html'
        ) );
    }

    /**
     * Display page.
     */
    public function page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['edit'] ) ) {
            $this->edit_page_html();
        } else {
            $this->list_page_html();
        }
    }

    /**
     * Bot Edit page.
     */
    private function edit_page_html() {
        global $wpdb;
        $bot = $wpdb->get_row( $wpdb->prepare( "
            SELECT *
            FROM teleadmin_bots
            WHERE bot_id = %d
        ", $_GET['edit'] ) );

        if ( $bot === null ) {
            echo 'Invalid bot id.';

            return;
        }

        if ( isset( $_POST['token'] ) ) {
            if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], BotsPage::$nonce_edit_bot ) ) {
                $this->edit_bot();
            }
        }

        $bot = $wpdb->get_row( $wpdb->prepare( "
            SELECT *
            FROM teleadmin_bots
            WHERE bot_id = %d
        ", $_GET['edit'] ) );

        if ( $bot === null ) {
            echo 'Invalid bot id.';

            return;
        }

        wp_enqueue_script( 'teleadmin_bots_javascript' );

        ?>
        <div class="wrap">
            <h1>Edit TeleAdmin Bot</h1>
            <?php $this->print_notices(); ?>
            <div id="<?php echo BotsPage::$ajax_token_validation; ?>"
                 data-nonce="<?php echo wp_create_nonce( BotsPage::$ajax_token_validation ); ?>"></div>
            <form method="post">
                <input type="hidden" name="_wpnonce"
                       value="<?php echo wp_create_nonce( BotsPage::$nonce_edit_bot ); ?>"/>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th>
                            <label for="token">Telegram Token</label>
                        </th>
                        <td>
                            <input name="token" id="token" type="text" class="code" size="50" aria-required="true"
                                   value="<?php echo $bot->token; ?>"/>
                            <p id="token-validation">Please enter your Telegram Bot token.</p>
                            <p>
                                This feature should only be used if the bot username or token has changed (for example
                                after revoking the old one), not for changing the bot itself.<br/>

                                Therefore you should make sure that the bot belonging to the entered token is the same
                                as it was before.
                                Otherwise you have to accept and reject all chats again.
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <p><input type="submit" name="submit" id="submit" class="button button-primary" value="Update Bot"/></p>
            </form>
        </div>
        <?php
    }

    /**
     * Bot list page.
     */
    private function list_page_html() {
        wp_enqueue_script( 'teleadmin_bots_javascript' );

        if ( isset( $_POST['token'] ) ) {
            if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], BotsPage::$nonce_add_bot ) ) {
                $this->add_bot();
            }
        }

        $this->error_messages = array_merge( $this->error_messages, BotsPage::generate_invalid_bot_notices() );
        $this->error_messages = array_merge( $this->error_messages, BotsPage::generate_webhook_not_working_notices() );

        $botListTableHeaderHtml = '
            <tr>
                <th scope="col" id="title" class="manage-column column-title column-primary">Username</th>
                <th scope="col" class="manage-column column-teleadmin-token">Telegram Token</th>
            </tr>
        ';

        global $wpdb;
        $bots = $wpdb->get_results( "
		    SELECT bot_id, username, token
		    FROM teleadmin_bots
		" );

        ?>
        <div class="wrap">
            <h1>TeleAdmin Bots</h1>
            <?php $this->print_notices(); ?>
            <div id="col-container" class="wp-clearfix">
                <div id="col-right">
                    <div class="col-wrap">
                        <table class="wp-list-table widefat fixed striped">
                            <thead><?php echo $botListTableHeaderHtml; ?></thead>
                            <tbody id="the-list">
                            <?php if ( empty( $bots ) ) { ?>
                                <tr class="no-items">
                                    <td colspan="2">No TeleAdmin Bots found.</td>
                                </tr>
                            <?php } else {
                                foreach ( $bots as $bot ): ?>
                                    <tr>
                                        <td class="column-title has-row-actions column-primary"
                                            data-colname="Username">
                                            @<?php echo $bot->username; ?>
                                            <div class="row-actions">
                                                <span>
                                                    <a href="<?php echo add_query_arg( array( 'edit' => $bot->bot_id ) ); ?>">Edit</a>
                                                </span> |
                                                <span>
                                                    <a style="color: #a00;" href="<?php echo add_query_arg( array(
                                                        '_wpnonce' => wp_create_nonce( BotsPage::$nonce_delete_bot ),
                                                        'action'   => 'teleadmin-delete-bot',
                                                        'bot'      => $bot->bot_id
                                                    ), remove_query_arg( 'page' ) ); ?>"
                                                       onclick="return confirm('Are you sure? This will reset all chat settings, i.e. you will need to confirm all chats again if you want to re-add this bot.')">Delete</a>
                                                </span>
                                            </div>
                                            <button type="button" class="toggle-row">
                                                <span class="screen-reader-text">Show more details</span>
                                            </button>
                                        </td>
                                        <td class="column-teleadmin-token" data-colname="Telegram Token">
                                            <kbd><?php echo $bot->token; ?></kbd></td>
                                    </tr>
                                <?php endforeach;
                            } ?>
                            </tbody>
                            <tfoot><?php echo $botListTableHeaderHtml; ?></tfoot>
                        </table>
                    </div>
                </div>
                <div id="col-left">
                    <div class="col-wrap">
                        <div class="form-wrap">
                            <h2>Add New Bot</h2>
                            You need to create a new Bot on Telegram first. Follow these instructions for doing so:
                            <ol>
                                <li>Open the Telegram App on any device (e.g. phone or web) and make sure you are
                                    logged
                                    in.
                                </li>
                                <li>Find the official Telegram Bot called <a
                                            href="https://t.me/BotFather">@BotFather</a> and if needed, press
                                    <kbd>Start</kbd>.
                                </li>
                                <li>Send the command <kbd>/newbot</kbd>.</li>
                                <li>Telegram asks you for a name for your bot.</li>
                                <li>Telegram asks you for a username for your bot.</li>
                                <li>
                                    Telegram gives you a token for access to the HTTP API which looks like this:
                                    <kbd>110201543:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw</kbd>.
                                    Never give this token to anybody else, as it gives complete control over your
                                    bot.
                                </li>
                                <li>Copy the token sent to you and paste it into the text field below.</li>
                                <li>Press <kbd>Add Bot</kbd>.</li>
                            </ol>
                            <div id="<?php echo BotsPage::$ajax_token_validation; ?>"
                                 data-nonce="<?php echo wp_create_nonce( BotsPage::$ajax_token_validation ); ?>"></div>
                            <form method="post" action="<?php menu_page_url( BotsPage::$menu_slug ); ?>">
                                <input type="hidden" name="_wpnonce"
                                       value="<?php echo wp_create_nonce( BotsPage::$nonce_add_bot ); ?>"/>
                                <div class="form-field form-required">
                                    <label for="token">Telegram Token</label>
                                    <input name="token" id="token" type="text" class="code" aria-required="true"/>
                                    <p id="token-validation">Please enter your Telegram Bot token.</p>
                                </div>
                                <p class="submit">
                                    <input type="submit" name="submit" id="submit" class="button button-primary"
                                           value="Add Bot"/>
                                </p>
                            </form>

                            <h2>Improve Your Bot</h2>
                            You can always talk to the <a href="https://t.me/BotFather">@BotFather</a> if you want to
                            add a profile picture or change the name of your bot.<br/>

                            In order to get suggestions by your bot when typing a command (e.g. /comments or /orders),
                            you need to send the command <kbd>/setcommands</kbd> to <a href="https://t.me/BotFather">@BotFather</a>
                            and paste the following list into the chat with him:
                            <pre>
help - Show bot help message
comments - Show recent comments
posts - Show recent posts
orders - Show recent orders
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Prints all error and success messages.
     */
    private function print_notices() {
        foreach ( $this->error_messages as $error_message ): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo $error_message; ?></p>
            </div>
        <?php endforeach; ?>
        <?php foreach ( $this->success_messages as $success_message ): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo $success_message; ?></p>
            </div>
        <?php endforeach;
    }

    /**
     * Ajax token validation.
     */
    public function token_validation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die();
        }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], BotsPage::$ajax_token_validation ) ) {
            wp_die( 'Invalid nonce.' );
        }
        if ( ! isset( $_POST['token'] ) ) {
            wp_die();
        }
        $token  = sanitize_text_field( $_POST['token'] );
        $result = array( 'token' => $token );
        try {
            $result['username'] = $this->validate_token( $token );
        } catch ( TelegramException $e ) {
            if ( $e->getMessage() === 'Error: 401 - Unauthorized' ) {
                $result['error'] = 'Invalid bot token';
            } else {
                $result['error'] = $e->getMessage();
            }
        }
        echo json_encode( $result );
        wp_die();
    }

    /**
     * Action called when delete bot link is clicked.
     */
    public function delete_bot() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die();
        }
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], BotsPage::$nonce_delete_bot ) ) {
            wp_die( 'Invalid nonce.' );
        }
        if ( ! isset( $_GET['bot'] ) ) {
            wp_die( 'No bot id given' );
        }
        $bot_id = $_GET['bot'];

        global $wpdb;
        $wpdb->query( $wpdb->prepare( "
            DELETE
            FROM teleadmin_messages
            WHERE chat_id IN (
              SELECT chat_id FROM
              teleadmin_chats
              WHERE bot_id = %d
            )
        ", $bot_id ) );
        $wpdb->query( $wpdb->prepare( "
            DELETE
            FROM teleadmin_chats
            WHERE bot_id = %d
        ", $bot_id ) );
        $wpdb->delete( 'teleadmin_bots', array( 'bot_id' => $bot_id ) );

        wp_redirect( wp_get_referer() );
    }

    /**
     * Add Bot form used.
     * Permissions and nonce already checked.
     */
    private function add_bot() {
        global $wpdb;

        // Check whether token is valid and get username
        $token    = sanitize_text_field( $_POST['token'] );
        $username = null;
        try {
            $username = $this->validate_token( $token );
        } catch ( TelegramException $e ) {
            array_push( $this->error_messages, $e->getMessage() );

            return;
        }

        // Check whether bot is already in database
        $exists = $wpdb->get_var( $wpdb->prepare( "
		    SELECT COUNT(*)
		    FROM teleadmin_bots
            WHERE token = %s
            OR username = %s
		", $token, $username ) );
        if ( $exists ) {
            array_push( $this->error_messages, 'Bot with this token or username does already exist.' );

            return;
        }

        // Try to enable webhook
        $webhookEnabled = false;
        try {
            $telegram       = new Telegram( $token, $username );
            $response       = $telegram->setWebhook( [
                'url' => BotWebhook::getWebhookUrl( $token )
            ] );
            $webhookEnabled = $response->isOk();
        } catch ( TelegramException $e ) {
            error_log( $e, 3, teleadmin_error_log_file() );
        }
        $wpdb->insert( 'teleadmin_bots', array(
            'token'    => $token,
            'username' => $username,
            'webhook'  => $webhookEnabled
        ), array( '%s', '%s', '%d' ) );

        array_push( $this->success_messages,
            'Successfully added bot <a href="https://telegram.me/' . $username . '">@' . $username . '</a>.<br/>' .
            'Now you can add this bot to a group or write him a private message on Telegram and confirm the chat afterwards on the <a href="' . menu_page_url( ChatsPage::$menu_slug, false ) . '">Chat page</a>.' );
    }

    /**
     * Edit Bot form used.
     * Permissions and nonce already checked.
     */
    private function edit_bot() {
        global $wpdb;

        // Check whether token is valid and get username
        $token    = sanitize_text_field( $_POST['token'] );
        $username = null;
        try {
            $username = $this->validate_token( $token );
        } catch ( TelegramException $e ) {
            array_push( $this->error_messages, $e->getMessage() );

            return;
        }

        // Try to enable webhook
        $webhookEnabled = false;
        try {
            $telegram       = new Telegram( $token, $username );
            $response       = $telegram->setWebhook( [
                'url' => BotWebhook::getWebhookUrl( $token )
            ] );
            $webhookEnabled = $response->isOk();
        } catch ( TelegramException $e ) {
            error_log( $e, 3, teleadmin_error_log_file() );
        }
        $wpdb->update( 'teleadmin_bots', array(
            'token'    => $token,
            'username' => $username,
            'webhook'  => $webhookEnabled,
            'invalid'  => 0
        ), array( 'bot_id' => $_GET['edit'] ), array( '%s', '%s', '%d', '%d' ), '%d' );

        array_push( $this->success_messages, 'Successfully edited bot @' . $username . '.' );
    }

    /**
     * Gives the username corresponding to the given token or throws an exception if not possible.
     *
     * @param string $token
     *
     * @return string username
     * @throws TelegramException
     */
    private function validate_token( string $token ) {
        $telegram = new Telegram( $token );
        $response = $telegram->getMe();
        if ( ! $response->isOk() ) {
            throw new TelegramException( 'Error: ' . $response->getErrorCode() . ' - ' . $response->getDescription() );
        }

        return $response->getResult()->getUsername();
    }

}