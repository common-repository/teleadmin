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
 * Edited channel post command
 */
class EditedchannelpostCommand extends SystemCommand {
	/**
	 * @var string
	 */
	protected $name = 'editedchannelpost';

	/**
	 * @var string
	 */
	protected $description = 'Handle edited channel post';

	/**
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Execute command
	 *
	 * @return mixed
	 * @throws \Longman\TelegramBot\Exception\TelegramException
	 */
	public function execute() {
		$helper = new CommandHelper( $this->getEditedChannelPost()->getChat(), $this->getTelegram() );
		if ( ! $helper->is_authorized() ) {
			return $helper->getResponse();
		}

		return $helper->getResponse();
	}
}
