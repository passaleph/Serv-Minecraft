<?php

declare(strict_types=1);

namespace forms;

use pocketmine\form\Form;
use pocketmine\player\Player;
use pocketmine\item\StringToItemParser;
use onebone\economyapi\EconomyAPI;


class SuccesListForm implements Form {

    /**
     * @param \SuccesPlugin $plugin Le plugin (pour appeler sa fonction au clic)
     * @param array $succes La liste des succès (venant du succes.yml)
     *                       Format : ["bucheron" => ["item"=>..., "reward"=>..., ...], ...]
     */
    public function __construct(private \SuccesPlugin $plugin, private array $succes) {}

    public function jsonSerialize(): array {
        $buttons = [];

        // Un bouton coloré par succès
        foreach ($this->succes as $nom => $data) {
            $reward = $data["reward"] ?? "?";
            $amount = $data["amount"] ?? "?";
            $item   = $data["item"] ?? "?";

            $buttons[] = [
                "text" =>
                    "§l§6★ " . ucfirst($nom) . "§r\n" .
                    "§8" . $amount . "x " . $item . " §7➜ §2" . $reward
            ];
        }

        // Si aucun succès défini
        if (count($buttons) === 0) {
            $buttons[] = ["text" => "§8§oAucun succès pour l'instant..."];
        }

        return [
            "type"    => "form",
            "title"   => "§l§6✦ §eS§6U§eC§6C§eÈ§6S §e✦",
            "content" =>
                "§7Voici la liste de tes §esuccès§7.\n" .
                "§8Clique sur l'un d'eux pour voir les détails.\n" .
                "§8──────────────────",
            "buttons" => $buttons,
        ];
    }

    public function handleResponse(Player $player, $data): void {
        // $data = l'index du bouton cliqué (ou null si le joueur a fermé le menu)
        if ($data === null) {
            return;
        }

        // On retrouve le succès cliqué à partir de l'index du bouton
        $noms = array_keys($this->succes);
        $nomChoisi = $noms[$data] ?? null;
        if ($nomChoisi === null) {
            return;
        }
        $succes = $this->succes[$nomChoisi];

        // On appelle la fonction du plugin avec la récompense ET ce qu'il faut
        $this->verifierSucces(
            $player,
            $succes["item"],           // ce qu'il faut avoir
            $succes["amount"],         // combien
            $succes["reward"],         // la récompense
            $succes["reward_amount"]   // combien de récompense
        );
    }

    private function verifierSucces(Player $player, $item, $amount, $reward , $reward_amount) :void {
        $items = StringToItemParser::getInstance()->parse($item);
        if ($items === null) {
            return;
        }
        $items->setCount($amount);

    
        if ($player->getInventory()->contains($items)) {

            if ($reward === "money") {
                // Récompense en argent (EconomyAPI)
                $player->getInventory()->removeItem($items);
                EconomyAPI::getInstance()->addMoney($player->getName(), $reward_amount);
                $player->sendMessage("§aTu as reçu §e" . $reward_amount . "§a pièces !");
            } else {
                // Récompense en item
                $finalReward = StringToItemParser::getInstance()->parse($reward);
                if ($finalReward !== null) {
                    $finalReward->setCount($reward_amount);
                    $player->getInventory()->removeItem($items);   
                    $player->getInventory()->addItem($finalReward);
                    $player->sendMessage("§8Récompense ajoutée");
                }
            }

        } else {
            $player->sendMessage("§fVous n'avez pas seuil requis");
        }


    }
}
