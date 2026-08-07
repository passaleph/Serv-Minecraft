<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\registry;

use grinn34\vanillaMobAI\entity\hostile\HostileCreeper;
use grinn34\vanillaMobAI\entity\hostile\HostileSkeleton;
use grinn34\vanillaMobAI\entity\hostile\HostileSpider;
use grinn34\vanillaMobAI\entity\hostile\HostileZombie;
use pocketmine\entity\Living;
use pocketmine\entity\Zombie;
use function mt_rand;

final class MobCombatRegistry{
	public const ATTACK_MELEE = "melee";
	public const ATTACK_RANGED = "ranged";
	public const ATTACK_SWELL = "swell";

	private const DEFAULT_REACH_MULTIPLIER = 2.0;
	private const DEFAULT_ATTACK_COOLDOWN = 20;

	/** @var array<class-string<Living>, array<string, float|int|string>> */
	private const PROFILES = [
		HostileZombie::class => [
			"attack_type" => self::ATTACK_MELEE,
			"damage" => 3.0,
			"detection_range" => 16.0,
			"follow_range" => 35.0,
			"unseen_memory_ticks" => 60,
			"reach_multiplier" => 2.0,
			"attack_cooldown" => 20,
		],
		Zombie::class => [
			"attack_type" => self::ATTACK_MELEE,
			"damage" => 3.0,
			"detection_range" => 25.0,
			"follow_range" => 35.0,
			"reach_multiplier" => 2.0,
			"attack_cooldown" => 20,
		],
		HostileSkeleton::class => [
			"attack_type" => self::ATTACK_RANGED,
			"ranged_damage" => 2.0,
			"arrow_velocity" => 2.1,
			"detection_range" => 16.0,
			"follow_range" => 35.0,
			"attack_range" => 15.0,
			"unseen_memory_ticks" => 60,
			"movement_speed" => 0.25,
			"attack_windup_ticks" => 20,
			"attack_cooldown_min" => 20,
			"attack_cooldown_max" => 60,
		],
		HostileSpider::class => [
			"attack_type" => self::ATTACK_MELEE,
			"damage" => 2.0,
			"detection_range" => 16.0,
			"follow_range" => 35.0,
			"unseen_memory_ticks" => 60,
			"reach_multiplier" => 2.0,
			"attack_cooldown" => 20,
		],
		HostileCreeper::class => [
			"attack_type" => self::ATTACK_SWELL,
			"detection_range" => 16.0,
			"follow_range" => 16.0,
			"unseen_memory_ticks" => 60,
			"swell_distance" => 3.0,
			"fuse_ticks" => 30,
			"explosion_radius" => 3.0,
		],
	];

	private function __construct(){}

	/**
	 * @return array<string, float|int|string>
	 */
	public static function getProfile(Living $entity) : array{
		return self::PROFILES[$entity::class] ?? [
			"attack_type" => self::ATTACK_MELEE,
			"damage" => 2.0,
			"detection_range" => 16.0,
			"follow_range" => 32.0,
			"reach_multiplier" => self::DEFAULT_REACH_MULTIPLIER,
			"attack_cooldown" => self::DEFAULT_ATTACK_COOLDOWN,
		];
	}

	public static function getAttackType(Living $entity) : string{
		$type = self::getProfile($entity)["attack_type"] ?? self::ATTACK_MELEE;
		return is_string($type) ? $type : self::ATTACK_MELEE;
	}

	public static function getMeleeDamage(Living $attacker) : float{
		return (float) (self::getProfile($attacker)["damage"] ?? 2.0);
	}

	public static function getRangedDamage(Living $attacker) : float{
		return (float) (self::getProfile($attacker)["ranged_damage"] ?? 2.0);
	}

	public static function getArrowVelocity(Living $attacker) : float{
		return (float) (self::getProfile($attacker)["arrow_velocity"] ?? 2.0);
	}

	public static function getDetectionRange(Living $entity) : float{
		return (float) (self::getProfile($entity)["detection_range"] ?? 16.0);
	}

	public static function getFollowRange(Living $entity) : float{
		return (float) (self::getProfile($entity)["follow_range"] ?? 32.0);
	}

	public static function getAttackRange(Living $entity) : float{
		return (float) (self::getProfile($entity)["attack_range"] ?? 15.0);
	}

	public static function getAttackWindupTicks(Living $entity) : int{
		return (int) (self::getProfile($entity)["attack_windup_ticks"] ?? 20);
	}

	public static function getMovementSpeed(Living $entity) : ?float{
		$speed = self::getProfile($entity)["movement_speed"] ?? null;
		return is_numeric($speed) ? (float) $speed : null;
	}

	public static function getUnseenMemoryTicks(Living $entity) : int{
		return (int) (self::getProfile($entity)["unseen_memory_ticks"] ?? 60);
	}

	public static function getAttackCooldown(Living $entity) : int{
		return (int) (self::getProfile($entity)["attack_cooldown"] ?? self::DEFAULT_ATTACK_COOLDOWN);
	}

	public static function rollAttackCooldown(Living $entity) : int{
		$profile = self::getProfile($entity);
		$min = (int) ($profile["attack_cooldown_min"] ?? self::DEFAULT_ATTACK_COOLDOWN);
		$max = (int) ($profile["attack_cooldown_max"] ?? $min);

		return mt_rand($min, max($min, $max));
	}

	public static function getSwellDistance(Living $entity) : float{
		return (float) (self::getProfile($entity)["swell_distance"] ?? 3.0);
	}

	public static function getFuseTicks(Living $entity) : int{
		return (int) (self::getProfile($entity)["fuse_ticks"] ?? 30);
	}

	public static function getExplosionRadius(Living $entity) : float{
		return (float) (self::getProfile($entity)["explosion_radius"] ?? 3.0);
	}

	public static function getMeleeReach(Living $attacker, Living $victim) : float{
		$profile = self::getProfile($attacker);
		$combinedHalfWidth = ($attacker->getSize()->getWidth() + $victim->getSize()->getWidth()) / 2;
		$reachMultiplier = (float) ($profile["reach_multiplier"] ?? self::DEFAULT_REACH_MULTIPLIER);

		return $combinedHalfWidth * $reachMultiplier;
	}
}
