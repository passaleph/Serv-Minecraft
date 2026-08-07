<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\listener;

use grinn34\vanillaMobAI\config\PluginSettings;
use grinn34\vanillaMobAI\registry\MobRegistry;
use grinn34\vanillaMobAI\registry\MobSpawnEggRegistry;
use grinn34\vanillaMobAI\spawner\MonsterSpawnerHelper;
use grinn34\vanillaMobAI\spawning\MobSpawnHelper;
use pocketmine\block\tile\MonsterSpawner;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;

final class SpawnEggListener implements Listener{
	public function __construct(
		private readonly MobRegistry $mobRegistry
	){}

	public function onEntitySpawn(EntitySpawnEvent $event) : void{
		$entity = $event->getEntity();
		$mobKey = MobSpawnEggRegistry::resolveMobKeyFromEntity($entity);
		if($mobKey === null){
			return;
		}

		$targetClass = MobSpawnEggRegistry::getEntityClass($mobKey);
		if($targetClass === null || is_a($entity, $targetClass, true)){
			return;
		}

		$location = $entity->getLocation();
		$nameTag = $entity->getNameTag();
		$entity->flagForDespawn();

		MobSpawnHelper::spawnMob($this->mobRegistry, $mobKey, $location, $nameTag !== "" ? $nameTag : null);
	}

	public function onPlayerInteract(PlayerInteractEvent $event) : void{
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}

		$item = $event->getItem();
		$mobKey = MobSpawnEggRegistry::resolveMobKeyFromItem($item);
		if($mobKey === null){
			return;
		}

		$player = $event->getPlayer();
		$world = $player->getWorld();
		$blockPos = $event->getBlock()->getPosition();
		$tile = $world->getTileAt($blockPos->getFloorX(), $blockPos->getFloorY(), $blockPos->getFloorZ());

		if($tile instanceof MonsterSpawner){
			$event->cancel();

			$settings = PluginSettings::get();
			if(!$settings->isMonsterSpawnersEnabled() || !$settings->allowsSpawnerWorld($world)){
				return;
			}

			if(!MonsterSpawnerHelper::setMobTypeFromEgg($tile, $mobKey)){
				return;
			}

			$this->consumeSpawnEgg($player, $item);
			return;
		}

		$event->cancel();

		$spawnPos = MobSpawnHelper::resolveSpawnPositionFromBlock($world, $event->getBlock(), $event->getFace());
		if($spawnPos === null){
			return;
		}

		$location = Location::fromObject($spawnPos, $world, MobSpawnHelper::randomYaw(), 0);

		$nameTag = $item->hasCustomName() ? $item->getCustomName() : null;
		if(MobSpawnHelper::spawnMob($this->mobRegistry, $mobKey, $location, $nameTag) === null){
			return;
		}

		$this->consumeSpawnEgg($player, $item);
	}

	private function consumeSpawnEgg(\pocketmine\player\Player $player, \pocketmine\item\Item $item) : void{
		if(!$player->getCreativeInventory()->contains($item)){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
		}
	}
}
