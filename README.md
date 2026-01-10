# ianseo-addon

**FR 🇫🇷**  
Module personnalisable pour **I@nseo** afin d'étendre l'expérience d'organisation : vues graphiques, vérifications des inscriptions (1 départ/2 départs...), impression des « autres tirs », greffe, aide concours. Le module s'installe dans **Modules/Custom** pour rester intact lors des mises à jour de I@nseo.

**EN 🇬🇧**  
Customizable module for **I@nseo** that extends the competition workflow with graphical views, consistency checks, and print for archers shooting outside the tournament. Installs under **Modules/Custom** to survive I@nseo updates.

---

## 🧠 Création assistée par IA / AI-assisted creation

**FR 🇫🇷**
Ces modules, ainsi que ses fonctionnalités et sa documentation, ont été conçus avec l'aide d'outils d'Intelligence Artificielle pour accélérer le développement et la rédaction.

**EN 🇬🇧**
These modules and their documentation were created with the assistance of Artificial Intelligence tools to speed up development and writing.

---

## ✨ Fonctionnalités / Features

- **GraphicalView** : vue graphique des cibles/archers pour faciliter affectation et contrôle.  
- **Verif** : contrôles des inscriptions (archers enregistrés à leur 1er départ et 2ème départs, etc.) avant validation.  
- **AutresTirs** : impression des « autres tirs ».  
- **Greffe** : gestion simplifiée des greffes et tirs supplémentaires.  
- **Aide Concours** : interface centralisée avec tous les raccourcis et procédures (avant/pendant/après la compétition).  
- **Mise à jour automatique** : bouton intégré pour mettre à jour depuis GitHub.  

**English summary**  
- **GraphicalView**: Graphical view of targets and archers to simplify assignment and control.
- **Verif**: Registration checks (1 session / 2 sessions…).  
- **AutresTirs**: Print for archers shooting outside the tournament.
- **Greffe**: Simplified management of additional shoots.
- **Competition Help**: Centralized interface with all shortcuts and procedures.
- **Auto-update**: Built-in button to update from GitHub.


---

## 📦 Prérequis / Requirements

- **I@nseo** installé (PHP ≥ 8 comme requis par I@nseo ; MySQL ≥ 8 recommandé).  
- Accès au serveur (Windows XAMPP / Linux / macOS) pour copier les fichiers dans `Modules/Custom`.

---

## 🛠️ Installation

### Méthode 1 : Installation manuelle (recommandée pour débuter)

**FR 🇫🇷**

1. **Téléchargez** ce dépôt (bouton vert **Code** → **Download ZIP**).
2. **Décompressez** l'archive sur votre ordinateur.
3. **Copiez TOUS les dossiers et fichiers** dans le dossier **Modules/Custom** de votre installation I@nseo :
```bash
📁 Extrait du ZIP :
├── AutresTirs/
├── GraphicalView/
├── Greffe/
├── Perso/
├── ScoreCibles/
├── Verif/
├── aide/
├── test/
├── menu.php
└── README.md

📁 Destination sur votre serveur :
C:\ianseo\htdocs\Modules\Custom\ (Windows)
/var/www/html/ianseo/Modules/Custom/ (Linux)
```

4. **Permissions (Linux)** : si nécessaire, donnez les droits d'écriture :
```bash
sudo chmod -R 755 /var/www/html/ianseo/Modules/Custom/
sudo chown -R www-data:www-data /var/www/html/ianseo/Modules/Custom/
```

### Méthode 2 : Installation via le bouton de mise à jour (après installation initiale)

    Installez d'abord manuellement le fichier aide-concours.php et github_update.php dans Modules/Custom/aide/.

    Accédez à l'aide concours dans I@nseo : VotreURL/Modules/Custom/aide/aide-concours.php

    Cliquez sur le bouton "🔄 Mettre à jour le Addon" pour télécharger automatiquement tous les fichiers.

### Méthode 3 : Installation Git (pour utilisateurs avancés)
```bash

cd /chemin/vers/ianseo/Modules/Custom/
git clone https://github.com/loloz3/ianseo-addon .
# Pour mettre à jour ultérieurement :
git pull origin main
```