<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use TeleAdmin\Bot\CommandHelper;
use TeleAdmin\Bot\CommentMessenger;

class CommentsCommand extends UserCommand {

	protected $name = 'comments';
	protected $description = 'Show recent comments';
	protected $usage = '/comments';
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

        (new CommentMessenger())->send_paging_message($helper->getTeleAdminBot(), $helper->getOurChat());

        return $helper->getResponse();
	}

}