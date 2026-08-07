<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\movement;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;

final class MobEquipmentHelper{
	private function __construct(){}

	public static function syncMainHand(Entity $entity, Item $item) : void{
		$packet = MobEquipmentPacket::create(
			$entity->getId(),
			new ItemStackWrapper(0, TypeConverter::getInstance()->coreItemStackToNet($item)),
			0,
			0,
			ContainerIds::INVENTORY
		);

		NetworkBroadcastUtils::broadcastPackets($entity->getViewers(), [$packet]);
	}
}
