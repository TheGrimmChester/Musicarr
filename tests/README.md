# Tests pour Musicarr

Ce dossier contient tous les tests pour l'application Musicarr, organisés selon leur type et leur namespace.

## Structure des tests

### Tests Unitaires (`tests/Unit/`)
- **Entity/** : Tests des entités Doctrine
  - `ArtistImageTest.php` - Tests de validation et normalisation des images d'artiste
- **Service/** : Tests des services avec mocks
  - `FileNamingTest.php` - Tests de génération de noms de fichiers
  - `StringSimilarityTest.php` - Tests de calcul de similarité de chaînes
- **TaskProcessor/** : Tests des processeurs de tâches
  - `RenameFilesTaskProcessorTest.php` - Tests de renommage de fichiers
- **LibraryScanning/Processor/** : Tests des processeurs de scan de bibliothèque
  - `AbstractLibraryScanProcessorTest.php` - Tests de base pour les processeurs
  - `EmptyDirectoryCleanupProcessorTest.php` - Tests de nettoyage des répertoires vides
  - `FileCountProcessorTest.php` - Tests de comptage de fichiers
  - `MainLibraryScanProcessorTest.php` - Tests du processeur principal
- **TrackMatcher/Calculator/** : Tests des calculateurs de score
  - `AbstractScoreCalculatorTest.php` - Tests de base pour les calculateurs
  - `AlbumMatchCalculatorTest.php` - Tests de correspondance d'albums
  - `ArtistMatchCalculatorTest.php` - Tests de correspondance d'artistes
  - `DurationMatchCalculatorTest.php` - Tests de correspondance de durée
  - `NullCalculatorTest.php` - Tests du calculateur nul
  - `ScoreCalculatorChainTest.php` - Tests de chaînage des calculateurs
  - `TitleMatchCalculatorTest.php` - Tests de correspondance de titres
  - `TrackNumberMatchCalculatorTest.php` - Tests de correspondance de numéros de piste
  - `YearMatchCalculatorTest.php` - Tests de correspondance d'années

### Tests Fonctionnels (`tests/Functional/`)
- **Controller/** : Tests des contrôleurs Symfony
  - `ArtistControllerTest.php` - Tests d'intégration des contrôleurs d'artiste

### Tests d'Intégration (`tests/Integration/`)
- **LibraryScanning/** : Tests d'intégration du scan de bibliothèque
  - `LibraryScanChainTest.php` - Tests de chaînage des processeurs de scan
- **TrackMatcher/** : Tests d'intégration du système de correspondance
  - `TrackMatcherTest.php` - Tests d'intégration du matcher de pistes
  - `YearMatchIntegrationTest.php` - Tests d'intégration de correspondance par année

## Exécution des tests

### Tous les tests
```bash
vendor/bin/phpunit
```

### Tests unitaires uniquement
```bash
vendor/bin/phpunit tests/Unit/
```

### Tests fonctionnels uniquement
```bash
vendor/bin/phpunit tests/Functional/
```

### Tests d'intégration uniquement
```bash
vendor/bin/phpunit tests/Integration/
```

### Tests par catégorie spécifique
```bash
# Tests des entités
vendor/bin/phpunit tests/Unit/Entity/

# Tests des services
vendor/bin/phpunit tests/Unit/Service/

# Tests des processeurs de tâches
vendor/bin/phpunit tests/Unit/TaskProcessor/

# Tests des processeurs de scan
vendor/bin/phpunit tests/Unit/LibraryScanning/

# Tests des calculateurs de score
vendor/bin/phpunit tests/Unit/TrackMatcher/

# Tests des contrôleurs
vendor/bin/phpunit tests/Functional/Controller/
```

### Avec couverture de code
```bash
vendor/bin/phpunit --coverage-html coverage/
```

## Configuration des mocks

Les tests utilisent PHPUnit MockObject pour simuler les dépendances externes :

- **Repositories** : Mockés pour éviter les appels à la base de données
- **Services externes** : Mockés pour éviter les appels réseau
- **Clients API** : Mockés avec des réponses prédéfinies

## Bonnes pratiques

1. **Nommage** : Les classes de test doivent suivre le pattern `{ClasseTestée}Test`
2. **Isolation** : Chaque test doit être indépendant des autres
3. **Mocks** : Utiliser les mocks pour les dépendances externes
4. **Assertions** : Utiliser des assertions spécifiques et descriptives
5. **Coverage** : Viser une couverture de code élevée (>80%)
6. **Organisation** : Placer les tests dans le bon dossier selon leur type et namespace

## Configuration de l'environnement de test

L'environnement de test utilise :
- Base de données SQLite en mémoire
- Configuration spécifique dans `config/packages/test/`
- Variables d'environnement de test dans `.env.test` 
