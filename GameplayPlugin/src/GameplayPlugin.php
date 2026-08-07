<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;

class GameplayPlugin extends PluginBase {

    public function onEnable(): void {
        // Toutes les secondes : on applique les règles de gameplay à chaque joueur
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                $this->appliquerRegles($player);
            }
        }), 20); // 20 ticks = 1 seconde
    }

    /**
     * Toutes les règles de jeu appliquées à chaque joueur.
     * -> Ajoute ici tes futures conditions (mécaniques custom).
     */
    private function appliquerRegles(Player $player): void {
        $this->regenerationRapide($player);
        // Exemples à venir : bonus PvP, malus dans certaines zones, etc.
    }

    /**
     * Régénération accélérée quand la faim est au MAXIMUM.
     */
    private function regenerationRapide(Player $player): void {
        $hunger = $player->getHungerManager();

        // Faim pleine ET vie pas au max -> on booste la régen
        if ($hunger->getFood() >= $hunger->getMaxFood() && $player->getHealth() < $player->getMaxHealth()) {
            // Régénération II pendant 2s, réappliquée chaque seconde tant que la faim est pleine
            $player->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), 40, 0, false));
        }
    }
}
