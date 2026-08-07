<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;

class BorderPlugin extends PluginBase implements Listener {

    /** @var array<string, int>  ["village" => 1000, "survie" => 2000] */
    private array $borders = [];

    public function onEnable(): void {
        // On copie le config par défaut puis on le charge UNE SEULE FOIS en mémoire
        $this->saveDefaultConfig();
        $this->borders = $this->getConfig()->get("borders", []);

        // On écoute les déplacements
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $world  = $player->getWorld();
        $monde  = $world->getFolderName();

        // Ce monde a-t-il une bordure ? (lookup instantané en mémoire)
        $limite = $this->borders[$monde] ?? null;
        if ($limite === null) {
            return; // pas de bordure pour ce monde
        }

        // La bordure est centrée sur le spawn du monde (/setworldspawn)
        $spawn = $world->getSpawnLocation();
        $to    = $event->getTo();

        // Carré : distance au spawn en X ou en Z supérieure à la limite -> on bloque
        if (abs($to->x - $spawn->x) > $limite || abs($to->z - $spawn->z) > $limite) {
            $event->cancel();
        }
    }
}
