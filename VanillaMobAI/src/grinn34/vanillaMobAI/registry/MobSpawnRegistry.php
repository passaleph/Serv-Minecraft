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
use pocketmine\entity\Living;
use pocketmine\entity\Location;

final class MobSpawnRegistry{
	/** @var array<string, class-string<Living>> */
	private const TYPES = [
		"cow" => Cow::class,
		"pig" => Pig::class,
		"sheep" => Sheep::class,
		"chicken" => Chicken::class,
		"zombie" => HostileZombie::class,
		"skeleton" => HostileSkeleton::class,
		"spider" => HostileSpider::class,
		"creeper" => HostileCreeper::class,
	];

	private function __construct(){}

	/**
	 * @return class-string<Living>|null
	 */
	public static function resolve(string $name) : ?string{
		return self::TYPES[strtolower($name)] ?? null;
	}

	/**
	 * @return string[]
	 */
	public static function getNames() : array{
		return array_keys(self::TYPES);
	}

	public static function create(string $name, Location $location) : ?Living{
		$class = self::resolve($name);
		if($class === null){
			return null;
		}

		/** @var Living $entity */
		$entity = new $class($location);
		return $entity;
	}
}
