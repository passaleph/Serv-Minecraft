<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\listener;

use grinn34\vanillaMobAI\breeding\BreedingHelper;
use grinn34\vanillaMobAI\entity\passive\BreedableMob;
use grinn34\vanillaMobAI\registry\MobTemptRegistry;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\item\VanillaItems;

final class BreedingListener implements Listener{
	public function onEntityInteract(PlayerEntityInteractEvent $event) : void{
		if($event->isCancelled()){
			return;
		}

		$entity = $event->getEntity();
		if(!$entity instanceof BreedableMob){
			return;
		}

		$player = $event->getPlayer();
		$item = $player->getInventory()->getItemInHand();
		if(!MobTemptRegistry::isTemptItem($entity, $item)){
			return;
		}

		if(!$entity->canEnterLoveMode()){
			return;
		}

		$inventory = $player->getInventory();
		$heldItem = $inventory->getItemInHand();
		if($heldItem->getCount() > 1){
			$heldItem->pop();
			$inventory->setItemInHand($heldItem);
		}else{
			$inventory->setItemInHand(VanillaItems::AIR());
		}

		$entity->enterLoveMode();
		BreedingHelper::spawnHeartsAt(
			$entity->getWorld(),
			$entity->getPosition()->add(0, $entity->getSize()->getHeight() * 0.8, 0)
		);

		$event->cancel();
	}
}
