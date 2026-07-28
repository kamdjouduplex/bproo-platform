# 03 — Configuration de l'entreprise (Branding)

> **Acteur :** 🟠 Admin tenant  
> **Permission requise :** `configuration.view`, `configuration.edit`  
> **Route :** Menu → Configuration (icône engrenage)

---

## Processus 3.1 — Paramétrer l'identité de l'entreprise

### Objectif
Personnaliser le portail avec le nom commercial, le message d'accueil et les coordonnées de l'entreprise.

### Prérequis
- Être connecté au portail tenant avec un compte Admin
- Module **Configuration** activé

### Étapes

**1. Menu sidebar → Configuration**

**2. Onglet Général** (ou formulaire principal)

Remplir les champs suivants :

| Champ | Valeur de test |
|-------|---------------|
| Nom commercial (shop_name) | `KREOBAT SARL` |
| Message de bienvenue | `Bienvenue sur votre espace de gestion KREOBAT` |
| Email de contact | `contact@kreobat.cm` |
| Téléphone | `+237 6 99 00 11 22` |
| Adresse | `Rue des Palmiers, Akwa, Douala` |

**3. Enregistrer**

### Résultat attendu
- Le nom en haut de la sidebar change pour **KREOBAT SARL**
- L'événement de mise à jour branding se propage immédiatement (Alpine.js)

---

## Processus 3.2 — Paramétrer la TVA et la devise

### Objectif
S'assurer que toutes les factures et devis utilisent la bonne devise et le bon taux de TVA.

### Étapes

**1. Configuration → Paramètres financiers**

| Champ | Valeur de test |
|-------|---------------|
| Devise | `XOF` |
| Taux TVA par défaut | `19.25` |
| Conditions de paiement | `30 jours` |

**2. Enregistrer**

### Résultat attendu
- Les nouveaux devis et factures pré-remplissent automatiquement TVA à 19,25% et devise XOF

---

## Processus 3.3 — Changer la langue de l'interface

### Objectif
Basculer l'interface tenant entre Français et Anglais.

### Étapes

**1. En haut à droite de la topbar** → drapeau langue → menu déroulant

**2. Cliquer sur 🇬🇧 English** ou 🇫🇷 Français

### Résultat attendu
- Toutes les étiquettes de l'interface changent de langue
- La préférence est sauvegardée en session
