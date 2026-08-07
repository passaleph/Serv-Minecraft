<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\interactions;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\world\sound\Sound;

final class LevelSoundEventSound implements Sound{
	public function __construct(
		private readonly int $soundEventId
	){}

	public function encode(Vector3 $pos) : array{
		return [LevelSoundEventPacket::nonActorSound($this->soundEventId, $pos, false)];
	}
}
