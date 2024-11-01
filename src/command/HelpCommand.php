<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Commands\UserCommand;
use TeleAdmin\Bot\CommandHelper;

class HelpCommand extends UserCommand {

    protected $name = 'help';
    protected $description = 'List all commands';
    protected $usage = '/help';
    protected $version = '1.0.0';

    public function execute() {
        $helper = new CommandHelper( $this->getMessage()->getChat(), $this->getTelegram() );
        $helper->update_admin_username();
        if ( ! $helper->is_authorized() ) {
            return $helper->send_not_authorized_notice_if_not_first_message();
        }

        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $data    = [
            'chat_id'    => $chat_id,
            'parse_mode' => 'markdown',
        ];

        $commands = array_filter(
            $this->telegram->getCommandsList(),
            /**
             * @param $command Command
             *
             * @return mixed
             */
            function ( $command ) {
                return $command->isUserCommand();
            } );
        ksort( $commands );

        $data['text'] = '*Commands List*:' . PHP_EOL;
        foreach ( $commands as $command ) {
            $data['text'] .= '/' . $command->getName() . ' - ' . $command->getDescription() . PHP_EOL;
        }

        return $helper->getTeleAdminBot()->sendMessage( $data );
    }

}
