<?php

declare(strict_types=1);

namespace forms;

use pocketmine\form\Form;
use pocketmine\player\Player;

class PrimeListForm implements Form {

    /**
     * @param array $primes Liste des primes : [ ["pseudo" => ..., "amount" => ...], ... ]
     */
    public function __construct(private array $primes) {}

    public function jsonSerialize(): array {
        $buttons = [];

        foreach ($this->primes as $prime) {
            $buttons[] = [
                "text" =>
                    "§c§l☠ " . $prime["pseudo"] . "§r\n" .
                    "§7Prime : §a" . $prime["amount"] . " §7pièces",
                // Icône générique (tête de skin réelle impossible sur Bedrock, voir note)
                "image" => [
                    "type" => "path",
                    "data" => "textures/ui/icon_multiplayer"
                ]
            ];
        }

        if (count($buttons) === 0) {
            $buttons[] = ["text" => "§8§oAucune prime active pour le moment..."];
        }

        return [
            "type"    => "form",
            "title"   => "§l§4☠ §cPRIMES §4☠",
            "content" => "§7Les têtes mises à prix sur le serveur :\n§8──────────────────",
            "buttons" => $buttons,
        ];
    }

    public function handleResponse(Player $player, $data): void {
        // Affichage seulement (pas d'action au clic pour l'instant)
    }
}
