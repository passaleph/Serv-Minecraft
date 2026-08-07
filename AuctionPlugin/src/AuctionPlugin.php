<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use onebone\economyapi\EconomyAPI;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\type\InvMenuTypeIds;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use forms\ConfirmForm;

class AuctionPlugin extends PluginBase {

    private const EXPIRE_SECONDS = 7 * 24 * 3600; // durée d'une vente : 7 jours

    private \SQLite3 $db;

    public function onEnable(): void {
        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        $this->db = new \SQLite3($this->getDataFolder() . "auctions.sqlite");
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS auctions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seller_id TEXT NOT NULL,
                seller_name TEXT NOT NULL,
                item TEXT NOT NULL,
                price INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                expire_at INTEGER NOT NULL DEFAULT 0
            )
        ");
        // Migration : ajoute expire_at si une ancienne table existe sans cette colonne
        if (!$this->colonneExiste("auctions", "expire_at")) {
            $this->db->exec("ALTER TABLE auctions ADD COLUMN expire_at INTEGER NOT NULL DEFAULT 0");
        }
    }

    public function onDisable(): void {
        if (isset($this->db)) {
            $this->db->close();
        }
    }

    private function colonneExiste(string $table, string $colonne): bool {
        $res = $this->db->query("PRAGMA table_info(" . $table . ")");
        while (($c = $res->fetchArray(SQLITE3_ASSOC)) !== false) {
            if ($c["name"] === $colonne) {
                return true;
            }
        }
        return false;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("Commande réservée aux joueurs.");
            return true;
        }

        $sub = strtolower($args[0] ?? "");

        if ($sub === "sell") {
            if (!isset($args[1]) || !ctype_digit($args[1])) {
                $sender->sendMessage("§cUsage: /ah sell <prix>");
                return true;
            }
            $this->sellItem($sender, (int) $args[1]);
            return true;
        }

        if ($sub === "mes") {
            $this->openMyListings($sender);
            return true;
        }

        $this->openShop($sender);
        return true;
    }

    // ==================== VENTE ====================

    public function sellItem(Player $player, int $price): void {
        if ($price <= 0) {
            $player->sendMessage("§cLe prix doit être supérieur à 0.");
            return;
        }
        $item = $player->getInventory()->getItemInHand();
        if ($item->isNull()) {
            $player->sendMessage("§cTiens l'objet à vendre dans ta main.");
            return;
        }

        $now = time();
        $stmt = $this->db->prepare("INSERT INTO auctions (seller_id, seller_name, item, price, created_at, expire_at) VALUES (:sid, :sname, :item, :price, :ca, :ea)");
        $stmt->bindValue(":sid", $player->getXuid(), SQLITE3_TEXT);
        $stmt->bindValue(":sname", $player->getName(), SQLITE3_TEXT);
        $stmt->bindValue(":item", $this->itemToString($item), SQLITE3_TEXT);
        $stmt->bindValue(":price", $price, SQLITE3_INTEGER);
        $stmt->bindValue(":ca", $now, SQLITE3_INTEGER);
        $stmt->bindValue(":ea", $now + self::EXPIRE_SECONDS, SQLITE3_INTEGER);
        $stmt->execute();

        $player->getInventory()->setItemInHand(VanillaItems::AIR());
        $player->sendMessage("§aObjet mis en vente pour §e" . $price . "$ §a!");
    }

    // ==================== MENUS (grille-coffre InvMenu) ====================

    public function openShop(Player $player): void {
        // On ne montre que les ventes actives (non expirées)
        $stmt = $this->db->prepare("SELECT id, seller_id, seller_name, item, price, expire_at FROM auctions WHERE expire_at > :now ORDER BY id DESC LIMIT 54");
        $stmt->bindValue(":now", time(), SQLITE3_INTEGER);
        $this->ouvrirMenu($player, $this->rowsToListings($stmt->execute()), "buy");
    }

    public function openMyListings(Player $player): void {
        // Toutes les ventes du joueur (actives + expirées à récupérer)
        $stmt = $this->db->prepare("SELECT id, seller_id, seller_name, item, price, expire_at FROM auctions WHERE seller_id = :id ORDER BY id DESC LIMIT 54");
        $stmt->bindValue(":id", $player->getXuid(), SQLITE3_TEXT);
        $this->ouvrirMenu($player, $this->rowsToListings($stmt->execute()), "cancel");
    }

    /** @param array $listings */
    private function ouvrirMenu(Player $player, array $listings, string $mode): void {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName($mode === "cancel" ? "§lMes ventes" : "§lHotel des ventes");

        $inventaire = $menu->getInventory();
        $slotVersId = []; // slot -> id de la vente
        $slot = 0;
        foreach ($listings as $l) {
            if ($slot >= 54) {
                break;
            }
            $inventaire->setItem($slot, $this->itemAffichage($l, $mode));
            $slotVersId[$slot] = $l["id"];
            $slot++;
        }

        $menu->setListener(function (InvMenuTransaction $transaction) use ($slotVersId, $mode): InvMenuTransactionResult {
            $slot = $transaction->getAction()->getSlot();
            if (!isset($slotVersId[$slot])) {
                return $transaction->discard();
            }
            $id = $slotVersId[$slot];

            // then() = exécuté après fermeture propre de l'inventaire (pour ouvrir un form)
            return $transaction->discard()->then(function (Player $player) use ($id, $mode): void {
                $player->removeCurrentWindow();
                if ($mode === "cancel") {
                    $this->cancelListing($player, $id);
                } else {
                    $this->confirmBuy($player, $id);
                }
            });
        });

        $menu->send($player);
    }

    /** Objet affiché dans le coffre : le vrai item + un lore (prix, vendeur, expiration). */
    private function itemAffichage(array $listing, string $mode): Item {
        $item = clone $listing["item"];
        $reste = $listing["expire_at"] - time();

        $lore = [
            "§r§7Prix: §e" . $listing["price"] . "§6$",
            "§r§7Vendeur: §b" . $listing["seller_name"],
            "§r§7Expire dans: §f" . $this->formatDuree($reste),
            "§r",
        ];
        if ($mode === "cancel") {
            $lore[] = $reste <= 0 ? "§r§c§lEXPIRÉ §7- clique pour récupérer" : "§r§e§lClique pour retirer";
        } else {
            $lore[] = "§r§a§lClique pour acheter";
        }
        $item->setLore($lore);
        return $item;
    }

    public function confirmBuy(Player $player, int $id): void {
        $listing = $this->getListing($id);
        if ($listing === null) {
            $player->sendMessage("§cCette vente n'existe plus.");
            return;
        }
        $item = $listing["item"];
        $desc = "§7Objet: §f" . $item->getName() . " §7x" . $item->getCount()
            . "\n§7Prix: §e" . $listing["price"] . "$"
            . "\n§7Vendeur: §b" . $listing["seller_name"];
        $player->sendForm(new ConfirmForm($this, $id, $desc));
    }

    // ==================== ACHAT / RETRAIT ====================

    public function buyListing(Player $player, int $id): void {
        $listing = $this->getListing($id);
        if ($listing === null || $listing["expire_at"] <= time()) {
            $player->sendMessage("§cCette vente n'existe plus.");
            return;
        }
        if ($listing["seller_id"] === $player->getXuid()) {
            $player->sendMessage("§cC'est ta propre vente (§e/ah mes §cpour la retirer).");
            return;
        }

        $item = $listing["item"];
        $price = $listing["price"];
        $economy = EconomyAPI::getInstance();

        if ($economy->myMoney($player) < $price) {
            $player->sendMessage("§cTu n'as pas assez d'argent (il te faut §e" . $price . "$§c).");
            return;
        }
        if (!$player->getInventory()->canAddItem($item)) {
            $player->sendMessage("§cTon inventaire est plein.");
            return;
        }

        // On supprime d'abord -> évite le double achat
        $del = $this->db->prepare("DELETE FROM auctions WHERE id = :id");
        $del->bindValue(":id", $id, SQLITE3_INTEGER);
        $del->execute();
        if ($this->db->changes() === 0) {
            $player->sendMessage("§cCette vente vient d'être achetée par quelqu'un d'autre.");
            return;
        }

        $economy->reduceMoney($player, $price);
        $economy->addMoney($listing["seller_name"], $price);

        $player->getInventory()->addItem($item);
        $player->sendMessage("§aTu as acheté §f" . $item->getName() . " §apour §e" . $price . "$ §a!");

        $vendeur = $this->getServer()->getPlayerExact($listing["seller_name"]);
        if ($vendeur !== null) {
            $vendeur->sendMessage("§a" . $player->getName() . " a acheté ton §f" . $item->getName() . " §apour §e" . $price . "$ §a!");
        }
    }

    public function cancelListing(Player $player, int $id): void {
        $listing = $this->getListing($id);
        if ($listing === null || $listing["seller_id"] !== $player->getXuid()) {
            $player->sendMessage("§cCette vente n'existe pas ou n'est pas la tienne.");
            return;
        }
        $item = $listing["item"];
        if (!$player->getInventory()->canAddItem($item)) {
            $player->sendMessage("§cTon inventaire est plein.");
            return;
        }
        $del = $this->db->prepare("DELETE FROM auctions WHERE id = :id");
        $del->bindValue(":id", $id, SQLITE3_INTEGER);
        $del->execute();

        $player->getInventory()->addItem($item);
        $player->sendMessage("§aVente retirée, objet récupéré.");
    }

    // ==================== BDD ====================

    private function getListing(int $id): ?array {
        $stmt = $this->db->prepare("SELECT id, seller_id, seller_name, item, price, expire_at FROM auctions WHERE id = :id");
        $stmt->bindValue(":id", $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return $row === false ? null : $this->rowToListing($row);
    }

    private function rowsToListings(\SQLite3Result $res): array {
        $out = [];
        while (($row = $res->fetchArray(SQLITE3_ASSOC)) !== false) {
            $out[] = $this->rowToListing($row);
        }
        return $out;
    }

    private function rowToListing(array $row): array {
        return [
            "id" => $row["id"],
            "seller_id" => $row["seller_id"],
            "seller_name" => $row["seller_name"],
            "item" => $this->stringToItem($row["item"]),
            "price" => $row["price"],
            "expire_at" => $row["expire_at"],
        ];
    }

    // ==================== Utilitaires ====================

    private function formatDuree(int $sec): string {
        if ($sec <= 0) {
            return "expiré";
        }
        $j = intdiv($sec, 86400); $sec %= 86400;
        $h = intdiv($sec, 3600); $sec %= 3600;
        $m = intdiv($sec, 60);
        $parts = [];
        if ($j > 0) $parts[] = $j . "j";
        if ($h > 0) $parts[] = $h . "h";
        if ($m > 0 || empty($parts)) $parts[] = $m . "m";
        return implode(" ", $parts);
    }

    private function itemToString(Item $item): string {
        return base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($item->nbtSerialize())));
    }

    private function stringToItem(string $data): Item {
        return Item::nbtDeserialize((new LittleEndianNbtSerializer())->read(base64_decode($data))->mustGetCompoundTag());
    }
}
