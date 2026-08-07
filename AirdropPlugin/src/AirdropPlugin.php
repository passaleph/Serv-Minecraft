<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\StringToItemParser;
use pocketmine\block\tile\Chest;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Config;
use pocketmine\scheduler\ClosureTask;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\world\particle\FloatingTextParticle;


class AirdropPlugin extends PluginBase implements Listener {

    private ?AxisAlignedBB $zone = null;
    private array $coffres = [];
    private int $timeLeft = 0;
    private int $nextAirdrop = 3600;   // secondes avant le prochain airdrop (1h)

    private bool $coffresVerrouilles = false;
    private int $coffreTimer = 0;              // secondes avant déverrouillage des coffres
    private array $textesFlottants = [];       // ["pos"=>Vector3, "particle"=>FloatingTextParticle]
    private ?World $airdropWorld = null;       // monde de l'airdrop en cours

    public function onEnable(): void {
        $this->saveResource("loot.yml");

        // On écoute les événements (pour le PvP dans la zone)
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Un seul chrono chaque seconde : il gère l'affichage ET le déclenchement
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            // compte à rebours de l'airdrop en cours
            if ($this->timeLeft > 0) {
                $this->timeLeft--;
            }

            // compte à rebours avant le prochain airdrop
            $this->nextAirdrop--;
            if ($this->nextAirdrop <= 0) {
                $this->startAirdrop();
                $this->nextAirdrop = 3600;   // on repart pour 1h
            }

            // compte à rebours du verrouillage des coffres (+ maj des textes flottants)
            if ($this->coffresVerrouilles) {
                $this->coffreTimer--;
                if ($this->coffreTimer <= 0) {
                    $this->coffresVerrouilles = false;
                    $this->supprimerTextesFlottants();
                    $this->getServer()->broadcastMessage("§aLes coffres de l'airdrop sont déverrouillés !");
                } else {
                    $this->updateTextesFlottants();
                }
            }

            $this->updateTimerTag();
        }), 20);   // toutes les secondes
    }

    private function updateTimerTag(): void {
        // Une seule valeur : soit l'airdrop en cours, soit le temps avant le prochain
        if ($this->timeLeft > 0) {
            $valeur = "En cours (" . $this->timeLeft . "s)";
        } else {
            $valeur = "dans " . $this->formatTime($this->nextAirdrop);
        }

        $scoreboard = $this->getServer()->getPluginManager()->getPlugin("ScoreboardPlugin");
        if ($scoreboard instanceof \ScoreboardPlugin) {
            $scoreboard->setGlobalTag("airdrop", $valeur);
        }
    }

    private function formatTime(int $seconds): string {
        $m = intdiv($seconds, 60);   // minutes entières
        $s = $seconds % 60;          // secondes restantes
        return $m . "m " . $s . "s"; // ex: "59m 3s"
    }

    // PvP autorisé UNIQUEMENT dans la zone d'airdrop
    public function onDamage(EntityDamageByEntityEvent $event): void {
        $victime = $event->getEntity();
        $attaquant = $event->getDamager();

        // On ne s'occupe que d'un joueur qui frappe un joueur
        if ($victime instanceof Player && $attaquant instanceof Player) {
         
            if ($this->zone === null || !$this->zone->isVectorInside($victime->getPosition())) {
                $event->cancel();
            }
        }
    }

   public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() !== "airdrop") {
            return false;
        }
        $this->startAirdrop();
        return true;
    }

    public function startAirdrop(): void {

        $this->timeLeft = 30; 
        $world = $this->getServer()->getWorldManager()->getWorldByName("survie");

        if ($world === null) {
            $this->getServer()->broadcastMessage("§cCe monde n'existe pas ou n'est pas chargé.");
            return;
        }

    
        $centreX = mt_rand(-1000, 1000);
        $centreZ = mt_rand(-1000, 1000);

        $minX = $centreX - 50;
        $maxX = $centreX + 50;
        $minZ = $centreZ - 50;
        $maxZ = $centreZ + 50;

        $this->zone = new AxisAlignedBB($minX, -64, $minZ, $maxX, 320, $maxZ);

       
        $config = new Config($this->getDataFolder() . "loot.yml", Config::YAML);
        $loot = $config->get("items");

        // Sécurité : si le loot.yml est vide/invalide, on n'essaie pas (sinon crash)
        if (!is_array($loot) || count($loot) === 0) {
            $this->getServer()->broadcastMessage("§cErreur airdrop : le loot.yml est vide ou invalide.");
            return;
        }

        // Verrouillage des coffres pendant X minutes
        $time_chest = rand(2, 5);
        $this->coffresVerrouilles = true;
        $this->coffreTimer = $time_chest * 60;   // en secondes
        $this->airdropWorld = $world;
        $this->textesFlottants = [];

      
        $nb_coffre = rand(1, 5);
        for ($i = 0; $i < $nb_coffre; $i++) {

            $x = rand($minX, $maxX);    
            $z = rand($minZ, $maxZ);
            $y = $world->getHighestBlockAt($x, $z) + 1;      

            $pos = new Vector3($x, $y, $z);
            $world->setBlock($pos, VanillaBlocks::CHEST());
            $this->coffres[] = $pos;

            // Texte flottant avec le timer au-dessus du coffre
            $posTexte = $pos->add(0.5, 1.2, 0.5);
            $texte = new FloatingTextParticle("§e🔒 " . $time_chest . ":00");
            $world->addParticle($posTexte, $texte);
            $this->textesFlottants[] = ["pos" => $posTexte, "particle" => $texte]; 
          
            $tile = $world->getTile($pos);
            if ($tile instanceof Chest) {
                // Chaque coffre passe en revue TOUTE la loot table.
                // "chance" = probabilité que CET item soit dans le coffre.
                // -> même logique pour chaque coffre, et les rares apparaissent enfin.
                $nbAjoutes = 0;
                foreach ($loot as $itemChoisi) {
                    if (mt_rand(1, 100) <= $itemChoisi['chance']) {
                        $item = StringToItemParser::getInstance()->parse($itemChoisi['id']);
                        if ($item !== null) {
                            $item->setCount(rand($itemChoisi['min'], $itemChoisi['max']));
                            $tile->getInventory()->addItem($item);
                            $nbAjoutes++;
                        }
                    }
                }

                // Coffre vide ? -> on force un item au hasard (jamais de coffre vide)
                if ($nbAjoutes === 0) {
                    $itemChoisi = $loot[array_rand($loot)];
                    $item = StringToItemParser::getInstance()->parse($itemChoisi['id']);
                    if ($item !== null) {
                        $item->setCount(rand($itemChoisi['min'], $itemChoisi['max']));
                        $tile->getInventory()->addItem($item);
                    }
                }
            }
        }

        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($world): void {
            foreach ($this->coffres as $pos) {
                $world->setBlock($pos, VanillaBlocks::AIR());
            }
            $this->coffres = [];
            $this->zone = null;
            $this->coffresVerrouilles = false;
            $this->supprimerTextesFlottants();
            $this->getServer()->broadcastMessage("§efermeture de airdrop");
        }), 20 * 60 * 10);   // 30 secondes (pour tester) — remets 20 * 60 * 10 pour 10 min


        $this->getServer()->broadcastMessage("§aAirdrop lancé ! Centre en X:$centreX Z:$centreZ ($nb_coffre coffres) les coffres seront déverrouillés dans $time_chest minute.");
    }

    /** Met à jour le texte de chaque coffre avec le temps restant (mm:ss). */
    private function updateTextesFlottants(): void {
        if ($this->airdropWorld === null) {
            return;
        }
        $m = intdiv($this->coffreTimer, 60);
        $s = $this->coffreTimer % 60;
        $txt = sprintf("§e🔒 %d:%02d", $m, $s);
        foreach ($this->textesFlottants as $t) {
            $t["particle"]->setText($txt);
            $this->airdropWorld->addParticle($t["pos"], $t["particle"]); // re-send = maj
        }
    }

    /** Fait disparaître tous les textes flottants. */
    private function supprimerTextesFlottants(): void {
        if ($this->airdropWorld !== null) {
            foreach ($this->textesFlottants as $t) {
                $t["particle"]->setInvisible(true);
                $this->airdropWorld->addParticle($t["pos"], $t["particle"]);
            }
        }
        $this->textesFlottants = [];
    }

    /** Empêche d'ouvrir un coffre d'airdrop tant qu'il est verrouillé. */
    public function onInteract(PlayerInteractEvent $event): void {
        if (!$this->coffresVerrouilles) {
            return;
        }
        $pos = $event->getBlock()->getPosition();
        foreach ($this->coffres as $coffrePos) {
            if ($coffrePos->equals($pos)) {
                $event->cancel();
                $event->getPlayer()->sendMessage("§cCe coffre est encore verrouillé !");
                return;
            }
        }
    }

    /** Rend les coffres d'airdrop incassables. */
    public function onBreak(BlockBreakEvent $event): void {
        $pos = $event->getBlock()->getPosition();
        foreach ($this->coffres as $coffrePos) {
            if ($coffrePos->equals($pos)) {
                $event->cancel();
                $event->getPlayer()->sendMessage("§cTu ne peux pas casser un coffre d'airdrop !");
                return;
            }
        }
    }
}
