<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\listener;

use grinn34\vanillaMobAI\entity\passive\Cow;
use grinn34\vanillaMobAI\entity\passive\Sheep;
use grinn34\vanillaMobAI\interactions\DyeItemHelper;
use grinn34\vanillaMobAI\interactions\InteractionHelper;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerEntityInteractEvent;
use grinn34\vanillaMobAI\interactions\LevelSoundEventSound;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\world\sound\DyeUseSound;

final class MobInteractionListener implements Listener{
	public function onEntityInteract(PlayerEntityInteractEvent $event) : void{
		if($event->isCancelled()){
			return;
		}

		$entity = $event->getEntity();
		$player = $event->getPlayer();
		$held = $player->getInventory()->getItemInHand();

		if($entity instanceof Cow && $held->equals(VanillaItems::BUCKET(), true, false)){
			if($entity->isBaby()){
				return;
			}

			if(!InteractionHelper::milkWithBucket($player, $entity->getPosition())){
				return;
			}
			$player->getWorld()->addSound($entity->getPosition(), new LevelSoundEventSound(LevelSoundEvent::MILK));
			$event->cancel();
			return;
		}

		if($entity instanceof Sheep){
			if($held->equals(VanillaItems::SHEARS(), true, false)){
				if(!$entity->shear($player)){
					return;
				}

				InteractionHelper::damageHeldItem($player);
				$player->getWorld()->addSound($entity->getPosition(), new LevelSoundEventSound(LevelSoundEvent::SHEAR));
				$event->cancel();
				return;
			}

			$dyeColor = DyeItemHelper::resolveDyeColor($held);
			if($dyeColor !== null){
				$entity->dye($dyeColor);
				InteractionHelper::consumeOneFromHand($player);
				$player->getWorld()->addSound($entity->getPosition(), new DyeUseSound());
				$event->cancel();
			}
		}
	}
}
