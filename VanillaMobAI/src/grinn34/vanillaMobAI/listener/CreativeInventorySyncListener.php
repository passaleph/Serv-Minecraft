<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\listener;

use grinn34\vanillaMobAI\item\SpawnEggRegistrar;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

final class CreativeInventorySyncListener implements Listener{
	public function onPlayerJoin(PlayerJoinEvent $event) : void{
		SpawnEggRegistrar::syncCreativeForPlayer($event->getPlayer());
	}
}
