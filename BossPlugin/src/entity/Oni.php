<?php

declare(strict_types=1);

namespace entity;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\world\particle\FlameParticle;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\item\VanillaItems;
use pocketmine\item\StringToItemParser;
use pocketmine\block\VanillaBlocks;

class Oni extends Living {

    // Compteur pour ne pas attaquer à chaque tick (20 ticks = 1 seconde)
    private int $attackCooldown = 0;

    // Passe à true quand le boss entre en phase 2 (une seule fois)
    private bool $enraged = false;

    // Liste des joueurs à qui on affiche la barre de boss (id => Player)
    private array $barViewers = [];

    // Dégâts infligés au boss par chaque joueur (XUID => total des dégâts)
    private array $damageDealt = [];

    // Détection de blocage : dernière position + compteur de ticks sans avancer
    private ?Vector3 $lastPos = null;
    private int $stuckTicks = 0;

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);   // on garde ce que fait la classe parente
        $this->setMaxHealth(500);   // vie MAXIMALE (500 = 250 coeurs)
        $this->setHealth(500);      // vie de DÉPART = plein
    }

    public static function getNetworkTypeId(): string {
        return "boss:oni";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(2.7, 1.2); // hauteur, largeur (hitbox)
    }

    protected function getInitialDragMultiplier(): float {
        return 0.02;
    }

    protected function getInitialGravity(): float {
        return 0.08;
    }

    public function getName(): string {
        return "Oni Boss";
    }

    /**
     * IMMUNITÉ AU KNOCKBACK : on redéfinit knockBack() pour qu'elle ne fasse RIEN.
     * Du coup le boss ne recule jamais quand on le frappe. (Pas d'immunité au feu.)
     */
    public function knockBack(float $x, float $z, float $force = 0.4, ?float $verticalLimit = 0.4): void {
        // volontairement vide : le boss est inébranlable
    }

    /**
     * Appelée à chaque fois que le boss PREND un coup.
     * En phase 2 : 20% de chance de relancer une onde de choc.
     */
    public function attack(EntityDamageEvent $source): void {
        parent::attack($source); // on laisse les dégâts se faire normalement

        if ($source->isCancelled()) {
            return; // le coup a été annulé -> on ne compte rien
        }

        // On mémorise les dégâts infligés par un joueur (pour le loot à la mort)
        if ($source instanceof EntityDamageByEntityEvent) {
            $damager = $source->getDamager();
            if ($damager instanceof Player) {
                $xuid = $damager->getXuid();
                $this->damageDealt[$xuid] = ($this->damageDealt[$xuid] ?? 0) + $source->getFinalDamage();

                // 1 chance sur 20 de se téléporter directement sur l'attaquant
                if (mt_rand(1, 20) === 1) {
                    $this->teleport($damager->getPosition());
                }
            }
        }

        // En phase 2 : 20% de chance de relancer une onde de choc quand il prend un coup
        if ($this->enraged && mt_rand(1, 100) <= 20) {
            $this->shockwave();
        }
    }

    /**
     * Joue une animation du resource pack sur CE boss, pour tous les joueurs qui le voient.
     */
    public function playAnimation(string $animation): void {
        $pk = AnimateEntityPacket::create($animation, "", "", 0, "", 0.0, [$this->getId()]);
        NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [$pk]);
    }

    /**
     * onUpdate() = le "cerveau" du boss, appelé 20 fois par seconde.
     */
    public function onUpdate(int $currentTick): bool {
        $parent = parent::onUpdate($currentTick);

        // Cooldown d'attaque qui descend
        if ($this->attackCooldown > 0) {
            $this->attackCooldown--;
        }

        // Toutes les 1/2 seconde (10 ticks) : aura + barre de boss (pas besoin de le faire 20x/s)
        if ($currentTick % 10 === 0) {
            $this->updateAura();
            $this->updateBossBar();
        }

        // PHASE 2 : dès que la vie passe sous la moitié, une seule fois
        if (!$this->enraged && $this->getHealth() <= $this->getMaxHealth() / 2) {
            $this->enterPhase2();
        }

        // 1) Chercher le joueur le plus proche dans 100 blocs (en ignorant créatif/spectateur)
        $cible = null;
        $distanceMin = 100.0;
        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($player->isCreative() || $player->isSpectator()) {
                continue; // pas d'agro sur les joueurs en créatif/spectateur
            }
            $distance = $this->getPosition()->distance($player->getPosition());
            if ($distance < $distanceMin) {
                $distanceMin = $distance;
                $cible = $player;
            }
        }

        if ($cible === null) {
            return $parent; // personne à proximité -> le boss attend
        }

        // 2) Toujours regarder la cible
        $this->lookAt($cible->getEyePos());

        if ($distanceMin >= 20.0) {
            // 3) Trop loin (anti-fuite) -> il se téléporte directement sur le joueur
            $this->teleport($cible->getPosition());
            $this->stuckTicks = 0;
        } elseif ($distanceMin > 3.0) {
            // 4) À portée -> avancer vers la cible en CONTOURNANT les vrais trous.
            $base = $cible->getPosition()->subtractVector($this->getPosition())->normalize();

            // On cherche une direction praticable : tout droit, puis en tournant (contourne les trous).
            $choisie = null;
            foreach ([0.0, 45.0, -45.0, 90.0, -90.0] as $angle) {
                $candidat = $this->rotationY($base, $angle);
                if ($this->directionPraticable($candidat)) {
                    $choisie = $candidat;
                    break;
                }
            }

            if ($choisie !== null) {
                // SAUT si bloqué par un bloc devant lui
                $motionY = $this->getMotion()->y;
                if ($this->isCollidedHorizontally && $this->onGround) {
                    $motionY = 0.5;
                }
                $vitesse = $this->enraged ? 0.45 : 0.3;
                $this->setMotion(new Vector3($choisie->x * $vitesse, $motionY, $choisie->z * $vitesse));
            }
            // sinon : aucune direction sûre -> il attend (le TP anti-fuite finira par le rejoindre)

            // BLOCAGE : s'il n'avance quasiment plus pendant 2s -> il se TP sur la cible
            if ($this->lastPos !== null && $this->getPosition()->distanceSquared($this->lastPos) < 0.0025) {
                $this->stuckTicks++;
                if ($this->stuckTicks >= 40) { // 40 ticks = 2 secondes bloqué
                    $this->teleport($cible->getPosition());
                    $this->stuckTicks = 0;
                }
            } else {
                $this->stuckTicks = 0; // il a bougé -> pas bloqué
            }
            $this->lastPos = $this->getPosition()->asVector3();
        } elseif ($this->attackCooldown === 0) {
            // 5) Assez proche -> ATTAQUE
            $this->playAnimation("animation.oni.atk");
            $cible->attack(new EntityDamageByEntityEvent(
                $this, $cible, EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK, 20
            ));
            $this->attackCooldown = 30; // 1.5s avant la prochaine attaque
        }

        return $parent;
    }

    /** Fait tourner une direction (x,z) autour de l'axe Y de $deg degrés. */
    private function rotationY(Vector3 $dir, float $deg): Vector3 {
        $r = deg2rad($deg);
        $cos = cos($r);
        $sin = sin($r);
        return new Vector3($dir->x * $cos - $dir->z * $sin, 0, $dir->x * $sin + $dir->z * $cos);
    }

    /**
     * Une direction est praticable s'il y a du sol dans les 2 blocs sous le pas suivant
     * (donc : sol plat OU descente d'1 bloc = OK ; trou de 2 blocs+ = non praticable).
     */
    private function directionPraticable(Vector3 $dir): bool {
        $devant = $this->getPosition()->add($dir->x, 0, $dir->z);
        $sol1 = $this->getWorld()->getBlock($devant->subtract(0, 1, 0));
        $sol2 = $this->getWorld()->getBlock($devant->subtract(0, 2, 0));
        return $sol1->isSolid() || $sol2->isSolid();
    }

    /**
     * AURA DE FEU : particules de feu en cercle (5 blocs) + Slowness aux joueurs dedans.
     */
    private function updateAura(): void {
        $centre = $this->getPosition();

        // Cercle de particules de feu à 5 blocs de rayon
        for ($i = 0; $i < 12; $i++) {
            $angle = ($i / 12) * 2 * M_PI;
            $x = $centre->x + cos($angle) * 5;
            $z = $centre->z + sin($angle) * 5;
            $this->getWorld()->addParticle(new Vector3($x, $centre->y + 0.5, $z), new FlameParticle());
        }

        // Slowness à tous les joueurs à 5 blocs ou moins
        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($centre->distance($player->getPosition()) <= 5) {
                // Slowness I pendant 3s (60 ticks), réappliqué en continu tant qu'on reste dedans
                $player->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 60, 0, false));
            }
        }
    }

    /**
     * BARRE DE BOSS : affiche/maj la barre rouge pour les joueurs à 40 blocs, la cache pour les autres.
     */
    private function updateBossBar(): void {
        $pourcent = $this->getHealth() / $this->getMaxHealth();
        if ($pourcent < 0) {
            $pourcent = 0.0;
        }

        $proches = [];
        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($this->getPosition()->distance($player->getPosition()) <= 40) {
                $proches[$player->getId()] = $player;

                if (!isset($this->barViewers[$player->getId()])) {
                    // Nouveau joueur proche -> on affiche la barre
                    $player->getNetworkSession()->sendDataPacket(
                        BossEventPacket::show($this->getId(), $this->getName(), $pourcent, BossBarColor::RED)
                    );
                    $this->barViewers[$player->getId()] = $player;
                } else {
                    // Déjà affichée -> on met juste la vie à jour
                    $player->getNetworkSession()->sendDataPacket(
                        BossEventPacket::healthPercent($this->getId(), $pourcent)
                    );
                }
            }
        }

        // Joueurs partis trop loin (ou déconnectés) -> on cache la barre
        foreach ($this->barViewers as $id => $player) {
            if (!isset($proches[$id])) {
                if ($player->isConnected()) {
                    $player->getNetworkSession()->sendDataPacket(BossEventPacket::hide($this->getId()));
                }
                unset($this->barViewers[$id]);
            }
        }
    }

    /**
     * PHASE 2 : déclenchée une seule fois quand la vie tombe sous 50%.
     */
    private function enterPhase2(): void {
        $this->enraged = true;

        // Cri de rage + annonce
        $this->playAnimation("animation.oni.warcry");
        $this->getWorld()->getServer()->broadcastMessage("§4§l⚔ L'Oni entre en RAGE ! ⚔");

        // Onde de choc au moment de la transition
        $this->shockwave();
    }

    /**
     * ONDE DE CHOC : repousse violemment tous les joueurs à 8 blocs + burst de feu.
     * Réutilisée à la phase 2 ET quand le boss prend un coup (20% de chance).
     */
    private function shockwave(): void {
        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($this->getPosition()->distance($player->getPosition()) <= 8) {
                $direction = $player->getPosition()->subtractVector($this->getPosition())->normalize();
                // On propulse le joueur loin (x/z) et vers le haut (y)
                $player->setMotion(new Vector3($direction->x * 1.8, 0.9, $direction->z * 1.8));
            }
        }

        // Burst de particules de feu autour du boss
        $pos = $this->getPosition();
        for ($i = 0; $i < 30; $i++) {
            $ox = mt_rand(-20, 20) / 10;
            $oy = mt_rand(0, 30) / 10;
            $oz = mt_rand(-20, 20) / 10;
            $this->getWorld()->addParticle($pos->add($ox, $oy, $oz), new FlameParticle());
        }
    }

    /**
     * MORT SPECTACULAIRE : animation de mort + explosion de feu, puis récompense au tueur.
     */
    protected function onDeath(): void {
        parent::onDeath();

        // Animation de mort
        $this->playAnimation("animation.oni.death");

        // Grosse explosion de particules de feu
        $pos = $this->getPosition();
        for ($i = 0; $i < 60; $i++) {
            $ox = mt_rand(-25, 25) / 10;
            $oy = mt_rand(0, 40) / 10;
            $oz = mt_rand(-25, 25) / 10;
            $this->getWorld()->addParticle($pos->add($ox, $oy, $oz), new FlameParticle());
        }

        // On enlève la barre de boss pour tout le monde
        foreach ($this->barViewers as $player) {
            if ($player->isConnected()) {
                $player->getNetworkSession()->sendDataPacket(BossEventPacket::hide($this->getId()));
            }
        }
        $this->barViewers = [];

        // On cherche le joueur qui a porté le coup fatal
        $cause = $this->getLastDamageCause();
        $tueur = null;
        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();
            if ($damager instanceof Player) {
                $tueur = $damager;
            }
        }

        $this->giveReward($tueur);

        // Loot pour tous les joueurs ayant infligé au moins 10% de la vie du boss
        // (les clés de crate sont incluses dans le loot, voir giveLoot())
        $this->distributeLoot();
    }

    /**
     * Donne le loot à chaque joueur ayant fait au moins 10% des dégâts du boss.
     */
    private function distributeLoot(): void {
        $seuil = $this->getMaxHealth() * 0.10; // 10% de 500 = 50 de dégâts

        foreach ($this->getWorld()->getPlayers() as $player) {
            $degats = $this->damageDealt[$player->getXuid()] ?? 0;
            if ($degats >= $seuil) {
                $this->giveLoot($player);
            }
        }
    }

    /**
     * Le contenu du loot donné à un joueur méritant.
     */
    private function giveLoot(Player $player): void {
        $inv = $player->getInventory();

        // 100% : 3 diamants
        $inv->addItem(VanillaItems::DIAMOND()->setCount(3));

        // 10% : la Lame de l'Oni (item custom de WeaponPlugin)
        if (mt_rand(1, 100) <= 10) {
            $arme = StringToItemParser::getInstance()->parse("weaponplugin:oni_blade");
            if ($arme !== null) {
                $inv->addItem($arme);
            }
        }

        // 5% : un spawner
        if (mt_rand(1, 100) <= 5) {
            $inv->addItem(VanillaBlocks::MONSTER_SPAWNER()->asItem());
        }

        // 30% : clé RARE (pour ouvrir une crate rare)
        if (mt_rand(1, 100) <= 30) {
            $cle = StringToItemParser::getInstance()->parse("boxplugin:rare_key");
            if ($cle !== null) {
                $inv->addItem($cle);
            }
        }

        // 10% : clé LÉGENDAIRE
        if (mt_rand(1, 100) <= 10) {
            $cle = StringToItemParser::getInstance()->parse("boxplugin:legendary_key");
            if ($cle !== null) {
                $inv->addItem($cle);
            }
        }

        $player->sendMessage("§6Tu as reçu le butin de l'§4§lOni Boss §6!");
    }

    /**
     * RÉCOMPENSE — la fonction est prête, À TOI de mettre le contenu (argent, loot...).
     * $tueur peut être null (mort par le vide, la lave, etc.).
     */
    private function giveReward(?Player $tueur): void {
        if ($tueur === null) {
            return; // personne à récompenser
        }

        // Annonce à tout le serveur
        $this->getWorld()->getServer()->broadcastMessage(
            "§6§l" . $tueur->getName() . " §ea vaincu l'§4§lOni Boss §e!"
        );

        // ================== À COMPLÉTER PAR TOI ==================
        // Exemple argent (EconomyAPI) :
        //   EconomyAPI::getInstance()->addMoney($tueur, 5000);
        // Exemple loot :
        //   $tueur->getInventory()->addItem(VanillaItems::DIAMOND()->setCount(10));
        // =========================================================
    }
}
