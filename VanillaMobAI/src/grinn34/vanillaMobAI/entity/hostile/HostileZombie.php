<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\hostile;

use grinn34\vanillaMobAI\item\VanillaMobSpawnEgg;
use pocketmine\item\Item;
use pocketmine\entity\Zombie;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

class HostileZombie extends Zombie implements HostileMob, AggressivePoseCapable{
	private bool $aggressivePose = false;

	public function setAggressivePose(bool $aggressive) : void{
		if($this->aggressivePose === $aggressive){
			return;
		}

		$this->aggressivePose = $aggressive;
		$this->networkPropertiesDirty = true;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::DELAYED_ATTACKING, $this->aggressivePose);
	}

	public function getPickedItem() : ?Item{
		return VanillaMobSpawnEgg::create("zombie");
	}
}
