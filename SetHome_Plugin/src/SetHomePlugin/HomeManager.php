<?php

namespace SetHomePlugin;

use pocketmine\player\Player;
use pocketmine\utils\Config;

class HomeManager{

    private Main $plugin;
    private Config $homes;


    public function __construct(Main $plugin){

        $this->plugin = $plugin;

        @mkdir($plugin->getDataFolder());

        $this->homes = new Config(
            $plugin->getDataFolder() . "homes.json",
            Config::JSON
        );
    }


    /**
     * Ajouter ou modifier un home
     */
    public function setHome(Player $player, string $name): bool{

        $uuid = $player->getUniqueId()->toString();

        $data = $this->homes->get($uuid, []);


        // Vérifie la limite des 4 homes
        if(!isset($data[$name]) && count($data) >= 4){

            return false;
        }


        $location = $player->getLocation();


        $data[$name] = [

            "world" => $location->getWorld()->getFolderName(),

            "x" => $location->getX(),
            "y" => $location->getY(),
            "z" => $location->getZ(),

            "yaw" => $location->getYaw(),
            "pitch" => $location->getPitch()
        ];


        $this->homes->set($uuid, $data);
        $this->homes->save();


        return true;
    }



    /**
     * Récupérer un home
     */
    public function getHome(Player $player, string $name): ?array{

        $uuid = $player->getUniqueId()->toString();

        $data = $this->homes->get($uuid, []);


        return $data[$name] ?? null;
    }



    /**
     * Supprimer un home
     */
    public function deleteHome(Player $player, string $name): bool{

        $uuid = $player->getUniqueId()->toString();

        $data = $this->homes->get($uuid, []);


        if(!isset($data[$name])){

            return false;
        }


        unset($data[$name]);


        $this->homes->set($uuid, $data);
        $this->homes->save();


        return true;
    }



    /**
     * Liste des homes
     */
    public function getHomes(Player $player): array{

        $uuid = $player->getUniqueId()->toString();

        return $this->homes->get($uuid, []);
    }

}