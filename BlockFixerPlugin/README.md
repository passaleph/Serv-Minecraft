# BlockFixerPlugin 2.0

Corrige les erreurs **« Unknown block ID »** au chargement de la map Kepler sur
PocketMine-MP, en enregistrant les blocs Bedrock que ce build de PocketMine
n'implemente pas encore cote serveur.

## Le probleme

La map a ete creee sur **Minecraft Bedrock 1.21.101** (voir `InventoryVersion`
dans `level.dat`). Elle contient des blocs vanilla que PocketMine ne sait pas
decoder. Au chargement des chunks, PocketMine affiche :

```
Errors decoding blocks:
 - Unknown block ID "minecraft:pointed_dripstone"
 - Unknown block ID "minecraft:dripstone_block"
```

Resultat : ces blocs sont remplaces par un bloc inconnu / de l'air => trous et
terrain casse.

### Blocs concernes (verifies dans la base LevelDB de la map)

| Bloc Bedrock                   | Occurrences | Proprietes stockees                     |
|--------------------------------|-------------|-----------------------------------------|
| `minecraft:pointed_dripstone`  | ~51 050     | `dripstone_thickness` (str) + `hanging` |
| `minecraft:dripstone_block`    | ~17 783     | aucune                                   |
| `minecraft:kelp` (algue)       | ~114 094    | `kelp_age` (int)                         |
| `minecraft:seagrass` (algue)   | ~42 130     | `sea_grass_type` (str)                   |

> Note : « algue » = **kelp** (varech) + **seagrass**. PocketMine 5.44.3
> n'implemente que `DRIED_KELP` (bloc de varech seche), pas la plante vivante.

## La solution

Ce plugin enregistre les blocs manquants **avant** le chargement des mondes
(`onLoad`, `load: STARTUP`). Pour chaque bloc :

1. creation d'un objet `Block` cote serveur (collision / lumiere) ;
2. inscription dans le `RuntimeBlockStateRegistry` ;
3. **serializer** serveur -> etat Bedrock renvoye au client, avec l'etat vanilla
   EXACT, donc le client 1.21 affiche le vrai bloc (aucun resource pack requis) ;
4. **deserializer** monde `.mcworld` -> objet `Block`, qui lit les proprietes
   stockees dans la map (thickness, hanging, kelp_age, sea_grass_type).

Le pointed_dripstone est enregistre en 10 variantes (5 epaisseurs x 2 hanging)
et le seagrass en 3 variantes, pour conserver l'apparence exacte.

## Installation

1. Copier le dossier `BlockFixerPlugin/` dans le dossier `plugins/` du serveur.
   (Chargement des plugins-dossier via **DevTools** — deja utilise pour tes
   autres plugins FactionPlugin / SurviePlugin / TpPlugin.)
2. Ou, pour un `.phar` : avec DevTools installe, lancer en console
   `makeplugin BlockFixerPlugin`, puis mettre le `.phar` genere dans `plugins/`.
3. Redemarrer le serveur. Tu dois voir :
   `[BlockFixerPlugin] 4 bloc(s) manquant(s) enregistre(s) ...`
4. **Important** : supprimer le dossier de monde deja charge / re-extraire la map
   proprement n'est PAS necessaire — le plugin agit au decodage. Mais si des
   chunks ont deja ete sauvegardes AVEC les blocs casses (air) lors d'un
   demarrage precedent sans le plugin, il faut re-extraire la map d'origine pour
   ces zones (voir ci-dessous).

## Si le terrain a deja ete « abime »

Si tu as demarre le serveur AVANT d'avoir ce plugin (ou avec l'ancienne version
qui mettait de l'air), les chunks charges puis sauvegardes ont perdu leurs
blocs. Dans ce cas :

1. arrete le serveur ;
2. re-extrais la map d'origine (`kepler.mcworld`) dans un dossier de monde neuf ;
3. installe CE plugin AVANT le premier demarrage ;
4. demarre : les blocs se chargeront correctement des le debut.

## Ajouter d'autres blocs manquants plus tard

Si d'autres `Unknown block ID` apparaissent dans les logs, il suffit d'ajouter un
bloc dans `src/BlockFixer/Main.php` sur le meme modele (make + serializer->map +
deserializer->map). Les proprietes exactes d'un bloc se lisent dans le message
d'erreur ou dans le wiki Bedrock.
