# Storage Kit - Documentation complète

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## 📖 Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Concepts fondamentaux](#concepts-fondamentaux)
4. [Les adaptateurs de stockage](#les-adaptateurs-de-stockage)
   - [MemoryStorage - Stockage en mémoire](#memorystorage---stockage-en-mémoire)
   - [JsonlStorage - Stockage JSONL](#jsonlstorage---stockage-jsonl)
   - [CacheStorage - Stockage avec cache](#cachestorage---stockage-avec-cache)
   - [SessionStorage - Stockage en session](#sessionstorage---stockage-en-session)
   - [CookieStorage - Stockage en cookies](#cookiestorage---stockage-en-cookies)
   - [SqliteStorage - Stockage SQLite](#sqlitestorage---stockage-sqlite)
5. [La factory](#la-factory)
6. [Cas d'usage réels](#cas-dusage-réels)
   - [Cache de sessions utilisateur](#cache-de-sessions-utilisateur)
   - [Persistance d'état d'application](#persistance-détat-dapplication)
   - [Cache multi-niveaux](#cache-multi-niveaux)
   - [Migration de données](#migration-de-données)
   - [Cache de résultats d'API](#cache-de-résultats-dapi)
   - [Base de données embarquée](#base-de-données-embarquée)
7. [Performance](#performance)
8. [Intégration](#intégration)
   - [Avec Laravel](#avec-laravel)
   - [Avec AlgoKIT](#avec-algokit)
9. [Exemples complets](#exemples-complets)
10. [API Reference](#api-reference)

---

## Introduction

**StorageKit** est une bibliothèque PHP qui fournit des adaptateurs de stockage unifiés pour différents types de persistance. Elle propose une interface commune (`StorageInterface`) pour le stockage et la récupération de données, avec six implémentations :

| Adaptateur | Description | Cas d'usage |
|------------|-------------|-------------|
| **MemoryStorage** | Stockage en mémoire (RAM) | Tests, développement, données éphémères |
| **JsonlStorage** | Stockage persistant JSONL | Données persistantes, logs structurés |
| **CacheStorage** | Stockage avec cache (PhpFastCache) | Haute performance, TTL, multi-backends |
| **SessionStorage** | Stockage en session PHP | Données utilisateur, authentification |
| **CookieStorage** | Stockage en cookies navigateur | Préférences, données légères côté client |
| **SqliteStorage** | Stockage SQLite ACID | Base de données embarquée, persistance fiable |

### Pourquoi StorageKit ?

- ✅ **Interface unifiée** : La même API pour tous les storages
- ✅ **Flexibilité** : Changez de storage sans modifier votre code
- ✅ **Performance** : Choisissez le storage adapté à vos besoins
- ✅ **Persistance** : Avec JSONL, Cache, Session, Cookie ou SQLite, vos données survivent aux requêtes
- ✅ **TTL** : Gérez l'expiration des données (CacheStorage, JsonlStorage)
- ✅ **Statistiques** : Suivez les performances de votre cache
- ✅ **Batch operations** : Optimisez les accès multiples
- ✅ **Transactions ACID** : Avec SqliteStorage pour l'intégrité des données

---

## Installation

```bash
composer require andydefer/storage-kit
```

### Prérequis

- PHP 8.1 ou supérieur
- Extension `json` (activée par défaut)
- (Optionnel) Extension `sqlite3` pour SqliteStorage et le driver SQLite de CacheStorage
- **SessionStorage** : La session PHP doit être démarrée (`session_start()`)

---

## Concepts fondamentaux

### StorageInterface

Tous les storages implémentent la même interface :

```php
interface StorageInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function getMultiple(array $keys): array;
    public function set(string $key, mixed $value): void;
    public function setMultiple(array $items): void;
    public function delete(string $key): bool;
    public function deleteMultiple(array $keys): void;
    public function exists(string $key): bool;
    public function clear(): void;
}
```

### SqliteStorageInterface (étendue)

SqliteStorage ajoute des méthodes spécifiques aux bases de données :

```php
interface SqliteStorageInterface extends StorageInterface
{
    public function getDatabasePath(): string;
    public function getTableName(): string;
    public function isMemoryDatabase(): bool;
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollback(): bool;
    public function inTransaction(): bool;
    public function count(): int;
    public function getDatabaseSize(): int;
    public function getStats(): SqliteStorageStatsRecord;
    public function vacuum(): bool;
    public function getConnection(): SQLite3;
    public function close(): bool;
}
```

---

## Les adaptateurs de stockage

### MemoryStorage - Stockage en mémoire

**Description :** Stocke les données dans un tableau PHP en mémoire RAM.

**Caractéristiques :**
- ⚡ Vitesse maximale
- ❌ Pas de persistance (perdu à la fin du script)
- 💾 Faible consommation mémoire (dépend des données)

**Utilisation :**

```php
use AndyDefer\StorageKit\Storage\MemoryStorage;

$storage = new MemoryStorage();

// Stockage
$storage->set('user_123', ['name' => 'John', 'age' => 30]);

// Récupération
$user = $storage->get('user_123');
// ['name' => 'John', 'age' => 30]

// Batch
$storage->setMultiple([
    'key1' => 'value1',
    'key2' => 'value2',
]);

$values = $storage->getMultiple(['key1', 'key2']);
// ['key1' => 'value1', 'key2' => 'value2']

// Vérification
if ($storage->exists('user_123')) {
    // ...
}

// Suppression
$storage->delete('user_123');

// Nettoyage complet
$storage->clear();
```

**Cas d'usage :**
- Cache de résultats d'API dans une requête
- Compteur en mémoire
- Cache de données de formulaire
- Cache de calculs lourds

---

### JsonlStorage - Stockage JSONL

**Description :** Stocke les données sur disque au format JSON Lines.

**Caractéristiques :**
- 💾 Persistant sur disque
- 📁 Un fichier par clé (organisation par hash)
- ⏰ Support du TTL global
- 🔄 Support du contexte
- 📊 Statistiques d'utilisation

**Structure des fichiers :**

```
/var/data/
├── a/
│   └── b/
│       └── user_123.jsonl
├── c/
│   └── d/
│       └── session_456.jsonl
└── e/
    └── f/
        └── cache_789.jsonl
```

**Utilisation :**

```php
use AndyDefer\StorageKit\Storage\JsonlStorage;

$storage = new JsonlStorage(
    basePath: '/var/data',
    ttl: 3600,
    hashLevels: 2
);

// Stockage
$storage->set('user_123', ['name' => 'John', 'email' => 'john@example.com']);

// Récupération
$user = $storage->get('user_123');

// Batch
$storage->setMultiple([
    'user_456' => ['name' => 'Jane'],
    'user_789' => ['name' => 'Bob'],
]);

$users = $storage->getMultiple(['user_123', 'user_456']);

// Vérification d'existence (vérifie aussi l'expiration)
if ($storage->exists('user_123')) {
    echo "User exists and is not expired";
}

// Gestion du TTL global
$storage->setTTL(7200); // Prolonge à 2 heures
$storage->set('session_456', 'active');

// État avec contexte (pour AlgoKIT)
$storage->saveState('trie_french', ['words' => ['bonjour']], 'french');
$state = $storage->loadState('trie_french', 'french');

// Nettoyage des entrées expirées
$deleted = $storage->cleanExpired();
echo "Supprimé {$deleted} entrées expirées";

// Statistiques
$stats = $storage->getStats();
echo "Lignes traitées: " . $stats->total_lines_processed;
echo "Fichiers traités: " . $stats->processed_files;

// Suppression
$storage->delete('user_123');
$storage->deleteMultiple(['user_456', 'user_789']);

// Nettoyage complet
$storage->clear();
```

**Format du fichier JSONL :**

```jsonl
{"key":"user_123","value":"{\"value\":{\"name\":\"John\",\"email\":\"john@example.com\"}}","expires_at":"2024-01-15T14:35:00+00:00"}
```

**Cas d'usage :**
- Persistance des données d'application
- Logs structurés
- Cache persistant
- Sauvegarde d'état (AlgoKIT)

---

### CacheStorage - Stockage avec cache

**Description :** Stockage utilisant PhpFastCache avec support de multiples backends.

**Caractéristiques :**
- 🚀 Haute performance
- 💾 Persistant (selon le driver)
- ⏰ TTL par clé
- 📊 Statistiques de performance
- 🔌 Multi-backends (Files, Sqlite)

**Drivers supportés :**

| Driver | Description | Cas d'usage |
|--------|-------------|-------------|
| **Files** | Stockage sur disque | Développement, petite structure |
| **Sqlite** | Base de données SQLite | Performance, grande structure |

**Utilisation avec Files :**

```php
use AndyDefer\StorageKit\Storage\CacheStorage;
use AndyDefer\StorageKit\Enums\CacheDriver;

$storage = new CacheStorage(CacheDriver::FILES);

// Stockage avec TTL
$storage->setWithTTL('user_123', ['name' => 'John'], 3600);

// Récupération
$user = $storage->get('user_123');

// Batch
$storage->setMultiple([
    'config' => ['debug' => true],
    'settings' => ['theme' => 'dark'],
]);

// Modification du TTL
$storage->setTTL('user_123', 7200);

// Statistiques
$stats = $storage->getStats();
echo "Hits: {$stats->hits}, Misses: {$stats->misses}";
echo "Taux de hit: " . ($stats->hits / ($stats->hits + $stats->misses) * 100) . "%";

// Suppression
$storage->delete('user_123');
$storage->deleteMultiple(['config', 'settings']);

// Nettoyage complet
$storage->clear();
```

**Utilisation avec SQLite :**

```php
use AndyDefer\StorageKit\Records\CacheConfigRecord;

$config = new CacheConfigRecord('/tmp/cache.sqlite');
$storage = new CacheStorage(CacheDriver::SQLITE, $config);

$storage->setWithTTL('session_123', 'active', 1800);
$session = $storage->get('session_123');
```

**Cas d'usage :**
- Cache de données
- Sessions utilisateur
- Mise en cache d'API
- Applications haute performance

---

### SessionStorage - Stockage en session

**Description :** Stocke les données dans la session PHP (`$_SESSION`). Les données persistent pendant toute la session utilisateur.

**Caractéristiques :**
- 🔒 Persistant pendant la session utilisateur
- 🏷️ Isolation par namespace
- 📦 Stockage côté serveur
- ✅ Support de tous les types PHP
- ⚡ Accès rapide (mémoire)

**Prérequis :**
```php
session_start(); // La session doit être démarrée avant utilisation
```

**Utilisation :**

```php
session_start();

use AndyDefer\StorageKit\Storage\SessionStorage;

// Création avec namespace personnalisé
$storage = new SessionStorage('user_data');

// Stockage
$storage->set('user_id', 123);
$storage->set('preferences', ['theme' => 'dark', 'language' => 'fr']);

// Récupération
$userId = $storage->get('user_id'); // 123
$preferences = $storage->get('preferences'); // ['theme' => 'dark', 'language' => 'fr']

// Batch operations
$storage->setMultiple([
    'username' => 'john_doe',
    'role' => 'admin',
]);

$values = $storage->getMultiple(['username', 'role']);

// Vérification d'existence
if ($storage->exists('user_id')) {
    echo "Utilisateur connecté";
}

// Récupération de toutes les données du namespace
$allData = $storage->getAll();

// Vérification si vide
if ($storage->isEmpty()) {
    echo "Aucune donnée dans ce namespace";
}

// Changement de namespace (les données sont conservées)
$storage->setNamespace('new_namespace');

// Vérification de l'état de la session
if ($storage->isSessionActive()) {
    echo "Session active";
}

// Suppression
$storage->delete('user_id');
$storage->deleteMultiple(['username', 'role']);

// Nettoyage complet
$storage->clear(); // Vide tout le namespace
```

**Cas d'usage :**
- Authentification utilisateur
- Panier d'achat
- Données de formulaire multi-étapes
- État de l'application par utilisateur
- Messages flash

---

### CookieStorage - Stockage en cookies

**Description :** Stocke les données dans les cookies du navigateur. Les données persistent sur le poste du client.

**Caractéristiques :**
- 🌐 Stocké côté client
- ⏰ Expiration configurable
- 🔒 Options de sécurité (Secure, HttpOnly, SameSite)
- 📦 Taille limitée (~4KB par cookie)
- 🔄 Nombre de cookies limité (~50-150 par domaine)

**Utilisation :**

```php
use AndyDefer\StorageKit\Storage\CookieStorage;

// Création avec configuration
$storage = new CookieStorage(
    prefix: 'app_',
    expires: '+30 days',
    domain: null,
    path: '/',
    secure: false,
    httpOnly: true,
    sameSite: 'Lax'
);

// Récupération du préfixe
$prefix = $storage->getPrefix(); // 'app_'

// Stockage
$storage->set('theme', 'dark');
$storage->set('preferences', ['language' => 'fr', 'notifications' => true]);

// Récupération avec valeur par défaut
$theme = $storage->get('theme', 'light');
$preferences = $storage->get('preferences', []);

// Batch operations
$storage->setMultiple([
    'language' => 'en',
    'timezone' => 'Europe/Paris',
]);

$values = $storage->getMultiple(['language', 'timezone']);

// Vérification d'existence
if ($storage->exists('theme')) {
    echo "Thème: " . $storage->get('theme');
}

// Récupération de tous les cookies du préfixe
$allData = $storage->getAll();

// Vérification si vide
if ($storage->isEmpty()) {
    echo "Aucun cookie avec ce préfixe";
}

// Configuration dynamique des cookies
$storage->setExpires('+1 year');      // Expiration dans 1 an
$storage->setExpires(time() + 3600);  // Expiration dans 1 heure
$storage->setDomain('.example.com');   // Domaine
$storage->setPath('/admin');           // Chemin
$storage->setSecure(true);             // HTTPS uniquement
$storage->setHttpOnly(false);          // Accessible en JavaScript
$storage->setSameSite('Strict');       // SameSite

// Récupération de la configuration
$expires = $storage->getExpires();
$domain = $storage->getDomain();
$path = $storage->getPath();
$isSecure = $storage->isSecure();
$isHttpOnly = $storage->isHttpOnly();
$sameSite = $storage->getSameSite();

// Stockage multiple avec la configuration actuelle
$storage->setMultipleWithConfig([
    'session_id' => 'abc123',
    'user_role' => 'admin',
]);

// Suppression
$storage->delete('theme');
$storage->deleteMultiple(['language', 'timezone']);

// Nettoyage complet (supprime tous les cookies du préfixe)
$storage->clear();
```

**Cas d'usage :**
- Préférences utilisateur (thème, langue)
- Panier d'achat léger
- Paramètres d'affichage
- Suivi de session simple
- Cookies de consentement (RGPD)
- Mémorisation des choix utilisateur

---

### SqliteStorage - Stockage SQLite

**Description :** Stockage persistant utilisant SQLite comme base de données embarquée avec support ACID.

**Caractéristiques :**
- 💾 Persistant sur disque ou en mémoire
- 🔒 ACID (Atomicité, Cohérence, Isolation, Durabilité)
- 🔄 Transactions imbriquées
- ⏰ Support des opérations batch
- 📊 Statistiques détaillées
- 🚀 Optimisation VACUUM
- 📁 Création automatique des répertoires

**Utilisation :**

```php
use AndyDefer\StorageKit\Storage\SqliteStorage;

// Base de données persistante
$storage = new SqliteStorage('/var/data/storage.db', 'storage_kv');

// Base en mémoire (tests)
$memoryDb = new SqliteStorage(':memory:', 'test_table');

// Stockage
$storage->set('user_123', ['name' => 'John', 'age' => 30]);
$storage->set('config', ['debug' => true, 'version' => '1.0']);

// Récupération
$user = $storage->get('user_123');
$config = $storage->get('config', []);

// Batch operations
$storage->setMultiple([
    'user_456' => ['name' => 'Jane'],
    'user_789' => ['name' => 'Bob'],
]);

$users = $storage->getMultiple(['user_123', 'user_456', 'user_789']);

// Transactions
$storage->beginTransaction();
$storage->set('key1', 'value1');
$storage->set('key2', 'value2');
$storage->commit(); // Persiste tout ou rien

// Transactions imbriquées
$storage->beginTransaction(); // Transaction externe
$storage->set('key1', 'value1');

$storage->beginTransaction(); // Transaction interne
$storage->set('key2', 'value2');
$storage->commit(); // Commit interne

$storage->commit(); // Commit externe

// Vérification de transaction
if ($storage->inTransaction()) {
    echo "Transaction en cours";
}

// Annulation
$storage->beginTransaction();
$storage->set('temp_key', 'temp_value');
$storage->rollback(); // Annule les modifications

// Statistiques détaillées
$stats = $storage->getStats();
echo "Éléments: {$stats->total_items}\n";
echo "Taille: " . round($stats->database_size / 1024, 2) . " KB\n";
echo "Écritures: {$stats->write_count}\n";
echo "Lectures: {$stats->read_count}\n";
echo "Pages: {$stats->total_pages}\n";

// Optimisation
$storage->vacuum(); // Défragmente et récupère l'espace

// Comptage
$count = $storage->count();
echo "Nombre d'éléments: {$count}\n";

// Suppression
$storage->delete('user_123');
$storage->deleteMultiple(['user_456', 'user_789']);

// Nettoyage complet
$storage->clear();

// Fermeture
$storage->close();
```

**Cas d'usage :**
- Base de données embarquée
- Persistance d'état d'application
- Configuration d'application
- Logs structurés avec SQL
- Stockage pour AlgoKIT
- Applications sans serveur SQL externe

---

## La factory

**StorageFactory** simplifie la création des storages en centralisant la configuration.

**Utilisation basique :**

```php
use AndyDefer\StorageKit\Factory\StorageFactory;
use AndyDefer\StorageKit\Enums\StorageSystem;

$factory = new StorageFactory(
    basePath: '/var/data',
    ttl: 3600,
    hashLevels: 2
);

// Création de différents storages
$memory = $factory->create(StorageSystem::MEMORY);
$jsonl = $factory->create(StorageSystem::JSONL);
$cache = $factory->create(StorageSystem::CACHE);
$session = $factory->create(StorageSystem::SESSION);
$cookie = $factory->create(StorageSystem::COOKIE);
$sqlite = $factory->create(StorageSystem::SQLITE);
```

**Configuration dynamique :**

```php
$factory = new StorageFactory();

$factory->setBasePath('/custom/path');
$factory->setTTL(7200);
$factory->setHashLevels(3);

$storage = $factory->create(StorageSystem::JSONL);
```

**Création avancée :**

```php
use AndyDefer\StorageKit\Enums\CacheDriver;
use AndyDefer\StorageKit\Records\CacheConfigRecord;

// Cache personnalisé
$config = new CacheConfigRecord('/custom/cache');
$cache = $factory->createCacheStorage(
    driver: CacheDriver::SQLITE,
    config: $config,
    cacheKeyPrefix: 'app_'
);

// Cookie personnalisé
$cookie = $factory->createCookieStorage(
    prefix: 'app_',
    expires: '+1 year',
    domain: '.example.com',
    path: '/',
    secure: true,
    httpOnly: true,
    sameSite: 'Strict'
);

// Session avec namespace personnalisé
$session = $factory->createSessionStorage('user_preferences');

// SQLite personnalisé
$sqlite = $factory->createSqliteStorage('/var/data/app.db', 'custom_kv');
$persistent = $factory->createPersistentSqliteStorage('app.db', 'my_table');
```

---

## Cas d'usage réels

### Cache de sessions utilisateur

```php
class SessionManager
{
    private CacheStorage $cache;
    private int $ttl;
    
    public function __construct(CacheStorage $cache, int $ttl = 3600)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }
    
    public function startSession(string $userId, array $data): void
    {
        $this->cache->setWithTTL('session_' . $userId, $data, $this->ttl);
    }
    
    public function getSession(string $userId): ?array
    {
        return $this->cache->get('session_' . $userId);
    }
    
    public function extendSession(string $userId, int $extraTime = 1800): void
    {
        if ($this->cache->exists('session_' . $userId)) {
            $this->cache->setTTL('session_' . $userId, $extraTime);
        }
    }
    
    public function endSession(string $userId): void
    {
        $this->cache->delete('session_' . $userId);
    }
    
    public function getStats(): array
    {
        $stats = $this->cache->getStats();
        return [
            'active_sessions' => $stats->items_count,
            'hits' => $stats->hits,
            'misses' => $stats->misses,
        ];
    }
}

// Utilisation
$cache = new CacheStorage(CacheDriver::FILES);
$sessions = new SessionManager($cache, 7200);

$sessions->startSession('user_123', [
    'username' => 'john_doe',
    'login_time' => time(),
]);

$session = $sessions->getSession('user_123');

if ($session) {
    echo "Welcome back, " . $session['username'];
}

$sessions->extendSession('user_123', 3600);
```

### Persistance d'état d'application avec SQLite

```php
class ApplicationState
{
    private SqliteStorage $storage;
    
    public function __construct(string $dbPath)
    {
        $this->storage = new SqliteStorage($dbPath, 'app_state');
    }
    
    public function setState(string $key, $value): void
    {
        $this->storage->set($key, $value);
    }
    
    public function getState(string $key, $default = null)
    {
        return $this->storage->get($key, $default);
    }
    
    public function getStats(): SqliteStorageStatsRecord
    {
        return $this->storage->getStats();
    }
    
    public function optimize(): void
    {
        $this->storage->vacuum();
    }
    
    public function close(): void
    {
        $this->storage->close();
    }
}

// Utilisation
$state = new ApplicationState('/var/data/app_state.db');

// Sauvegarde de l'état
$state->setState('app_version', '1.2.3');
$state->setState('last_cron_run', time());
$state->setState('config', ['debug' => true, 'maintenance' => false]);

// Récupération
$version = $state->getState('app_version');
$lastRun = $state->getState('last_cron_run', 0);

echo "App version: $version\n";
echo "Dernier cron: " . date('Y-m-d H:i:s', $lastRun) . "\n";

$stats = $state->getStats();
echo "Éléments: {$stats->total_items}\n";

$state->close();
```

### Cache multi-niveaux

```php
class MultiLevelCache
{
    private MemoryStorage $l1Cache; // Premier niveau (RAM)
    private CacheStorage $l2Cache;  // Deuxième niveau (Cache)
    private JsonlStorage $l3Cache;  // Troisième niveau (Persistant)
    
    public function __construct()
    {
        $this->l1Cache = new MemoryStorage();
        $this->l2Cache = new CacheStorage(CacheDriver::FILES);
        $this->l3Cache = new JsonlStorage('/var/data', 86400);
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        // Niveau 1 - RAM
        if ($value = $this->l1Cache->get($key)) {
            return $value;
        }
        
        // Niveau 2 - Cache
        if ($value = $this->l2Cache->get($key)) {
            $this->l1Cache->set($key, $value);
            return $value;
        }
        
        // Niveau 3 - Persistant
        if ($value = $this->l3Cache->get($key)) {
            $this->l2Cache->setWithTTL($key, $value, 3600);
            $this->l1Cache->set($key, $value);
            return $value;
        }
        
        return $default;
    }
    
    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->l1Cache->set($key, $value);
        $this->l2Cache->setWithTTL($key, $value, $ttl);
        $this->l3Cache->set($key, $value);
    }
    
    public function clear(): void
    {
        $this->l1Cache->clear();
        $this->l2Cache->clear();
        $this->l3Cache->clear();
    }
}

// Utilisation
$cache = new MultiLevelCache();
$cache->set('user_data', ['name' => 'John']);

$data = $cache->get('user_data'); // Récupération rapide depuis RAM
```

### Base de données embarquée avec SQLite

```php
class ProductCatalog
{
    private SqliteStorage $storage;
    
    public function __construct(string $dbPath)
    {
        $this->storage = new SqliteStorage($dbPath, 'products');
    }
    
    public function addProduct(string $id, string $name, float $price): void
    {
        $this->storage->set("product:{$id}", [
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'created_at' => time(),
        ]);
    }
    
    public function getProduct(string $id): ?array
    {
        return $this->storage->get("product:{$id}");
    }
    
    public function updatePrice(string $id, float $newPrice): void
    {
        $product = $this->getProduct($id);
        if ($product) {
            $product['price'] = $newPrice;
            $product['updated_at'] = time();
            $this->storage->set("product:{$id}", $product);
        }
    }
    
    public function deleteProduct(string $id): void
    {
        $this->storage->delete("product:{$id}");
    }
    
    public function getStats(): SqliteStorageStatsRecord
    {
        return $this->storage->getStats();
    }
    
    public function optimize(): void
    {
        $this->storage->vacuum();
    }
    
    public function close(): void
    {
        $this->storage->close();
    }
}

// Utilisation
$catalog = new ProductCatalog('/var/data/products.db');

// Ajout de produits
$catalog->addProduct('p1', 'Laptop', 999.99);
$catalog->addProduct('p2', 'Smartphone', 599.99);
$catalog->addProduct('p3', 'Headphones', 89.99);

// Récupération
$product = $catalog->getProduct('p1');
echo $product['name'] . " - " . $product['price'] . "€\n";

// Mise à jour
$catalog->updatePrice('p1', 899.99);

// Statistiques
$stats = $catalog->getStats();
echo "Produits: {$stats->total_items}\n";

$catalog->close();
```

---

## Performance

### Comparatif des storages

| Storage | Vitesse | Persistance | TTL | Transactions | Mémoire | Cas d'usage |
|---------|---------|-------------|-----|--------------|---------|-------------|
| **MemoryStorage** | ⚡⚡⚡ | ❌ | ❌ | ❌ | 💾 | Tests, éphémère |
| **SessionStorage** | ⚡⚡ | ✅ | ❌ | ❌ | 💾 | Données utilisateur |
| **CookieStorage** | ⚡⚡ | ✅ | ✅ | ❌ | 💾 | Préférences client |
| **CacheStorage** | ⚡⚡ | ✅ | ✅ | ❌ | 💾 | Cache haute perf |
| **JsonlStorage** | 🐌 | ✅ | ✅ | ❌ | 💾💾 | Persistance |
| **SqliteStorage** | ⚡ | ✅ | ❌ | ✅ | 💾 | Base de données ACID |

### Limitations

| Storage | Limitation |
|---------|------------|
| **CookieStorage** | ~4KB par cookie, ~50-150 cookies par domaine |
| **SessionStorage** | Dépend de la configuration PHP (session.gc_maxlifetime) |
| **JsonlStorage** | I/O disque, lent pour de nombreuses écritures |
| **SqliteStorage** | 1 écriture simultanée, pas adapté aux gros volumes |

### Optimisations

**1. Utilisez MemoryStorage pour les données temporaires**

```php
// ✅ Bon - Données éphémères
$cache = new MemoryStorage();
$cache->set('api_response', $data);
```

**2. Utilisez CacheStorage pour les données fréquentes**

```php
// ✅ Bon - Données fréquemment accédées
$cache = new CacheStorage();
$cache->setWithTTL('popular_data', $data, 3600);
```

**3. Utilisez SqliteStorage pour les données structurées**

```php
// ✅ Bon - Données relationnelles et ACID
$db = new SqliteStorage('/data/app.db');
$db->beginTransaction();
$db->setMultiple($data);
$db->commit();
```

**4. Batch operations pour de nombreux éléments**

```php
// ❌ Mauvais - N opérations
foreach ($items as $key => $value) {
    $storage->set($key, $value);
}

// ✅ Bon - 1 opération
$storage->setMultiple($items);
```

**5. Transactions SQLite pour l'intégrité des données**

```php
// ✅ Bon - Atomicité garantie
$db->beginTransaction();
try {
    $db->set('balance', $newBalance);
    $db->set('history', $newHistory);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

**6. VACUUM périodique pour SQLite**

```php
// ✅ Bon - Optimisation périodique
if ($storage->count() > 10000) {
    $storage->vacuum();
}
```

---

## Intégration

### Avec Laravel

**1. Configuration**

```php
// config/storage-kit.php
return [
    'default' => env('STORAGE_KIT_DEFAULT', 'sqlite'),
    'paths' => [
        'base' => env('STORAGE_KIT_PATH', storage_path('storage-kit')),
        'cache' => env('STORAGE_KIT_CACHE_PATH', storage_path('storage-kit/cache')),
        'sqlite' => env('STORAGE_KIT_SQLITE_PATH', storage_path('storage-kit/database.db')),
    ],
    'ttl' => env('STORAGE_KIT_TTL', 86400),
    'hash_levels' => env('STORAGE_KIT_HASH_LEVELS', 2),
    'cache' => [
        'driver' => env('STORAGE_KIT_CACHE_DRIVER', 'Files'),
        'prefix' => env('STORAGE_KIT_CACHE_PREFIX', 'storage_'),
    ],
    'session' => [
        'namespace' => env('STORAGE_KIT_SESSION_NAMESPACE', 'storage_kit'),
    ],
    'cookie' => [
        'prefix' => env('STORAGE_KIT_COOKIE_PREFIX', 'storage_'),
        'expires' => env('STORAGE_KIT_COOKIE_EXPIRES', '+30 days'),
        'path' => env('STORAGE_KIT_COOKIE_PATH', '/'),
        'secure' => env('STORAGE_KIT_COOKIE_SECURE', false),
        'http_only' => env('STORAGE_KIT_COOKIE_HTTP_ONLY', true),
        'same_site' => env('STORAGE_KIT_COOKIE_SAME_SITE', 'Lax'),
    ],
    'sqlite' => [
        'table' => env('STORAGE_KIT_SQLITE_TABLE', 'storage_kv'),
    ],
];
```

**2. Service Provider**

```php
// App/Providers/StorageKitServiceProvider.php
namespace App\Providers;

use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;
use AndyDefer\StorageKit\Factory\StorageFactory;
use AndyDefer\StorageKit\Enums\StorageSystem;
use Illuminate\Support\ServiceProvider;

class StorageKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StorageFactory::class, function ($app) {
            $config = config('storage-kit');
            
            return new StorageFactory(
                basePath: $config['paths']['base'],
                ttl: $config['ttl'],
                hashLevels: $config['hash_levels']
            );
        });

        $this->app->singleton(StorageInterface::class, function ($app) {
            $factory = $app->make(StorageFactory::class);
            $default = config('storage-kit.default', 'sqlite');
            
            return match ($default) {
                'sqlite' => $factory->createSqliteStorage(
                    config('storage-kit.paths.sqlite'),
                    config('storage-kit.sqlite.table', 'storage_kv')
                ),
                'jsonl' => $factory->create(StorageSystem::JSONL),
                'cache' => $factory->create(StorageSystem::CACHE),
                'session' => $factory->create(StorageSystem::SESSION),
                'cookie' => $factory->create(StorageSystem::COOKIE),
                default => $factory->create(StorageSystem::MEMORY),
            };
        });
    }
}
```

**3. Utilisation dans un Controller**

```php
use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;
use AndyDefer\StorageKit\Storage\SqliteStorage;

class ProductController extends Controller
{
    private StorageInterface $storage;
    
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }
    
    public function show(int $id)
    {
        $cacheKey = 'product_' . $id;
        
        if ($this->storage->exists($cacheKey)) {
            $product = $this->storage->get($cacheKey);
            return response()->json(['product' => $product, 'cached' => true]);
        }
        
        $product = Product::find($id);
        $this->storage->set($cacheKey, $product->toArray());
        
        return response()->json(['product' => $product, 'cached' => false]);
    }
    
    public function update(Request $request, int $id)
    {
        $this->storage->beginTransaction();
        
        try {
            $product = Product::find($id);
            $product->update($request->all());
            $this->storage->set('product_' . $id, $product->toArray());
            $this->storage->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            $this->storage->rollback();
            throw $e;
        }
    }
}
```

### Avec AlgoKIT

```php
use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\StorageKit\Factory\StorageFactory;
use AndyDefer\StorageKit\Enums\StorageSystem;
use AndyDefer\StorageKit\Storage\SqliteStorage;

$factory = new StorageFactory('/var/data', 3600);

// SQLite pour les structures persistantes (ACID)
$sqlite = new SqliteStorage('/var/data/algo.db', 'algo_kv');
$trie = new Trie($sqlite, 'autocomplete');
$bkTree = new BKTree($sqlite, 'dictionary');

// CacheStorage pour les structures avec TTL
$cache = $factory->create(StorageSystem::CACHE);
$bloom = new BloomFilter($cache, 10000, 3, 'url_index');
$cms = new CountMinSketch($cache, 10000, 5, 'frequencies');

// Memory pour les structures temporaires
$memory = $factory->create(StorageSystem::MEMORY);
$hll = new HyperLogLog($memory, 14, 'temp_visitors');
$topK = new TopK($memory, 10, 'temp_top');

// SessionStorage pour les données utilisateur
session_start();
$session = $factory->create(StorageSystem::SESSION);
$trie = new Trie($session, 'user_autocomplete');

// Utilisation combinée
$trie->insert('laravel');
$bloom->insert('https://example.com');
$cms->add('php');
$hll->add('user_123');

echo "Trie: " . implode(', ', array_map(fn($r) => $r->word, $trie->search('la')->toArray())) . "\n";
echo "Bloom: " . ($bloom->exists('https://example.com') ? '✅' : '❌') . "\n";
echo "CMS: " . $cms->count('php') . "\n";
echo "HLL: " . $hll->count() . "\n";

$sqlite->close();
```

---

## Exemples complets

### Système de cache complet avec tous les storages

```php
class MultiStorageSystem
{
    private StorageFactory $factory;
    private StorageInterface $memory;
    private StorageInterface $jsonl;
    private StorageInterface $cache;
    private StorageInterface $session;
    private StorageInterface $cookie;
    private SqliteStorage $sqlite;
    
    public function __construct()
    {
        session_start();
        
        $this->factory = new StorageFactory('/var/data', 3600);
        
        $this->memory = $this->factory->create(StorageSystem::MEMORY);
        $this->jsonl = $this->factory->create(StorageSystem::JSONL);
        $this->cache = $this->factory->create(StorageSystem::CACHE);
        $this->session = $this->factory->create(StorageSystem::SESSION);
        $this->cookie = $this->factory->create(StorageSystem::COOKIE);
        $this->sqlite = $this->factory->createSqliteStorage('/var/data/multi.db', 'multi_kv');
    }
    
    public function getMemory(): StorageInterface
    {
        return $this->memory;
    }
    
    public function getJsonl(): StorageInterface
    {
        return $this->jsonl;
    }
    
    public function getCache(): StorageInterface
    {
        return $this->cache;
    }
    
    public function getSession(): StorageInterface
    {
        return $this->session;
    }
    
    public function getCookie(): StorageInterface
    {
        return $this->cookie;
    }
    
    public function getSqlite(): SqliteStorage
    {
        return $this->sqlite;
    }
    
    public function close(): void
    {
        $this->sqlite->close();
    }
}

// Utilisation
$storage = new MultiStorageSystem();

// Données éphémères (Memory)
$storage->getMemory()->set('temp_data', 'value');

// Données persistantes (Jsonl)
$storage->getJsonl()->set('user_profiles', $profiles);

// Données fréquentes (Cache)
$storage->getCache()->setWithTTL('popular_data', $data, 3600);

// Données utilisateur (Session)
$storage->getSession()->set('user_id', 123);

// Préférences (Cookie)
$storage->getCookie()->set('theme', 'dark');

// Données structurées (SQLite)
$sqlite = $storage->getSqlite();
$sqlite->beginTransaction();
$sqlite->setMultiple($batchData);
$sqlite->commit();

$stats = $sqlite->getStats();
echo "SQLite: {$stats->total_items} éléments, " . round($stats->database_size/1024,2) . " KB\n";

$storage->close();
```

### Application de monitoring avec SQLite

```php
class MonitoringSystem
{
    private SqliteStorage $storage;
    
    public function __construct(string $dbPath)
    {
        $this->storage = new SqliteStorage($dbPath, 'monitoring');
    }
    
    public function logMetric(string $name, float $value, array $tags = []): void
    {
        $key = "metric:{$name}:" . time();
        $this->storage->set($key, [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => time(),
        ]);
    }
    
    public function getMetric(string $name, int $limit = 100): array
    {
        // Récupération des métriques (implémentation simplifiée)
        $results = [];
        // ... logique de récupération
        return $results;
    }
    
    public function getStats(): SqliteStorageStatsRecord
    {
        return $this->storage->getStats();
    }
    
    public function optimize(): void
    {
        $this->storage->vacuum();
    }
    
    public function close(): void
    {
        $this->storage->close();
    }
}

// Utilisation
$monitor = new MonitoringSystem('/var/data/monitoring.db');

$monitor->logMetric('cpu_usage', 45.2, ['host' => 'server1']);
$monitor->logMetric('memory_usage', 1024.5, ['host' => 'server1']);
$monitor->logMetric('requests_per_second', 1500, ['endpoint' => '/api']);

$stats = $monitor->getStats();
echo "Métriques: {$stats->total_items}\n";
echo "Taille: " . round($stats->database_size / 1024, 2) . " KB\n";

$monitor->close();
```

---

## API Reference

### Interfaces

| Interface | Description |
|-----------|-------------|
| `StorageInterface` | Interface de base pour tous les storages |
| `CacheStorageInterface` | Interface étendue pour CacheStorage |
| `JsonlStorageInterface` | Interface étendue pour JsonlStorage |
| `SessionStorageInterface` | Interface étendue pour SessionStorage |
| `CookieStorageInterface` | Interface étendue pour CookieStorage |
| `SqliteStorageInterface` | Interface étendue pour SqliteStorage |

### Classes

| Classe | Description |
|--------|-------------|
| `MemoryStorage` | Stockage en mémoire |
| `JsonlStorage` | Stockage JSONL |
| `CacheStorage` | Stockage avec cache |
| `SessionStorage` | Stockage en session |
| `CookieStorage` | Stockage en cookies |
| `SqliteStorage` | Stockage SQLite |
| `StorageFactory` | Factory de storages |

### Enums

| Enum | Description |
|------|-------------|
| `StorageSystem` | Systèmes de stockage disponibles |
| `CacheDriver` | Drivers de cache disponibles |

### Records

| Record | Description |
|--------|-------------|
| `CacheConfigRecord` | Configuration de CacheStorage |
| `CacheStorageStatsRecord` | Statistiques de CacheStorage |
| `JsonlStorageStatsRecord` | Statistiques de JsonlStorage |
| `SqliteStorageStatsRecord` | Statistiques de SqliteStorage |
| `JsonlStorageRecord` | Record pour JSONL |

---

## License

MIT © [Andy Defer](https://github.com/andydefer)