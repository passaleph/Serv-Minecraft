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

        // Cle d'identification = XUID (identifiant Xbox stable, ne change pas
        // meme si le joueur change de pseudo). owner_id / player_id contiennent le XUID.
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS factions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT UNIQUE NOT NULL,
                description TEXT,
                owner_id TEXT NOT NULL,
                site_web TEXT,
                gemmes INTEGER DEFAULT 0,
                slazyx INTEGER DEFAULT 0
            )
        ");
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS faction_members (
                faction_id INTEGER NOT NULL,
                player_id TEXT NOT NULL
            )
        ");
    }


    public function onDisable(): void {
        $this->db->close();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        $name = $command->getName();

        if ($name !== "faction") {
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

        if ($sousCommande === "show") {
            return $this->showFaction($sender);
        }

        if ($sousCommande === "delete") {
            return $this->deleteFaction($sender);
        }

        $sender->sendMessage("§cSous-commande inconnue.");
        return true;
    }

    /**
     * Renvoie le XUID du joueur, ou null (avec un message) si indisponible.
     * Le XUID est vide si le serveur est en mode hors-ligne (xbox-auth=false).
     */
    private function xuid(Player $player): ?string {
        $xuid = $player->getXuid();
        if ($xuid === "") {
            $player->sendMessage("§cAuthentification Xbox requise (le serveur doit être en mode en ligne).");
            return null;
        }
        return $xuid;
    }

    private function handleCreate(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage("§cUsage: /faction create <nom>");
            return true;
        }

        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        $nom = $args[1];

        if ($this->joueurAUneFaction($xuid)) {
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

    /** @param string $xuid XUID du joueur */
    public function joueurAUneFaction(string $xuid): bool {
        $stmt = $this->db->prepare("
            SELECT f.id FROM factions f
            LEFT JOIN faction_members fm ON fm.faction_id = f.id
            WHERE fm.player_id = :id OR f.owner_id = :id
        ");
        $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        return $result !== false;
    }

    public function nomFactionExiste(string $nom): bool {
        $stmt = $this->db->prepare("SELECT id FROM factions WHERE nom = :nom");
        $stmt->bindValue(":nom", $nom, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        return $result !== false;
    }

    /** @param string $ownerXuid XUID du proprietaire */
    public function creerFaction(string $nom, string $description, string $siteWeb, string $ownerXuid): void {
        $stmt = $this->db->prepare("
            INSERT INTO factions (nom, description, owner_id, site_web, gemmes, slazyx)
            VALUES (:nom, :description, :owner_id, :site_web, 0, 0)
        ");
        $stmt->bindValue(":nom", $nom, SQLITE3_TEXT);
        $stmt->bindValue(":description", $description, SQLITE3_TEXT);
        $stmt->bindValue(":owner_id", $ownerXuid, SQLITE3_TEXT);
        $stmt->bindValue(":site_web", $siteWeb, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function showFaction(Player $player): bool {

        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        if (!$this->joueurAUneFaction($xuid)) {
            $player->sendMessage("§cTu ne possèdes pas de faction, crée-en une avec /faction create <NOM>");
            return true;
        }

        $stmt = $this->db->prepare("
            SELECT f.nom FROM factions f
            LEFT JOIN faction_members fm ON fm.faction_id = f.id
            WHERE fm.player_id = :id OR f.owner_id = :id
        ");
        $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($result === false) {
            $player->sendMessage("§cImpossible de récupérer ta faction.");
            return true;
        }

        $player->sendMessage("§aTon nom de faction est §e" . $result['nom']);
        return true;
    }

    public function deleteFaction(Player $player): bool {

        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        // Seul le proprietaire peut supprimer sa faction
        $stmt = $this->db->prepare("SELECT id FROM factions WHERE owner_id = :id");
        $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($row === false) {
            $player->sendMessage("§cTu n'es propriétaire d'aucune faction.");
            return true;
        }

        $factionId = $row['id'];

        // On supprime d'abord les membres, puis la faction
        $delMembers = $this->db->prepare("DELETE FROM faction_members WHERE faction_id = :fid");
        $delMembers->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $delMembers->execute();

        $delFaction = $this->db->prepare("DELETE FROM factions WHERE id = :fid");
        $delFaction->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $delFaction->execute();

        $player->sendMessage("§aTa faction a été supprimée.");
        return true;
    }

}
