<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\registry;

use grinn34\vanillaMobAI\entity\hostile\HostileCreeper;
use grinn34\vanillaMobAI\entity\hostile\HostileMob;
use grinn34\vanillaMobAI\entity\hostile\HostileSkeleton;
use grinn34\vanillaMobAI\entity\hostile\HostileSpider;
use grinn34\vanillaMobAI\entity\hostile\HostileZombie;
use grinn34\vanillaMobAI\entity\passive\Chicken;
use grinn34\vanillaMobAI\entity\passive\Cow;
use grinn34\vanillaMobAI\entity\passive\PassiveMob;
use grinn34\vanillaMobAI\entity\passive\Pig;
use grinn34\vanillaMobAI\entity\passive\Sheep;
use grinn34\vanillaMobAI\config\PluginSettings;
use pocketmine\entity\Living;
use function array_sum;
use function mt_rand;

final class MobNaturalSpawnRegistry{
	public const CATEGORY_PASSIVE = "passive";
	public const CATEGORY_HOSTILE = "hostile";

	/** @see World::getDifficulty() — 0 = peaceful, 1 = easy, 2 = normal, 3 = hard */
	public const DIFFICULTY_PEACEFUL = 0;

	/** @var array<string, array{class: class-string<Living>, category: string, weight: int, pack_min: int, pack_max: int}> */
	private const RULES = [
		"cow" => [
			"class" => Cow::class,
			"category" => self::CATEGORY_PASSIVE,
			"weight" => 12,
			"pack_min" => 2,
			"pack_max" => 4,
		],
		"pig" => [
			"class" => Pig::class,
			"category" => self::CATEGORY_PASSIVE,
			"weight" => 10,
			"pack_min" => 2,
			"pack_max" => 4,
		],
		"sheep" => [
			"class" => Sheep::class,
			"category" => self::CATEGORY_PASSIVE,
			"weight" => 12,
			"pack_min" => 2,
			"pack_max" => 4,
		],
		"chicken" => [
			"class" => Chicken::class,
			"category" => self::CATEGORY_PASSIVE,
			"weight" => 10,
			"pack_min" => 1,
			"pack_max" => 3,
		],
		"zombie" => [
			"class" => HostileZombie::class,
			"category" => self::CATEGORY_HOSTILE,
			"weight" => 100,
			"pack_min" => 1,
			"pack_max" => 1,
		],
		"skeleton" => [
			"class" => HostileSkeleton::class,
			"category" => self::CATEGORY_HOSTILE,
			"weight" => 20,
			"pack_min" => 1,
			"pack_max" => 1,
		],
		"spider" => [
			"class" => HostileSpider::class,
			"category" => self::CATEGORY_HOSTILE,
			"weight" => 100,
			"pack_min" => 1,
			"pack_max" => 1,
		],
		"creeper" => [
			"class" => HostileCreeper::class,
			"category" => self::CATEGORY_HOSTILE,
			"weight" => 20,
			"pack_min" => 1,
			"pack_max" => 1,
		],
	];

	private function __construct(){}

	/**
	 * @return array{class: class-string<Living>, category: string, weight: int, pack_min: int, pack_max: int}|null
	 */
	public static function getRule(string $name) : ?array{
		return self::RULES[strtolower($name)] ?? null;
	}

	/**
	 * @return array<string, array{class: class-string<Living>, category: string, weight: int, pack_min: int, pack_max: int}>
	 */
	public static function getRulesForCategory(string $category) : array{
		$settings = PluginSettings::get();
		$result = [];
		foreach(self::RULES as $name => $rule){
			if($rule["category"] === $category && $settings->isNaturalSpawnMobEnabled($name)){
				$result[$name] = $rule;
			}
		}
		return $result;
	}

	public static function rollRule(string $category) : ?string{
		$rules = self::getRulesForCategory($category);
		if($rules === []){
			return null;
		}

		$totalWeight = array_sum(array_map(static fn(array $rule) => $rule["weight"], $rules));
		if($totalWeight <= 0){
			return null;
		}

		$roll = mt_rand(1, $totalWeight);
		$cursor = 0;
		foreach($rules as $name => $rule){
			$cursor += $rule["weight"];
			if($roll <= $cursor){
				return $name;
			}
		}

		return array_key_first($rules);
	}

	public static function getCapForCategory(string $category) : int{
		$settings = PluginSettings::get();
		return match($category){
			self::CATEGORY_PASSIVE => $settings->getPassiveCap(),
			self::CATEGORY_HOSTILE => $settings->getHostileCap(),
			default => 0
		};
	}

	public static function isManagedMob(Living $entity) : bool{
		return $entity instanceof PassiveMob || $entity instanceof HostileMob;
	}

	public static function getCategory(Living $entity) : ?string{
		if($entity instanceof PassiveMob){
			return self::CATEGORY_PASSIVE;
		}
		if($entity instanceof HostileMob){
			return self::CATEGORY_HOSTILE;
		}
		return null;
	}
}
