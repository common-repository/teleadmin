<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use TeleAdmin\Bot\CommandHelper;
use TeleAdmin\Bot\CommentMessenger;
use TeleAdmin\Bot\OrderMessenger;
use function TeleAdmin\teleadmin_needed_woocommerce_version;
use function TeleAdmin\teleadmin_woocommerce_is_installed;

class OrdersCommand extends UserCommand {

    protected $name = 'orders';
    protected $description = 'Show recent orders';
    protected $usage = '/orders';
    protected $version = '1.0.0';

    /**
     * Execute command
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute() {
        $helper = new CommandHelper( $this->getMessage()->getChat(), $this->getTelegram() );
        $helper->update_admin_username();
        if ( ! $helper->is_authorized() ) {
            return $helper->send_not_authorized_notice_if_not_first_message();
        }

        if ( teleadmin_woocommerce_is_installed() ) {
            ( new OrderMessenger() )->send_paging_message( $helper->getTeleAdminBot(), $helper->getOurChat() );
        } else {
            return $helper->getTeleAdminBot()->sendMessage( [
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'text'    => 'WooCommerce version ≥' . teleadmin_needed_woocommerce_version() . ' is required.'
            ] );
        }

        return $helper->getResponse();
    }

}