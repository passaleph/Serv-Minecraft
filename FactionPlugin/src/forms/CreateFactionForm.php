<?php

declare(strict_types=1);

namespace forms;

use pocketmine\form\Form;
use pocketmine\player\Player;

class CreateFactionForm implements Form {

    // public function __construct(
    //     private FactionPlugin $plugin,
    //     private string $nomFaction
    // ) {}
    // Nouvelle méthode plus rapide mais je suis trop null mdr 


    private \FactionPlugin $plugin;
    private string $nomFaction;

    public function __construct(\FactionPlugin $plugin, string $nomFaction) {
        $this->plugin = $plugin;
        $this->nomFaction = $nomFaction;
    }

    public function jsonSerialize(): array {
        return [
            "type" => "custom_form",
            "title" => "Créer la faction: " . $this->nomFaction,
            "content" => [
                [
                    "type" => "label",
                    "text" => "Configure ta nouvelle faction"
                ],
                [
                    "type" => "input",
                    "text" => "Description",
                    "placeholder" => "Décris ta faction..."
                ],
                [
                    "type" => "input",
                    "text" => "Site web (optionnel)",
                    "placeholder" => "https://..."
                ]
            ]
        ];
    }

    public function handleResponse(Player $player, $data): void {
        if ($data === null) {
            $player->sendMessage("§cCréation annulée.");
            return;
        }

        // $data est un tableau indexé dans l'ordre des champs
        // $data[0] = le label (pas de valeur utile)
        // $data[1] = la description
        // $data[2] = le site web
        $description = $data[1];
        $siteWeb = $data[2];

        $this->plugin->creerFaction(
            $this->nomFaction,
            $description,
            $siteWeb,
            $player->getXuid()
        );

        $player->sendMessage("§aFaction \"" . $this->nomFaction . "\" créée avec succès !");
    }
}