<?php

namespace SetHomePlugin;

use pocketmine\player\Player;
use pocketmine\utils\Config;


class CooldownManager{


    private Main $plugin;

    private Config $cooldowns;



    public function __construct(Main $plugin){


        $this->plugin = $plugin;


        $this->cooldowns = new Config(

            $plugin->getDataFolder() . "cooldowns.json",

            Config::JSON

        );

    }





    /**
     * Mettre un cooldown
     */
    public function setCooldown(Player $player): void{


        $uuid = $player->getUniqueId()->toString();


        $time = time();


        $this->cooldowns->set(

            $uuid,

            $time

        );


        $this->cooldowns->save();

    }





    /**
     * Vérifier le cooldown
     */
    public function hasCooldown(Player $player): bool{


        $uuid = $player->getUniqueId()->toString();


        if(!$this->cooldowns->exists($uuid)){

            return false;

        }



        $last = $this->cooldowns->get($uuid);



        $cooldown = $this->plugin
            ->getConfigFile()
            ->get("home-cooldown");



        return (time() - $last) < $cooldown;

    }





    /**
     * Temps restant
     */
    public function getRemaining(Player $player): int{


        $uuid = $player->getUniqueId()->toString();


        $last = $this->cooldowns->get($uuid, 0);



        $cooldown = $this->plugin
            ->getConfigFile()
            ->get("home-cooldown");



        $remaining = $cooldown - (time() - $last);



        return max(0, $remaining);

    }


}