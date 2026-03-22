# README — Système de demande de devis (JOËL ELEC)

Ici tu as un système complet pour gérer des demandes de devis depuis ton site.
L’idée est simple : quelqu’un remplit le formulaire → les infos sont enregistrées → tu reçois un mail → tu peux voir la photo via un lien sécurisé.

# À quoi ça sert

Ce système te permet de :

* récupérer les infos des clients
* stocker tout ça proprement en base de données
* recevoir un mail automatiquement
* consulter les photos sans les exposer publiquement


# Structure du projet

```
/project
  /api
    submit-devis.php     → traite le formulaire
    view-photo.php       → affiche les images de manière sécurisée
  /config
    db.php               → connexion à la base de données
    app.php              → config globale (email, clé, url)
  /uploads
    .htaccess            → bloque l’accès direct aux fichiers
  /js
    devis.js             → envoi du formulaire
  formulaire.html        → formulaire côté client
```

---

# Comment ça marche

## 1. L’utilisateur remplit le formulaire

Il met ses infos :

* nom
* téléphone
* service
* message
* photo (optionnel)

## 2. Le formulaire est envoyé

Le fichier JS envoie les données vers :

```
/api/submit-devis.php
```

## 3. Le serveur fait le boulot

Le script :

* vérifie les données
* enregistre en base
* upload la photo
* génère un mail
* crée un lien sécurisé pour la photo

---

# La gestion des photos

Les images ne sont pas accessibles directement.

Tu ne peux pas faire ça :

```
/uploads/image.png
```

Ça sera bloqué.

À la place, tu passes par :

```
/api/view-photo.php?file=xxx&token=yyy
```

Le script vérifie :

* que le token est valide
* que le fichier existe
* que c’est bien une image

Sinon accès refusé.

---

# Le mail

Quand quelqu’un envoie un devis :

* tu reçois un mail avec toutes les infos
* si une photo existe, tu as un lien sécurisé
* tu peux répondre directement au client (Reply-To)

---

# La base de données

Table principale : devis

Elle contient :

* nom
* téléphone
* email
* service
* type de bâtiment
* localisation
* message
* photo
* IP
* date

---

# Test en local

1. Installer XAMPP ou WAMP
2. Importer le fichier SQL dans phpMyAdmin
3. Modifier :

config/db.php

```
localhost / root / pas de mot de passe
```

config/app.php

```
BASE_URL = http://localhost/ton-projet
PHOTO_ACCESS_SECRET = clé longue
```

4. Lancer Apache + MySQL
5. Ouvrir le formulaire
6. Tester

Note : les mails peuvent ne pas marcher en local, c’est normal.

---

# Mise en ligne

Avant de déployer :

* mettre le vrai domaine dans BASE_URL
* mettre une vraie clé secrète
* configurer la base de données
* vérifier le dossier uploads
* tester l’envoi de mail

---

# À savoir

* la fonction mail() dépend de ton hébergeur
* les mails peuvent aller en spam si le domaine n’est pas bien configuré
* si quelqu’un transfère ton mail, il peut accéder à la photo (normal)

---

# Améliorations possibles

* ajouter une expiration sur les liens photo
* passer à SMTP pour les mails
* ajouter un anti-spam
* créer un espace admin

---

# Conclusion

Tu as un système propre, fonctionnel et déjà solide.

Tu peux l’utiliser comme ça et améliorer petit à petit si besoin.

Si tu reprends le projet plus tard, pense juste à vérifier la config et les clés.
