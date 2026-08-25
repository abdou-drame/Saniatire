# Imagerie — branchement futur d'un serveur PACS

Le module Imagerie ne stocke, n'affiche ni ne transmet aucune image
DICOM : `imaging_studies` ne fait que **référencer** où l'étude réelle vit
ailleurs, via deux colonnes optionnelles :

| colonne | rôle |
|---|---|
| `external_reference_url` | URL vers l'étude sur le serveur PACS (ex. viewer web Orthanc/dcm4chee) |
| `storage_reference` | identifiant interne côté PACS si différent de l'URL (ex. clé S3, ID Orthanc) |

Les autres champs DICOM standards déjà présents (`study_instance_uid`,
`accession_number`, `modality`) sont ceux qu'un vrai PACS attend pour
retrouver l'étude côté serveur — ils sont donc prêts à l'emploi pour un
futur appel API.

## Point d'extension pour une intégration réelle

1. **Serveur PACS** : déployer Orthanc (léger, API REST + plugin DICOMweb)
   ou dcm4chee (plus lourd, orienté entreprise). Les deux exposent une API
   DICOMweb (QIDO-RS/WADO-RS) standard.
2. **Réception des études** : quand la modalité (échographe, scanner, IRM)
   envoie l'étude au PACS, le PACS génère le `StudyInstanceUID` réel — un
   webhook ou un job planifié doit alors `updateOrCreate` la ligne
   `imaging_studies` correspondante (par `accession_number`, généré côté
   RIS/Sanitaire au moment de la prescription) avec l'URL retournée par le
   PACS dans `external_reference_url`.
3. **Affichage** : le frontend (hors périmètre de cette étape) ouvrirait
   simplement `external_reference_url` dans un viewer web (OHIF, Orthanc
   Explorer) — aucun changement de schéma nécessaire.
4. **Aucune logique consommatrice n'existe aujourd'hui** : ce README documente
   l'intention, mais tant qu'aucun PACS n'est branché, `external_reference_url`
   reste un simple champ texte libre alimenté manuellement si besoin.
