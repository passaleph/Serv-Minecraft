<?php

declare(strict_types=1);

namespace BlockFixer\block;

use pocketmine\block\Flowable;

/**
 * Petit bloc "plante" sans collision (comme les fleurs vanilla).
 * Utilise pour kelp et seagrass : cote client, Minecraft affiche la vraie
 * plante vanilla ; cote serveur, ce bloc evite juste que le joueur reste bloque.
 *
 * Flowable est abstraite dans le coeur de PocketMine, on en fait donc une
 * sous-classe concrete instanciable.
 */
final class WaterPlant extends Flowable{

}
