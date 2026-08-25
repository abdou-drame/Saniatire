# Référentiel LOINC — import du jeu de données complet

`LoincCodeSeeder` peuple `loinc_codes` avec un jeu de données restreint
(~30 analyses courantes et variées : NFS, glycémie, créatinine, CRP, groupe
sanguin, bilan lipidique, TSH, etc.) avec leurs vrais codes LOINC, pour que
le module laboratoire fonctionne de bout en bout sans dépendre du
référentiel complet. Aucun changement de schéma n'est nécessaire pour
passer à un import complet — seul le contenu du seeder change.

Les codes de ce jeu restreint ont été saisis manuellement à partir de la
mémoire du référentiel LOINC ; ils doivent être revérifiés contre la table
officielle (loinc.org) avant tout usage en production.

## Structure attendue

`loinc_codes` :

| colonne | rôle |
|---|---|
| `code` | code LOINC (ex. `2345-7`) — unique |
| `label` | libellé de l'analyse (français) |
| `component` | catégorie/composant (ex. `Hématologie`, `Biochimie`) |
| `default_unit` | unité par défaut du résultat (ex. `g/L`), `null` si sans unité (ex. groupe sanguin) |
| `version` | version du référentiel (`LOINC`, à affiner avec le numéro de release lors d'un import complet) |
| `status` | `actif` ou `deprecie` |

Table partagée entre tenants (pas de `structure_id`), même doctrine que
`icd_codes` — voir `App\Domain\Laboratoire\Models\LoincCode`.

## Point d'extension pour un import complet

1. **Source** : LOINC distribue son référentiel complet (fichiers CSV) sur
   loinc.org après un enregistrement gratuit. Regenstrief Institute publie
   aussi une API de recherche (FHIR `$lookup`/`$validate-code`).
2. **Import** : écrire une commande Artisan (`loinc:import`) qui lit
   l'export CSV officiel et fait un `updateOrCreate` par `code`, en
   respectant la même forme `(code, label, component, default_unit,
   version, status)`.
3. **Interfaçage automate** : `lab_analyzer_interface_logs` (voir sa
   migration) prévoit déjà la structure pour journaliser les échanges avec
   un automate de laboratoire (HL7/ASTM) une fois celui-ci branché — aucune
   logique consommatrice n'existe encore, c'est un point d'extension pur.
4. **Historisation déjà en place** : contrairement à `icd_codes` /
   `consultation_diagnoses`, les résultats de labo (`lab_results`) ne
   snapshotent pas le libellé LOINC au moment du prélèvement — un import
   ultérieur qui modifierait un libellé LOINC changerait donc l'affichage
   des anciens résultats liés à ce code. Si ce comportement doit être
   historisé comme pour le CIM, ajouter des colonnes `label_snapshot`/
   `unit_snapshot` sur `lab_order_items` en suivant exactement le pattern
   de `consultation_diagnoses`.
5. **Idempotence** : garder `firstOrCreate`/`updateOrCreate` par `code`
   pour que la commande soit rejouable sans dupliquer.
