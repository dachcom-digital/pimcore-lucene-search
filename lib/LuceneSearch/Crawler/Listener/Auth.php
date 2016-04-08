<?php

namespace LuceneSearch\Crawler\Listener;

use Symfony\Component\EventDispatcher\Event;

class Auth {

    /**
     * @var null
     */
    var $username = NULL;

    /**
     * @var null
     */
    var $password = NULL;

    public function __construct( $username = NULL, $password = NULL)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     */
    public  function setAuth(Event $event) {

        $client = $event->getSubject()->getRequestHandler()->getClient();
        $client->setDefaultOption('auth', array($this->username, $this->password, 'Basic'));
    }

}