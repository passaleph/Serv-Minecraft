<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\registry;

use grinn34\vanillaMobAI\entity\hostile\HostileCreeper;
use grinn34\vanillaMobAI\entity\hostile\HostileMob;
use grinn34\vanillaMobAI\entity\hostile\HostileSkeleton;
use grinn34\vanillaMobAI\entity\hostile\HostileSpider;
use grinn34\vanillaMobAI\entity\hostile\HostileZombie;
use pocketmine\entity\Living;
use pocketmine\entity\Zombie;

final class MobHostileRegistry{
	/** @var array<class-string<Living>, true> */
	private const HOSTILES = [
		HostileZombie::class => true,
		HostileSkeleton::class => true,
		HostileSpider::class => true,
		HostileCreeper::class => true,
		Zombie::class => true,
	];

	private function __construct(){}

	public static function isHostile(Living $entity) : bool{
		return $entity instanceof HostileMob || isset(self::HOSTILES[$entity::class]);
	}

	/**
	 * @param class-string<Living> $class
	 */
	public static function isHostileClass(string $class) : bool{
		return isset(self::HOSTILES[$class]) || is_a($class, HostileMob::class, true);
	}
}
