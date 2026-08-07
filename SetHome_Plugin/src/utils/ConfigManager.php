<?php

namespace utils;

use pocketmine\utils\Config;


class ConfigManager{


    private Config $config;



    public function __construct(Config $config){

        $this->config = $config;

    }



    public function get(string $key){

        return $this->config->get($key);

    }


}