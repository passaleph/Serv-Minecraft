<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\performance;

final class TickThrottle{
	private static int $globalTick = 0;

	private function __construct(){}

	public static function setGlobalTick(int $tick) : void{
		self::$globalTick = $tick;
	}

	public static function getGlobalTick() : int{
		return self::$globalTick;
	}

	public static function every(int $salt, int $interval) : bool{
		if($interval <= 1){
			return true;
		}

		return (self::$globalTick + $salt) % $interval === 0;
	}
}
