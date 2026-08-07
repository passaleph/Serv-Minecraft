<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\spawner;

use grinn34\vanillaMobAI\config\PluginSettings;
use grinn34\vanillaMobAI\registry\MobHostileRegistry;
use grinn34\vanillaMobAI\registry\MobNaturalSpawnRegistry;
use grinn34\vanillaMobAI\registry\MobSpawnEggRegistry;
use grinn34\vanillaMobAI\registry\MobRegistry;
use grinn34\vanillaMobAI\spawning\MobSpawnHelper;
use pocketmine\block\tile\MonsterSpawner;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\World;
use function max;
use function mt_rand;

final class MonsterSpawnerHelper{
	private const TAG_ENTITY_IDENTIFIER = "EntityIdentifier";
	private const TAG_SPAWN_DELAY = "Delay";
	private const TAG_MIN_SPAWN_DELAY = "MinSpawnDelay";
	private const TAG_MAX_SPAWN_DELAY = "MaxSpawnDelay";
	private const TAG_SPAWN_COUNT = "SpawnCount";
	private const TAG_MAX_NEARBY_ENTITIES = "MaxNearbyEntities";
	private const TAG_REQUIRED_PLAYER_RANGE = "RequiredPlayerRange";
	private const TAG_SPAWN_RANGE = "SpawnRange";

	private function __construct(){}

	public static function setMobTypeFromEgg(MonsterSpawner $tile, string $mobKey) : bool{
		$entityId = MobSpawnEggRegistry::getEntityId($mobKey);
		if($entityId === null){
			return false;
		}

		$nbt = $tile->saveNBT();
		$nbt->setString(self::TAG_ENTITY_IDENTIFIER, $entityId);
		$nbt->setShort(self::TAG_SPAWN_DELAY, MonsterSpawner::DEFAULT_MIN_SPAWN_DELAY);
		$tile->readSaveData($nbt);
		$tile->setDirty();

		return true;
	}

	public static function tick(MonsterSpawner $tile, MobRegistry $mobRegistry) : void{
		if($tile->isClosed()){
			return;
		}

		$world = $tile->getPosition()->getWorld();
		$nbt = $tile->saveNBT();

		$entityId = self::readString($nbt, self::TAG_ENTITY_IDENTIFIER);
		$mobKey = MobSpawnEggRegistry::resolveMobKeyFromEntityId($entityId);
		if($mobKey === null){
			return;
		}

		$requiredPlayerRange = self::readShort(
			$nbt,
			self::TAG_REQUIRED_PLAYER_RANGE,
			MonsterSpawner::DEFAULT_REQUIRED_PLAYER_RANGE
		);
		if(!self::hasPlayerInRange($world, $tile->getPosition(), $requiredPlayerRange)){
			return;
		}

		$spawnDelay = self::readShort($nbt, self::TAG_SPAWN_DELAY, MonsterSpawner::DEFAULT_MIN_SPAWN_DELAY);
		$spawnDelay--;

		if($spawnDelay > 0){
			self::writeSpawnDelay($tile, $nbt, $spawnDelay);
			return;
		}

		$minDelay = max(1, self::readShort($nbt, self::TAG_MIN_SPAWN_DELAY, MonsterSpawner::DEFAULT_MIN_SPAWN_DELAY));
		$maxDelay = max($minDelay, self::readShort($nbt, self::TAG_MAX_SPAWN_DELAY, MonsterSpawner::DEFAULT_MAX_SPAWN_DELAY));
		$spawnCount = max(1, self::readShort($nbt, self::TAG_SPAWN_COUNT, 4));
		$maxNearby = self::readShort($nbt, self::TAG_MAX_NEARBY_ENTITIES, MonsterSpawner::DEFAULT_MAX_NEARBY_ENTITIES);
		$spawnRange = self::readShort($nbt, self::TAG_SPAWN_RANGE, MonsterSpawner::DEFAULT_SPAWN_RANGE);

		if(self::isPeacefulHostileBlocked($world, $mobKey)){
			self::writeSpawnDelay($tile, $nbt, mt_rand($minDelay, $maxDelay));
			return;
		}

		if(self::countNearbyMatching($world, $tile->getPosition(), $spawnRange + 2, $mobKey) >= $maxNearby){
			self::writeSpawnDelay($tile, $nbt, mt_rand($minDelay, $maxDelay));
			return;
		}

		$spawned = 0;
		for($i = 0; $i < $spawnCount; $i++){
			$spawnPosition = MobSpawnHelper::findSpawnerPosition($world, $tile->getPosition()->add(0.5, 0, 0.5), $spawnRange);
			if($spawnPosition === null){
				continue;
			}

			$location = Location::fromObject(
				$spawnPosition,
				$world,
				MobSpawnHelper::randomYaw(),
				0
			);

			if(MobSpawnHelper::spawnMob($mobRegistry, $mobKey, $location) !== null){
				$spawned++;
			}
		}

		$nextDelay = $spawned > 0 ? mt_rand($minDelay, $maxDelay) : max(1, (int) ($minDelay * 0.5));
		self::writeSpawnDelay($tile, $nbt, $nextDelay);
	}

	private static function writeSpawnDelay(MonsterSpawner $tile, CompoundTag $nbt, int $spawnDelay) : void{
		$nbt->setShort(self::TAG_SPAWN_DELAY, max(0, $spawnDelay));
		$tile->readSaveData($nbt);
		$tile->setDirty();
	}

	private static function hasPlayerInRange(World $world, \pocketmine\math\Vector3 $position, int $range) : bool{
		$rangeSq = $range * $range;
		foreach($world->getPlayers() as $player){
			if(!$player instanceof Player || !$player->isConnected() || $player->isClosed() || $player->isSpectator()){
				continue;
			}

			if($player->getPosition()->distanceSquared($position) <= $rangeSq){
				return true;
			}
		}

		return false;
	}

	private static function isPeacefulHostileBlocked(World $world, string $mobKey) : bool{
		$definition = MobSpawnEggRegistry::get($mobKey);
		if($definition === null){
			return true;
		}

		return PluginSettings::get()->spawnerBlocksHostilesOnPeaceful()
			&& MobHostileRegistry::isHostileClass($definition["class"])
			&& $world->getDifficulty() === MobNaturalSpawnRegistry::DIFFICULTY_PEACEFUL;
	}

	private static function countNearbyMatching(World $world, \pocketmine\math\Vector3 $center, int $radius, string $mobKey) : int{
		$class = MobSpawnEggRegistry::getEntityClass($mobKey);
		if($class === null){
			return 0;
		}

		$radiusSq = $radius * $radius;
		$count = 0;
		foreach($world->getEntities() as $entity){
			if(!$entity instanceof Living || !is_a($entity, $class, true)){
				continue;
			}

			if($entity->getPosition()->distanceSquared($center) <= $radiusSq){
				$count++;
			}
		}

		return $count;
	}

	private static function readString(CompoundTag $nbt, string $tag) : string{
		return $nbt->getString($tag, "");
	}

	private static function readShort(CompoundTag $nbt, string $tag, int $default) : int{
		return $nbt->getShort($tag, $default);
	}
}
