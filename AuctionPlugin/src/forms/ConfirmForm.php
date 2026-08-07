<?php

declare(strict_types=1);

namespace forms;

use pocketmine\form\Form;
use pocketmine\player\Player;

class ConfirmForm implements Form {

    public function __construct(
        private \AuctionPlugin $plugin,
        private int $listingId,
        private string $description
    ) {}

    public function jsonSerialize(): array {
        return [
            "type" => "modal",
            "title" => "§lConfirmer l'achat",
            "content" => $this->description,
            "button1" => "§a§lACHETER",
            "button2" => "§c§lANNULER",
        ];
    }

    public function handleResponse(Player $player, $data): void {
        // $data = true si "button1" (Acheter)
        if ($data === true) {
            $this->plugin->buyListing($player, $this->listingId);
        }
    }
}
