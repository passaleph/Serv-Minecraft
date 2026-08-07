<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\performance;

use pocketmine\entity\Living;
use pocketmine\player\Player;

final class MobActivationHelper{
	private function __construct(){}

	public static function isActivated(Living $entity) : bool{
		$world = $entity->getWorld();
		$position = $entity->getPosition();
		$rangeSq = PerformanceConfig::activationRangeSq();

		foreach($world->getPlayers() as $player){
			if(!$player instanceof Player || !$player->isConnected() || $player->isClosed() || $player->isSpectator()){
				continue;
			}

			if($player->getWorld() !== $world){
				continue;
			}

			if($position->distanceSquared($player->getPosition()) <= $rangeSq){
				return true;
			}
		}

		return false;
	}
}
