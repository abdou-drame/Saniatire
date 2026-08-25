# Checklist — Intégrations réelles à finaliser avant mise en production

> Ce document liste tout ce qui a été volontairement laissé en "structure/placeholder" pendant le développement backend, avec ce qu'il faudra concrètement obtenir/configurer avant la mise en production. Rien de tout ça n'est bloquant pour continuer le développement (frontend, mobile) — mais rien ne doit être oublié avant le lancement réel.

---

## 1. Référentiels médicaux (données à compléter)

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **Référentiel CIM-10/CIM-11** | Structure complète + ~30 codes réels de démonstration | Importer le référentiel complet de l'OMS. Nécessite un enregistrement sur l'API ICD de l'OMS (`icd.who.int`) et l'acceptation de leurs conditions de licence. |
| **Référentiel LOINC** | Structure complète + ~30 codes réels de démonstration | Importer le référentiel LOINC complet. Nécessite une inscription (gratuite) sur le site officiel LOINC (Regenstrief Institute) et acceptation de la licence d'utilisation. |

## 2. Paiements

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **Mobile Money (Wave)** | Champ de référence de transaction + statut simple | Compte marchand Wave, clés API, contrat commercial. |
| **Mobile Money (Orange Money)** | Champ de référence de transaction + statut simple | Compte marchand Orange Money, clés API, contrat commercial. |
| **Mobile Money (Free Money)** | Champ de référence de transaction + statut simple | Compte marchand Free Money, clés API, contrat commercial. |

## 3. Communications

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **Email** | Intégration réelle fonctionnelle (driver `log` en dev) | Un vrai compte SMTP de production (ex. SES, Mailtrap payant, ou autre) + variables d'environnement à renseigner. **C'est juste une bascule de config, pas de code à refaire.** |
| **SMS** | Structure/driver prêt, envoi simulé | Compte chez un fournisseur SMS (ex. Twilio, ou un fournisseur local sénégalais/africain), clé API, numéro expéditeur validé. |
| **WhatsApp** | Structure/driver prêt, envoi simulé | Compte WhatsApp Business API (validation Meta requise, délai possible), clé API. |
| **Push mobile** | Structure/driver prêt, envoi simulé | Compte Firebase Cloud Messaging (ou équivalent) + clés de config, à faire en coordination avec le développement de l'app mobile. |

## 4. Imagerie médicale

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **PACS / serveur DICOM** | Structure de métadonnées + champ de référence externe (pas de vraies images stockées) | Déployer un vrai serveur PACS (ex. Orthanc, dcm4chee) ou souscrire à un service PACS cloud, et brancher le champ de référence dessus. C'est un vrai chantier d'infrastructure à part entière. |

## 5. Téléconsultation

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **Visio pour téléconsultation** | Structure de session + champ de lien externe | Compte chez un service de visio (Twilio Video, Daily.co, Jitsi self-hébergé...) et intégration du vrai lien de session. |

## 6. Assistance vocale et IA

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **Dictée vocale (transcription)** | Endpoint de statut prêt (en attente/terminée/échouée), pas de vraie transcription | Compte chez un service de transcription (ex. Whisper API d'OpenAI, ou équivalent), clé API. |
| **Résumé de consultation / détection d'anomalies (IA)** | Vraie intégration avec l'API Anthropic (Claude), avec mode dégradé si pas de clé configurée | Une vraie clé API Anthropic de production (avec le volume d'usage prévu et la facturation associée). **Rappel important : la validation humaine systématique reste une exigence non négociable, à ne jamais retirer même en production.** |

## 7. Automates et systèmes tiers

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **Interfaçage automates de laboratoire** | Structure d'extension prévue, pas d'interfaçage réel | Travail spécifique selon la marque/le modèle des automates réellement utilisés par chaque structure cliente (chaque fournisseur a son propre protocole). À traiter au cas par cas, pas générique. |
| **Systèmes nationaux de santé** | Aucune intégration réelle, seulement l'architecture FHIR générique | Si un système national de santé sénégalais (ou autre) existe avec une vraie API, il faudra obtenir les accès/accréditations nécessaires. À clarifier si cette exigence est réellement d'actualité pour le lancement. |

## 8. Infrastructure et exploitation

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **Sauvegardes automatiques** | Stratégie documentée, pas d'infrastructure réelle en place | Mettre en place de vraies sauvegardes automatisées, testées régulièrement (restaurer pour vérifier que ça marche, pas juste que la sauvegarde existe). |
| **Plan de continuité d'activité** | Documenté en principe | Définir et tester un vrai plan concret (serveur de secours, procédure en cas de panne majeure). |
| **Hébergement / environnements** | Développement local uniquement | Choisir un hébergeur, mettre en place les environnements staging/production, certificats HTTPS/TLS, nom de domaine. |
| **Gestion des secrets** | Variables d'environnement `.env` classiques | Pour la production, envisager un vrai coffre-fort de secrets (ex. Vault, AWS Secrets Manager, ou équivalent) plutôt qu'un simple fichier `.env` sur le serveur. |

## 9. Documents et exports

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **Export PDF (factures, comptes rendus, reçus)** | Export CSV uniquement pour certains rapports | Implémenter la génération PDF réelle (factures, reçus, comptes rendus) — pas fait du tout pour l'instant, prévu pour une étape ultérieure liée aux documents/frontend. |

## 10. Paramétrage métier (pas technique, mais à ne pas oublier)

| Élément | Ce qui a été fait | Ce qu'il faut avant prod |
|---|---|---|
| **Seuils de validation des achats** | Mécanisme technique paramétrable en place | Chaque structure cliente doit définir ses **propres** seuils réels (montants, rôles) — ce n'est pas un choix technique, c'est une décision organisationnelle propre à chaque structure. |
| **Rôles soumis à la 2FA obligatoire** | Mécanisme technique en place | Confirmer la liste définitive des rôles concernés avec chaque structure cliente si elle diffère de la proposition par défaut. |
| **Templates de notification** | Système de templates personnalisables en place | Rédiger/valider le contenu réel des messages (RDV, résultats, paiements...) dans la langue et le ton souhaités par chaque structure, au-delà des templates par défaut. |

## 11. Conformité réglementaire (point à traiter séparément, non technique)

- Vérifier la conformité avec la réglementation sénégalaise/locale sur la protection des données de santé (ex. loi sur la protection des données personnelles), au-delà des mesures techniques déjà en place (chiffrement, audit, isolation).
- Ce point dépasse le cadre du développement pur — une revue juridique/réglementaire est recommandée avant le lancement commercial, en particulier pour les données PMA et santé mentale.

---

## Comment utiliser ce document

- Rien dans cette liste ne bloque la suite du développement (frontend, mobile) — toutes les architectures sont déjà prêtes à recevoir ces vraies intégrations sans refonte.
- Avant la mise en production, reprends ce tableau ligne par ligne avec l'équipe/le client pour confirmer ce qui est réellement nécessaire au lancement (tout ne sera peut-être pas utile dès le jour 1 selon le type de structure cliente) et ce qui peut être différé.
- Je recommande de garder ce fichier à jour au fur et à mesure — si une intégration réelle est branchée en cours de route, raye la ligne correspondante plutôt que de la supprimer, pour garder une trace de ce qui a été traité.