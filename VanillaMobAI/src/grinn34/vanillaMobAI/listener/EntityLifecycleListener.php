<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\listener;

use grinn34\vanillaMobAI\AIManager;
use grinn34\vanillaMobAI\combat\CombatHelper;
use grinn34\vanillaMobAI\entity\hostile\HostileSkeleton;
use grinn34\vanillaMobAI\movement\MobEquipmentHelper;
use grinn34\vanillaMobAI\registry\MobHostileRegistry;
use grinn34\vanillaMobAI\registry\MobRegistry;
use pocketmine\item\VanillaItems;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\entity\Living;
use pocketmine\player\Player;

final class EntityLifecycleListener implements Listener{
	public function __construct(
		private readonly MobRegistry $mobRegistry,
		private readonly AIManager $aiManager
	){}

	public function onSpawn(EntitySpawnEvent $event) : void{
		$entity = $event->getEntity();
		$this->mobRegistry->tryAttach($entity);

		if($entity instanceof HostileSkeleton){
			MobEquipmentHelper::syncMainHand($entity, VanillaItems::BOW());
		}
	}

	public function onDespawn(EntityDespawnEvent $event) : void{
		$this->mobRegistry->detach($event->getEntity());
	}

	public function onDeath(EntityDeathEvent $event) : void{
		$this->mobRegistry->detach($event->getEntity());
	}

	public function onDamage(EntityDamageEvent $event) : void{
		$entity = $event->getEntity();
		if(!$entity instanceof Living){
			return;
		}

		$brain = $this->aiManager->getBrain($entity);
		if($brain === null){
			return;
		}

		if(!$event->isCancelled() && $event instanceof EntityDamageByEntityEvent && $event->getKnockBack() > 0){
			$brain->pauseMovement();
		}

		if(MobHostileRegistry::isHostile($entity)){
			if($event instanceof EntityDamageByEntityEvent){
				$damager = $event->getDamager();
				if(
					$damager instanceof Living
					&& (!($damager instanceof Player) || CombatHelper::canBeHostileTarget($damager))
				){
					$brain->setAttackTarget($damager);
				}
			}
			return;
		}

		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();
			if($damager !== null){
				$brain->triggerPanic($damager->getPosition(), 140);
				return;
			}
		}

		$brain->triggerPanic($entity->getPosition(), 80);
	}
}
