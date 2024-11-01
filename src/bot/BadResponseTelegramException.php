<?php

namespace TeleAdmin\Bot;

use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class BadResponseTelegramException extends TelegramException {

    /**
     * @var ServerResponse
     */
    private $response;

    /**
     * @param $response ServerResponse
     */
    public function __construct( $response ) {
        $this->response = $response;
        parent::__construct( "Telegram exception: " . $response->getErrorCode() . ' - ' . $response->getDescription() );
    }

    /**
     * @return ServerResponse
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * @return bool
     */
    public function isMessageNotFoundError() {
        return $this->response->getErrorCode() === 400 && strstr( $this->response, 'message to edit not found' ) !== false;
    }

}