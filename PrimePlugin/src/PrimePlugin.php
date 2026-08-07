<?php


declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use forms\PrimeListForm;
use onebone\economyapi\EconomyAPI;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;



  


class PrimePlugin extends PluginBase implements Listener {

     private \SQLite3 $db;


    public function onEnable(): void {
        $this->db = new \SQLite3($this->getDataFolder() . "stats.sqlite");

        // Cle d'identification = XUID (identifiant Xbox stable, ne change pas
        // meme si le joueur change de pseudo). owner_id / player_id contiennent le XUID.
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS primes (
                primes_id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_pseudo_prime TEXT NOT NULL,
                pseudo_prime TEXT NOT NULL,
                amount INTEGER NOT NULL DEFAULT 0
            )
        ");

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }


    public function onDisable(): void {
     
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        $name = $command->getName();

        if ($command->getName() !== "prime" ) {
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage("Cette commande est réservée aux joueurs.");
            return true;
        }

        if (count($args) > 3) {
            $sender->sendMessage("Nombre d'arguments invalide.");
            return true;
        }

        if (!isset($args[0])) {
            $sender->sendMessage("§cUsage: /prime depose <pseudo> <montant> ou /prime list");
            return true;
        }
            
        $sousCommande = $args[0];

        if ($sousCommande === "depose") {
            return $this->handleDepose($sender, $args);
        }

        if ($sousCommande === "list") {
            return $this->showPrimes($sender);
        }

        return true;

    }


    private function handleDepose(Player $player, array $args ): bool {
            if (!isset($args[1]) || !isset($args[2])) {
                $player->sendMessage("§cUsage: /prime depose <pseudo> <montant>");
                return true;
            }
            $pseudo = $args[1];
            $amount = (int)$args[2];


            $cible = $this->getServer()->getPlayerExact($pseudo);

            if ($pseudo == null) {
                $player->sendMessage("§cUsage: /prime depose <pseudo> <montant>");
                return true;
            }

            if ($amount == null || !is_numeric($amount)) {
                  $player->sendMessage("§cMontant invalide.");
                return true;
            }

             $cible = $this->getServer()->getPlayerExact($pseudo);
            if ($cible === null) {
                $player->sendMessage("§cCe joueur n'est pas connecté.");
                return true;
            }
            $xuid = $cible->getXuid();

            $economy = EconomyAPI::getInstance();
            if ($economy->myMoney($player) < $amount) {
                $player->sendMessage("§cTu n'as pas assez d'argent pour cette prime.");
                return true;
            }
            $economy->reduceMoney($player, $amount);

            $stmt = $this->db->prepare("
                INSERT INTO primes (id_pseudo_prime, pseudo_prime, amount)
                VALUES (:id, :pseudo, :amount)
            ");
            $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
            $stmt->bindValue(":pseudo", $cible->getName(), SQLITE3_TEXT);
            $stmt->bindValue(":amount", $amount, SQLITE3_INTEGER);
            $stmt->execute();

            $player->sendMessage("§aPrime de §e" . $amount . "§a déposée sur §e" . $cible->getName());
            return true;

        }


    private function showPrimes(Player $player): bool {
        $primes = [];

        $result = $this->db->query("
            SELECT pseudo_prime, SUM(amount) AS total
            FROM primes
            GROUP BY id_pseudo_prime
            ORDER BY total DESC
        ");

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $primes[] = ["pseudo" => $row["pseudo_prime"], "amount" => $row["total"]];
        }

        $player->sendForm(new PrimeListForm($primes));
        return true;
    }


    public function onDeath(PlayerDeathEvent $event): void {
        $victime = $event->getPlayer();
        $xuid = $victime->getXuid();

        $cause = $victime->getLastDamageCause();
        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }
        $tueur = $cause->getDamager();
        if (!$tueur instanceof Player) {
            return;
        }

        $stmt = $this->db->prepare("SELECT SUM(amount) AS total FROM primes WHERE id_pseudo_prime = :id");
        $stmt->bindValue(":id", $xuid, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $total = ($row !== false && $row["total"] !== null) ? (int) $row["total"] : 0;

        if ($total <= 0) {
            return;
        }

        EconomyAPI::getInstance()->addMoney($tueur, $total);

        $del = $this->db->prepare("DELETE FROM primes WHERE id_pseudo_prime = :id");
        $del->bindValue(":id", $xuid, SQLITE3_TEXT);
        $del->execute();

        $this->getServer()->broadcastMessage("§e" . $tueur->getName() . " §aa réclamé la prime de §e" . $total . " §asur §e" . $victime->getName() . " §a!");
    }

}   



