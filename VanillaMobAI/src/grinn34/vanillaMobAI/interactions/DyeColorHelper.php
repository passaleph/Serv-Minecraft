<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\interactions;

use pocketmine\block\utils\DyeColor;

final class DyeColorHelper{
	private function __construct(){}

	public static function fromColorId(int $colorId) : DyeColor{
		$cases = DyeColor::cases();
		return $cases[max(0, min(count($cases) - 1, $colorId))];
	}

	public static function toColorId(DyeColor $color) : int{
		$index = array_search($color, DyeColor::cases(), true);
		return is_int($index) ? $index : 0;
	}
}
