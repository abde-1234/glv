# Tâche 6C — audit GLV

État vérifié le 14 septembre 2026. Reprise des fichiers existants, sans Git disponible dans ce répertoire. Aucune migration ajoutée, aucune route HTTP dupliquée, aucun module métier créé. Les données de développement n’ont pas été modifiées.

## Résultats

- Reprise du 15 septembre 2026 : suite complète réexécutée, **95 tests réussis, 1 043 assertions** en 30,15 s ; compilation des vues Blade réussie. Le contrôle des documents réels en mode `--dry-run` reste bloqué par la connexion MySQL refusée sur 127.0.0.1:3306.
- Avant corrections : 70 tests réussis, 505 assertions.
- Après corrections : **95 tests réussis, 1 043 assertions**, aucun échec final (`php artisan test --compact`).
- 24 scénarios dans 7 nouvelles suites, plus 1 scénario ajouté à la suite Super Admin existante.
- Build Vite réussi (`npm.cmd run build`).
- Tests exclusivement SQLite `:memory:` ; le TestCase refuse tout autre environnement avant l’exécution de RefreshDatabase.
- Les tests de documents utilisent des disques simulés ; les tests PDF génèrent de vrais octets PDF et vérifient leurs en-têtes ainsi que les données et le HTML utilisés pour leur rendu.

## Problèmes trouvés et corrections

| Constat | Correction |
| --- | --- |
| Documents d’agence enregistrés sur le disque public : une URL statique connue contourne l’autorisation du contrôleur. | Nouveau disque `documents` privé, sans route de partage automatique. Téléchargement authentifié, nom nettoyé, `nosniff` et cache privé interdit. |
| Chemin d’un document issu d’un ancien enregistrement non validé avant lecture/suppression. | Chemin strictement limité à `agences/{agence_id}/documents/{nom}.{extension}` ; chemins absolus, traversées et autres agences refusés. |
| Relations héritées incohérentes non contrôlées systématiquement. Une réservation étrangère masquée par le scope pouvait être traitée comme une relation absente dans le PDF. | Contrôle explicite du contrat, du client, du véhicule et de la réservation ; distinction entre relation facultative absente et ID de relation étranger. |
| Vérification des conflits avant la transaction et absence de verrou pour les doublons de contrats. | Vérification et création sous transaction avec verrou sur la voiture ou la réservation. Les réservations annulées ne bloquent pas la disponibilité ; leur réactivation est contrôlée. |
| Immatriculation reçue comme tableau convertie en chaîne avant validation ; montant calculé susceptible de dépasser la colonne SQL. | Validation scalaire avant normalisation et borne du montant côté serveur ; réponse de validation au lieu d’une erreur serveur. |
| Un Super Admin déjà connecté puis désactivé n’était pas bloqué par son middleware. | Statut actif et `agence_id = NULL` exigés. Le middleware Agence exige également un statut actif explicite. |
| Seeder Super Admin pouvant réinitialiser un mot de passe existant à la valeur de démonstration. | Création seulement si le compte est absent ; refus hors environnements local/test. |
| Ancien logo d’agence conservé après remplacement depuis Super Admin. | Suppression après enregistrement du nouveau logo, uniquement pour un chemin de logo attendu. |
| Pages 403/404 génériques, validations anglaises et bloc document PDF sans dimensions pour son icône. | Pages GLV compactes sans détails techniques, messages de validation français, styles limités au bloc PDF et retour à la ligne des actions d’abonnement. |

## Protections vérifiées

- IDOR dans les deux sens A → B et B → A : voitures, clients, réservations, contrats, PDF, documents, utilisateurs secondaires.
- Toutes les routes Super Admin refusent les rôles Admin Agence et Employé.
- Les champs `agence_id`, `role`, références, prix et montants injectés ne prennent pas le contrôle des écritures concernées.
- Contrôles explicites d’appartenance en plus du scope Eloquent et du route model binding.
- SQL : `orderByRaw` des utilisateurs et autres expressions brutes sont statiques ; recherches liées par paramètres.
- XSS : noms, descriptions, notes et utilisateurs échappés dans Blade. Le `nl2br(e(...))` du PDF échappe le contenu avant insertion des sauts de ligne.
- CSRF : formulaires POST munis de jetons ; tests POST/PUT/PATCH/DELETE avec le middleware réel, sans son exemption de test, réponses 419 sans jeton.
- Authentification : connexion selon rôle, refus des mauvaises informations, limitation des tentatives, remember me existant, déconnexion, mot de passe actuel et confirmation, ancien mot de passe inutilisable après changement.
- Employés : accès métier existant conservé, administration des paramètres/utilisateurs refusée ; élévation en Super Admin et auto-suppression refusées.
- Abonnement : actif, essai valide, expiration proche, expiré, suspendu, journée d’expiration. Mutations et PDF bloqués si expiré/suspendu ; abonnement, profil et déconnexion restent accessibles.
- Workflow complet testé : Super Admin → création agence/gérant → abonnement → connexion agence → voiture → client → réservation → contrat → aperçu/téléchargement PDF → profil → abonnement → déconnexion.

## Routes sensibles

- `/super-admin/*` (toutes les routes et méthodes déclarées).
- `/voitures`, `/clients`, `/reservations`, `/contrats` : listes, créations, détails, éditions et mutations existantes.
- `/reservations/{id}/annuler`, `/reservations/{id}/contrat/create`.
- `/contrats/{id}/resilier`, `/contrats/{id}/pdf`, `/contrats/{id}/pdf/preview`.
- `/parametres/documents`, `/parametres/documents/{id}/telecharger`, suppression.
- `/parametres/utilisateurs`, modifications et suppressions par ID.
- `/parametres/abonnement`, `/profil`, `/profil/password`, `/login`, `/logout`.

## Fichiers créés

- `app/Support/AgencyAccess.php`
- `app/Support/AgencyDocumentStorage.php`
- `app/Console/Commands/SecureAgencyDocuments.php`
- `resources/views/errors/layout.blade.php`
- `resources/views/errors/403.blade.php`
- `resources/views/errors/404.blade.php`
- `lang/fr/validation.php`
- `lang/en/validation.php` (réutilise les messages français de l’interface GLV pour les installations conservant la locale Laravel par défaut)
- `tests/Concerns/CreatesAgencyFixtures.php`
- `tests/Feature/MultiTenantIsolationTest.php`
- `tests/Feature/ReservationSecurityTest.php`
- `tests/Feature/ContractPdfSecurityTest.php`
- `tests/Feature/DocumentSecurityTest.php`
- `tests/Feature/AuthRolesSecurityTest.php`
- `tests/Feature/SubscriptionAccessTest.php`
- `tests/Feature/GlvWorkflowTest.php`
- `docs/task-6c-audit.md`

## Fichiers modifiés

- `app/Http/Controllers/Agence/AgenceUserController.php`
- `app/Http/Controllers/Agence/ClientController.php`
- `app/Http/Controllers/Agence/ContratController.php`
- `app/Http/Controllers/Agence/ContractPdfController.php`
- `app/Http/Controllers/Agence/DocumentController.php`
- `app/Http/Controllers/Agence/ReservationController.php`
- `app/Http/Controllers/Agence/VoitureController.php`
- `app/Http/Controllers/SuperAdmin/AgenceController.php`
- `app/Http/Controllers/SuperAdmin/AbonnementController.php`
- `app/Http/Middleware/AdminAgenceMiddleware.php`
- `app/Http/Middleware/SuperAdminMiddleware.php`
- `app/Models/User.php`
- `config/filesystems.php`
- `database/seeders/SuperAdminSeeder.php`
- `resources/views/agence/settings/subscription.blade.php`
- `resources/css/app.css`
- `resources/css/subscriptions.css`
- `tests/TestCase.php`
- `tests/Feature/AgencySettingsTest.php` (stockage attendu désormais privé)
- `tests/Feature/SuperAdminSpaceTest.php` (test de remplacement de logo)
- `public/build/manifest.json` et assets compilés par Vite.

## Vérifications restant dépendantes de l’environnement

1. **MySQL local indisponible** (connexion refusée sur 127.0.0.1:3306). L’inventaire des documents réels n’a pas pu être lu. Aucun document n’était présent dans le dossier public inspecté ; cela ne prouve pas l’absence de références en base.
2. Après redémarrage de MySQL, exécuter `php artisan glv:secure-documents --dry-run`, puis `php artisan glv:secure-documents`. La commande conserve les enregistrements et chemins, vérifie l’identité SHA-256 de la copie privée avant de retirer la copie publique et refuse tout conflit. Elle est testée comme idempotente. Un téléchargement autorisé traite aussi un ancien fichier public avant de le servir ; la commande globale reste nécessaire pour retirer toutes les anciennes URL publiques éventuelles.
3. **Aucun navigateur disponible** dans la session. Les pages sont rendues dans les Feature Tests et leurs règles responsive ont été inspectées, mais le contrôle visuel à 1366×768, 1440×900, 1920×1080, tablette et mobile reste à effectuer.
4. Les verrous de concurrence ont été ajoutés au code. SQLite en mémoire ne valide pas le comportement concurrent réel d’InnoDB ; un contrôle de requêtes simultanées sur MySQL reste recommandé.

Les corrections et tests applicatifs sont terminés. La validation visuelle et la vérification des documents de la base réelle ne sont pas déclarées terminées. Aucune Tâche 6D commencée.
