<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Manuel — Nouvelles fonctionnalités</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            line-height: 1.45;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .cover {
            text-align: center;
            padding: 50px 30px 36px;
            border-bottom: 4px solid #2563eb;
            margin-bottom: 28px;
        }
        .cover h1 {
            font-size: 22pt;
            color: #0f172a;
            margin: 0 0 8px;
        }
        .cover .subtitle {
            font-size: 12pt;
            color: #1d4ed8;
            margin-bottom: 20px;
        }
        .cover .meta {
            font-size: 9.5pt;
            color: #64748b;
        }
        h2 {
            font-size: 13pt;
            color: #1d4ed8;
            margin: 22px 0 8px;
            page-break-after: avoid;
        }
        h3 {
            font-size: 11pt;
            color: #334155;
            margin: 14px 0 6px;
            page-break-after: avoid;
        }
        p { margin: 0 0 8px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 14px;
            font-size: 9.5pt;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #eff6ff;
            color: #1e40af;
            font-weight: bold;
        }
        tr:nth-child(even) td { background: #f8fafc; }
        code {
            font-family: DejaVu Sans Mono, monospace;
            background: #f1f5f9;
            font-size: 9pt;
        }
        ol, ul { margin: 6px 0 12px; padding-left: 20px; }
        li { margin-bottom: 4px; }
        .note {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 8px 12px;
            margin: 10px 0;
            font-size: 9.5pt;
        }
        .step {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            margin: 8px 0;
            font-size: 9.5pt;
        }
        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 8.5pt;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="cover">
    <h1>Nouvelles fonctionnalités</h1>
    <div class="subtitle">BprooDev ERP / Inov-Com — Guide utilisateur</div>
    <div class="meta">
        Mise à jour : juin 2026<br>
        Modules concernés : Tableau de bord, Clients, Achats étrangers, Facturation
    </div>
</div>

<h2>1. Tableau de bord</h2>
<p>Les indicateurs du tableau de bord ont été simplifiés pour suivre l’activité <strong>facturation</strong> du mois en cours.</p>

<table>
    <tr><th>Indicateur</th><th>Signification</th></tr>
    <tr>
        <td><strong>CA facture ce mois</strong></td>
        <td>Montant HT net des factures émises, partiellement payées ou payées ce mois (hors taxes reversées).</td>
    </tr>
    <tr>
        <td><strong>CA encaissé ce mois</strong></td>
        <td>Part HT des paiements enregistrés sur factures ce mois (au prorata de chaque facture).</td>
    </tr>
    <tr>
        <td><strong>Factures à encaisser</strong></td>
        <td>Nombre de factures émises ou partiellement payées, avec le solde restant en FCFA.</td>
    </tr>
    <tr>
        <td><strong>Dépenses du mois</strong></td>
        <td>Total des dépenses approuvées ou payées du mois (visible si droit « Rapports »).</td>
    </tr>
</table>

<div class="note">
    <strong>Accès :</strong> menu principal → <strong>Tableau de bord</strong> après connexion à votre boutique (<code>/app/login?tenant=VOTRE_CODE</code>).
</div>

<p>Les anciens blocs « CA du jour », « Marge du jour » et « Caisse » ne s’affichent plus dans les KPI. Le graphique des 7 derniers jours et les dernières factures restent disponibles.</p>

<h2>2. Historique client par référence article</h2>
<p>Retrouvez en un clic tous les documents d’un client contenant une <strong>référence produit (SKU)</strong> : devis, factures, bons de livraison et ventes caisse.</p>

<h3>Comment faire</h3>
<ol>
    <li>Menu <strong>Clients</strong> → ouvrir un client → <strong>Voir</strong>.</li>
    <li>Section <strong>Vue 360° — Historique &amp; relances</strong>.</li>
    <li>Onglet <strong>Par référence produit</strong>.</li>
    <li>Saisir la référence exacte (ex. <code>REF-001</code>), optionnellement une plage de dates.</li>
    <li>Cliquer <strong>Rechercher</strong>.</li>
</ol>

<div class="step">
    <strong>Résultat :</strong> tableau avec type de document, n° document, date, quantité, prix unitaire, montant et bouton <strong>Voir</strong> pour ouvrir le document.<br>
    <strong>Export :</strong> bouton <strong>Exporter Excel</strong> après une recherche.
</div>

<div class="note">
    La recherche est <strong>exacte</strong> sur le SKU (insensible à la casse). Elle porte sur le client affiché uniquement, pas sur tous les clients.
</div>

<h2>3. Achats étrangers — historique des prix en devise</h2>
<p>Lors d’un nouvel achat étranger, le système propose le <strong>dernier prix en devise</strong> déjà utilisé pour le même article (priorité : même fournisseur + même devise).</p>

<h3>Utilisation</h3>
<ol>
    <li>Menu <strong>Achats étrangers</strong> → <strong>Nouvel achat</strong>.</li>
    <li>Choisir le fournisseur et la devise (EUR, USD, etc.).</li>
    <li>Rechercher un article : si un historique existe, le dernier prix s’affiche en bleu.</li>
    <li>Ajouter l’article au panier : le prix est prérempli.</li>
    <li><strong>Modifier le prix</strong> dans la colonne « Prix unit. (devise) » si le marché a changé.</li>
    <li>Enregistrer et <strong>confirmer</strong> la commande pour alimenter l’historique.</li>
</ol>

<div class="note">
    L’historique se construit à partir des commandes <strong>confirmées</strong> après la mise à jour. Les prix FCFA du panier se recalculent automatiquement selon le taux de change saisi.
</div>

<h2>4. Liste des clients</h2>
<p>La colonne <strong>Segment</strong> a été retirée du tableau de la liste clients pour alléger l’affichage. Le filtre par segment reste disponible dans la barre d’outils si besoin.</p>

<h2>5. Rappels utiles</h2>
<table>
    <tr><th>Besoin</th><th>Où aller</th></tr>
    <tr><td>Voir les factures impayées</td><td>Tableau de bord → alerte ou module Facturation</td></tr>
    <tr><td>Encaisser une facture</td><td>Facturation → Paiements factures (si module activé)</td></tr>
    <tr><td>Imprimer un bon d’achat étranger</td><td>Achats étrangers → Voir → Imprimer</td></tr>
    <tr><td>Historique complet d’un client</td><td>Clients → Voir → onglets Devis, Factures, etc.</td></tr>
</table>

<div class="footer">
    BprooDev ERP / Inov-Com — Document généré automatiquement · {{ now()->format('d/m/Y') }}
</div>

</body>
</html>
