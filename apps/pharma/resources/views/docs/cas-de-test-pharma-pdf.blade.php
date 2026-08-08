<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Cas de test — Bproo Pharma</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            line-height: 1.4;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        .cover {
            text-align: center;
            padding: 48px 24px 32px;
            border-bottom: 4px solid #0f766e;
            margin-bottom: 20px;
        }
        .cover h1 { font-size: 20pt; margin: 0 0 8px; color: #0b1f2a; }
        .cover .subtitle { font-size: 12pt; color: #0f766e; margin-bottom: 16px; }
        .cover .meta { font-size: 9pt; color: #64748b; }
        h2 {
            font-size: 12.5pt;
            color: #0f766e;
            margin: 20px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #d7ebe4;
            page-break-after: avoid;
        }
        h3 {
            font-size: 10.5pt;
            color: #334155;
            margin: 14px 0 6px;
            page-break-after: avoid;
        }
        p { margin: 0 0 7px; }
        .toc { margin: 10px 0 18px; }
        .toc li { margin-bottom: 3px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 12px;
            font-size: 8.5pt;
            page-break-inside: auto;
        }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #ecfdf8;
            color: #115e59;
            font-weight: bold;
        }
        tr:nth-child(even) td { background: #f8fafc; }
        .id { white-space: nowrap; font-weight: bold; color: #0f766e; width: 52px; }
        .check { width: 28px; text-align: center; }
        .note {
            background: #ecfdf8;
            border-left: 3px solid #0f766e;
            padding: 7px 10px;
            margin: 8px 0 12px;
            font-size: 8.5pt;
        }
        .warn {
            background: #fffbeb;
            border-left: 3px solid #d97706;
            padding: 7px 10px;
            margin: 8px 0 12px;
            font-size: 8.5pt;
        }
        ol, ul { margin: 4px 0 10px; padding-left: 18px; }
        li { margin-bottom: 3px; }
        .footer-note {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 8pt;
            color: #64748b;
            text-align: center;
        }
        code { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; background: #f1f5f9; }
    </style>
</head>
<body>
    <div class="cover">
        <h1>{{ $appName }}</h1>
        <div class="subtitle">Cahier de cas de test — couverture fonctionnelle complète</div>
        <div class="meta">
            Document de recette manuelle · Généré le {{ $generatedAt }}<br>
            URL app : <code>{{ $appUrl }}</code><br>
            Portail admin : Control Center (hors périmètre de cette app)
        </div>
    </div>

    <div class="note">
        <strong>Comment utiliser ce document :</strong> exécutez chaque cas dans l’ordre recommandé des parcours bout-en-bout (sections 20–22),
        puis validez les modules isolés. Cochez la colonne « OK ». Un cas est réussi seulement si le résultat attendé est intégralement observé.
        Prérequis globaux : tenant <code>pharma</code> (ou équivalent), modules pharmacie actifs, utilisateur avec droits adaptés, caisse ouverte pour les ventes espèces.
    </div>

    <div class="warn">
        <strong>Périmètre :</strong> Bproo Pharma (espace boutique <code>/app?tenant=CODE</code>). Le Control Center (création vendeurs, plans, packages) n’est plus dans cette app.
    </div>

    <h2>Sommaire</h2>
    <ol class="toc">
        <li>Préparation &amp; accès</li>
        <li>Tableau de bord &amp; Hub Pharmacie</li>
        <li>Configuration &amp; branding</li>
        <li>Utilisateurs, rôles &amp; permissions</li>
        <li>Médicaments (catalogue)</li>
        <li>Clients</li>
        <li>Fournisseurs</li>
        <li>Achats &amp; réception avec lots</li>
        <li>Achats étrangers (si module actif)</li>
        <li>Stock</li>
        <li>Inventaire (si actif)</li>
        <li>Lots &amp; péremption</li>
        <li>Ordonnances</li>
        <li>Caisse</li>
        <li>Ventes POS (cœur PMS)</li>
        <li>Retours de vente</li>
        <li>Dettes / crédit client</li>
        <li>Dépenses, pertes</li>
        <li>Devis, réservations, prospects, CRM</li>
        <li>Facturation &amp; paiements factures</li>
        <li>Retours &amp; avoirs (module returns)</li>
        <li>Rapports</li>
        <li>RH : présence &amp; paie</li>
        <li>Tickets</li>
        <li>Abonnement tenant</li>
        <li>Multi-boutiques (si activé)</li>
        <li>Parcours bout-en-bout (E2E)</li>
        <li>Non-régression &amp; indépendance modules</li>
        <li>Matrice de rôles pharmacie</li>
    </ol>

    {{-- ========== 1 ========== --}}
    <h2>1. Préparation &amp; accès</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">A-01</td>
                <td>Landing accessible</td>
                <td>Ouvrir <code>{{ $appUrl }}/</code></td>
                <td>Page marketing Bproo Pharma s’affiche. Pas de lien admin.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">A-02</td>
                <td>/admin retiré</td>
                <td>Ouvrir <code>{{ $appUrl }}/admin</code> et <code>/admin/login</code></td>
                <td>404 (ou erreur route). Pas de portail admin local.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">A-03</td>
                <td>Login tenant sans code</td>
                <td>Ouvrir <code>/app/login</code> sans <code>?tenant=</code></td>
                <td>Message / redirection indiquant qu’un tenant est requis.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">A-04</td>
                <td>Login tenant valide</td>
                <td>Ouvrir <code>/app/login?tenant=pharma</code>, saisir email/mot de passe valides, se connecter</td>
                <td>Redirection tableau de bord. Header + sidebar visibles. Logo boutique si configuré.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">A-05</td>
                <td>Login invalide</td>
                <td>Mauvais mot de passe</td>
                <td>Erreur affichée, session non ouverte.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">A-06</td>
                <td>Page connexion visuelle PMS</td>
                <td>Observer login</td>
                <td>Image rayonnage + marque Bproo Pharma + formulaire « Accès officine ». Pas de logo boutique imposé sur le panneau gauche.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">A-07</td>
                <td>Déconnexion</td>
                <td>Cliquer Déconnexion</td>
                <td>Retour login tenant. Accès /app/ protégé.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">A-08</td>
                <td>Tenant inactif / inconnu</td>
                <td>Tenter login avec tenant inexistant ou désactivé</td>
                <td>Accès refusé clairement.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 2 ========== --}}
    <h2>2. Tableau de bord &amp; Hub Pharmacie</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">D-01</td>
                <td>KPIs pharmacie</td>
                <td>Ouvrir Tableau de bord après connexion</td>
                <td>KPIs visibles selon modules : ventes du jour (+tendance), produits en stock, péremptions 90 j, ordonnances actives, caisse.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">D-02</td>
                <td>Graphique 7 jours POS</td>
                <td>Vérifier « Évolution des ventes »</td>
                <td>Barres 7 jours basées sur ventes POS (pas uniquement factures).</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">D-03</td>
                <td>Colonne alertes</td>
                <td>Avoir stock faible et/ou lots proches</td>
                <td>Alertes listées avec liens vers stock / lots.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">D-04</td>
                <td>Table stock faible</td>
                <td>Articles sous seuil</td>
                <td>Statut Faible/Critique + lien Commander si Achats actif.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">D-05</td>
                <td>Ventes récentes</td>
                <td>Après au moins 1 vente</td>
                <td>Liste des dernières ventes cliquables.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">D-06</td>
                <td>Actions rapides</td>
                <td>Cliquer Nouvelle vente, Ordonnances, Lots, Stock, Caisse</td>
                <td>Chaque bouton mène à la bonne page.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">D-07</td>
                <td>Hub Pharmacie</td>
                <td>Ouvrir Hub Pharmacie</td>
                <td>Grille d’accès rapide aux modules métier pharmacie.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 3 ========== --}}
    <h2>3. Configuration &amp; branding</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">C-01</td>
                <td>Nom boutique</td>
                <td>Configuration → modifier nom magasin → enregistrer</td>
                <td>Nom mis à jour (header / docs selon config).</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">C-02</td>
                <td>Message bienvenue login</td>
                <td>Modifier message de bienvenue</td>
                <td>Valeur sauvegardée (utilisée si affichée côté config boutique).</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">C-03</td>
                <td>Upload logo principal</td>
                <td>Branding / Configuration → upload logo → reload</td>
                <td>Logo visible dans le header, taille confortable (~48px). Fichier servi via <code>/storage/...</code>.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">C-04</td>
                <td>Upload icône</td>
                <td>Upload logo icon</td>
                <td>Icône prioritaire dans le header si définie.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">C-05</td>
                <td>Infos légales documents</td>
                <td>Renseigner adresse, téléphone, NIF/RCCM, pied de facture</td>
                <td>Valeurs sauvegardées ; apparaissent sur impressions si module doc actif.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">C-06</td>
                <td>Devise</td>
                <td>Vérifier devise (XOF)</td>
                <td>Montants dashboard / ventes en FCFA (ou devise configurée).</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 4 ========== --}}
    <h2>4. Utilisateurs, rôles &amp; permissions</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">U-01</td>
                <td>Créer utilisateur</td>
                <td>Utilisateurs → nouveau → email, mot de passe, rôle</td>
                <td>Utilisateur créé ; peut se connecter.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">U-02</td>
                <td>Modifier utilisateur</td>
                <td>Changer nom / rôle</td>
                <td>Modifications persistées.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">U-03</td>
                <td>Rôles pharmacie</td>
                <td>Vérifier présence rôles (pharmacien, caissier, magasinier, admin…)</td>
                <td>Rôles Hub/Pharma disponibles avec permissions cohérentes.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">U-04</td>
                <td>Restriction caissier</td>
                <td>Se connecter en caissier</td>
                <td>UI caisse / ventes limitée ; pas d’accès admin modules interdits.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">U-05</td>
                <td>Permission refusée</td>
                <td>Utilisateur sans <code>sales.create</code> tente nouvelle vente</td>
                <td>Accès bloqué / bouton absent.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">U-06</td>
                <td>Désactiver / supprimer user</td>
                <td>Désactiver un compte puis tenter login</td>
                <td>Connexion impossible.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 5 ========== --}}
    <h2>5. Médicaments (catalogue)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">M-01</td>
                <td>Liste médicaments</td>
                <td>Menu Médicaments</td>
                <td>Liste / recherche / pagination OK. Module Articles ERP non affiché en doublon.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">M-02</td>
                <td>Créer médicament complet</td>
                <td>Nouveau : nom, SKU, prix, coût, DCI, dosage, forme, famille, fabricant, suivi lot ON, ordonnance ON/OFF</td>
                <td>Fiche créée ; formulaire pharmacie (pas commerce générique).</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">M-03</td>
                <td>Champs requis pharmacie</td>
                <td>Soumettre sans DCI / dosage / forme</td>
                <td>Validation refuse l’enregistrement.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">M-04</td>
                <td>Médicament sans suivi lot</td>
                <td>Créer avec batch_tracked OFF</td>
                <td>Ventes/stock globaux sans lot obligatoire.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">M-05</td>
                <td>Médicament sur ordonnance</td>
                <td>Cocher requires_prescription</td>
                <td>Métadonnée enregistrée ; vente bloquée sans Rx.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">M-06</td>
                <td>Modifier / fiche détail</td>
                <td>Éditer prix, voir show</td>
                <td>Infos pharma affichées (DCI, lot, Rx…).</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">M-07</td>
                <td>Unités &amp; conversion</td>
                <td>Configurer unité boîte / unité unitaire si applicable</td>
                <td>Conversion prise en compte à la vente.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">M-08</td>
                <td>Set / kit (si utilisé)</td>
                <td>Créer set de composants</td>
                <td>Vente décompose le stock des composants.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">M-09</td>
                <td>Recherche catalogue</td>
                <td>Rechercher par nom / SKU / DCI</td>
                <td>Résultats pertinents.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 6 ========== --}}
    <h2>6. Clients</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">CL-01</td>
                <td>Créer client</td>
                <td>Clients → nouveau (nom, téléphone, email)</td>
                <td>Client créé et listé.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CL-02</td>
                <td>Limite de crédit</td>
                <td>Définir credit_limit</td>
                <td>Sauvegardé ; utilisé à la vente crédit.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CL-03</td>
                <td>Fiche / 360°</td>
                <td>Ouvrir fiche client et vue 360 si dispo</td>
                <td>Historique / infos cohérents.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CL-04</td>
                <td>Doublons</td>
                <td>Ouvrir détection doublons si dispo</td>
                <td>Liste ou message clair.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CL-05</td>
                <td>Recherche client à la caisse</td>
                <td>POS → chercher client</td>
                <td>Sélection possible dans le panier.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 7 ========== --}}
    <h2>7. Fournisseurs</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">F-01</td>
                <td>Créer fournisseur</td>
                <td>Fournisseurs → nouveau</td>
                <td>Fiche créée.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">F-02</td>
                <td>Modifier / contacts</td>
                <td>Ajouter contact, conditions paiement</td>
                <td>Données persistées.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">F-03</td>
                <td>Utiliser sur commande</td>
                <td>Choisir le fournisseur à l’achat</td>
                <td>Fournisseur lié à la commande.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 8 ========== --}}
    <h2>8. Achats &amp; réception avec lots</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">P-01</td>
                <td>Créer commande brouillon</td>
                <td>Achats → nouvelle commande → lignes médicaments → prix</td>
                <td>Commande draft avec totaux.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">P-02</td>
                <td>Confirmer commande</td>
                <td>Passer statut confirmé / prêt réception</td>
                <td>Statut mis à jour ; réception possible.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">P-03</td>
                <td>Réception partielle</td>
                <td>Recevoir une partie des quantités</td>
                <td>Reste à recevoir &gt; 0 ; statut partiel.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">P-04</td>
                <td>Réception lot obligatoire</td>
                <td>Médicament batch_tracked : recevoir SANS n° lot / péremption</td>
                <td>Erreur : lot + date obligatoires.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">P-05</td>
                <td>Réception lot OK</td>
                <td>Saisir n° lot + date péremption future + qty</td>
                <td>Lot créé/augmenté ; stock global ↑ ; bon de réception numéroté.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">P-06</td>
                <td>Réception article non lotifié</td>
                <td>Article sans batch_tracked</td>
                <td>Stock ↑ sans champs lot.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">P-07</td>
                <td>Réception complète</td>
                <td>Recevoir le reste</td>
                <td>Commande statut reçue / complète.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">P-08</td>
                <td>Historique prix achat</td>
                <td>Vérifier historique prix si UI dispo</td>
                <td>Prix d’achat tracé.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">P-09</td>
                <td>Annulation / modification limitée</td>
                <td>Tenter modifier après réceptions</td>
                <td>Règles métier respectées (message clair).</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 9 ========== --}}
    <h2>9. Achats étrangers (si module actif)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">PF-01</td>
                <td>Module off</td>
                <td>Vérifier absence menu si désactivé</td>
                <td>Pas d’entrée sidebar.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">PF-02</td>
                <td>Créer + réceptionner (si on)</td>
                <td>Parcours commande étrangère + réception</td>
                <td>Flux cohérent sans casser achats locaux.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 10 ========== --}}
    <h2>10. Stock</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">S-01</td>
                <td>Niveaux stock</td>
                <td>Stock → liste</td>
                <td>Quantités alignées après réceptions.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">S-02</td>
                <td>Seuil réappro</td>
                <td>Définir reorder_point bas</td>
                <td>Article apparaît stock faible dashboard.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">S-03</td>
                <td>Ajustement entrée</td>
                <td>Ajustement +qty</td>
                <td>Stock ↑ ; mouvement tracé.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">S-04</td>
                <td>Ajustement sortie</td>
                <td>Ajustement −qty</td>
                <td>Stock ↓ ; pas de quantité négative illégale.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">S-05</td>
                <td>Cohérence lots ↔ stock</td>
                <td>Pour un batch_tracked : somme lots ≈ stock disponible</td>
                <td>Écart nul ou expliqué.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 11 ========== --}}
    <h2>11. Inventaire (si module actif)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">I-01</td>
                <td>Lancer inventaire</td>
                <td>Créer session inventaire</td>
                <td>Session ouverte.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">I-02</td>
                <td>Saisir comptage</td>
                <td>Entrer quantités comptées</td>
                <td>Écarts calculés.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">I-03</td>
                <td>Valider inventaire</td>
                <td>Confirmer</td>
                <td>Stock ajusté selon règles module.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 12 ========== --}}
    <h2>12. Lots &amp; péremption</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">L-01</td>
                <td>Liste lots</td>
                <td>Lots / Péremption</td>
                <td>Lots avec n°, dates, quantités.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">L-02</td>
                <td>Filtrer proches / périmés</td>
                <td>Observer lots &lt; 90 j et périmés</td>
                <td>Visibles ; dashboard alerte cohérente.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">L-03</td>
                <td>Création manuelle lot (si UI)</td>
                <td>Créer lot via formulaire batches</td>
                <td>Lot enregistré.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">L-04</td>
                <td>FEFO lecture</td>
                <td>Deux lots dates différentes, qty &gt; 0</td>
                <td>À la vente, le plus tôt périmé est consommé en premier.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 13 ========== --}}
    <h2>13. Ordonnances</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">RX-01</td>
                <td>Créer ordonnance</td>
                <td>Patient, médecin, lignes médicaments + quantités + instructions</td>
                <td>Rx active numérotée.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RX-02</td>
                <td>Dates de validité</td>
                <td>Définir valid_from / valid_until</td>
                <td>Sauvegardées.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RX-03</td>
                <td>Modifier Rx</td>
                <td>Ajouter/retirer ligne</td>
                <td>Mise à jour OK.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RX-04</td>
                <td>Liste / filtres statut</td>
                <td>Filtrer active / dispensed</td>
                <td>Filtres fonctionnels.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RX-05</td>
                <td>Dispensation partielle via vente</td>
                <td>Vendre une partie des qtés liées à la Rx</td>
                <td>quantity_dispensed ↑ ; statut encore active si reste.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RX-06</td>
                <td>Dispensation totale</td>
                <td>Vendre le reste</td>
                <td>Statut dispensed.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RX-07</td>
                <td>Annuler / expirer</td>
                <td>Passer statut cancelled ou expired</td>
                <td>Statut respecté ; ne plus proposer en active si filtré.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 14 ========== --}}
    <h2>14. Caisse</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">CA-01</td>
                <td>Ouvrir session</td>
                <td>Caisse → ouvrir avec fond de caisse</td>
                <td>Session ouverte ; dashboard « Ouverte ».</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CA-02</td>
                <td>Vente espèces impacte caisse</td>
                <td>Vente cash</td>
                <td>Solde caisse ↑ ; mouvement lié à la vente.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CA-03</td>
                <td>Mobile money / non-cash</td>
                <td>Vente OM/MTN</td>
                <td>Selon règles : pas nécessairement tiroir cash.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CA-04</td>
                <td>Mouvement manuel</td>
                <td>Entrée / sortie caisse si UI</td>
                <td>Solde recalculé.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CA-05</td>
                <td>Fermer caisse</td>
                <td>Clôture session</td>
                <td>Session fermée ; nouvelle ouverture requise.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CA-06</td>
                <td>Vente cash sans session</td>
                <td>Fermer caisse puis vendre cash</td>
                <td>Avertissement ou non-posting caisse (comportement documenté).</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 15 ========== --}}
    <h2>15. Ventes POS (cœur PMS)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">V-01</td>
                <td>Nouvelle vente simple</td>
                <td>Ajouter médicament non-Rx, payer cash exact</td>
                <td>Vente enregistrée ; stock/lot ↓ ; ticket/numéro VTE-…</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-02</td>
                <td>Recherche article POS</td>
                <td>Chercher par nom/SKU</td>
                <td>Ajout au panier.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-03</td>
                <td>Quantités &amp; totaux</td>
                <td>Modifier qty, vérifier line_total / total</td>
                <td>Calculs corrects (remise si testée).</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-04</td>
                <td>Remise montant / %</td>
                <td>Appliquer remise</td>
                <td>Total dû mis à jour.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-05</td>
                <td>Paiement multi-modes</td>
                <td>Cash + mobile money = total</td>
                <td>Accepté si somme = total.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-06</td>
                <td>Paiement incorrect</td>
                <td>Somme ≠ total</td>
                <td>Erreur, vente non enregistrée.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-07</td>
                <td>Crédit client OK</td>
                <td>Partie crédit + client avec limite</td>
                <td>Solde client ↑ ; vente OK.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-08</td>
                <td>Crédit sans client</td>
                <td>Crédit sans sélection client</td>
                <td>Erreur.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-09</td>
                <td>Dépassement limite crédit</td>
                <td>Crédit &gt; limite</td>
                <td>Erreur limite.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-10</td>
                <td>Rx obligatoire</td>
                <td>Panier avec médicament requires_prescription, sans ordonnance</td>
                <td>Blocage validation.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-11</td>
                <td>Rx liée</td>
                <td>Sélectionner ordonnance active puis valider</td>
                <td>Vente OK ; prescription_id sur vente ; dispensation maj.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-12</td>
                <td>FEFO multi-lots</td>
                <td>Vendre qty couvrant 2 lots</td>
                <td>Lignes découpées avec batch_id différents ; lots ↓ correctement.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-13</td>
                <td>Refus stock lot insuffisant / périmé</td>
                <td>Tenter vendre plus que stock non périmé</td>
                <td>Erreur stock lot ; vente annulée (transaction).</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-14</td>
                <td>Panier vide</td>
                <td>Valider sans lignes</td>
                <td>Erreur panier vide.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-15</td>
                <td>Suspendre / reprendre (si UI)</td>
                <td>Suspendre vente puis reprendre</td>
                <td>Panier restauré.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-16</td>
                <td>Détail vente</td>
                <td>Ouvrir vente créée</td>
                <td>Lignes, paiements, client, Rx affichés.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-17</td>
                <td>Impression ticket / PDF</td>
                <td>Imprimer si dispo</td>
                <td>Document généré avec branding.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">V-18</td>
                <td>Liste &amp; filtres ventes</td>
                <td>Filtrer par date / client</td>
                <td>Résultats cohérents.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 16 ========== --}}
    <h2>16. Retours de vente</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">VR-01</td>
                <td>Retour partiel avec batch_id</td>
                <td>Retourner une ligne issue d’un lot</td>
                <td>Lot restauré (restoreToBatch) ; stock ↑.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">VR-02</td>
                <td>Retour total</td>
                <td>Retourner toute la vente</td>
                <td>Vente marquée retournée / soldes cohérents.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">VR-03</td>
                <td>Remboursement</td>
                <td>Choisir mode remboursement</td>
                <td>Mouvement financier / caisse selon règles.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">VR-04</td>
                <td>Permission retour</td>
                <td>User sans droit retour</td>
                <td>Action impossible.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">VR-05</td>
                <td>Double retour impossible</td>
                <td>Retenter retour sur qty déjà retournée</td>
                <td>Refus.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 17 ========== --}}
    <h2>17. Dettes / crédit client</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">DE-01</td>
                <td>Voir dettes</td>
                <td>Module Dettes après ventes crédit</td>
                <td>Soldes clients visibles.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">DE-02</td>
                <td>Encaisser dette</td>
                <td>Enregistrer paiement partiel/total</td>
                <td>Solde ↓ ; historique.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 18 ========== --}}
    <h2>18. Dépenses &amp; pertes (si actifs)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">XP-01</td>
                <td>Créer dépense</td>
                <td>Saisir montant, catégorie, statut</td>
                <td>Enregistrée ; visible reporting si droits.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">XP-02</td>
                <td>Approuver / payer</td>
                <td>Workflow statut</td>
                <td>Statuts respectés.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">LO-01</td>
                <td>Enregistrer perte</td>
                <td>Perte confirmée sur article/lot</td>
                <td>Valeur tracée ; stock impacté si prévu.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 19 ========== --}}
    <h2>19. Devis, réservations, prospects, CRM (si actifs)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">Q-01</td>
                <td>Créer devis</td>
                <td>Lignes + client → enregistrer</td>
                <td>Devis numéroté.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">Q-02</td>
                <td>Statuts devis</td>
                <td>Envoyer / accepter / rejeter</td>
                <td>Statuts OK ; impression si dispo.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">R-01</td>
                <td>Réservation</td>
                <td>Créer / confirmer / annuler</td>
                <td>Cycle de vie OK.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">PR-01</td>
                <td>Prospect</td>
                <td>Créer prospect</td>
                <td>Fiche créée.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">CRM-01</td>
                <td>CRM KPI / activités</td>
                <td>Parcourir sous-menus CRM</td>
                <td>Pages accessibles sans erreur.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 20 ========== --}}
    <h2>20. Facturation &amp; paiements factures (si actifs)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">FA-01</td>
                <td>Créer facture</td>
                <td>Client + lignes → émettre</td>
                <td>Facture issued ; totaux HT/TTC cohérents.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">FA-02</td>
                <td>Paiement partiel</td>
                <td>Payer une partie</td>
                <td>Statut partial ; balance ↓.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">FA-03</td>
                <td>Paiement total</td>
                <td>Soldes à 0</td>
                <td>Statut paid.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">FA-04</td>
                <td>Impression / PDF facture</td>
                <td>Générer PDF</td>
                <td>Logo + infos boutique présents.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">FA-05</td>
                <td>Annulation</td>
                <td>Annuler facture selon règles</td>
                <td>Statut cancelled ; plus d’encaissement.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 21 ========== --}}
    <h2>21. Retours &amp; avoirs (module returns)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">AV-01</td>
                <td>Créer avoir / retour facture</td>
                <td>Depuis facture ou module returns</td>
                <td>Document créé ; impact stock/finance selon type.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">AV-02</td>
                <td>Valider workflow</td>
                <td>Confirmer retour</td>
                <td>Statut final cohérent.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 22 ========== --}}
    <h2>22. Rapports</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">RP-01</td>
                <td>Accès reporting</td>
                <td>User avec reporting.view</td>
                <td>Page rapports ouverte.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RP-02</td>
                <td>Rapport ventes période</td>
                <td>Filtrer dates</td>
                <td>Totaux ≈ ventes POS / factures selon onglet.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RP-03</td>
                <td>Rapport stock / achats</td>
                <td>Ouvrir onglets dispo</td>
                <td>Données affichées sans erreur.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RP-04</td>
                <td>Export</td>
                <td>Exporter si bouton présent</td>
                <td>Fichier généré.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RP-05</td>
                <td>Sans permission</td>
                <td>User sans reporting.view</td>
                <td>Accès refusé.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 23 ========== --}}
    <h2>23. RH : présence &amp; paie (si actifs)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">RH-01</td>
                <td>Pointage</td>
                <td>Widget présence / entrée-sortie</td>
                <td>Pointage enregistré.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RH-02</td>
                <td>Feuille présence</td>
                <td>Consulter / imprimer feuille</td>
                <td>PDF ou vue OK.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">RH-03</td>
                <td>Paie</td>
                <td>Créer période / bulletin si module on</td>
                <td>Flux sans erreur critique.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 24 ========== --}}
    <h2>24. Tickets (si actif)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">TK-01</td>
                <td>Créer ticket</td>
                <td>Ouvrir ticket support interne</td>
                <td>Ticket créé avec statut.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">TK-02</td>
                <td>Changer statut</td>
                <td>En cours → résolu</td>
                <td>Historique à jour.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 25 ========== --}}
    <h2>25. Abonnement tenant</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">AB-01</td>
                <td>Page abonnement</td>
                <td>Ouvrir /app/subscription?tenant=…</td>
                <td>Statut plan / modules visibles (lecture tenant).</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 26 ========== --}}
    <h2>26. Multi-boutiques (si activé)</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">MS-01</td>
                <td>Sélecteur magasin</td>
                <td>Changer boutique dans header</td>
                <td>Contexte stock/ventes filtré.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">MS-02</td>
                <td>Vue toutes boutiques</td>
                <td>Choisir « Toutes » si droit</td>
                <td>Agrégats cohérents.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">MS-03</td>
                <td>Isolation stock</td>
                <td>Réception / vente sur magasin A</td>
                <td>Magasin B non impacté.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 27 E2E ========== --}}
    <h2>27. Parcours bout-en-bout (obligatoires)</h2>
    <div class="note">Exécuter ces scénarios dans l’ordre : ils valident l’intégration PMS réelle.</div>

    <h3>E2E-01 — Cycle médicament lotifié</h3>
    <ol>
        <li>Créer médicament batch_tracked + prix.</li>
        <li>Créer fournisseur + commande d’achat (qty 100).</li>
        <li>Réceptionner 60 avec lot L1 péremption J+60, puis 40 avec lot L2 péremption J+200.</li>
        <li>Vérifier 2 lots et stock 100.</li>
        <li>Ouvrir caisse.</li>
        <li>Vendre 70 unités (doit consommer L1 puis L2) — contrôler batch_id sur lignes.</li>
        <li>Retourner 10 unités — lot d’origine rechargé.</li>
        <li>Dashboard : ventes jour, alertes péremption L1 si proche.</li>
    </ol>
    <p><strong>OK E2E-01 :</strong> ☐ &nbsp;&nbsp; Notes : _______________________________</p>

    <h3>E2E-02 — Ordonnance + dispensation</h3>
    <ol>
        <li>Créer médicament requires_prescription.</li>
        <li>Réceptionner stock avec lot.</li>
        <li>Créer client + ordonnance 20 unités.</li>
        <li>Tenter vente sans Rx → refus.</li>
        <li>Vente 12 avec Rx liée → dispensé 12, reste 8, Rx active.</li>
        <li>Vente 8 → Rx dispensed.</li>
    </ol>
    <p><strong>OK E2E-02 :</strong> ☐ &nbsp;&nbsp; Notes : _______________________________</p>

    <h3>E2E-03 — Crédit + dette + encaissement</h3>
    <ol>
        <li>Client limite 50 000.</li>
        <li>Vente 30 000 dont 20 000 crédit.</li>
        <li>Vérifier solde client / dettes.</li>
        <li>Encaisser 20 000 → solde 0.</li>
        <li>Tenter crédit 60 000 → refus limite.</li>
    </ol>
    <p><strong>OK E2E-03 :</strong> ☐ &nbsp;&nbsp; Notes : _______________________________</p>

    <h3>E2E-04 — Caisse journée</h3>
    <ol>
        <li>Ouvrir caisse fond 10 000.</li>
        <li>3 ventes cash + 1 mobile money + 1 crédit.</li>
        <li>Contrôler solde caisse (cash only selon règles).</li>
        <li>Fermer caisse.</li>
        <li>Tenter vente cash → comportement attendu (warning / blocage posting).</li>
    </ol>
    <p><strong>OK E2E-04 :</strong> ☐ &nbsp;&nbsp; Notes : _______________________________</p>

    <h3>E2E-05 — Stock faible → réappro</h3>
    <ol>
        <li>Mettre seuil haut / stock bas.</li>
        <li>Dashboard alerte + table Critique/Faible.</li>
        <li>Commander via Achats depuis le flux.</li>
        <li>Réceptionner → alerte disparaît.</li>
    </ol>
    <p><strong>OK E2E-05 :</strong> ☐ &nbsp;&nbsp; Notes : _______________________________</p>

    <h3>E2E-06 — Utilisateur restreint</h3>
    <ol>
        <li>Créer caissier permissions ventes/caisse seulement.</li>
        <li>Vérifier menus absents (paie, config critique, etc.).</li>
        <li>Peut encaisser ; ne peut pas gérer users.</li>
    </ol>
    <p><strong>OK E2E-06 :</strong> ☐ &nbsp;&nbsp; Notes : _______________________________</p>

    {{-- ========== 28 ========== --}}
    <h2>28. Non-régression &amp; indépendance modules</h2>
    <table>
        <thead><tr><th class="id">ID</th><th>Cas</th><th>Étapes</th><th>Résultat attendu</th><th class="check">OK</th></tr></thead>
        <tbody>
            <tr>
                <td class="id">NR-01</td>
                <td>Ventes sans module Rx</td>
                <td>Désactiver prescriptions (tenant test) ; vendre article non-Rx</td>
                <td>POS fonctionne ; pas de plantage PrescriptionsApi.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">NR-02</td>
                <td>Ventes sans lots</td>
                <td>Article non batch_tracked</td>
                <td>Déstockage stock classique OK.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">NR-03</td>
                <td>Module optionnel off</td>
                <td>Désactiver inventaire / CRM / paie</td>
                <td>Menus absents ; app stable.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">NR-04</td>
                <td>Médicaments vs Articles</td>
                <td>Tenant pharma</td>
                <td>Sidebar Médicaments ; pas de double catalogue Articles.</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">NR-05</td>
                <td>Storage logo</td>
                <td>Après upload, hard refresh</td>
                <td>Logo toujours visible (lien storage OK).</td>
                <td class="check">☐</td>
            </tr>
            <tr>
                <td class="id">NR-06</td>
                <td>Pas de fuite admin</td>
                <td>Chercher routes system.*</td>
                <td>Inexistantes sur Pharma.</td>
                <td class="check">☐</td>
            </tr>
        </tbody>
    </table>

    {{-- ========== 29 ========== --}}
    <h2>29. Matrice rôles pharmacie (smoke)</h2>
    <table>
        <thead>
            <tr>
                <th>Capacité</th>
                <th>Admin</th>
                <th>Pharmacien</th>
                <th>Caissier</th>
                <th>Magasinier</th>
                <th class="check">OK</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Tableau de bord</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
            <tr><td>Médicaments CRUD</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
            <tr><td>Ventes POS</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
            <tr><td>Ordonnances</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
            <tr><td>Lots / réception</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
            <tr><td>Achats</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
            <tr><td>Caisse ouvrir/fermer</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
            <tr><td>Utilisateurs</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
            <tr><td>Configuration</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
            <tr><td>Rapports</td><td>☐</td><td>☐</td><td>☐</td><td>☐</td><td class="check">☐</td></tr>
        </tbody>
    </table>
    <p class="note">Cochez selon le comportement réel observé (autorisé / refusé). Ajustez les permissions si un rôle est trop permissif ou trop strict.</p>

    <h2>Synthèse de recette</h2>
    <table>
        <thead><tr><th>Bloc</th><th>Nb cas approx.</th><th>Passés</th><th>Échoués</th><th>Bloqués</th></tr></thead>
        <tbody>
            <tr><td>Accès / Dashboard / Config / Users</td><td>~30</td><td></td><td></td><td></td></tr>
            <tr><td>Catalogue / Clients / Fournisseurs</td><td>~20</td><td></td><td></td><td></td></tr>
            <tr><td>Achats / Stock / Lots / Inventaire</td><td>~30</td><td></td><td></td><td></td></tr>
            <tr><td>Rx / Caisse / Ventes / Retours</td><td>~45</td><td></td><td></td><td></td></tr>
            <tr><td>Finance / CRM / RH / Tickets / Reports</td><td>~35</td><td></td><td></td><td></td></tr>
            <tr><td>E2E + non-régression + rôles</td><td>~20</td><td></td><td></td><td></td></tr>
            <tr><td><strong>Total</strong></td><td><strong>~180</strong></td><td></td><td></td><td></td></tr>
        </tbody>
    </table>

    <p><strong>Recette validée le :</strong> ____ / ____ / ________ &nbsp;&nbsp; <strong>Par :</strong> ________________</p>
    <p><strong>Version testée :</strong> ________________ &nbsp;&nbsp; <strong>Environnement :</strong> local / staging / prod</p>

    <div class="footer-note">
        {{ $appName }} — Cahier de cas de test complet · {{ $generatedAt }} · Régénérer : <code>php artisan docs:pharma-test-cases-pdf</code>
    </div>
</body>
</html>
