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
 * Start command
 */
class StartCommand extends SystemCommand {
	/**
	 * @var string
	 */
	protected $name = 'start';

	/**
	 * @var string
	 */
	protected $description = 'Start command';

	/**
	 * @var string
	 */
	protected $usage = '/start';

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
		$helper->update_admin_username();
		if ( ! $helper->is_authorized() ) {
			return $helper->send_not_authorized_notice_if_not_first_message();
		}

		return $helper->getResponse();
	}
}
