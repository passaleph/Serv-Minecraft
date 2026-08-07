<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\item;

use grinn34\vanillaMobAI\registry\MobSpawnEggRegistry;
use grinn34\vanillaMobAI\registry\MobSpawnRegistry;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\SpawnEgg;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class VanillaMobSpawnEgg extends SpawnEgg{
	/** @var array<string, int> */
	private static array $customTypeIds = [];

	public static function create(string $mobKey) : Item{
		if($mobKey === "zombie"){
			return VanillaItems::ZOMBIE_SPAWN_EGG();
		}

		$definition = MobSpawnEggRegistry::get($mobKey);
		if($definition === null){
			throw new \InvalidArgumentException("Unknown mob spawn egg: " . $mobKey);
		}

		foreach($definition["aliases"] as $alias){
			$parsed = StringToItemParser::getInstance()->parse($alias);
			if($parsed !== null){
				return $parsed;
			}
		}

		return self::createCustom($mobKey);
	}

	public static function createCustom(string $mobKey) : self{
		$definition = MobSpawnEggRegistry::get($mobKey);
		if($definition === null){
			throw new \InvalidArgumentException("Unknown mob spawn egg: " . $mobKey);
		}

		$typeId = $definition["type_id"] ?? (self::$customTypeIds[$mobKey] ??= ItemTypeIds::newId());
		return new self(new ItemIdentifier($typeId), $definition["display_name"], $mobKey);
	}

	public function __construct(
		ItemIdentifier $identifier,
		string $name,
		private readonly string $mobKey
	){
		parent::__construct($identifier, $name);
	}

	public function getMobKey() : string{
		return $this->mobKey;
	}

	protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
		$entity = MobSpawnRegistry::create($this->mobKey, Location::fromObject($pos, $world, $yaw, $pitch));
		if($entity === null){
			throw new \RuntimeException("Failed to create mob for spawn egg: " . $this->mobKey);
		}

		return $entity;
	}
}
