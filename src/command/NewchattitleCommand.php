<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use TeleAdmin\Bot\CommandHelper;

/**
 * New chat title command
 */
class NewchattitleCommand extends SystemCommand {
	/**
	 * @var string
	 */
	protected $name = 'newchattitle';

	/**
	 * @var string
	 */
	protected $description = 'New chat Title';

	/**
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Command execute method
	 *
	 * @return mixed
	 * @throws \Longman\TelegramBot\Exception\TelegramException
	 */
	public function execute() {
		$helper = new CommandHelper( $this->getMessage()->getChat(), $this->getTelegram() );

		global $wpdb;
		$wpdb->update( 'teleadmin_chats', array(
			'title' => $this->getMessage()->getNewChatTitle()
		), array(
			'chat_id' => $helper->getOurChat()->chat_id
		), '%s', '%d' );

		if ( ! $helper->is_authorized() ) {
			return $helper->getResponse();
		}

		return $helper->getResponse();
	}
}
