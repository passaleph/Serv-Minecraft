<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\registry;

use grinn34\vanillaMobAI\entity\hostile\HostileCreeper;
use grinn34\vanillaMobAI\entity\hostile\HostileSkeleton;
use grinn34\vanillaMobAI\entity\hostile\HostileSpider;
use grinn34\vanillaMobAI\entity\hostile\HostileZombie;
use grinn34\vanillaMobAI\entity\passive\Chicken;
use grinn34\vanillaMobAI\entity\passive\Cow;
use grinn34\vanillaMobAI\entity\passive\Pig;
use grinn34\vanillaMobAI\entity\passive\Sheep;
use grinn34\vanillaMobAI\item\VanillaMobSpawnEgg;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Zombie;
use pocketmine\item\StringToItemParser;
use pocketmine\item\ItemTypeIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function strtolower;

final class MobSpawnEggRegistry{
	/**
	 * @var array<string, array{
	 *     class: class-string<Living>,
	 *     entity_id: string,
	 *     display_name: string,
	 *     aliases: string[],
	 *     type_id: int|null,
	 *     replace_class: class-string<Entity>|null
	 * }>
	 */
	private const MOBS = [
		"cow" => [
			"class" => Cow::class,
			"entity_id" => EntityIds::COW,
			"display_name" => "Cow Spawn Egg",
			"aliases" => ["cow_spawn_egg"],
			"type_id" => null,
			"replace_class" => null,
		],
		"pig" => [
			"class" => Pig::class,
			"entity_id" => EntityIds::PIG,
			"display_name" => "Pig Spawn Egg",
			"aliases" => ["pig_spawn_egg"],
			"type_id" => null,
			"replace_class" => null,
		],
		"sheep" => [
			"class" => Sheep::class,
			"entity_id" => EntityIds::SHEEP,
			"display_name" => "Sheep Spawn Egg",
			"aliases" => ["sheep_spawn_egg"],
			"type_id" => null,
			"replace_class" => null,
		],
		"chicken" => [
			"class" => Chicken::class,
			"entity_id" => EntityIds::CHICKEN,
			"display_name" => "Chicken Spawn Egg",
			"aliases" => ["chicken_spawn_egg"],
			"type_id" => null,
			"replace_class" => null,
		],
		"zombie" => [
			"class" => HostileZombie::class,
			"entity_id" => EntityIds::ZOMBIE,
			"display_name" => "Zombie Spawn Egg",
			"aliases" => ["zombie_spawn_egg"],
			"type_id" => ItemTypeIds::ZOMBIE_SPAWN_EGG,
			"replace_class" => Zombie::class,
		],
		"skeleton" => [
			"class" => HostileSkeleton::class,
			"entity_id" => EntityIds::SKELETON,
			"display_name" => "Skeleton Spawn Egg",
			"aliases" => ["skeleton_spawn_egg"],
			"type_id" => null,
			"replace_class" => null,
		],
		"spider" => [
			"class" => HostileSpider::class,
			"entity_id" => EntityIds::SPIDER,
			"display_name" => "Spider Spawn Egg",
			"aliases" => ["spider_spawn_egg"],
			"type_id" => null,
			"replace_class" => null,
		],
		"creeper" => [
			"class" => HostileCreeper::class,
			"entity_id" => EntityIds::CREEPER,
			"display_name" => "Creeper Spawn Egg",
			"aliases" => ["creeper_spawn_egg"],
			"type_id" => null,
			"replace_class" => null,
		],
	];

	private function __construct(){}

	/**
	 * @return array<string, array{
	 *     class: class-string<Living>,
	 *     entity_id: string,
	 *     display_name: string,
	 *     aliases: string[],
	 *     type_id: int|null,
	 *     replace_class: class-string<Entity>|null
	 * }>
	 */
	public static function getAll() : array{
		return self::MOBS;
	}

	/**
	 * @return array{
	 *     class: class-string<Living>,
	 *     entity_id: string,
	 *     display_name: string,
	 *     aliases: string[],
	 *     type_id: int|null,
	 *     replace_class: class-string<Entity>|null
	 * }|null
	 */
	public static function get(string $mobKey) : ?array{
		return self::MOBS[strtolower($mobKey)] ?? null;
	}

	public static function resolveMobKey(string $identifier) : ?string{
		$normalized = strtolower($identifier);
		if(isset(self::MOBS[$normalized])){
			return $normalized;
		}

		foreach(self::MOBS as $mobKey => $definition){
			if($definition["entity_id"] === $normalized){
				return $mobKey;
			}

			foreach($definition["aliases"] as $alias){
				if(strtolower($alias) === $normalized){
					return $mobKey;
				}
			}
		}

		if(str_starts_with($normalized, "minecraft:")){
			return self::resolveMobKey(substr($normalized, 10));
		}

		return null;
	}

	public static function resolveMobKeyFromEntity(Entity $entity) : ?string{
		foreach(self::MOBS as $mobKey => $definition){
			$replaceClass = $definition["replace_class"];
			if($replaceClass !== null && $entity::class === $replaceClass){
				return $mobKey;
			}
		}

		if($entity instanceof Living && method_exists($entity, "getNetworkTypeId")){
			$networkId = $entity::getNetworkTypeId();
			foreach(self::MOBS as $mobKey => $definition){
				if($definition["entity_id"] === $networkId && !is_a($entity, $definition["class"], true)){
					return $mobKey;
				}
			}
		}

		return null;
	}

	public static function resolveMobKeyFromItem(\pocketmine\item\Item $item) : ?string{
		if($item instanceof VanillaMobSpawnEgg){
			return $item->getMobKey();
		}

		foreach(self::MOBS as $mobKey => $definition){
			if($definition["type_id"] !== null && $item->getTypeId() === $definition["type_id"]){
				return $mobKey;
			}

			foreach($definition["aliases"] as $alias){
				$parsed = StringToItemParser::getInstance()->parse($alias);
				if($parsed !== null && $item->getTypeId() === $parsed->getTypeId()){
					return $mobKey;
				}
			}
		}

		return null;
	}

	public static function resolveMobKeyFromEntityId(string $entityId) : ?string{
		return self::resolveMobKey($entityId);
	}

	/**
	 * @return class-string<Living>|null
	 */
	public static function getEntityClass(string $mobKey) : ?string{
		return self::get($mobKey)["class"] ?? null;
	}

	public static function getEntityId(string $mobKey) : ?string{
		return self::get($mobKey)["entity_id"] ?? null;
	}
}
