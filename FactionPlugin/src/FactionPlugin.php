<?php


declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\world\Position;
use pocketmine\block\tile\Container;
use onebone\economyapi\EconomyAPI;
use forms\CreateFactionForm;


class FactionPlugin extends PluginBase implements Listener {

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

         $this->db->exec("
         CREATE TABLE IF NOT EXISTS faction_invite (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_sender TEXT NOT NULL,     -- XUID du joueur qui a ENVOYÉ l'invitation
            id_receiver TEXT NOT NULL,   -- XUID du joueur qui a REÇU l'invitation
            faction_name TEXT NOT NULL   -- nom de la faction concernée
            );
        ");

     
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS faction_chunk (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                faction_id INTEGER NOT NULL,
                world TEXT NOT NULL,
                chunk_x INTEGER NOT NULL,
                chunk_z INTEGER NOT NULL,
                UNIQUE(world, chunk_x, chunk_z)
            )
        ");

        // On écoute les événements (protection des chunks)
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
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
            return $this->helpFaction($sender);
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

        if ($sousCommande === "invite") {
            return $this->inviteFaction($sender, $args);
        }

        if ($sousCommande === "accept") {
            return $this->acceptFaction($sender);
        }

        if ($sousCommande === "about") {
            return $this->aboutFaction($sender);
        }

        if ($sousCommande === "tp") {
            return $this->tpFaction($sender);
        }

        if ($sousCommande === "donate") {
            return $this->donateFaction($sender, $args);
        }

        if ($sousCommande === "chunk") {
            $action = $args[1] ?? "";
            if ($action === "claim") {
                return $this->claimChunk($sender);
            }
            if ($action === "see") {
                return $this->seeChunks($sender);
            }
            $sender->sendMessage("§cUsage: /faction chunk <claim|see>");
            return true;
        }

        if ($sousCommande === "kick") {
            return $this->kickFaction($sender, $args);
        }

        if ($sousCommande === "help") {
            return $this->helpFaction($sender);
        }

        $sender->sendMessage("§cSous-commande inconnue. Tape §e/faction help §cpour la liste.");
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

        $delChunks = $this->db->prepare("DELETE FROM faction_chunk WHERE faction_id = :fid");
        $delChunks->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $delChunks->execute();

        $delFaction = $this->db->prepare("DELETE FROM factions WHERE id = :fid");
        $delFaction->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $delFaction->execute();

        $player->sendMessage("§aTa faction a été supprimée.");
        return true;
    }


    public function inviteFaction(Player $player, array $args): bool {

        $senderXuid = $this->xuid($player);
        if ($senderXuid === null) {
            return true;
        }
        if (!isset($args[1])) {
            $player->sendMessage("§cVeuillez saisir un pseudo a inviter.");
            return true;
        }

        if (!$this->joueurAUneFaction($senderXuid)) {
            $player->sendMessage("§cTu ne possèdes pas de faction, crée-en une avec /faction create <NOM>");
            return true;
        }

        $stmt = $this->db->prepare("
            SELECT f.nom  FROM factions f
            LEFT JOIN faction_members fm ON fm.faction_id = f.id
            WHERE fm.player_id = :id OR f.owner_id = :id
        ");
        $stmt->bindValue(":id", $senderXuid, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($result === false) {
            $player->sendMessage("§cImpossible de récupérer ta faction.");
            return true;
        }

        $pseudo = $args[1];
        $cible = $this->getServer()->getPlayerExact($pseudo);
        if ($cible === null) {
            $player->sendMessage("§4Impossible de récupérer ce joueur vérifier si ils est connecté ou si le psuedo est bien éccrit.");
            return true;
        }
        $player->sendMessage("§2Demande bien envoyé");
        $receiverXuid = $this->xuid($cible);
        $cible->sendMessage("§bVous avez été invité a rejoindre la faction " . $result['nom'] . "§3 pour rejoindre faite §6/faction accept" );
        $factionName = $result['nom'];
       

       
        $stmt = $this->db->prepare("
            INSERT INTO faction_invite (id_sender, id_receiver, faction_name)
            VALUES (:id_sender, :id_receiver, :faction_name)
        ");
        $stmt->bindValue(":id_sender", $senderXuid, SQLITE3_TEXT);
        $stmt->bindValue(":id_receiver", $receiverXuid, SQLITE3_TEXT);
        $stmt->bindValue(":faction_name", $factionName, SQLITE3_TEXT);
        $stmt->execute();

        // L'invitation expire au bout de 30s : on programme sa suppression de la BDD.
        // (si le joueur accepte avant, elle est déjà supprimée -> ça ne supprime rien de plus)
        $inviteId = $this->db->lastInsertRowID();
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($inviteId): void {
            $del = $this->db->prepare("DELETE FROM faction_invite WHERE id = :id");
            $del->bindValue(":id", $inviteId, SQLITE3_INTEGER);
            $del->execute();
        }), 20 * 30); // 20 ticks/s * 30 = 30 secondes

        return true;
    }

    /**
     * /faction accept -> accepte l'invitation en attente et rejoint la faction.
     */
    public function acceptFaction(Player $player): bool {

        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        // Déjà dans une faction ?
        if ($this->joueurAUneFaction($xuid)) {
            $player->sendMessage("§cTu as déjà une faction !");
            return true;
        }

        // Récupérer l'invitation en attente la plus récente
        $stmt = $this->db->prepare("
            SELECT faction_name FROM faction_invite
            WHERE id_receiver = :id
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
        $invite = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($invite === false) {
            $player->sendMessage("§cTu n'as aucune invitation en attente (ou elle a expiré).");
            return true;
        }

        $factionName = $invite['faction_name'];

        // Retrouver l'id de la faction
        $stmt = $this->db->prepare("SELECT id FROM factions WHERE nom = :nom");
        $stmt->bindValue(":nom", $factionName, SQLITE3_TEXT);
        $faction = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($faction === false) {
            // La faction n'existe plus -> on nettoie l'invitation
            $del = $this->db->prepare("DELETE FROM faction_invite WHERE id_receiver = :id");
            $del->bindValue(":id", $xuid, SQLITE3_TEXT);
            $del->execute();
            $player->sendMessage("§cCette faction n'existe plus.");
            return true;
        }

        $factionId = $faction['id'];

        // Ajouter le joueur comme membre
        $stmt = $this->db->prepare("
            INSERT INTO faction_members (faction_id, player_id)
            VALUES (:fid, :pid)
        ");
        $stmt->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $stmt->bindValue(":pid", $xuid, SQLITE3_TEXT);
        $stmt->execute();

        // Supprimer ses invitations en attente
        $del = $this->db->prepare("DELETE FROM faction_invite WHERE id_receiver = :id");
        $del->bindValue(":id", $xuid, SQLITE3_TEXT);
        $del->execute();

        $player->sendMessage("§aTu as rejoint la faction §e" . $factionName . " §a!");
        return true;
    }

    /**
     * Renvoie la faction du joueur (membre OU chef), ou null.
     * @return array<string,mixed>|null
     */
    private function getFactionByPlayer(string $xuid): ?array {
        $stmt = $this->db->prepare("
            SELECT f.* FROM factions f
            LEFT JOIN faction_members fm ON fm.faction_id = f.id
            WHERE fm.player_id = :id OR f.owner_id = :id
        ");
        $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * /faction about -> affiche les stats de la faction (accessible à tous les membres).
     */
    public function aboutFaction(Player $player): bool {
        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        $faction = $this->getFactionByPlayer($xuid);
        if ($faction === null) {
            $player->sendMessage("§cTu ne possèdes pas de faction.");
            return true;
        }

        // Nombre de membres (les membres dans faction_members + le chef qui n'y est pas)
        $stmt = $this->db->prepare("SELECT COUNT(*) AS n FROM faction_members WHERE faction_id = :fid");
        $stmt->bindValue(":fid", $faction['id'], SQLITE3_INTEGER);
        $nbMembres = $stmt->execute()->fetchArray(SQLITE3_ASSOC)['n'] + 1; // +1 pour le chef

        // Nombre de chunks claim
        $stmt = $this->db->prepare("SELECT COUNT(*) AS n FROM faction_chunk WHERE faction_id = :fid");
        $stmt->bindValue(":fid", $faction['id'], SQLITE3_INTEGER);
        $nbChunks = $stmt->execute()->fetchArray(SQLITE3_ASSOC)['n'];

        $player->sendMessage("§6§l===== Faction " . $faction['nom'] . " =====");
        $player->sendMessage("§7Description: §f" . ($faction['description'] ?? "-"));
        $player->sendMessage("§7Site web: §f" . ($faction['site_web'] ?? "-"));
        $player->sendMessage("§7Argent (slazyx): §e" . $faction['slazyx']);
        $player->sendMessage("§7Gemmes: §d" . $faction['gemmes']);
        $player->sendMessage("§7Membres: §a" . $nbMembres);
        $player->sendMessage("§7Chunks claim: §b" . $nbChunks);
        return true;
    }

    /**
     * /faction chunk claim -> claim le chunk où se tient le joueur (chef uniquement).
     * Prix : 1er gratuit, 2e-4e = 1000, 5e et + = 10000 (en slazyx).
     */
    public function claimChunk(Player $player): bool {
        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        // Seul le CHEF (propriétaire) peut claim
        $stmt = $this->db->prepare("SELECT id, slazyx FROM factions WHERE owner_id = :id");
        $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
        $faction = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($faction === false) {
            $player->sendMessage("§cSeul le chef de la faction peut claim des chunks.");
            return true;
        }
        $factionId = $faction['id'];
        $slazyx = $faction['slazyx'];

        // Chunk où se trouve le joueur (>> 4 = division par 16 arrondie vers le bas)
        $world = $player->getWorld()->getFolderName();
        $cx = $player->getPosition()->getFloorX() >> 4;
        $cz = $player->getPosition()->getFloorZ() >> 4;

        // Déjà claim (par n'importe qui) ?
        $stmt = $this->db->prepare("SELECT faction_id FROM faction_chunk WHERE world = :w AND chunk_x = :x AND chunk_z = :z");
        $stmt->bindValue(":w", $world, SQLITE3_TEXT);
        $stmt->bindValue(":x", $cx, SQLITE3_INTEGER);
        $stmt->bindValue(":z", $cz, SQLITE3_INTEGER);
        if ($stmt->execute()->fetchArray(SQLITE3_ASSOC) !== false) {
            $player->sendMessage("§cCe chunk est déjà claim.");
            return true;
        }

        // Combien de chunks la faction possède déjà -> détermine le prix
        $stmt = $this->db->prepare("SELECT COUNT(*) AS n FROM faction_chunk WHERE faction_id = :fid");
        $stmt->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $nb = $stmt->execute()->fetchArray(SQLITE3_ASSOC)['n'];

        if ($nb === 0) {
            $prix = 0;          // 1er gratuit
        } elseif ($nb < 4) {
            $prix = 1000;       // 2e, 3e, 4e
        } else {
            $prix = 10000;      // 5e et +
        }

        // Assez d'argent ?
        if ($prix > 0 && $slazyx < $prix) {
            $player->sendMessage("§cIl faut §e" . $prix . " slazyx §cpour ce chunk (ta faction en a §e" . $slazyx . "§c).");
            return true;
        }

        // Débiter les slazyx
        if ($prix > 0) {
            $upd = $this->db->prepare("UPDATE factions SET slazyx = slazyx - :prix WHERE id = :fid");
            $upd->bindValue(":prix", $prix, SQLITE3_INTEGER);
            $upd->bindValue(":fid", $factionId, SQLITE3_INTEGER);
            $upd->execute();
        }

        // Enregistrer le chunk
        $ins = $this->db->prepare("INSERT INTO faction_chunk (faction_id, world, chunk_x, chunk_z) VALUES (:fid, :w, :x, :z)");
        $ins->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $ins->bindValue(":w", $world, SQLITE3_TEXT);
        $ins->bindValue(":x", $cx, SQLITE3_INTEGER);
        $ins->bindValue(":z", $cz, SQLITE3_INTEGER);
        $ins->execute();

        $player->sendMessage("§aChunk claim ! §7(" . $cx . ", " . $cz . ") §7- coût: §e" . $prix . " slazyx");
        return true;
    }

    /**
     * /faction chunk see -> affiche une carte des chunks autour du joueur (style Paladium).
     */
    public function seeChunks(Player $player): bool {
        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        $faction = $this->getFactionByPlayer($xuid);
        if ($faction === null) {
            $player->sendMessage("§cTu ne possèdes pas de faction.");
            return true;
        }
        $maFactionId = $faction['id'];

        $world = $player->getWorld()->getFolderName();
        $centerCx = $player->getPosition()->getFloorX() >> 4;
        $centerCz = $player->getPosition()->getFloorZ() >> 4;
        $rayon = 5; // grille de 11x11 chunks

        // Récupérer tous les chunks claim dans la zone visible
        $stmt = $this->db->prepare("
            SELECT chunk_x, chunk_z, faction_id FROM faction_chunk
            WHERE world = :w
              AND chunk_x BETWEEN :xmin AND :xmax
              AND chunk_z BETWEEN :zmin AND :zmax
        ");
        $stmt->bindValue(":w", $world, SQLITE3_TEXT);
        $stmt->bindValue(":xmin", $centerCx - $rayon, SQLITE3_INTEGER);
        $stmt->bindValue(":xmax", $centerCx + $rayon, SQLITE3_INTEGER);
        $stmt->bindValue(":zmin", $centerCz - $rayon, SQLITE3_INTEGER);
        $stmt->bindValue(":zmax", $centerCz + $rayon, SQLITE3_INTEGER);
        $res = $stmt->execute();

        $claimed = []; // "cx:cz" => faction_id
        while (($row = $res->fetchArray(SQLITE3_ASSOC)) !== false) {
            $claimed[$row['chunk_x'] . ":" . $row['chunk_z']] = $row['faction_id'];
        }

        $player->sendMessage("§6§l=== Carte des chunks §7(le centre = toi) §6§l===");
        for ($dz = -$rayon; $dz <= $rayon; $dz++) {
            $ligne = "";
            for ($dx = -$rayon; $dx <= $rayon; $dx++) {
                $cx = $centerCx + $dx;
                $cz = $centerCz + $dz;
                $cle = $cx . ":" . $cz;

                if ($dx === 0 && $dz === 0) {
                    $ligne .= "§e█"; // toi
                } elseif (isset($claimed[$cle])) {
                    $ligne .= ($claimed[$cle] == $maFactionId) ? "§a█" : "§c█";
                } else {
                    $ligne .= "§8█"; // libre
                }
            }
            $player->sendMessage($ligne);
        }
        $player->sendMessage("§e█ §7Toi  §a█ §7Ta faction  §c█ §7Autre  §8█ §7Libre");
        return true;
    }

    /**
     * /faction donate slazyx <montant>
     * Le joueur donne <montant> de son argent (EconomyAPI). La faction reçoit montant / 2 en slazyx.
     */
    public function donateFaction(Player $player, array $args): bool {
        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        // Syntaxe : /faction donate slazyx <montant>
        if (($args[1] ?? "") !== "slazyx" || !isset($args[2]) || !ctype_digit($args[2])) {
            $player->sendMessage("§cUsage: /faction donate slazyx <montant>");
            return true;
        }
        $montant = (int) $args[2];
        if ($montant <= 0) {
            $player->sendMessage("§cLe montant doit être supérieur à 0.");
            return true;
        }

        // Le joueur doit avoir une faction
        $faction = $this->getFactionByPlayer($xuid);
        if ($faction === null) {
            $player->sendMessage("§cTu ne possèdes pas de faction.");
            return true;
        }

        // Vérifier qu'il a bien assez d'argent
        $economy = EconomyAPI::getInstance();
        if ($economy->myMoney($player) < $montant) {
            $player->sendMessage("§cTu n'as pas assez d'argent (il te faut §e" . $montant . "§c).");
            return true;
        }

        // Débiter l'argent du joueur
        $economy->reduceMoney($player, $montant);

        // Conversion : argent / 2 = slazyx
        $gainSlazyx = intdiv($montant, 2);

        // Ajouter les slazyx à la faction en BDD
        $upd = $this->db->prepare("UPDATE factions SET slazyx = slazyx + :gain WHERE id = :fid");
        $upd->bindValue(":gain", $gainSlazyx, SQLITE3_INTEGER);
        $upd->bindValue(":fid", $faction['id'], SQLITE3_INTEGER);
        $upd->execute();

        $player->sendMessage(
            "§aTu as donné §e" . $montant . " §ad'argent à ta faction §7= §e" . $gainSlazyx . " slazyx §a!"
        );
        return true;
    }

    // ======================= PROTECTION DES CHUNKS =======================

    /** Renvoie l'id de la faction qui possède ce chunk, ou null si libre. */
    private function getChunkOwner(string $world, int $cx, int $cz): ?int {
        $stmt = $this->db->prepare("SELECT faction_id FROM faction_chunk WHERE world = :w AND chunk_x = :x AND chunk_z = :z");
        $stmt->bindValue(":w", $world, SQLITE3_TEXT);
        $stmt->bindValue(":x", $cx, SQLITE3_INTEGER);
        $stmt->bindValue(":z", $cz, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return $row === false ? null : (int) $row['faction_id'];
    }

    /** Le joueur (xuid) est-il chef OU membre de cette faction ? */
    private function isMemberOf(string $xuid, int $factionId): bool {
        // Chef ?
        $stmt = $this->db->prepare("SELECT id FROM factions WHERE id = :fid AND owner_id = :xuid");
        $stmt->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $stmt->bindValue(":xuid", $xuid, SQLITE3_TEXT);
        if ($stmt->execute()->fetchArray(SQLITE3_ASSOC) !== false) {
            return true;
        }
        // Membre ?
        $stmt = $this->db->prepare("SELECT faction_id FROM faction_members WHERE faction_id = :fid AND player_id = :xuid");
        $stmt->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $stmt->bindValue(":xuid", $xuid, SQLITE3_TEXT);
        return $stmt->execute()->fetchArray(SQLITE3_ASSOC) !== false;
    }

    private function getFactionName(int $factionId): ?string {
        $stmt = $this->db->prepare("SELECT nom FROM factions WHERE id = :id");
        $stmt->bindValue(":id", $factionId, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return $row === false ? null : $row['nom'];
    }

    /** Le joueur peut-il modifier (casser/poser) à cette position ? */
    private function canBuild(Player $player, Position $pos): bool {
        $owner = $this->getChunkOwner($pos->getWorld()->getFolderName(), $pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
        if ($owner === null) {
            return true; // chunk libre -> tout le monde peut
        }
        return $this->isMemberOf($player->getXuid(), $owner);
    }

    public function onBreak(BlockBreakEvent $event): void {
        if (!$this->canBuild($event->getPlayer(), $event->getBlock()->getPosition())) {
            $event->cancel();
            $event->getPlayer()->sendMessage("§cCe terrain appartient à une faction.");
        }
    }

    public function onPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $world = $player->getWorld()->getFolderName();
        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $owner = $this->getChunkOwner($world, $x >> 4, $z >> 4);
            if ($owner !== null && !$this->isMemberOf($player->getXuid(), $owner)) {
                $event->cancel();
                $player->sendMessage("§cCe terrain appartient à une faction.");
                return;
            }
        }
    }

    public function onExplode(EntityExplodeEvent $event): void {
        $world = $event->getPosition()->getWorld()->getFolderName();
        $cache = [];   // "cx:cz" => bool (protégé ou non), pour éviter de requêter 2x le même chunk
        $safe = [];
        foreach ($event->getBlockList() as $block) {
            $pos = $block->getPosition();
            $cle = ($pos->getFloorX() >> 4) . ":" . ($pos->getFloorZ() >> 4);
            if (!isset($cache[$cle])) {
                $cache[$cle] = $this->getChunkOwner($world, $pos->getFloorX() >> 4, $pos->getFloorZ() >> 4) !== null;
            }
            if (!$cache[$cle]) {
                $safe[] = $block; // chunk libre -> le bloc peut exploser
            }
        }
        $event->setBlockList($safe); // les blocs des chunks claim sont retirés -> protégés
    }

    public function onBurn(BlockBurnEvent $event): void {
        $pos = $event->getBlock()->getPosition();
        if ($this->getChunkOwner($pos->getWorld()->getFolderName(), $pos->getFloorX() >> 4, $pos->getFloorZ() >> 4) !== null) {
            $event->cancel(); // le feu ne détruit pas les blocs d'une faction
        }
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $block = $event->getBlock();
        $pos = $block->getPosition();
        $owner = $this->getChunkOwner($pos->getWorld()->getFolderName(), $pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
        if ($owner === null) {
            return; // chunk libre
        }
        $player = $event->getPlayer();
        if ($this->isMemberOf($player->getXuid(), $owner)) {
            return; // membre -> accès autorisé
        }
        // Bloque uniquement les conteneurs (coffres, barils, fours...)
        $tile = $pos->getWorld()->getTile($pos);
        if ($tile instanceof Container) {
            $event->cancel();
            $player->sendMessage("§cCe coffre appartient à une faction.");
        }
    }

    public function onMove(PlayerMoveEvent $event): void {
        $from = $event->getFrom();
        $to = $event->getTo();

        // On ne fait rien tant qu'on ne change pas de chunk (évite de spammer la BDD)
        $fcx = $from->getFloorX() >> 4; $fcz = $from->getFloorZ() >> 4;
        $tcx = $to->getFloorX() >> 4;   $tcz = $to->getFloorZ() >> 4;
        if ($fcx === $tcx && $fcz === $tcz) {
            return;
        }

        $world = $to->getWorld()->getFolderName();
        $ownerTo = $this->getChunkOwner($world, $tcx, $tcz);
        $ownerFrom = $this->getChunkOwner($world, $fcx, $fcz);

        if ($ownerTo !== $ownerFrom) {
            if ($ownerTo === null) {
                $event->getPlayer()->sendMessage("§7» Tu quittes un territoire de faction.");
            } else {
                $event->getPlayer()->sendMessage("§e» Tu entres sur le territoire de la faction §6" . $this->getFactionName($ownerTo));
            }
        }
    }

    /**
     * /faction kick <pseudo> -> exclut un membre (chef uniquement, cible connectée).
     */
    public function kickFaction(Player $player, array $args): bool {
        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        if (!isset($args[1])) {
            $player->sendMessage("§cUsage: /faction kick <pseudo>");
            return true;
        }

        // Seul le chef peut exclure
        $stmt = $this->db->prepare("SELECT id FROM factions WHERE owner_id = :id");
        $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
        $faction = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($faction === false) {
            $player->sendMessage("§cSeul le chef de la faction peut exclure un membre.");
            return true;
        }
        $factionId = $faction['id'];

        // La cible doit être connectée (pour retrouver son XUID à partir du pseudo)
        $cible = $this->getServer()->getPlayerExact($args[1]);
        if ($cible === null) {
            $player->sendMessage("§cJoueur introuvable ou hors ligne.");
            return true;
        }
        $cibleXuid = $cible->getXuid();

        if ($cibleXuid === $xuid) {
            $player->sendMessage("§cTu ne peux pas t'exclure toi-même.");
            return true;
        }

        // La cible est-elle membre de CETTE faction ?
        $stmt = $this->db->prepare("SELECT faction_id FROM faction_members WHERE faction_id = :fid AND player_id = :pid");
        $stmt->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $stmt->bindValue(":pid", $cibleXuid, SQLITE3_TEXT);
        if ($stmt->execute()->fetchArray(SQLITE3_ASSOC) === false) {
            $player->sendMessage("§cCe joueur n'est pas dans ta faction.");
            return true;
        }

        // Exclure
        $del = $this->db->prepare("DELETE FROM faction_members WHERE faction_id = :fid AND player_id = :pid");
        $del->bindValue(":fid", $factionId, SQLITE3_INTEGER);
        $del->bindValue(":pid", $cibleXuid, SQLITE3_TEXT);
        $del->execute();

        $player->sendMessage("§a" . $cible->getName() . " a été exclu de ta faction.");
        $cible->sendMessage("§cTu as été exclu de ta faction.");
        return true;
    }

    /**
     * /faction tp -> téléporte le joueur au territoire de sa faction (1er chunk claim).
     */
    public function tpFaction(Player $player): bool {
        $xuid = $this->xuid($player);
        if ($xuid === null) {
            return true;
        }

        $faction = $this->getFactionByPlayer($xuid);
        if ($faction === null) {
            $player->sendMessage("§cTu ne possèdes pas de faction.");
            return true;
        }

        // Premier chunk claim = le territoire principal de la faction
        $stmt = $this->db->prepare("SELECT world, chunk_x, chunk_z FROM faction_chunk WHERE faction_id = :fid ORDER BY id ASC LIMIT 1");
        $stmt->bindValue(":fid", $faction['id'], SQLITE3_INTEGER);
        $chunk = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($chunk === false) {
            $player->sendMessage("§cTa faction n'a pas encore de territoire (/faction chunk claim).");
            return true;
        }

        $world = $this->getServer()->getWorldManager()->getWorldByName($chunk['world']);
        if ($world === null) {
            $player->sendMessage("§cLe monde du territoire est introuvable.");
            return true;
        }

        // Centre du chunk, sur la surface
        $x = $chunk['chunk_x'] * 16 + 8;
        $z = $chunk['chunk_z'] * 16 + 8;
        $world->loadChunk($chunk['chunk_x'], $chunk['chunk_z']);
        $y = $world->getHighestBlockAt($x, $z) + 1;

        $player->teleport(new Position($x + 0.5, $y, $z + 0.5, $world));
        $player->sendMessage("§aTéléporté au territoire de ta faction !");
        return true;
    }

    /**
     * /faction help -> liste stylée de toutes les commandes.
     */
    public function helpFaction(Player $player): bool {
        $lignes = [
            "§8§m                                        ",
            "§6§l                  ⚔ FACTIONS ⚔",
            "§8§m                                        ",
            "§e✦ §lGénéral",
            "  §b/faction create <nom> §8» §7Créer une faction",
            "  §b/faction show §8» §7Voir le nom de ta faction",
            "  §b/faction about §8» §7Voir les stats de ta faction",
            "  §b/faction delete §8» §7Supprimer ta faction §8(chef)",
            "§e✦ §lMembres",
            "  §b/faction invite <pseudo> §8» §7Inviter un joueur",
            "  §b/faction accept §8» §7Accepter une invitation",
            "  §b/faction kick <pseudo> §8» §7Exclure un membre §8(chef)",
            "§e✦ §lTerritoire",
            "  §b/faction chunk claim §8» §7Revendiquer le chunk §8(chef)",
            "  §b/faction chunk see §8» §7Voir la carte des chunks",
            "  §b/faction tp §8» §7Te téléporter au territoire",
            "§e✦ §lÉconomie",
            "  §b/faction donate slazyx <montant> §8» §7Donner de l'argent",
            "§8§m                                        ",
        ];
        $player->sendMessage(implode("\n", $lignes));
        return true;
    }

}
