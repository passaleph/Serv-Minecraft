<?php

namespace listeners;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;


class PlayerQuitListener implements Listener{


    public function onQuit(PlayerQuitEvent $event): void{


        $player = $event->getPlayer();


        // Nettoyage possible plus tard

    }

}