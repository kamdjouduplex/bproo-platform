<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Guide Super Admin — Inov-Com ERP</title>
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
            padding: 60px 30px 40px;
            border-bottom: 4px solid #16a34a;
            margin-bottom: 30px;
        }
        .cover h1 {
            font-size: 26pt;
            color: #0f172a;
            margin: 0 0 8px;
        }
        .cover .subtitle {
            font-size: 13pt;
            color: #166534;
            margin-bottom: 24px;
        }
        .cover .meta {
            font-size: 10pt;
            color: #64748b;
        }
        h2 {
            font-size: 14pt;
            color: #166534;
            margin: 26px 0 10px;
            page-break-after: avoid;
        }
        h3 {
            font-size: 11.5pt;
            color: #334155;
            margin: 16px 0 8px;
            page-break-after: avoid;
        }
        p { margin: 0 0 10px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 16px;
            font-size: 9.5pt;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f0fdf4;
            color: #166534;
            font-weight: bold;
        }
        tr:nth-child(even) td { background: #f8fafc; }
        code {
            font-family: DejaVu Sans Mono, monospace;
            background: #f1f5f9;
            font-size: 9pt;
        }
        pre {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
            font-size: 9pt;
            line-height: 1.4;
            white-space: pre-wrap;
        }
        ul, ol { margin: 6px 0 12px; padding-left: 20px; }
        li { margin-bottom: 4px; }
        .note {
            background: #f0fdf4;
            border-left: 4px solid #16a34a;
            padding: 8px 12px;
            margin: 12px 0;
            font-size: 9.5pt;
        }
        .page-break { page-break-before: always; }
        .footer {
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 9pt;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="cover">
    <h1>Guide Super Admin</h1>
    <div class="subtitle">Inov-Com ERP — Administration plateforme</div>
    <div class="meta">
        Public : administrateurs plateforme<br>
        URL : https://erp.afroinov.com/admin<br>
        Version : Juin 2026
    </div>
</div>

<h2>1. Rôle du Super Admin</h2>
<p>Le Super Admin gère la <strong>plateforme centrale</strong>. Il crée les vendeurs (boutiques), active les modules, gère les abonnements et surveille la santé technique.</p>
<table>
    <tr><th>Niveau</th><th>Qui</th><th>Accès</th></tr>
    <tr><td>Plateforme</td><td>Super Admin</td><td><code>/admin</code> — tous les vendeurs</td></tr>
    <tr><td>Vendeur</td><td>Admin boutique</td><td><code>/app?tenant=CODE</code> — une boutique</td></tr>
</table>
<p>Chaque vendeur possède un <strong>code</strong> unique, une <strong>base PostgreSQL isolée</strong> et des <strong>modules activables</strong> individuellement.</p>

<h2>2. Connexion</h2>
<table>
    <tr><th>Élément</th><th>Valeur</th></tr>
    <tr><td>URL</td><td><code>https://erp.afroinov.com/admin/login</code></td></tr>
    <tr><td>Compte initial</td><td><code>admin@demo.invo</code></td></tr>
    <tr><td>Mot de passe initial</td><td><code>password</code> — à changer immédiatement</td></tr>
</table>
<div class="note"><strong>Important :</strong> le compte Super Admin (<code>/admin</code>) est différent du compte admin boutique créé dans « Créer vendeur » (<code>/app/login?tenant=CODE</code>).</div>

<h2>3. Tableau de bord</h2>
<p><strong>Menu : Admin</strong> → <code>/admin</code></p>
<ul>
    <li>Nombre de vendeurs (total, actifs, en provisionnement, en échec)</li>
    <li>Modules enregistrés</li>
    <li>Derniers événements d'installation de modules</li>
</ul>

<h2>4. Gestion des vendeurs</h2>
<p><strong>Menu : Vendeurs</strong> → <code>/admin/tenants</code></p>

<h3>4.1 Créer un vendeur</h3>
<p><code>/admin/tenants/create</code></p>
<table>
    <tr><th>Champ</th><th>Description</th></tr>
    <tr><td>Nom du vendeur</td><td>Nom affiché (ex. Boutique Centrale)</td></tr>
    <tr><td>Code vendeur</td><td>Identifiant court sans espace (ex. demo, itc)</td></tr>
    <tr><td>Type d'activité</td><td>retail, pharmacie, boulangerie, restaurant, autre</td></tr>
    <tr><td>Contact clé</td><td>Nom, téléphone, adresse (optionnel)</td></tr>
    <tr><td>Admin boutique</td><td>Nom, e-mail, mot de passe du premier utilisateur</td></tr>
    <tr><td>Multi-magasin</td><td>Cocher si plusieurs points de vente</td></tr>
</table>
<p>La base PostgreSQL est créée <strong>automatiquement</strong> (<code>erp_{code}_xxxx</code>). Ne pas remplir les champs DB.</p>
<ol>
    <li>Remplir le formulaire et <strong>Enregistrer</strong></li>
    <li>Ouvrir <strong>Santé vendeurs</strong> — attendre statut <strong>OK</strong> (1–2 min)</li>
    <li>En cas d'échec → bouton <strong>Relancer</strong></li>
    <li><strong>Packages</strong> → installer les modules</li>
    <li>Communiquer URL : <code>https://erp.afroinov.com/app/login?tenant=CODE</code></li>
</ol>

<h3>4.2 Modifier / Paramètres / Supprimer</h3>
<ul>
    <li><strong>Édition</strong> : <code>/admin/tenants/{code}/edit</code></li>
    <li><strong>Paramètres</strong> : devise, TVA, préfixe factures, multi-magasin</li>
    <li><strong>Supprimer</strong> : supprime l'enregistrement plateforme (la base PostgreSQL reste sur le serveur)</li>
</ul>

<div class="page-break"></div>

<h2>5. Santé et provisionnement</h2>
<p><strong>Menu : Santé</strong> → <code>/admin/tenants/health</code></p>
<table>
    <tr><th>Statut</th><th>Signification</th><th>Action</th></tr>
    <tr><td>OK</td><td>Provisionnement terminé</td><td>Aucune</td></tr>
    <tr><td>En cours</td><td>Job en file d'attente</td><td>Attendre, Rafraîchir</td></tr>
    <tr><td>Erreur</td><td>Échec DB ou migrations</td><td>Lire message → <strong>Relancer</strong></td></tr>
</table>

<h2>6. Packages (modules)</h2>
<p><strong>Menu : Packages</strong> → <code>/admin/packages</code></p>
<ol>
    <li>Sélectionner le <strong>vendeur</strong> (obligatoire)</li>
    <li>Cliquer <strong>Installer</strong> ou <strong>Désinstaller</strong></li>
    <li><strong>Pour tous</strong> : installe pour tous les vendeurs</li>
    <li><strong>Synchroniser</strong> : met à jour le catalogue</li>
    <li><strong>Débloquer</strong> : efface un état « En cours… » bloqué</li>
</ol>
<div class="note">Un seul module par <strong>famille</strong> peut être actif (ex. une variante Ventes).</div>

<h2>7. Plans d'abonnement</h2>
<p><strong>Menu : Plans</strong> → <code>/admin/plans</code></p>
<ul>
    <li>Créer offres (nom, prix FCFA, intervalle)</li>
    <li>Plan <strong>Démo</strong> : jamais suspendu automatiquement</li>
</ul>

<h2>8. Abonnements vendeurs</h2>
<p><code>/admin/tenants/{code}/subscription</code></p>
<ul>
    <li>Enregistrer un paiement</li>
    <li>Appliquer le solde créditeur</li>
    <li>Changer de plan</li>
</ul>
<p>Si abonnement expiré : les utilisateurs vendeur sont redirigés vers <code>/app/subscription?tenant=CODE</code>.</p>

<h2>9. Multi-magasin</h2>
<ul>
    <li>Activer à la création du vendeur ou via Paramètres vendeur</li>
    <li>L'admin boutique configure les boutiques dans Configuration</li>
</ul>

<h2>10. Registre et événements</h2>
<ul>
    <li><strong>Modules</strong> (<code>/admin/modules</code>) : catalogue technique</li>
    <li><strong>Events</strong> (<code>/admin/module-events</code>) : historique + export</li>
</ul>

<h2>11. Ordre d'activation recommandé</h2>
<pre>Commerce détail :
Articles → Stock → Clients → Ventes → Caisse
→ Fournisseurs → Achats
→ Devis → Facturation → Paiements factures
→ Dépenses → Rapports

Pharmacie :
Articles → Stock → Lots → Ordonnances → Ventes</pre>

<h2>12. Mise à jour serveur</h2>
<pre>cd ~/apps/erp
COMPOSE_PROJECT_NAME=erp TENANT_CODE=demo bash deploy/docker/deploy-update.sh</pre>
<p>Guide serveur complet : <code>deploy/docker/DEPLOY.md</code></p>

<h2>13. Dépannage Super Admin</h2>
<table>
    <tr><th>Problème</th><th>Solution</th></tr>
    <tr><td>Vendeur en Erreur</td><td>Santé → Relancer ; vérifier DB_PROVISION sur serveur</td></tr>
    <tr><td>Module absent côté vendeur</td><td>Packages → Installer pour le bon vendeur</td></tr>
    <tr><td>Client ne se connecte pas</td><td>Vérifier abonnement ; URL avec ?tenant=CODE</td></tr>
    <tr><td>502 site</td><td>Voir DEPLOY.md section Dépannage</td></tr>
</table>

<h3>URLs utiles (production)</h3>
<table>
    <tr><th>Page</th><th>URL</th></tr>
    <tr><td>Admin login</td><td>https://erp.afroinov.com/admin/login</td></tr>
    <tr><td>Vendeurs</td><td>https://erp.afroinov.com/admin/tenants</td></tr>
    <tr><td>Packages</td><td>https://erp.afroinov.com/admin/packages</td></tr>
    <tr><td>Santé</td><td>https://erp.afroinov.com/admin/tenants/health</td></tr>
    <tr><td>Login vendeur</td><td>https://erp.afroinov.com/app/login?tenant=CODE</td></tr>
</table>

<div class="footer">
    Inov-Com ERP — Guide Super Admin — Document généré depuis la version courante du système.
</div>

</body>
</html>
