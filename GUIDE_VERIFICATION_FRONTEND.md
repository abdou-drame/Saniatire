# Guide frontend — compréhension, vérification manuelle et reste à faire

> Document de référence pour toi (product owner) : comment le frontend est construit, comment tester chaque écran sans rien oublier, et ce qui reste réellement à faire avant la mise en production. Rédigé à partir d'un audit complet du code au 30/08/2026 (routes, permissions, hooks, composants — pas de suppositions).
>
> Ce fichier complète — et ne remplace pas — [`reste.md`](reste.md) (checklist des intégrations tierces à finaliser, côté backend/infra) et [`MOBILE_SPEC.md`](MOBILE_SPEC.md) (spec technique pour les futures apps mobiles). Les trois documents sont cohérents entre eux ; celui-ci ajoute l'angle "frontend web" que les deux autres ne couvrent pas.

---

## Partie 1 — Comprendre le projet

### 1.1 Qu'est-ce que ce projet

C'est un **logiciel de gestion pour structures de santé** (cliniques, cabinets, centres de soins au Sénégal) — le genre d'outil qu'on appelle un système d'information hospitalier (SIH). Le but : qu'une structure de santé puisse faire tourner toute son activité — de l'arrivée d'un patient à l'accueil jusqu'à l'encaissement de sa facture, en passant par la consultation, les analyses, l'imagerie, une éventuelle hospitalisation ou intervention — dans un seul outil, avec une vraie gestion des droits selon le métier de chacun (médecin, secrétaire, caissier, pharmacien, RH, direction...).

Le logiciel est pensé pour plusieurs structures clientes indépendantes (multi-tenant) : chaque structure ne voit jamais les données d'une autre, sauf dans un seul cas explicitement autorisé et tracé — le référencement d'un patient d'une structure vers une autre (voir A.7 en Partie 2).

### 1.2 Ce que l'application couvre concrètement

Le projet demande de couvrir l'intégralité du parcours d'une structure de santé, regroupé en quatre grands domaines (repris tels quels dans le menu de l'application) :

- **Clinique** — accueil du patient, prise de rendez-vous, file d'attente, consultation (avec assistance IA pour le résumé et la détection d'anomalies, toujours validée par un humain), téléconsultation, référencement vers une autre structure, laboratoire, imagerie, hospitalisation, bloc opératoire, et 12 modules de spécialités médicales (maternité, dentaire, dialyse, ophtalmologie, cardiologie, kinésithérapie, oncologie, PMA, santé mentale, pédiatrie, médecine du travail, soins à domicile).
- **Logistique** — pharmacie/stocks, achats et fournisseurs, équipements biomédicaux (avec suivi de maintenance).
- **Administration** — facturation, caisse, assurances/IPM, créances, gestion du personnel (RH), comptes utilisateurs, prescripteurs externes, plannings, congés, gardes/astreintes, qualité et réclamations patients, structures et sites, templates de notification.
- **Pilotage** — rapports de direction multi-sites, journal d'audit (traçabilité réglementaire), paramètres de sécurité du compte (double authentification).

Au-delà du personnel, l'application sert aussi deux publics externes en self-service, chacun avec son propre espace : le **patient** (portail patient : rendez-vous, documents, factures, préférences) et le **prescripteur externe** (portail prescripteur : un médecin hors structure qui envoie des demandes d'analyses/imagerie et suit leurs résultats).

### 1.3 Les principes qui structurent tout le projet

Ce sont des règles produit posées dès la conception, qui reviennent dans presque tous les écrans — les connaître aide à comprendre pourquoi tel écran se comporte ainsi plutôt qu'un bug :

- **Isolation stricte entre structures**, avec une seule exception assumée et visible à l'écran (le référencement inter-structures).
- **Aucun calcul métier sensible n'est refait côté frontend** : les montants financiers, les écarts de caisse, la répartition assurance/patient, la disponibilité d'un praticien sont toujours décidés par le serveur — le frontend ne fait qu'afficher le résultat.
- **L'assistance IA ne décide jamais seule** : un résumé de consultation ou une alerte d'anomalie généré par l'IA reste un brouillon tant qu'un praticien ne l'a pas explicitement validé et, au besoin, corrigé.
- **Honnêteté sur ce qui est réellement branché** : quand une fonctionnalité (canal SMS/WhatsApp/Push, mobile money, visioconférence, PACS...) n'est pas reliée à un vrai service externe, l'interface le dit clairement plutôt que de faire semblant.
- **Le journal d'audit est intouchable**, même par un administrateur — consultable, jamais modifiable.
- **Les permissions affichées à l'écran sont un confort de lecture, jamais la vraie sécurité** : c'est toujours le serveur qui tranche en dernier ressort, et son message d'erreur est montré tel quel, sans reformulation.

### 1.4 Les trois espaces d'utilisateurs

| Espace | Qui l'utilise | Ce qu'il permet |
|---|---|---|
| **Personnel** | Le personnel de la structure (médecins, secrétaires, caissiers, administrateurs, RH...) | Toute la gestion opérationnelle listée en 1.2 |
| **Portail patient** | Les patients, en self-service | Prendre/consulter ses rendez-vous, voir ses documents médicaux et ses factures, gérer ses préférences de notification |
| **Portail prescripteur** | Les médecins externes à la structure | Envoyer une demande d'analyse/imagerie pour un de leurs patients et suivre le résultat |

Ce sont trois comptes, trois connexions et trois droits d'accès totalement indépendants — un patient et un membre du personnel n'utilisent jamais le même espace, même s'ils travaillent avec les mêmes données sous-jacentes.

### 1.5 Repères utiles pour situer un fichier dans le code

- Pages : `frontend/src/pages/<module>/<écran>-page.tsx`
- Composants réutilisables d'un module : `frontend/src/components/<module>/`
- Statuts/labels/couleurs de badges par écran : `frontend/src/pages/<module>/<module>-status.ts`
- Routing global et gardes d'accès : `frontend/src/App.tsx`
- Menu / sidebar : `frontend/src/config/navigation.ts`

---

## Partie 2 — Guide de vérification manuelle, écran par écran

Comment lire ce guide : pour chaque écran — la route, qui doit y avoir accès, ce qu'il faut cliquer/essayer, les états à observer (chargement/erreur/vide), et les **particularités à ne pas signaler comme un bug** (comportement volontaire, documenté dans le code). Teste idéalement avec au moins deux comptes de rôles différents par écran : un qui a toutes les permissions concernées, un qui n'en a qu'une partie (ex. `caissier`, `secretaire`).

### A. Section "Clinique"

#### A.1 Tableau de bord (`/dashboard`)
- Accès : tout utilisateur connecté. Contenu différent selon le rôle :
  - **Rôle `medecin`** → Dashboard médecin, **branché sur de vraies données** : KPI (patients du jour, temps d'attente moyen, consultations terminées), bandeau "Consultation en cours" avec bouton Reprendre, file d'attente personnelle (rafraîchie toutes les 30s), clic sur une ligne → ouvre la fiche patient.
  - **Tout autre rôle** → Dashboard générique. **⚠️ Cet écran est entièrement factice : les 4 KPI, le graphique et le tableau "Activité récente" sont des données codées en dur dans le composant, aucun appel API.** Ne teste pas la justesse de ces chiffres — ils ne représentent rien de réel. À vérifier uniquement : que la page s'affiche sans erreur et que le nom de l'utilisateur connecté apparaît dans le message de bienvenue.

#### A.2 Répertoire patients (`/patients`, permission `patients.view`)
- Rechercher un patient par nom, prénom, téléphone, numéro de dossier (filtre local, pas d'appel réseau par frappe).
- Cliquer une ligne → ouvre la fiche patient existante.
- "Nouveau patient" (si `patients.create`) → dialogue de création avec détection de doublon (le nouveau dossier est toujours créé, un écran propose ensuite de garder les deux ou de "reprendre" l'existant — ce n'est **pas** un blocage).
- États vide/erreur/chargement standards.

#### A.3 Fiche patient (`/patients/:id`)
- Infos démographiques, allergies, groupe sanguin.
- Timeline complète (consultations, labo, imagerie, hospitalisations, chirurgie) — vérifie qu'un événement créé ailleurs (ex. une analyse labo) apparaît bien ici.
- Panneau constantes cliniques (graphique température si ≥2 mesures).
- Panneau hospitalisation (si `hospitalisation.view`) : notes de suivi, sortie du patient (si `hospitalisation.update`).
- Boutons de spécialités : n'apparaissent que si la permission `.view` de la spécialité correspondante est accordée.
- Activation du portail patient (si `patients.update`) : bouton "Envoyer le lien d'activation", devient "Lien envoyé".
- Si `medecin` : formulaire de consultation (voir A.4), prescriptions labo/imagerie hors consultation.
- **Particularité** : le panneau hospitalisation renvoie silencieusement `null` (pas de message d'erreur) si la permission manque — contrairement à la garde de route globale qui, elle, affiche "Accès non autorisé". Ne pas confondre les deux comportements.

#### A.4 Consultation clinique (intégrée à la fiche patient, rôle `medecin` uniquement)
- Créer/modifier la consultation en cours : motif, histoire de la maladie, 10 constantes cliniques bornées, IMC auto-calculé, examen clinique, recommandations, orientation.
- Diagnostic CIM : le premier code ajouté devient automatiquement "principal", les suivants "secondaires".
- Bloc IA (si `ai.consultation_summary`) : générer un résumé → reste un brouillon éditable → rien n'est écrit tant que "Insérer dans la consultation" n'est pas cliqué explicitement.
- Bloc anomalies (si `ai.anomaly_detection`) : purement informatif, un bouton "Rafraîchir", aucune validation/acquittement.
- "Proposer une hospitalisation" / "Proposer une intervention chirurgicale" : masqués si le patient a déjà un épisode actif du même type.
- Clôturer la consultation → tous les champs deviennent en lecture seule.
- **⚠️ Aucune dictée vocale n'existe dans cette interface**, malgré la permission backend `voice_dictation.create/view` accordée à `medecin`/`infirmier`. Ne cherche pas de bouton micro — il n'y en a pas (voir Partie 3).

#### A.5 Rendez-vous / Accueil (`/rendez-vous`, rôles `secretaire`/`administrateur`/`direction`/`directeur_medical` — garde par **rôle**, pas par permission)
- Calendrier (vues Jour/Semaine/Mois), filtre par praticien, création/report/annulation de rendez-vous.
- Si le praticien n'est pas disponible : le formulaire propose une case "Forcer malgré l'indisponibilité" — la validation réelle reste serveur.
- File d'attente du jour : triée par priorité puis heure d'arrivée ; boutons Appeler → Démarrer → Terminer selon le statut.
- "Enregistrer l'arrivée" : avec ou sans rendez-vous existant.

#### A.6 Téléconsultation (`/teleconsultations`, permission `teleconsultation.view`)
- Planifier depuis un rendez-vous existant non encore converti.
- Démarrer, clôturer (formulaire dédié : motif requis, histoire de la maladie, examen clinique, recommandations, orientation, RDV de contrôle) → crée une vraie consultation clôturée, redirige vers la fiche patient.
- Annuler.
- **⚠️ Aucune visioconférence intégrée.** Le "lien de session" est un champ texte affiché tel quel, avec la mention explicite "à transmettre manuellement au patient" — c'est le comportement voulu, pas un lien cassé.

#### A.7 Référencement inter-structures (`/referencement`, permission `referrals.view`)
- Bandeau permanent rappelant que c'est le **seul endroit de l'application** où un partage de données patient entre deux structures est autorisé et journalisé.
- Deux tableaux : envoyés / reçus.
- Envoyer un référencement (patient, structure destinataire, praticien référent, motif).
- Côté structure destinataire : accès seulement à un **résumé minimal** du patient (nom, numéro, date de naissance) — jamais le dossier complet.
- Cycle : Envoyé → Accepté/Refusé → (si accepté) Contre-référence → Complété.
- Teste explicitement le cas négatif : une structure tierce ne doit rien voir de ce référencement (404, pas juste un accès restreint).

#### A.8 Laboratoire (`/laboratoire`, permission `laboratoire.view`)
- Poste technicien (si `hasRole("technicien_laboratoire")`) : enregistrer un prélèvement, saisir des résultats, valider techniquement.
- Poste biologiste (si `laboratoire.validate_biologique`) : valider biologiquement, transmettre le résultat. Badges "hors norme"/critique.
- Cycle : demande → prélèvement effectué → en analyse → résultats disponibles → transmis (ou annulé).

#### A.9 Imagerie (`/imagerie`, permission `imagerie.view`)
- Manipulateur (`imagerie.create`) : créer un examen (champ "Référence de stockage" — **pas une vraie intégration PACS**, juste un texte libre).
- Radiologue (`imagerie.interpreter`/`imagerie.validate`) : rédiger le compte-rendu, valider, transmettre.

#### A.10 Hospitalisation (`/hospitalisation`, permission `hospitalisation.view`)
- Grille des lits (lecture seule, couleurs par statut), stats rafraîchies toutes les 30s.
- Admettre un patient (`hospitalisation.create`) — filtre local aux lits "libres" (confort, pas une garantie serveur).
- **⚠️ La sortie du patient et les notes journalières ne se font PAS ici** — elles sont sur la fiche patient (A.3). Absence volontaire sur cet écran.

#### A.11 Bloc opératoire (`/bloc-operatoire`, permission `bloc_operatoire.view`)
- Planifier une intervention (`bloc_operatoire.create`), démarrer/terminer (`bloc_operatoire.update`), annuler (`bloc_operatoire.cancel`).
- Checklist en 3 étapes (avant anesthésie / avant incision / avant sortie de bloc), validée étape par étape (`bloc_operatoire.validate`).
- **⚠️ Le bouton "Terminer l'intervention" reste actif même si la checklist est incomplète** — volontaire (le backend est seul juge et renvoie une erreur 422 affichée telle quelle). Ne pas le signaler comme un bug si le clic échoue avec un message serveur.

#### A.12 Les 12 écrans de spécialités (accessibles depuis la fiche patient, `/patients/:id/<specialite>`)
Maternité · Dentaire · Dialyse · Ophtalmologie · Cardiologie · Kinésithérapie · Oncologie · PMA · Santé mentale · Pédiatrie · Médecine du travail · Soins à domicile.
- Pour chacune : vérifier la création du dossier racine (sauf ophtalmo et médecine du travail, qui n'en ont pas — chaque examen/visite y est autonome), l'ajout d'un élément dans chaque sous-section (visites, séances, cycles, mesures...), et la carte Diagnostic CIM commune.
- **⚠️ Ces 12 routes n'ont aucune garde de route dédiée** (contrairement à Labo/Imagerie/Hospitalisation/Bloc) : si tu navigues directement par URL sans la permission, tu obtiens une erreur de chargement générique, pas un écran "Accès non autorisé" propre. À connaître pour ne pas le confondre avec un vrai bug d'accès.
- Cas particuliers à tester spécifiquement : l'odontogramme (Dentaire, grille des 32 dents avec 8 statuts), la validation croisée "Restrictions obligatoires si aptitude = Apte avec réserves" (Médecine du travail), le champ nom d'échelle en texte libre (Santé mentale).

### B. Section "Logistique"

#### B.1 Pharmacie (`/pharmacie`, permission `stock.view`)
- Catalogue produits par catégorie, détail produit + lots.
- Mouvement de stock (`stock.dispense`) : entrée/sortie/ajustement/transfert.
- Alertes péremption (fenêtre réglable en jours) et seuil bas.

#### B.2 Achats (`/achats`, permission `achats.view`)
- Demande d'achat → Approbation (`achats.validate`) → Commande (`achats.create`) → Soumission (`achats.update`) → Validation par niveau (`achats.approve`) → Réception (`stock.create`).
- **⚠️ Le bouton "Valider" une commande reste actif même si le niveau d'approbation du rôle courant semble insuffisant** — le backend calcule dynamiquement le niveau requis et renvoie un 403 explicite si besoin. Ne pas s'attendre à un bouton désactivé par anticipation.
- Créer un fournisseur.

#### B.3 Équipements biomédicaux (`/equipements`, permission `biomedical.view`)
- Créer un équipement (`biomedical.create`), enregistrer une maintenance (`biomedical.maintenance`).
- KPI "Maintenances à venir" / "en retard".

### C. Section "Administration"

#### C.1 Facturation (`/facturation`, permission `facturation.view`)
- **Factures** : création à partir des prestations "à facturer" du patient (**pas de saisie manuelle de ligne** — uniquement des prestations déjà générées côté serveur), émission (`facturation.validate`), annulation (`facturation.cancel`).
- **Devis** : lignes libres, conversion en facture (`facturation.validate`) → redirige vers la nouvelle facture en brouillon.
- Vérifie que le total affiché en brouillon (devis ou facture) correspond bien au `montant_total` réellement renvoyé une fois créé/converti côté serveur.

#### C.2 Caisse (`/caisse`, permission `caisse.view`)
- Ouvrir une session (`caisse.create`), clôturer avec montant compté (`caisse.update`) → l'écart affiché est **toujours** calculé par le serveur.
- Encaisser un paiement (`caisse.encaisser`), y compris mode "mobile money" (référence + statut déclarés manuellement — **aucune vraie passerelle de paiement**, c'est une simulation assumée).
- Tester un encaissement en espèces sans session ouverte : message d'erreur backend + proposition d'ouvrir une session.

#### C.3 Assurances / IPM (`/assurances`, permission `assurance.view`)
- Créer un organisme, une convention, des règles de couverture (taux, plafond, exclusion).
- Assigner une couverture à un patient.
- **⚠️ Aucune simulation de répartition assurance/patient n'est affichée ici** — c'est volontairement une pure gestion de référentiel, le calcul réel n'a lieu qu'à la facturation.

#### C.4 Créances (`/creances`, permission dédiée `facturation.creances` — distincte de `facturation.view`)
- Balance âgée par tranches (0-30/31-60/61-90/90+ jours), filtrable par patient et par organisme assureur.
- Vérifie qu'un utilisateur avec `facturation.view` mais sans `facturation.creances` est bien bloqué ici — c'est voulu.

#### C.5 Personnel (`/personnel`, permission `rh.view`)
- Créer/éditer une fiche RH (`rh.create`/`rh.update`) — l'utilisateur rattaché est figé après création.
- Pas de suppression possible (aucun bouton, c'est volontaire).

#### C.6 Comptes utilisateurs (`/comptes-utilisateurs`, permission `users.view`)
- Créer un compte (`users.create`) : **mot de passe initial saisi manuellement et affiché en clair une seule fois** à la création — pas d'invitation par email pour le personnel (à la différence des portails patient/prescripteur).
- Pont pratique : après création, bouton "Créer la fiche RH" pré-rempli (si `rh.create`).
- Désactivation via case à cocher (pas de suppression de compte).

#### C.7 Prescripteurs externes (`/prescripteurs-externes`, permission `prescripteurs.view`)
- Créer/éditer (`prescripteurs.create`/`.update`).
- Envoyer le lien d'activation du portail (même mécanisme que le patient — c'est le prescripteur qui choisit son mot de passe, jamais saisi ici).

#### C.8 Plannings (`/plannings` — pas de garde de route, "Mon planning" toujours visible)
- Section "Gestion des plannings" (autres praticiens) visible si `rh.create` ou `rh.update`.
- Créer un horaire récurrent (jour de semaine) ou ponctuel (date précise), type normal/garde/astreinte.
- Supprimer un horaire si `rh.delete`.
- **⚠️ Le frontend n'infère jamais la disponibilité** — uniquement `PractitionerPresenceService::isPresent()` côté backend fait autorité.

#### C.9 Congés (`/conges` — pas de garde de route)
- Déposer sa propre demande (type, dates, commentaire) — aucune modification/annulation possible après dépôt.
- Section Validation (si `conges.validate`) : approuver/refuser.
- Teste le rôle `manager` (validation limitée à son équipe, sans `conges.validate_all`) : une tentative de validation hors équipe doit renvoyer le message backend exact.
- Vérifie l'affichage des avertissements de chevauchement garde/astreinte après approbation.

#### C.10 Gardes / astreintes (`/gardes-astreintes` — pas de garde de route)
- Section "De garde maintenant" : toujours visible, même sans droit particulier (usage d'urgence assumé).
- Section historique/planification réservée à `rh.view`.
- Écran strictement en lecture seule : la création se fait via Plannings (C.8).

#### C.11 Qualité & Réclamations (`/qualite` — pas de garde de route)
- Vue d'ensemble (si `dashboards.qualite`), Satisfaction (si `qualite.view`).
- Réclamations : créer (`reclamations.create`), s'assigner ou assigner (`reclamations.update`), répondre à tout moment si gestionnaire assigné, résoudre (statut "en_cours" uniquement), clôturer (statut "résolue" uniquement) — réservé au gestionnaire assigné ou à `reclamations.manage_all`.
- **⚠️ Le filtre de dates de la section Satisfaction est purement côté client** (pas de paramètre de période côté serveur pour cet endpoint) — ne pas s'étonner si ça ne redéclenche pas d'appel réseau.

#### C.12 Structures & sites (`/structures`) — ⚠️ **ÉCRAN PLACEHOLDER, VOIR PARTIE 3**

#### C.13 Templates de notification (`/templates-notification`, permission `notifications.view`)
- Créer/éditer/supprimer (`notifications.create/update/delete` — en pratique réservé à `administrateur`, `direction` n'a que la lecture).
- Aperçu de rendu en direct avec valeurs d'exemple (calcul 100% local, jamais envoyé au serveur).
- **⚠️ Confirmé : seul le canal Email envoie réellement un message.** SMS/WhatsApp/Push sont explicitement marqués "Non branché — simulation uniquement" à l'écran — on peut créer/activer un template sur ces canaux, mais rien ne sera réellement délivré tant qu'un fournisseur n'est pas branché côté backend (voir Partie 3, déjà dans `reste.md` §3).

### D. Section "Pilotage"

#### D.1 Rapports / Direction (`/rapports`, rôles `administrateur`/`direction`/`directeur_medical`)
- Filtres période + site, comparaison entre sites, tableau de bord médical/qualité (épidémiologie CIM, occupation des lits, actes par spécialité), tableau de bord financier (CA, encaissements par mode de paiement, balance âgée).
- 3 exports **CSV uniquement** (épidémiologie, chiffre d'affaires, balance âgée) — vérifie le téléchargement réel du fichier.
- **⚠️ Aucun export PDF sur cet écran** (cohérent avec `reste.md` §9 : la génération PDF n'est faite nulle part dans l'application).

#### D.2 Audit / Conformité (`/audit`, permission `audit.view` — réservée à `conformite` et `administrateur`, **ni** `direction` **ni** `directeur_medical`)
- Filtres (utilisateur, action, table, dates), pagination serveur.
- Détail d'une entrée : anciennes/nouvelles valeurs en JSON.
- **Écran strictement en lecture seule**, garanti à la fois par l'absence de route d'écriture et par des gardes Eloquent qui rejettent toute tentative de modification/suppression même pour un administrateur (vérifié côté backend).

#### D.3 Paramètres / 2FA (`/parametres` — accessible à tout utilisateur connecté)
- Activer la 2FA (QR code, confirmation par code à 6 chiffres, codes de récupération affichés **une seule fois**).
- Désactiver (si le rôle ne l'impose pas), avec confirmation par mot de passe.
- Teste un compte dont le rôle impose la 2FA (`administrateur`, `direction`, `directeur_medical`, `specialiste_pma`, `psychiatre`, `psychologue`) : à la première connexion, un écran plein-page bloquant force la configuration avant d'accéder au reste de l'application.

### E. Portail patient (`/portail/...`)

- **Connexion / Activation / Mot de passe oublié / Réinitialisation** : self-service complet par email+mot de passe. Le message de "mot de passe oublié" est **volontairement identique** que l'email existe ou non (anti-énumération) — même en cas d'erreur serveur.
- **Mes rendez-vous** (`/portail/rendez-vous`) : consultation à venir/passés uniquement. **⚠️ Aucune annulation ni modification possible depuis le portail** — c'est une limite réelle, pas un oubli d'affichage.
- **Nouveau rendez-vous** : site, praticien, créneaux sur 14 jours glissants, créneaux fixes de 30 min.
- **Mes documents** : résultats labo + comptes rendus imagerie, lecture seule, pas de téléchargement PDF individuel.
- **Mes factures** / **Détail facture** : solde dû, prestations, paiements déjà enregistrés. **⚠️ Aucun bouton de paiement en ligne — confirmé, aucun endpoint de paiement patient n'existe.** Le patient consulte, il ne paie jamais directement dans l'app ; l'encaissement reste toujours fait par un caissier (écran C.2).
- **Mes préférences** : choix des canaux de notification. Rappel : seul l'email est réellement fonctionnel (comme C.13).

### F. Portail prescripteur externe (`/portail-prescripteur/...`)

- **Connexion / Activation / Mot de passe oublié / Réinitialisation** : identique en comportement au portail patient.
- **Mes demandes** : suivi fusionné labo + imagerie, résultats visibles uniquement une fois "Transmis" par le personnel. **⚠️ Écran de suivi en lecture seule — aucune annulation/modification/relance depuis ce portail.**
- **Nouvelle demande** : recherche patient (2 caractères min., debounce 300ms), site, type d'examen, motif. Pas de pièce jointe possible.

---

## Partie 3 — Ce qui reste réellement à faire avant la mise en production

### 3.1 Ce que `reste.md` couvre déjà (backend/infra) — ne pas dupliquer, juste vérifier ligne par ligne avant le lancement

Le fichier [`reste.md`](reste.md) liste déjà, en 11 sections, tout ce qui est structurellement prêt mais pas branché à un vrai fournisseur/service externe : référentiels médicaux CIM/LOINC complets, mobile money (Wave/Orange/Free Money), SMS/WhatsApp/Push, PACS/DICOM, visio de téléconsultation, transcription vocale, clé Anthropic de production, automates de laboratoire, sauvegardes/PCA, hébergement, gestion des secrets, export PDF, seuils d'achats/2FA/templates à valider par structure cliente, conformité réglementaire sénégalaise. Cet audit frontend **confirme point par point** chacune de ces limites côté interface (téléconsultation sans visio réelle, mobile money simulé en caisse, canaux SMS/WhatsApp/Push marqués non branchés, pas d'export PDF nulle part). Rien de contradictoire trouvé.

### 3.2 Ce que cet audit ajoute — spécifique au frontend, absent de `reste.md`

**a) Deux écrans encore en placeholder générique ("Bientôt disponible"), visibles dans le menu**

| Écran | Route | Statut |
|---|---|---|
| Consultations | `/consultations` | `ComingSoonPage` — aucun contenu réel |
| Structures & sites | `/structures` | `ComingSoonPage` — aucun contenu réel |

À trancher avant la mise en production, pour chacun : **faut-il vraiment un écran dédié, ou le lien de menu doit-il simplement être retiré ?**
- Pour "Consultations" : la fonctionnalité elle-même existe déjà et fonctionne (intégrée à la fiche patient, section A.4) — seul un écran de **liste globale/transversale des consultations** (toutes, tous patients confondus) manquerait, s'il y a un vrai besoin de ce genre de vue. Sinon, le lien de menu est trompeur et devrait être retiré pour éviter la confusion.
- Pour "Structures & sites" : à vérifier s'il existe un vrai besoin de gérer les structures/sites depuis l'interface (création, édition) ou si c'est une donnée gérée uniquement côté configuration initiale/backend.

**b) Le tableau de bord générique (`/dashboard` pour tout rôle autre que `medecin`) est entièrement factice**
KPI, graphique et tableau "Activité récente" sont des données codées en dur, sans aucun appel API. Contrairement au dashboard médecin (branché sur de vraies données), ce dashboard n'affiche rien de réel sur la structure. À reconstruire avant mise en production si ce dashboard doit rester accessible aux rôles non-médecins.

**c) Dictée vocale : droits prêts côté backend, aucune interface côté frontend**
Les permissions `voice_dictation.create`/`voice_dictation.view` existent et sont accordées à `medecin`/`infirmier`, mais aucun composant (bouton micro, enregistrement, statut) n'existe nulle part dans le frontend pour les utiliser. Cohérent avec `reste.md` §6 (pas de vraie transcription côté backend non plus), mais à noter : même une fois un service de transcription branché côté backend, il faudra aussi construire l'interface — ce n'est pas qu'une bascule de configuration.

**d) Aucun centre de notifications in-app**
Les préférences de canaux existent (portail patient, et implicitement personnel), mais il n'y a nulle part dans le frontend un endroit où consulter un historique de notifications reçues dans l'application elle-même — seuls les canaux externes (email réel, SMS/WhatsApp/Push simulés) existent. À clarifier si un centre de notifications in-app fait partie du périmètre de lancement.

### 3.3 Comment utiliser cette partie 3

Comme pour `reste.md`, rien ici ne bloque la poursuite du développement — mais les 4 points ci-dessus (deux placeholders visibles dans le menu, un dashboard factice pour la majorité des rôles, une fonctionnalité annoncée par les permissions mais absente à l'écran) sont le genre d'écart qu'un testeur ou un client pourrait découvrir en premier en production. À trancher explicitement avant le lancement : soit on les construit, soit on retire/masque ce qui n'est pas prêt.

---

## Résultat de l'audit backend au moment de la rédaction

Suite de tests backend complète (`php artisan test`) exécutée en parallèle de cet audit : **27/27 tests passés (76 assertions)**, aucune régression détectée sur l'authentification, l'isolation multi-structures, les permissions par rôle, l'audit, les congés/RH, les notifications, la dictée vocale (statut), et le référencement inter-structures.
