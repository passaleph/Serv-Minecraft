<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\interactions;

use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;

final class InteractionHelper{
	private function __construct(){}

	public static function replaceItemInHand(Player $player, Item $replacement) : void{
		$inventory = $player->getInventory();
		$inventory->setItemInHand($replacement);
	}

	public static function milkWithBucket(Player $player, Vector3 $dropPosition) : bool{
		$inventory = $player->getInventory();
		$held = $inventory->getItemInHand();

		if(!$held->equals(VanillaItems::BUCKET(), true, false)){
			return false;
		}

		if($held->getCount() === 1){
			$inventory->setItemInHand(VanillaItems::MILK_BUCKET());
			return true;
		}

		$held->pop();
		$inventory->setItemInHand($held);

		foreach($inventory->addItem(VanillaItems::MILK_BUCKET()) as $leftover){
			self::dropItem($player->getWorld(), $dropPosition, $leftover);
		}

		return true;
	}

	public static function consumeOneFromHand(Player $player) : void{
		$inventory = $player->getInventory();
		$held = $inventory->getItemInHand();
		if($held->isNull()){
			return;
		}

		if($held->getCount() > 1){
			$held->pop();
			$inventory->setItemInHand($held);
		}else{
			$inventory->setItemInHand(VanillaItems::AIR());
		}
	}

	public static function damageHeldItem(Player $player, int $amount = 1) : void{
		$inventory = $player->getInventory();
		$held = $inventory->getItemInHand();
		if($held->isNull()){
			return;
		}

		$held->applyDamage($amount);
		if($held->isBroken()){
			$inventory->setItemInHand(VanillaItems::AIR());
		}else{
			$inventory->setItemInHand($held);
		}
	}

	public static function dropItem(World $world, Vector3 $position, Item $item) : void{
		$location = Location::fromObject($position, $world, lcg_value() * 360, 0);
		$itemEntity = new ItemEntity($location, $item);
		$itemEntity->setPickupDelay(40);
		$itemEntity->setMotion(new Vector3(
			(mt_rand() / mt_getrandmax() - 0.5) * 0.08,
			0.2,
			(mt_rand() / mt_getrandmax() - 0.5) * 0.08
		));
		$itemEntity->spawnToAll();
	}
}
