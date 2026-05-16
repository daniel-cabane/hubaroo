# Hubaroo — Liste complète des fonctionnalités

**URL de production :** hubaroo.online  
**Public cible :** Élèves français de CM1-CM2, Collège, Lycée — entièrement gratuit, sans collecte de données.

---

## 1. Accès sans compte (invité)

- **Rejoindre une session :** n'importe qui peut rejoindre une session Kangourou active en entrant son code à 6 caractères sur la page "Rejoindre une session".
- **Entrer son nom :** l'invité saisit son prénom/nom avant de commencer.
- **Passer le questionnaire :** navigation entre les 26 questions, sélection de réponses A–E (ou saisie numérique pour les questions 25–26).
- **Récupération de tentative :** un invité dont la session a été fermée par erreur peut retrouver sa tentative via le code de session.
- **Voir les résultats :** une fois la session expirée, l'invité peut consulter son score et la correction complète (réponses correctes surlignées).
- **Demander à reprendre :** si un invité a terminé ou quitté par accident, il peut envoyer une demande de reprise à l'enseignant.
- **Voir un sujet :** la page "Voir un sujet" permet à tout visiteur de consulter les questions d'un sujet (sans démarrer de session).

---

## 2. Authentification

- **Inscription :** création de compte avec email + mot de passe (choix du rôle : élève ou enseignant).
- **Connexion :** email + mot de passe.
- **Connexion Google OAuth :** inscription/connexion en un clic via Google.
- **Mot de passe oublié :** envoi d'un email de réinitialisation avec lien sécurisé.
- **Réinitialisation du mot de passe :** formulaire accessible depuis le lien reçu par email.
- **Modifier son nom :** les utilisateurs connectés peuvent mettre à jour leur nom d'affichage.
- **Récupération des tentatives invité :** à la connexion, l'application détecte les tentatives effectuées en invité et propose de les rattacher au compte.

---

## 3. Sessions Kangourou (utilisateurs connectés)

### 3.1 Créer une session

- Sélection du **sujet** par année + niveau (CM1, CM2, 3e, 4e, 5e, 6e, Lycée).
- Choix du **statut initial** : Brouillon (non disponible aux élèves) ou Active (ouverte immédiatement).
- Un **code à 6 caractères** est généré automatiquement et partageable.

### 3.2 Paramétrer une session (mode brouillon uniquement)

- **Confidentialité :** Publique (accessible avec le code) ou Privée (accès réservé aux classes liées).
- **Accès par classe :** pour les sessions privées, sélection des classes (divisions) autorisées.
- **Durée limite :** temps imparti en minutes pour chaque participant.
- **Correction :** Différée (résultats visibles seulement après expiration) ou Immédiate (résultats visibles dès soumission).
- **Ordre des questions :** Normal, Mélange par palier, Mélange paliers 1–3, Mélange complet.
- **Sécurité anti-fuite (blur) :** si activée, un compte à rebours se déclenche lorsque l'élève quitte l'onglet ; la tentative est soumise automatiquement si l'élève ne revient pas à temps.

### 3.3 Gérer une session

- **Activer** une session brouillon pour la rendre disponible aux participants.
- **Éditer l'expiration :** choisir la date/heure d'expiration d'une session active.
- **Changer le code :** générer un nouveau code (l'ancien devient invalide).
- **Afficher le code en plein écran :** bouton "fullscreen" pour projeter le code devant une classe.
- **Ouvrir/fermer pour une classe :** pour les sessions privées, activer ou désactiver l'accès d'une classe en temps réel.
- **Liste des tentatives :** voir toutes les tentatives en temps réel (nom, score, statut).
- **Modifier le nom d'une tentative :** corriger le nom d'un participant.
- **Supprimer une tentative :** retirer la tentative d'un participant.
- **Demandes de reprise :** recevoir et approuver/refuser les demandes de reprise des participants (centre d'alertes 🔔).

### 3.4 Passer un questionnaire (session Kangourou)

- **Navigation :** flèches gauche/droite, barre de navigation circulaire en haut (cercles colorés par statut : non répondu / répondu / question actuelle).
- **Répondre :** boutons A–B–C–D–E (questions 1–24) ; champ numérique (questions 25–26).
- **Chronomètre :** masqué par défaut, affichable au clic ; passe en rouge lors de la dernière minute.
- **Zoom image :** clic sur une question pour l'agrandir en overlay.
- **Sécurité blur :** si activée, un compte à rebours de 10 secondes s'affiche lorsque l'onglet perd le focus.
- **Soumettre :** bouton de soumission finale avec confirmation.
- **Reprendre une tentative :** si approuvé par l'enseignant, l'élève peut revenir sur sa tentative en cours.

### 3.5 Résultats

- **Score :** nombre de points affiché en grand.
- **Barre de réponses :** pastilles vertes/rouges/grises représentant chaque question.
- **Correction détaillée :** chaque question affiche l'image, la réponse de l'élève, la bonne réponse (si disponible).
- **Demander à reprendre :** depuis la page résultats, l'élève peut envoyer une nouvelle demande si la session est encore active.
- **Correction différée :** un message indique que la correction sera disponible à la fin de la session.

### 3.6 Mes sessions / Mes tentatives

- **Mes sessions :** liste de toutes les sessions créées par l'utilisateur (statut, code, date d'expiration), suppression, accès aux détails.
- **Mes tentatives :** liste de toutes les tentatives passées (titre, date, score), accès aux résultats ou reprise d'une tentative en cours.

---

## 4. Classes (Divisions)

### 4.1 Enseignant — Créer et gérer une classe

- **Créer une classe :** nom de la classe ; un code d'invitation unique est généré.
- **Inviter par email :** envoyer une invitation par email à un élève spécifique.
- **Changer le code d'invitation :** générer un nouveau code (l'ancien devient invalide).
- **Afficher le code en plein écran :** modal "fullscreen" montrant l'URL + le code pour projection.
- **Ouvrir/fermer les inscriptions :** activer ou désactiver la possibilité pour de nouveaux élèves de rejoindre.
- **Liste des élèves :** voir tous les membres avec leur nom de classe et email.
- **Modifier le nom de classe d'un élève :** corriger ou personnaliser le prénom/nom affiché dans les tableaux.
- **Retirer un élève :** supprimer un élève de la classe.
- **Archiver une classe :** rendre la classe inactive (sessions et invitations masquées).
- **Désarchiver :** remettre une classe archivée en état actif.
- **Supprimer une classe archivée :** suppression définitive.

### 4.2 Élève — Rejoindre une classe

- **Rejoindre par code :** saisir le code de classe pour rejoindre (avec saisie du prénom et nom).
- **Accepter/refuser une invitation :** depuis la page "Mes classes" ou depuis la poche en page d'accueil.
- **Vue de la classe :** affichage du nom de la classe et de l'enseignant, sessions disponibles, parcours et historique de sauts.

### 4.3 Sessions associées à une classe

- **Sessions actives visibles :** les élèves voient les sessions ouvertes pour leur classe dans la vue de classe et dans la poche d'accueil.
- **Analyse de session :** après expiration d'une session, l'enseignant peut voir le tableau des résultats par élève, le taux de réussite par question, et marquer des questions comme "revues".

---

## 5. Parcours (Courses)

### 5.1 Enseignant — Gérer un parcours

- **Créer un parcours :** titre ; le parcours est créé dans le contexte d'une classe.
- **Archiver / désarchiver un parcours :** masquer un parcours sans le supprimer.
- **Supprimer un parcours :** uniquement si aucun saut n'existe.
- **Tableau de bord élèves :** tableau croisant élèves × sauts, affichant le score de chaque élève à chaque saut, triable par nom, total ou par saut.
- **Statuts des sauts :** Brouillon / Actif / En expiration / Expiré — modifiable depuis le tableau.
- **Questions suggérées :** icône ampoule sur chaque parcours ; ouvre une modale listant les questions recommandées automatiquement après chaque saut (regroupées par niveau 1–3 étoiles) avec possibilité de rendre une question publique (visible aux élèves) ou de la supprimer.

### 5.2 Élève — Suivre son parcours

- **Vue parcours :** onglets par parcours, affichage du score total et du graphique d'évolution (score par saut ou total cumulé).
- **Commencer un saut actif :** bouton "Saut actif — Commencer" visible si un saut est disponible.
- **Demander à reprendre un saut :** si le saut est encore actif et la tentative terminée, l'élève peut envoyer une demande de reprise.
- **Questions à revoir :** section dans la vue classe et dans la poche d'accueil, affichant les questions publiques recommandées par l'enseignant (niveaux 1–3 étoiles), cliquables pour voir l'image.

---

## 6. Sauts (Jumps)

### 6.1 Enseignant — Créer et gérer un saut

- **Créer un saut :** nombre de questions, durée (minutes), sujet de référence (optionnel), date d'expiration.
- **Modifier un saut :** paramètres éditables tant que le saut est en brouillon.
- **Activer un saut :** rendre un saut disponible pour les élèves de la classe.
- **Supprimer un saut :** uniquement si aucune tentative n'existe.
- **Demandes de reprise (sauts) :** recevoir et approuver/refuser les demandes de reprise des élèves depuis le centre d'alertes.

### 6.2 Élève — Passer un saut

- **Interface identique au questionnaire Kangourou :** navigation, chronomètre, zoom image, sécurité blur.
- **Questions personnalisées :** les questions d'un saut sont sélectionnées en fonction du niveau de maîtrise (mastery) de l'élève dans la matière.
- **Résultats de saut :** après expiration, affichage du score, du nombre de bonnes réponses, et de la correction question par question.

---

## 7. Analyse automatique des sauts (AnalyseJump)

- Après l'expiration d'un saut, un **job en arrière-plan** analyse les résultats de tous les élèves.
- Il calcule la **moyenne de maîtrise** de la classe et identifie les questions auxquelles les élèves ont échoué.
- Il génère jusqu'à **18 questions suggérées** réparties en 3 niveaux de difficulté (★ facile, ★★ moyen, ★★★ difficile) adaptées au niveau moyen de la classe.
- Ces suggestions sont stockées dans le parcours et accessibles à l'enseignant via l'icône ampoule.

---

## 8. Centre d'alertes (🔔)

- **Icône de cloche** dans la barre de navigation, avec compteur rouge si des alertes sont en attente.
- **Enseignants :** voient les demandes de reprise en attente (sessions Kangourou et sauts) ; peuvent approuver ou refuser chaque demande directement depuis le centre.
- **Élèves :** voient l'état de leurs demandes de reprise (en attente, acceptée, refusée) ; bouton "Rejoindre" si acceptée.

---

## 9. La poche ("Dans la poche") — Page d'accueil

- Bandeau fixe en haut de la page d'accueil pour les utilisateurs connectés, masquable.
- Affiche jusqu'à 4 colonnes selon le contexte :
  1. **Invitations de classe :** invitations en attente avec boutons Accepter/Refuser.
  2. **Sauts actifs :** liens directs vers les sauts disponibles.
  3. **Sessions actives :** sessions de classe ouvertes non encore tentées.
  4. **Questions à revoir :** questions publiques suggérées par les enseignants (niveaux étoiles), cliquables pour voir l'image.
- **Compteur :** le bouton d'ouverture affiche le nombre total d'éléments en attente.

---

## 10. Signalement de bugs

- **Bouton "Signaler un bug" :** accessible depuis la navbar ou les pages concernées.
- **Formulaire :** description du problème + catégorie.
- **Compteur de bugs non résolus :** visible dans la navbar pour les utilisateurs ayant soumis des rapports.
- **Administration :** les admins peuvent voir, commenter et marquer les rapports comme résolus ou supprimés.

---

## 11. Interface d'administration

- Accessible uniquement aux utilisateurs ayant le rôle admin.
- **Gestion des sujets :** voir la liste de tous les sujets importés, modifier leurs métadonnées (titre, année, niveau).
- **Gestion des utilisateurs :** rechercher un utilisateur par nom/email, modifier son rôle (élève, enseignant, admin).
- **Gestion des roles :** liste des rôles disponibles.
- **Gestion des rapports de bugs :** voir tous les rapports, les résoudre ou les supprimer.

---

## 12. Fonctionnalités transversales

- **Mode sombre :** thème clair/sombre supporté dans toute l'interface.
- **Responsive :** interface adaptée mobile, tablette et desktop.
- **Temps réel :** mises à jour en direct via Laravel Echo / Reverb (ouverture/fermeture de sessions, activation de sauts, résolution de demandes de reprise).
- **Questions de niveau / difficulté :** chaque question a un niveau (palier 1–4) et une difficulté numérique utilisée pour personnaliser les sauts.
- **Maîtrise (mastery) :** score de maîtrise de chaque élève, mis à jour à l'inscription selon son âge, et après chaque saut expiré ; utilisé pour calibrer la difficulté des questions suggérées.
