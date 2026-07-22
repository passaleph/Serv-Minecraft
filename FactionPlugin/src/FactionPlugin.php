<?php


declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use forms\CreateFactionForm; 


class FactionPlugin extends PluginBase {

    private \SQLite3 $db;


    public function onEnable(): void {
        $this->db = new \SQLite3($this->getDataFolder() . "stats.sqlite");
    }


    public function onDisable(): void {
       $this->db->close();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if ($command->getName() !== "faction") {
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage("Cette commande est réservée aux joueurs.");
            return true;
        }


        if (count($args) < 1) {
            $sender->sendMessage("§cUsage: /faction create <nom>");
            return true;
        }

        $sousCommande = $args[0];

        if ($sousCommande === "create") {
            return $this->handleCreate($sender, $args);
        }

        $sender->sendMessage("§cSous-commande inconnue.");
            return true;
  
    }



      private function handleCreate(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage("§cUsage: /faction create <nom>");
            return true;
        }

        $nom = $args[1];

         if ($this->joueurAUneFaction($player->getName())) {
            $player->sendMessage("§cTu as déjà une faction !");
            return true;
        }

  
        if ($this->nomFactionExiste($nom)) {
            $player->sendMessage("§cCe nom de faction existe déjà !");
            return true;
        }

        // Ouvre le formulaire de configuration
        $player->sendForm(new CreateFactionForm($this, $nom));

        return true;

      }

      public function joueurAUneFaction(string $playerName): bool {
        $stmt = $this->db->prepare("
            SELECT f.id FROM factions f
            LEFT JOIN faction_members fm ON fm.faction_id = f.id
            WHERE fm.player_id = :name OR f.owner_id = :name
        ");
        $stmt->bindValue(":name", $playerName, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        return $result !== false;
    }

    public function nomFactionExiste(string $nom): bool {
        $stmt = $this->db->prepare("SELECT id FROM factions WHERE nom = :nom");
        $stmt->bindValue(":nom", $nom, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        return $result !== false;
    }

    public function creerFaction(string $nom, string $description, string $siteWeb, string $ownerId): void {
        $stmt = $this->db->prepare("
            INSERT INTO factions (nom, description, owner_id, site_web, gemmes, slazyx)
            VALUES (:nom, :description, :owner_id, :site_web, 0, 0)
        ");
        $stmt->bindValue(":nom", $nom, SQLITE3_TEXT);
        $stmt->bindValue(":description", $description, SQLITE3_TEXT);
        $stmt->bindValue(":owner_id", $ownerId, SQLITE3_TEXT);
        $stmt->bindValue(":site_web", $siteWeb, SQLITE3_TEXT);
        $stmt->execute();
    }
}



