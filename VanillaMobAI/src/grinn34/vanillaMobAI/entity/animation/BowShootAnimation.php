<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\animation;

use pocketmine\entity\Living;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\entity\animation\Animation;

final class BowShootAnimation implements Animation{
	public function __construct(
		private readonly Living $entity
	){}

	public function encode() : array{
		return [
			ActorEventPacket::create($this->entity->getId(), ActorEvent::USE_ITEM, 0, null)
		];
	}
}
