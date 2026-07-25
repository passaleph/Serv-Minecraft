<?php

namespace SetHomePlugin;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use SetHomePlugin\commands\SetHomeCommand;
use SetHomePlugin\commands\HomeCommand;
use SetHomePlugin\commands\DelHomeCommand;
use SetHomePlugin\commands\HomesCommand;

use SetHomePlugin\listeners\PlayerQuitListener;


class Main extends PluginBase{


    private static Main $instance;


    private HomeManager $homeManager;

    private CooldownManager $cooldownManager;


    private Config $config;

    private Config $messages;



    public function onEnable(): void{


        self::$instance = $this;



        @mkdir($this->getDataFolder());



        // Création des fichiers de configuration

        $this->saveResource("config.yml");

        $this->saveResource("messages.yml");




        // Chargement du config.yml

        $this->config = new Config(

            $this->getDataFolder() . "config.yml",

            Config::YAML

        );




        // Chargement du messages.yml

        $this->messages = new Config(

            $this->getDataFolder() . "messages.yml",

            Config::YAML

        );




        // Gestionnaire de homes

        $this->homeManager = new HomeManager($this);




        // Gestionnaire de cooldown

        $this->cooldownManager = new CooldownManager($this);





        // Enregistrement des commandes

        $this->getServer()->getCommandMap()->registerAll(

            "sethomeplugin",

            [

                new SetHomeCommand(),

                new HomeCommand(),

                new DelHomeCommand(),

                new HomesCommand()

            ]

        );





        // Enregistrement des événements

        $this->getServer()
            ->getPluginManager()
            ->registerEvents(

                new PlayerQuitListener(),

                $this

            );





        $this->getLogger()->info(

            "SetHomePlugin active !"

        );

    }






    public function getHomeManager(): HomeManager{


        return $this->homeManager;

    }






    public function getCooldownManager(): CooldownManager{


        return $this->cooldownManager;

    }






    public function getConfigFile(): Config{


        return $this->config;

    }






    public function getMessages(): Config{


        return $this->messages;

    }






    public static function getInstance(): Main{


        return self::$instance;

    }


}