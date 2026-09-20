# GLV V1 — préparation d'un déploiement en production

Ce document décrit les réglages à effectuer sur le serveur. Il ne contient aucun identifiant réel et ne remplace pas le fichier `.env` propre à l'environnement de production.

## 1. Prérequis vérifiés

- PHP 8.2 ou supérieur. Le projet impose `php: ^8.2` et utilise actuellement Laravel 12.69.2.
- Extensions imposées par les dépendances de production : `ctype`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `session` et `tokenizer`.
- Extensions requises par GLV : `PDO`, `pdo_mysql` pour MySQL et `gd` pour valider et normaliser les logos/photos intégrés par DomPDF.
- Extensions usuelles à activer sur cPanel : `curl`, `xml` et `zip`, notamment pour Composer, SMTP et les archives de déploiement.
- MySQL ou MariaDB compatible avec Laravel 12, tables InnoDB et encodage `utf8mb4`.
- Composer 2. Le projet utilise `barryvdh/laravel-dompdf` 3.1.2 et DomPDF 3.x.
- Node.js/npm ne sont nécessaires sur le serveur que si les assets Vite y sont construits. Le build local validé utilise Node 22.14.0 et npm 10.9.2 ; Vite est verrouillé en version 7.3.6.
- Un serveur web HTTPS dont le document root pointe vers le dossier Laravel `public`.
- Répertoires `storage` et `bootstrap/cache` accessibles en écriture par l'utilisateur du serveur web.
- Un gestionnaire de processus uniquement si des traitements asynchrones sont ajoutés ou activés.

Vérifier la plateforme avec :

```bash
composer check-platform-reqs
```

## 2. Variables d'environnement

Créer `.env` directement sur le serveur, sans le committer, puis définir au minimum :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://glv.example.com
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=glv_production
DB_USERNAME=glv_app
DB_PASSWORD=replace-with-a-strong-server-secret

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.example.com
MAIL_PORT=465
MAIL_USERNAME=replace-with-the-smtp-user
MAIL_PASSWORD=replace-with-the-smtp-secret
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="GLV"
```

Cette version de Laravel lit `MAIL_SCHEME` dans `config/mail.php`. La variable historique `MAIL_ENCRYPTION` n'est pas consommée par la configuration actuelle : choisir `MAIL_SCHEME` et le port conformément aux paramètres fournis par l'hébergeur SMTP.

Générer `APP_KEY` une seule fois pour une nouvelle installation :

```bash
php artisan key:generate
```

Ne pas régénérer cette clé après la mise en service : les données chiffrées et les sessions existantes deviendraient illisibles. Si HTTPS est terminé par un reverse proxy, configurer également les proxies de confiance selon l'infrastructure réelle avant l'ouverture au public.

## 3. MySQL et privilèges

Ne pas utiliser le compte `root` pour l'application. Créer une base et un utilisateur dédiés, avec un mot de passe généré sur le serveur.

Le compte d'exécution de GLV a normalement besoin de `SELECT`, `INSERT`, `UPDATE` et `DELETE` sur la base GLV. Les migrations nécessitent temporairement des droits DDL supplémentaires tels que `CREATE`, `ALTER`, `INDEX`, `DROP` et `REFERENCES`. Utiliser idéalement un compte de déploiement distinct, ou retirer ces droits supplémentaires après `php artisan migrate --force`.

Limiter l'hôte autorisé du compte MySQL à l'adresse du serveur applicatif. Activer TLS entre l'application et MySQL lorsqu'ils communiquent sur un réseau non local, via `MYSQL_ATTR_SSL_CA`.

## 4. Installation et mise à jour

Depuis la racine de l'application :

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Avant de remplacer une configuration en cache, `php artisan optimize:clear` permet de retirer proprement les anciens caches. Exécuter les migrations après sauvegarde de la base.

Le lien `public/storage` doit pointer vers `storage/app/public`. Les logos et photos sont publics par conception ; les documents d'agence restent sur le disque privé `documents` et doivent être servis uniquement par leur contrôleur authentifié.

Ne jamais lancer `migrate:fresh`, `migrate:refresh` ou `db:wipe` en production. Ne pas lancer `db:seed` : les seeders présents contiennent des comptes, agences, clients et mots de passe de démonstration. Ils sont protégés par l'environnement `local/testing`, mais n'ont aucune utilité sur le serveur de production.

## 5. Scheduler

La commande `subscriptions:check` est enregistrée quotidiennement à 08:00 dans Laravel. Le serveur doit appeler le scheduler chaque minute, par exemple avec cette entrée cron :

```cron
* * * * * cd /home/USERNAME/glv && php artisan schedule:run >> /dev/null 2>&1
```

Adapter le chemin et l'exécutable PHP au serveur. Vérifier avec `php artisan schedule:list`. La présence de cette documentation ne signifie pas que le cron est déjà installé.

## 6. Queue, mail et supervision

Les migrations créent bien `jobs`, `job_batches` et `failed_jobs`, et la configuration par défaut est `QUEUE_CONNECTION=database`. L'audit actuel ne trouve toutefois aucun job GLV implémentant `ShouldQueue` ni aucun dispatch applicatif : les notifications existantes sont synchrones. Aucun worker permanent n'est requis pour le comportement actuel.

Si des traitements asynchrones sont ajoutés ultérieurement, lancer un worker sous Supervisor, systemd ou le gestionnaire de processus proposé par l'hébergeur :

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Tester l'envoi SMTP depuis le serveur sans écrire les identifiants dans les logs ou dans Git. Surveiller `storage/logs`, les échecs de queue et l'exécution du scheduler, avec rotation et rétention limitées des journaux.

## 7. Vérifications avant ouverture

- Confirmer `APP_ENV=production`, `APP_DEBUG=false` et une `APP_URL` HTTPS.
- Confirmer que `.env` n'est ni versionné ni servi par le serveur web.
- Vérifier `/up`, la connexion, les rôles, un téléchargement PDF, un logo et une photo réels.
- Vérifier les en-têtes HTTPS, les cookies `Secure`, `HttpOnly` et `SameSite` dans le navigateur.
- Exécuter `php artisan test` dans un environnement de validation isolé, jamais contre la base de production.
- Configurer sauvegardes MySQL, restauration testée, surveillance, rotation des logs et renouvellement des secrets.

## 8. Structure cPanel recommandée

La structure la plus sûre est :

```text
/home/USERNAME/glv/
    app/
    bootstrap/
    config/
    database/
    public/
    resources/
    routes/
    storage/
    vendor/
    .env

Document root du domaine : /home/USERNAME/glv/public
```

Configurer le domaine ou sous-domaine dans cPanel afin que son document root soit `/home/USERNAME/glv/public`. Ne jamais exposer la racine `/home/USERNAME/glv`, car elle contient `.env`, `vendor`, les sources et le stockage privé.

Si l'offre ne permet pas de modifier le document root, demander au support si `public_html` peut être un lien vers `/home/USERNAME/glv/public`. Copier seulement le contenu de `public` dans `public_html` change les chemins relatifs de `index.php` et nécessite une adaptation spécifique au serveur ; cette solution ne doit pas être improvisée.

Le `.htaccess` Laravel actuel est compatible avec Apache/mod_rewrite : il désactive les index de dossiers, transmet les en-têtes d'autorisation et dirige les requêtes vers `index.php`. Préférer la redirection HTTPS fournie par cPanel/AutoSSL ou la configuration du vhost à des règles `.htaccess` agressives.

## 9. Déploiement Git ou ZIP

### Méthode A — Git

1. Cloner le dépôt dans `/home/USERNAME/glv`, ou effectuer un `git pull` contrôlé lors d'une mise à jour.
2. Ne jamais ajouter `.env` au dépôt.
3. Exécuter `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` sur le serveur.
4. Construire les assets sur le serveur seulement si Node/npm y sont disponibles.

Cette méthode facilite les mises à jour et l'audit des versions, mais exige Git et Composer sur l'hébergement.

### Méthode B — archive ZIP

1. Construire localement ou dans une CI propre avec `composer install --no-dev --prefer-dist --optimize-autoloader` puis `npm ci && npm run build`.
2. Inclure `vendor/` et `public/build/` dans l'archive si Composer ou Node ne sont pas disponibles sur cPanel.
3. Exclure `.env`, `.git`, `node_modules`, `tests`, les logs, les PDF E2E de `output/pdf` et les données E2E de `storage/app/public`.
4. Extraire dans `/home/USERNAME/glv`, puis créer `.env` directement sur le serveur.

Cette méthode fonctionne sans outils de build sur cPanel, mais impose de reconstruire et réenvoyer une archive à chaque version.

## 10. Ordre exact du premier déploiement

1. Créer le domaine ou sous-domaine et définir son document root sur `/home/USERNAME/glv/public`.
2. Créer la base MySQL dans cPanel.
3. Créer un utilisateur MySQL dédié, l'associer uniquement à cette base et lui attribuer les privilèges nécessaires.
4. Sélectionner PHP 8.2 ou supérieur et activer les extensions listées au chapitre 1.
5. Cloner ou téléverser le projet dans `/home/USERNAME/glv`.
6. Exécuter Composer, ou vérifier que `vendor/` préconstruit est présent.
7. Exécuter `npm ci && npm run build`, ou téléverser un `public/build/` préconstruit.
8. Créer `.env` sur le serveur avec les valeurs réelles, sans l'ajouter à Git.
9. Pour une nouvelle installation uniquement, exécuter `php artisan key:generate`. Ne jamais remplacer une clé existante.
10. Tester la connexion à la base, effectuer une sauvegarde si elle contient déjà des données, puis lancer `php artisan migrate --force`.
11. Exécuter `php artisan storage:link`.
12. Donner à l'utilisateur web l'écriture sur `storage` et `bootstrap/cache` sans utiliser `chmod 777`.
13. Exécuter `php artisan config:cache`.
14. Exécuter `php artisan route:cache`.
15. Exécuter `php artisan view:cache`.
16. Ajouter le cron du scheduler avec le chemin réel de PHP et du projet.
17. Ajouter un worker supervisé seulement si des jobs asynchrones sont réellement activés.
18. Activer le certificat SSL, la redirection HTTPS, `APP_URL=https://...` et `SESSION_SECURE_COOKIE=true`.
19. Tester la connexion et la déconnexion.
20. Tester l'espace Super Admin avec un compte de production créé de manière contrôlée, jamais via les seeders de démonstration.
21. Tester un compte agence.
22. Tester l'état et l'expiration d'un abonnement.
23. Créer et vérifier une réservation de recette.
24. Créer et vérifier un contrat de recette.
25. Tester aperçu et téléchargement PDF avec logo et photo réels, puis le fallback sans image.
26. Tester les notifications en base et l'envoi SMTP utilisé par l'installation.
27. Vérifier `php artisan schedule:list`, puis contrôler l'exécution réelle du cron dans les journaux cPanel.
28. Déclencher une sauvegarde finale et documenter la procédure de restauration.

## 11. Base de données et privilèges cPanel

Dans « MySQL Databases » :

1. créer une base dédiée ;
2. créer un utilisateur dédié avec un mot de passe aléatoire conservé hors Git ;
3. associer cet utilisateur à cette seule base ;
4. renseigner dans `.env` les noms préfixés imposés par cPanel ;
5. vérifier la connexion avec `php artisan migrate:status` ;
6. lancer uniquement `php artisan migrate --force` après sauvegarde.

Le compte applicatif a besoin de `SELECT`, `INSERT`, `UPDATE` et `DELETE`. Les migrations nécessitent également des droits DDL tels que `CREATE`, `ALTER`, `INDEX`, `DROP` et `REFERENCES`. Quand cPanel le permet, utiliser un compte de déploiement séparé ou retirer les droits DDL après la migration.

### Premier Super Admin

GLV ne possède pas d'inscription publique et les seeders fournis sont réservés à la démonstration locale. Pour une première installation vide, créer une seule fois le premier Super Admin depuis une session terminal cPanel protégée :

```bash
php artisan tinker
```

Puis, dans Tinker, en remplaçant uniquement le nom et l'adresse génériques :

```php
use App\Models\User;
use function Laravel\Prompts\password;

User::create([
    'agence_id' => null,
    'name' => 'Administrateur GLV',
    'email' => 'admin@example.com',
    'password' => password('Mot de passe initial'),
    'role' => User::ROLE_SUPER_ADMIN,
    'statut' => 'actif',
]);
```

La saisie du mot de passe est masquée et le modèle le hache automatiquement. Ne jamais placer le mot de passe dans la ligne de commande, un script versionné ou l'historique du terminal. Se connecter immédiatement, vérifier l'adresse e-mail si cette politique est activée, puis créer les autres administrateurs depuis l'interface protégée.

## 12. Permissions et stockage

Le propriétaire du projet et l'utilisateur du serveur web doivent pouvoir écrire dans :

```text
storage/
bootstrap/cache/
```

Utiliser les permissions minimales compatibles avec la configuration de l'hébergeur, généralement des dossiers `755` si le propriétaire est aussi l'utilisateur PHP, ou `775` avec un groupe correctement configuré. Ne jamais utiliser `777` comme solution normale.

Les logos d'agence et photos de véhicule vont sur le disque public `storage/app/public` et sont exposés par `public/storage`. Les documents d'agence vont dans `storage/app/private/documents` et restent accessibles uniquement via le contrôleur authentifié. Vérifier ces deux parcours après upload ou restauration.

## 13. Sauvegarde et restauration

- Sauvegarder MySQL quotidiennement et avant toute migration importante.
- Sauvegarder `storage/app/public` et `storage/app/private` avec une fréquence cohérente avec l'activité métier.
- Conserver une copie chiffrée de `.env` hors Git et hors document root.
- Définir une rétention et stocker au moins une copie hors de l'hébergement principal.
- Tester périodiquement une restauration MySQL + storage dans un environnement isolé.

La présente préparation ne configure aucun backup sur Namecheap : cette action reste manuelle.

## 14. Checklist de recette production

### Authentification

- [ ] login
- [ ] logout
- [ ] durée de session et cookie `Secure`, `HttpOnly`, `SameSite`

### Super Admin

- [ ] dashboard
- [ ] agences
- [ ] abonnements et demandes de renouvellement
- [ ] notifications

### Agence

- [ ] dashboard
- [ ] voitures
- [ ] clients
- [ ] réservations
- [ ] contrats
- [ ] aperçu et téléchargement PDF
- [ ] abonnement
- [ ] notifications

### Sécurité

- [ ] isolation IDOR entre deux agences
- [ ] isolation multi-agences
- [ ] rôles et permissions
- [ ] refus des requêtes sans jeton CSRF
- [ ] `.env` et stockage privé inaccessibles par HTTP

### Infrastructure

- [ ] HTTPS et redirection HTTP vers HTTPS
- [ ] connexion MySQL
- [ ] SMTP
- [ ] lien storage et permissions
- [ ] scheduler réellement exécuté
- [ ] queue supervisée si elle est utilisée
- [ ] backup et restauration testée
- [ ] `/up` répond HTTP 200

## 15. Secrets, logs et artefacts

`.env` est ignoré par Git. Ne jamais committer des fichiers `.key`, `.pem`, dumps SQL, journaux ou archives contenant des identifiants. Contrôler `storage/logs` avant chaque livraison et activer la rotation des logs en production.

Les trois fichiers de `output/pdf` sont des artefacts E2E de validation, déjà versionnés. Ils ne sont pas nécessaires au fonctionnement de GLV en production et devraient être retirés du dépôt dans une tâche de nettoyage dédiée, après accord, puis remplacés par une règle d'ignorance. Ne pas les inclure dans l'archive de production.

Les logos et photos E2E sous `storage/app/public/*/e2e` ne doivent pas être importés en production. Les données métier réelles doivent être créées sur le serveur par les utilisateurs autorisés ou restaurées depuis une sauvegarde approuvée.

## 16. Health check et validation

Laravel expose `/up`. Utiliser cette route pour vérifier que PHP, Laravel et le routage répondent, sans la considérer comme une preuve que MySQL, SMTP, le scheduler ou les PDF fonctionnent. Prévoir des contrôles distincts pour ces composants.

Après chaque déploiement :

```bash
php artisan about --only=environment
php artisan migrate:status
php artisan schedule:list
composer check-platform-reqs --no-dev
```

Exécuter la suite automatisée dans un environnement de validation isolé. Ne jamais lancer les tests contre la base de production.
