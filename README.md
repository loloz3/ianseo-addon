
# ianseo-addon

**FR 🇫🇷**  
Module personnalisable pour **I@nseo** afin d’étendre l’expérience d’organisation : vues graphiques, vérifications des inscriptions (1 départ/2 départ...) et impression des « autres tirs ». Le module s’installe dans **Modules/Custom** pour rester intact lors des mises à jour de I@nseo. [3](https://www.ianseo.net/Release/)

**EN 🇬🇧**  
Customizable module for **I@nseo** that extends the competition workflow with graphical views, consistency checks, and print for archers shooting outside the tournament. Installs under **Modules/Custom** to survive I@nseo updates. [3](https://www.ianseo.net/Release/)

---

## ✨ Fonctionnalités / Features

- **GraphicalView** : vue graphique des cibles/archers pour faciliter affectation et contrôle.  
- **Verif** : contrôles des inscriptions (archers enregistré à leur 1er départ et 2eme départs, etc.) avant validation.  
- **AutresTirs** : impression des « autres tirs ».  
- **Intégration Custom** : chargement via `Modules/Custom/menu.php`, compatible avec d’autres modules personnalisés.  
*(Basé sur la structure réelle du dépôt : `AutresTirs/`, `GraphicalView/`, `Verif/`, `menu.php`.)* [2](https://github.com/loloz3/ianseo-addon)

**English summary**  
- **GraphicalView**: Graphical view of targets and archers to simplify assignment and control.
- **Verif**: Registration checks (1 session / 2 sessions…).  
- **AutresTirs**: Print for archers shooting outside the tournament.
- **Custom integration** via `Modules/Custom/menu.php`. [2](https://github.com/loloz3/ianseo-addon)

---

## 📦 Prérequis / Requirements

- **I@nseo** installed (PHP ≥ 8 as required by I@nseo; MySQL ≥ 8 recommended).  
- Access to the server (Windows XAMPP / Linux / macOS) to copy files into `Modules/Custom`. [3](https://www.ianseo.net/Release/)

---

## 🛠️ Installation

**FR 🇫🇷**

1. **Téléchargez** ou **clonez** ce dépôt (bouton vert **Code** → **Download ZIP**).
2. **Copiez** `menu.php` et les dossiers `AutresTirs/`, `GraphicalView/`, `Verif/` dans le dossier **Modules/Custom** de votre installation I@nseo :
   - **Windows/macOS (XAMPP)** : `htdocs/Modules/Custom/`
   - **Linux (paquet I@nseo)** : `/opt/ianseo/Modules/Custom/`
3. **Permissions (Linux)** : si le module doit écrire ses propres fichiers, accordez les droits nécessaires (ex. `sudo chmod -R 775 /opt/ianseo/Modules/Custom/ianseo-addon/`).
4. **Cohabitation** : si `Modules/Custom/menu.php` existe déjà (autre module), **ne le remplacez pas** ; ouvrez‑le et **ajoutez** une ligne d’inclusion pour charger ce module en plus :
   ```php
   // Exemple d’inclusion dans Modules/Custom/menu.php
   include 'ianseo-addon-main/menu.php';
