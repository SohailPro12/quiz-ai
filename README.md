<p align="center">
	<img src="https://raw.githubusercontent.com/PKief/vscode-material-icon-theme/ec559a9f6bfd399b82bb44393651661b08aaf7ba/icons/folder-markdown-open.svg" align="center" width="30%">
</p>
<p align="center"><h1 align="center">QUIZ-AI</h1></p>
<p align="center">
	<em><code>❯ Génération intelligente de quiz pour e-learning</code></em>
</p>
<p align="center">
	<img src="https://img.shields.io/github/license/SohailPro12/quiz-ai?style=default&logo=opensourceinitiative&logoColor=white&color=0080ff" alt="license">
	<img src="https://img.shields.io/github/last-commit/SohailPro12/quiz-ai?style=default&logo=git&logoColor=white&color=0080ff" alt="last-commit">
	<img src="https://img.shields.io/github/languages/top/SohailPro12/quiz-ai?style=default&color=0080ff" alt="repo-top-language">
	<img src="https://img.shields.io/github/languages/count/SohailPro12/quiz-ai?style=default&color=0080ff" alt="repo-language-count">
</p>
<br>

## 🔗 Table des matières

- [📍 Aperçu](#-aperçu)
- [👾 Fonctionnalités](#-fonctionnalités)
- [📁 Structure du projet](#-structure-du-projet)
  - [📂 Index du projet](#-index-du-projet)
- [🚀 Démarrage rapide](#-démarrage-rapide)
  - [☑️ Prérequis](#-prérequis)
  - [⚙️ Installation](#-installation)
  - [🤖 Utilisation](#-utilisation)
  - [🧪 Tests](#-tests)
- [📌 Feuille de route](#-feuille-de-route)
- [🔰 Contribution](#-contribution)
- [🙌 Remerciements](#-remerciements)

---

## 📍 Aperçu

Ce projet a été développé dans le cadre de mon stage chez **innovation.ma**, ayant pour objectif la **modernisation d’une plateforme e-learning** par l’intégration d’outils d’Intelligence Artificielle.

### Contexte du stage

- **Sujet du stage :** Modernisation d’une plateforme e-learning avec intégration d’outils IA (innovation.ma).  
- **Contexte :** La plateforme propose des contenus pédagogiques autour de la Data, BI, IA et des cas pratiques. Afin d’améliorer l’expérience utilisateur et d’accroître l’engagement des apprenants, une évolution technologique a été mise en place : intégration d’IA générative, analytics et personnalisation.  

### Objectifs du stage

1. Réaliser un audit fonctionnel et technique de la plateforme existante.  
2. Proposer une architecture cible intégrant des modules IA.  
3. Concevoir et prototyper des fonctionnalités basées sur l’IA :  
   - Génération automatique de quiz à partir des contenus de cours.  

👩‍💼 **Encadrante :** Directrice BU Data BI & AI – [Samia Naciri](https://www.linkedin.com/in/samia-naciri/?originalSubdomain=ma)  

---

## 👾 Fonctionnalités

- **Génération de quiz par IA :** création automatique de quiz à partir du contenu pédagogique.  
- **Tableau de bord admin :** gestion centralisée des quiz, statistiques et contenu.  
- **Learning Analytics :** suivi des progrès et de la performance des apprenants.  
- **Feedback personnalisé :** retours intelligents adaptés aux réponses des apprenants.  
- **Intégration LearnPress :** compatibilité avec le LMS LearnPress pour la gestion des cours et quiz.  
- **Notifications email :** envoi automatique des résultats et alertes.  
- **Gestion de contenu :** organisation et mise à jour rapide des questions et réponses.  

---

## 📁 Structure du projet

```sh
└── quiz-ai/
	├── README.md
	├── admin
	│   ├── content-manager.php
	│   ├── dashboard.php
	│   ├── edit-quiz.php
	│   ├── emails.php
	│   ├── generate-quiz.php
	│   ├── quiz-list.php
	│   └── stats.php
	├── assets
	│   ├── css
	│   └── js
	├── includes
	│   ├── ai-functions.php
	│   ├── ajax-handlers.php
	│   ├── db-functions.php
	│   ├── email-functions.php
	│   ├── enhanced-feedback.php
	│   ├── frontend-functions.php
	│   ├── helpers.php
	│   ├── learnpress-db-fix.php
	│   ├── learnpress-integration.php
	│   └── rag-functions.php
	└── quiz-ai-pro.php
```

### 📂 Index du projet

Un aperçu détaillé des fichiers principaux est disponible dans la version anglaise du README (voir code source).  

---

## 🚀 Démarrage rapide

### ☑️ Prérequis

- **Langage :** PHP  
- **Plateforme :** WordPress  

### ⚙️ Installation

1. Cloner le dépôt :  
   ```sh
   git clone https://github.com/SohailPro12/quiz-ai
   ```
2. Copier le dossier du plugin dans WordPress :  
   ```sh
   cp -r quiz-ai /path/to/wordpress/wp-content/plugins/
   ```
3. Activer le plugin dans le panneau d’administration WordPress (**Quiz AI Pro**).  

### 🤖 Utilisation

- Accéder au menu **Quiz AI Pro** dans l’administration WordPress.  
- Générer des quiz depuis l’onglet *Generate Quiz*.  
- Suivre les statistiques et gérer le contenu depuis le tableau de bord.  
- Les apprenants peuvent réaliser les quiz et recevoir des retours personnalisés par email.  

### 🧪 Tests

- Vérifier la génération des quiz, la réception des emails et le suivi statistique directement sur WordPress.  

---

## 📌 Feuille de route

- [x] Génération de quiz par IA  
- [x] Notifications email automatiques  
- [x] Intégration LearnPress  
- [x] Suivi de l’engagement des utilisateurs  
- [x] Tableau de bord administrateur  
- [ ] Analytics avancés  
- [ ] Gamification (badges, classements, récompenses)  
- [ ] Optimisation mobile  

---

## 🔰 Contribution

Les contributions sont les bienvenues ! Vous pouvez proposer :  
- Personnalisation des parcours selon le profil et le niveau de l’apprenant.  
- Chatbot pédagogique pour expliquer ou résumer les leçons.  
- Système de recommandation de contenus adaptés.  

<details closed>
<summary>Contributing Guidelines</summary>

1. **Fork the Repository**: Start by forking the project repository to your github account.
2. **Clone Locally**: Clone the forked repository to your local machine using a git client.
   ```sh
   git clone https://github.com/SohailPro12/quiz-ai
   ```
3. **Create a New Branch**: Always work on a new branch, giving it a descriptive name.
   ```sh
   git checkout -b new-feature-x
   ```
4. **Make Your Changes**: Develop and test your changes locally.
5. **Commit Your Changes**: Commit with a clear message describing your updates.
   ```sh
   git commit -m 'Implemented new feature x.'
   ```
6. **Push to github**: Push the changes to your forked repository.
   ```sh
   git push origin new-feature-x
   ```
7. **Submit a Pull Request**: Create a PR against the original project repository. Clearly describe the changes and their motivations.
8. **Review**: Once your PR is reviewed and approved, it will be merged into the main branch. Congratulations on your contribution!
</details>

<details closed>
<summary>Contributor Graph</summary>
<br>
<p align="left">
   <a href="https://github.com{/SohailPro12/quiz-ai/}graphs/contributors">
      <img src="https://contrib.rocks/image?repo=SohailPro12/quiz-ai">
   </a>
</p>
</details>

---
---


## 🙌 Remerciements

- Merci à **innovation.ma** pour l’opportunité du stage et l’encadrement.  
- Merci à toutes les ressources open-source et communautés ayant inspiré ce projet.  
