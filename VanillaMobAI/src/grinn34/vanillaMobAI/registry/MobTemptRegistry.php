<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\registry;

use grinn34\vanillaMobAI\entity\passive\Chicken;
use grinn34\vanillaMobAI\entity\passive\Cow;
use grinn34\vanillaMobAI\entity\passive\Pig;
use grinn34\vanillaMobAI\entity\passive\Sheep;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

final class MobTemptRegistry{
	private const TEMPT_RANGE = 10.0;
	private const CONTINUE_RANGE = 12.0;

	private function __construct(){}

	public static function getTemptRange() : float{
		return self::TEMPT_RANGE;
	}

	public static function getContinueRange() : float{
		return self::CONTINUE_RANGE;
	}

	/**
	 * @return int[]
	 */
	public static function getTemptItemIds(Living $entity) : array{
		return match($entity::class){
			Cow::class, Sheep::class => [
				VanillaItems::WHEAT()->getTypeId(),
			],
			Pig::class => [
				VanillaItems::CARROT()->getTypeId(),
				VanillaItems::POTATO()->getTypeId(),
				VanillaItems::BEETROOT()->getTypeId(),
			],
			Chicken::class => [
				VanillaItems::WHEAT_SEEDS()->getTypeId(),
				VanillaItems::BEETROOT_SEEDS()->getTypeId(),
				VanillaItems::MELON_SEEDS()->getTypeId(),
				VanillaItems::PUMPKIN_SEEDS()->getTypeId(),
			],
			default => []
		};
	}

	public static function isTemptItem(Living $entity, Item $item) : bool{
		if($item->isNull()){
			return false;
		}

		$itemId = $item->getTypeId();
		foreach(self::getTemptItemIds($entity) as $temptId){
			if($itemId === $temptId){
				return true;
			}
		}

		return false;
	}
}
