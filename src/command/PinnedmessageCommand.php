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
 * Pinned message command
 */
class PinnedmessageCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'pinnedmessage';

    /**
     * @var string
     */
    protected $description = 'Message was pinned';

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
    public function execute()
    {
	    $helper = new CommandHelper($this->getMessage()->getChat(), $this->getTelegram());
	    $helper->update_admin_username();
	    if (!$helper->is_authorized()) {
		    return $helper->getResponse();
	    }

	    return $helper->getResponse();
    }
}
