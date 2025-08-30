# PluginQuiz

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
- [� Autres fonctionnalités](#-autres-fonctionnalités)
- [📁 Structure du projet](#-structure-du-projet)
  - [📂 Index du projet](#-index-du-projet)
- [🗄️ Structure de la base de données](#-structure-de-la-base-de-données)
- [� Shortcodes](#-shortcodes)
- [�🚀 Démarrage rapide](#-démarrage-rapide)
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

👨‍💻 **Stagiaire :** [Sohail Charef](https://www.linkedin.com/in/sohail-charef/)

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

## � Autres fonctionnalités

La plugin fournit plusieurs outils utilitaires accessibles depuis l'admin, non listés dans la section principale des fonctionnalités :

- Export des étudiants LearnPress en CSV
- Migration QSM → Quiz AI : importe les quiz, questions et réponses depuis QSM
- Suppression et gestion des emails spam / domaines de confiance
- Correction de la base LearnPress & intégrations diverses `.

Ces outils facilitent les opérations de maintenance et de migration pour les administrateurs du site.

## �📁 Structure du projet

```sh
└── quiz-ai/
	├── README.md
	├── quiz-ai-pro.php
	├── admin/
	│   ├── content-manager.php
	│   ├── dashboard.php
	│   ├── edit-quiz.php
	│   ├── emails.php
	│   ├── generate-quiz.php
	│   ├── others-delete-spam.php
	│   ├── others-export-learnpress.php
	│   ├── others-migrate-qsm.php
	│   ├── quiz-list.php
	│   └── stats.php
	├── assets/
	│   ├── css/
	│   │   ├── admin.css
	│   │   ├── course-references.css
	│   │   ├── enhanced-feedback.css
	│   │   ├── frontend.css
	│   │   └── quiz-ai-latest-quizzes.css
	│   ├── js/
	│   │   ├── admin-export-learnpress.js
	│   │   ├── admin-spam-batch.js
	│   │   ├── admin.js
	│   │   ├── frontend-script.js
	│   │   └── frontend.js
	│   └── media/
	│       └── no-image-default.jpg
	└── includes/
		├── ai-functions.php
		├── ajax-handlers.php
		├── db-functions.php
		├── email-functions.php
		├── enhanced-feedback.php
		├── frontend-functions.php
		├── helpers.php
		├── learnpress-db-fix.php
		├── learnpress-integration.php
		├── quiz-ai-latest-quizzes-shortcode.php
		└── rag-functions.php
```

### 📂 Index du projet

Un aperçu détaillé des fichiers principaux est disponible dans la version anglaise du README (voir code source).

---

## �️ Structure de la base de données

Le plugin crée les tables suivantes (avec `wp_` comme préfixe WordPress par défaut) :

### 1. `quiz_ia_quizzes`

Stocke les métadonnées des quiz.

- `id` (PK)
- `title`, `description`
- `course_id`, `category_id` (JSON)
- `quiz_type`, `form_type`, `grading_system`
- `featured_image`
- `time_limit`, `questions_per_page`, `total_questions`
- `settings` (longtext)
- `ai_provider`, `ai_generated`, `ai_instructions`
- `quiz_code` (unique)
- `status`, `views`, `participants`
- `learnpress_quiz_id`, `created_by`
- `created_at`, `updated_at`

### 2. `quiz_ia_questions`

Stocke les questions des quiz.

- `id` (PK)
- `quiz_id` (FK)
- `question_text`, `question_type`
- `correct_answer`, `points`, `explanation`
- `course_reference`, `featured_image`
- `sort_order`, `created_at`

### 3. `quiz_ia_answers`

Stocke les réponses possibles aux questions.

- `id` (PK)
- `question_id` (FK)
- `answer_text`, `is_correct`
- `sort_order`, `created_at`

### 4. `quiz_ia_results`

Stocke les résultats et tentatives des quiz.

- `id` (PK)
- `quiz_id` (FK)
- `user_email`, `user_name`, `user_id`
- `score`, `total_questions`, `correct_answers`
- `time_taken`, `percentage`, `status`
- `answers_data`, `questions_data`, `user_answers_json`
- `attempt_number`, `started_at`, `completed_at`

### 5. `quiz_ia_course_chunks`

Pour le traitement RAG et le découpage des cours.

- `id` (PK)
- `course_id` (FK)
- `chunk_text`, `keywords`, `summary`
- `word_count`, `chunk_order`
- `tfidf_vector`, `relevance_score`
- `created_at`

### 6. `quiz_ia_email_preferences`

Stocke les préférences email des utilisateurs pour les quiz.

- `id` (PK)
- `user_email`, `user_name`, `user_id`
- `receive_quiz_results`, `receive_new_quiz_alerts`
- `quiz_id`, `preferences_json`
- `created_at`, `updated_at`

### 7. `quiz_ia_comments`

Stocke les commentaires et évaluations des quiz.

- `id` (PK)
- `quiz_id` (FK)
- `user_id`, `user_name`, `user_email`
- `comment_text`, `rating`
- `ip_address`, `user_agent`
- `is_approved`, `created_at`

### 8. `quiz_ia_trusted_domains`

Stocke les domaines de confiance pour l'accès aux quiz.

- `id` (PK)
- `domain` (unique)
- `added_by`, `added_at`

---

## 🔧 Shortcodes

Le plugin fournit plusieurs shortcodes pour intégrer les fonctionnalités dans vos pages WordPress :

### 1. `[quiz_categories]`

Affiche les catégories de quiz avec leurs quiz associés.

**Paramètres :**

- `columns` (int) : Nombre de colonnes (défaut: 3)
- `show_empty` (bool) : Afficher les catégories vides (défaut: false)
- `order` (string) : Champ de tri (défaut: 'name')
- `orderby` (string) : Ordre de tri ASC/DESC (défaut: 'ASC')

**Exemple d'utilisation :**

```php
[quiz_categories columns="4" show_empty="true" orderby="name"]
```

### 2. `[quiz_ai_latest_quizzes]`

Affiche les derniers quiz créés dans une mise en page en cartes.

**Paramètres :**

- `number` (int) : Nombre de quiz à afficher (défaut: 6)
- `orderby` (string) : Champ de tri (défaut: 'created_at')
- `order` (string) : Ordre ASC/DESC (défaut: 'DESC')
- `only_with_images` (bool) : Afficher seulement les quiz avec images (défaut: false)
- `category` (string) : Filtrer par slug de catégorie (optionnel)

**Exemples d'utilisation :**

```php
[quiz_ai_latest_quizzes number="8" order="ASC"]
[quiz_ai_latest_quizzes only_with_images="true" category="data-science"]
```

### 3. `[quiz_ai_unsubscribe]`

Gère la désinscription des emails de quiz.

**Paramètres :** Aucun paramètre requis

**Exemple d'utilisation :**

```php
[quiz_ai_unsubscribe]
```

**Note :** Ce shortcode est généralement utilisé automatiquement dans les emails de désinscription.

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
- Générer des quiz depuis l’onglet _Generate Quiz_.
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
