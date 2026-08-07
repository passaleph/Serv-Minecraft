<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use onebone\economyapi\EconomyAPI;

class HudPlugin extends PluginBase {

    public function onEnable(): void {
        // Toutes les secondes : on met à jour les tags de chaque joueur
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                $this->majTags($player);
            }
        }), 20); // 20 ticks = 1 seconde
    }

    /**
     * Ici on met TOUT ce qu'on veut afficher dans le scoreboard.
     * Pour ajouter un truc : $this->setTag($player, "<nom>", "<valeur>");
     * puis mets {<nom>} dans scorehud.yml.
     */
    private function majTags(Player $player): void {
        // === Pseudo -> {hud.name} ===
        $this->setTag($player, "hud.name", $player->getName());

        // === Argent (EconomyAPI) -> {hud.money} ===
        $money = EconomyAPI::getInstance()->myMoney($player);
        $this->setTag($player, "hud.money", (string) $money);

        // === Joueurs en ligne -> {hud.online} et {hud.max_online} ===
        $online = count($this->getServer()->getOnlinePlayers());
        $max = $this->getServer()->getMaxPlayers();
        $this->setTag($player, "hud.online", (string) $online);
        $this->setTag($player, "hud.max_online", (string) $max);

        // === Ajoute d'autres tags ici, par exemple : ===
        // $this->setTag($player, "hud.ping", (string) $player->getNetworkSession()->getPing());
        // $this->setTag($player, "hud.monde", $player->getWorld()->getFolderName());
    }

    /** Envoie un tag au ScoreHud pour un joueur. */
    private function setTag(Player $player, string $tag, string $valeur): void {
        (new PlayerTagUpdateEvent($player, new ScoreTag($tag, $valeur)))->call();
    }
}
