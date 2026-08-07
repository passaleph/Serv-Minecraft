<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\movement;

use pocketmine\entity\Living;
use WeakMap;

final class MovementPause{
	private const DEFAULT_TICKS = 8;

	/** @var WeakMap<Living, int> */
	private static WeakMap $ticks;

	private function __construct(){}

	public static function activate(Living $entity, int $ticks = self::DEFAULT_TICKS) : void{
		self::$ticks ??= new WeakMap();
		$current = self::$ticks[$entity] ?? 0;
		self::$ticks[$entity] = max($current, $ticks);
	}

	public static function isActive(Living $entity) : bool{
		self::$ticks ??= new WeakMap();
		return isset(self::$ticks[$entity]) && self::$ticks[$entity] > 0;
	}

	public static function tick(Living $entity) : void{
		self::$ticks ??= new WeakMap();
		if(!isset(self::$ticks[$entity])){
			return;
		}

		if(self::$ticks[$entity] > 0){
			self::$ticks[$entity]--;
		}

		if(self::$ticks[$entity] <= 0){
			unset(self::$ticks[$entity]);
		}
	}
}
