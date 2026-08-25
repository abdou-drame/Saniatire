# Référentiel CIM — import du jeu de données complet

`IcdCodeSeeder` peuple `icd_codes` et `icd_code_mappings` avec un jeu de
données restreint (~30 codes CIM-10 + leurs équivalents CIM-11, avec leur
vraie hiérarchie chapitre → groupe → code) pour que le moteur de
codification fonctionne de bout en bout sans dépendre du référentiel
complet de l'OMS. Aucun changement de schéma n'est nécessaire pour passer à
un import complet — seul le contenu des seeders change.

## Structure attendue

`icd_codes` :

| colonne | rôle |
|---|---|
| `code` | code CIM (ex. `I10`, `BA00`) |
| `version` | `CIM-10` ou `CIM-11` |
| `label` | libellé (français) |
| `parent_id` | FK auto-référencée vers `icd_codes.id`, `null` pour un chapitre racine |
| `level` | `chapitre`, `groupe` ou `code` — permet la navigation hiérarchique sans dépendre de la longueur du code |
| `status` | `actif` ou `deprecie` |

Contrainte unique sur `(code, version)` : un même code peut exister dans les
deux versions sans collision.

`icd_code_mappings` : correspondance CIM-10 ↔ CIM-11, en colonnes texte
brutes (`code_source`, `version_source`, `code_cible`, `version_cible`) —
volontairement sans FK vers `icd_codes.id`, pour pouvoir importer les
tables GEM (General Equivalence Mappings) officielles de l'OMS telles
quelles, y compris pour des codes pas encore chargés dans `icd_codes`.

## Point d'extension pour un import complet

1. **Source** : l'OMS distribue le CIM-11 via l'API `icd.who.int` (format
   JSON, nécessite un enregistrement développeur gratuit) et le CIM-10 via
   des fichiers plats ClaML/XML. Le CIM-11 fournit aussi une API pour les
   correspondances GEM CIM-10 ↔ CIM-11.
2. **Import** : écrire une commande Artisan (`icd:import`) qui télécharge
   ou lit un export local, puis :
   - insère/`updateOrCreate` chaque entrée dans `icd_codes` en respectant
     la même forme `(code, version, label, parent_id, level, status)` ;
   - insère les couples de correspondance dans `icd_code_mappings`.
3. **Hiérarchie** : le référentiel officiel encode déjà les chapitres et
   groupes parents ; il suffit de résoudre les `parent_id` par un premier
   passage (créer tous les nœuds), puis un second passage (relier chaque
   enfant à son parent déjà existant), exactement comme fait ce seeder
   manuellement pour son jeu restreint.
4. **Historisation déjà en place** : `consultation_diagnoses` snapshotte
   `code_snapshot`/`label_snapshot`/`version_snapshot` à la codification —
   un import ultérieur qui modifie ou déprécie des libellés dans
   `icd_codes` ne modifie donc jamais rétroactivement l'historique des
   patients déjà codifiés.
5. **Idempotence** : garder `firstOrCreate`/`updateOrCreate` par
   `(code, version)` pour que la commande soit rejouable sans dupliquer.
