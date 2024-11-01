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
 * Group chat created command
 */
class GroupchatcreatedCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'groupchatcreated';

    /**
     * @var string
     */
    protected $description = 'Group chat created';

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
    public function execute()
    {
	    $helper = new CommandHelper($this->getMessage()->getChat(), $this->getTelegram());
	    if (!$helper->is_authorized()) {
		    return $helper->getResponse();
	    }

	    return $helper->getResponse();
    }
}
