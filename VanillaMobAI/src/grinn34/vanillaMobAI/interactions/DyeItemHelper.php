<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\interactions;

use pocketmine\block\utils\DyeColor;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;

final class DyeItemHelper{
	private function __construct(){}

	public static function resolveDyeColor(Item $item) : ?DyeColor{
		return match($item->getTypeId()){
			ItemTypeIds::DYE => $item instanceof Dye ? $item->getColor() : null,
			ItemTypeIds::INK_SAC => DyeColor::BLACK,
			ItemTypeIds::LAPIS_LAZULI => DyeColor::BLUE,
			ItemTypeIds::COCOA_BEANS => DyeColor::BROWN,
			ItemTypeIds::BONE_MEAL => DyeColor::WHITE,
			default => null
		};
	}
}
