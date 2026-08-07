<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use onebone\economyapi\EconomyAPI;

class ScoreboardPlugin extends PluginBase implements Listener {

    private const OBJECTIVE = "sidebar_board";

    private string $title;
    /** @var string[] */
    private array $lines;
    private int $interval;

    /** Balises valables pour TOUT le monde (ex: airdrop). @var array<string,string> */
    private array $globalTags = [];
    /** Balises propres a un joueur. @var array<string, array<string,string>> */
    private array $playerTags = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $config = $this->getConfig();
        $this->title    = (string) $config->get("title", "§bServeur");
        $this->lines    = (array)  $config->get("lines", []);
        $this->interval = max(1, (int) $config->get("update-interval", 20));

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Pour les joueurs deja connectes (reload)
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $this->createBoard($player);
        }

        // Rafraichissement periodique
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                $this->update($player);
            }
        }), $this->interval);
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $this->createBoard($player);
        $this->update($player);
    }

    public function onQuit(PlayerQuitEvent $event): void {
        unset($this->playerTags[$event->getPlayer()->getName()]);
    }

    // ============ API publique pour les autres plugins ============

    /** A appeler depuis un autre plugin : $scoreboard->setGlobalTag("airdrop", "2:30"); */
    public function setGlobalTag(string $key, string $value): void {
        $this->globalTags[$key] = $value;
    }

    /** Balise propre a un joueur : $scoreboard->setPlayerTag($player, "kills", "12"); */
    public function setPlayerTag(Player $player, string $key, string $value): void {
        $this->playerTags[$player->getName()][$key] = $value;
    }

    // ============ Construction du scoreboard ============

    private function createBoard(Player $player): void {
        $packet = SetDisplayObjectivePacket::create(
            "sidebar",
            self::OBJECTIVE,
            $this->parse($player, $this->title),
            "dummy",
            SetDisplayObjectivePacket::SORT_ORDER_ASCENDING
        );
        $player->getNetworkSession()->sendDataPacket($packet);
    }

    private function update(Player $player): void {
        $numero = 0;
        foreach ($this->lines as $ligne) {
            $texte = $this->parse($player, (string) $ligne);
            // Bedrock deconnecte sur une ligne vide/identique -> padding invisible unique (jamais vide)
            $texte .= str_repeat("§r", $numero + 1);
            $this->setLine($player, $numero, $texte);
            $numero++;
        }
    }

    private function setLine(Player $player, int $numero, string $texte): void {
        $session = $player->getNetworkSession();

        // 1) enlever l'ancienne ligne (type REMOVE)
        $remove = new ScorePacketEntry();
        $remove->objectiveName = self::OBJECTIVE;
        $remove->scoreboardId = $numero;
        $remove->score = $numero;
        $remove->type = ScorePacketEntry::TYPE_REMOVE;
        $session->sendDataPacket(SetScorePacket::create([$remove]));

        // 2) remettre la nouvelle ligne (type FAKE_PLAYER)
        $change = new ScorePacketEntry();
        $change->objectiveName = self::OBJECTIVE;
        $change->scoreboardId = $numero;
        $change->score = $numero;
        $change->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $change->customName = $texte;
        $session->sendDataPacket(SetScorePacket::create([$change]));
    }

    // ============ Remplacement des balises {xxx} ============

    private function parse(Player $player, string $texte): string {
        $server = $this->getServer();

        $remplacements = [
            "{name}"   => $player->getName(),
            "{online}" => (string) count($server->getOnlinePlayers()),
            "{max}"    => (string) $server->getMaxPlayers(),
            "{world}"  => $player->getWorld()->getDisplayName(),
            "{ping}"   => (string) ($player->getNetworkSession()->getPing() ?? 0),
            "{date}"   => date("H:i:s"),
            "{money}"  => $this->getMoney($player),
        ];

        // Balises globales (ex: airdrop)
        foreach ($this->globalTags as $cle => $valeur) {
            $remplacements["{" . $cle . "}"] = $valeur;
        }
        // Balises du joueur
        foreach ($this->playerTags[$player->getName()] ?? [] as $cle => $valeur) {
            $remplacements["{" . $cle . "}"] = $valeur;
        }

        $texte = strtr($texte, $remplacements);
        // Toute balise non remplacee -> vide
        return preg_replace('/\{[a-zA-Z0-9_.]+\}/', "", $texte);
    }

    private function getMoney(Player $player): string {
        if ($this->getServer()->getPluginManager()->getPlugin("EconomyAPI") === null) {
            return "0";
        }
        return (string) EconomyAPI::getInstance()->myMoney($player);
    }
}
