<?php

namespace utils;

use pocketmine\utils\Config;


class MessageManager{


    private Config $messages;



    public function __construct(Config $messages){

        $this->messages = $messages;

    }



    public function get(string $key){

        return $this->messages->get($key);

    }


}