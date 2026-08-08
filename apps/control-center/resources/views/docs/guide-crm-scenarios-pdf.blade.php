<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Guide scénarios CRM — Bproo Control Center</title>
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
            padding: 48px 28px 36px;
            border-bottom: 4px solid #16a34a;
            margin-bottom: 26px;
        }
        .cover h1 {
            font-size: 22pt;
            color: #0f172a;
            margin: 0 0 8px;
        }
        .cover .subtitle {
            font-size: 12pt;
            color: #166534;
            margin-bottom: 18px;
        }
        .cover .meta {
            font-size: 9.5pt;
            color: #64748b;
        }
        h2 {
            font-size: 13pt;
            color: #166534;
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
            background: #f0fdf4;
            color: #166534;
            font-weight: bold;
        }
        tr:nth-child(even) td { background: #f8fafc; }
        ol, ul { margin: 6px 0 12px; padding-left: 20px; }
        li { margin-bottom: 4px; }
        .note {
            background: #f0fdf4;
            border-left: 4px solid #16a34a;
            padding: 8px 12px;
            margin: 10px 0;
            font-size: 9.5pt;
        }
        .warn {
            background: #fff7ed;
            border-left: 4px solid #ea580c;
            padding: 8px 12px;
            margin: 10px 0;
            font-size: 9.5pt;
        }
        .flow {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin: 10px 0 14px;
            font-size: 10pt;
            text-align: center;
            font-weight: bold;
            color: #0f172a;
        }
        .scenario {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            margin: 10px 0 14px;
            page-break-inside: avoid;
        }
        .scenario h3 {
            margin-top: 0;
            color: #166534;
        }
        .step-label {
            font-weight: bold;
            color: #0f172a;
        }
        .page-break { page-break-before: always; }
        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9pt;
            color: #64748b;
            text-align: center;
        }
        .menu {
            font-weight: bold;
            color: #15803d;
        }
    </style>
</head>
<body>
    <div class="cover">
        <div style="font-size:11pt;letter-spacing:0.12em;color:#64748b;margin-bottom:10px;">BPROO CONTROL CENTER</div>
        <h1>Guide scénarios CRM</h1>
        <div class="subtitle">Relation client — quand utiliser chaque écran</div>
        <div class="meta">
            Prospects · Opportunités · Clients · Activités<br>
            Mode d’emploi pratique pour acquérir et convertir des entreprises<br>
            {{ now()->format('d/m/Y') }}
        </div>
    </div>

    <h2>1. L’essentiel (30 secondes)</h2>
    <p>
        Relation client est votre tunnel commercial pour vendre les apps Bproo (ERP, Pressing, BAT…).
        Une entreprise commence en <strong>Prospect</strong>, devient une <strong>Opportunité</strong> dès qu’il y a un vrai deal,
        devient un <strong>Client</strong> à la conversion, et les <strong>Activités</strong> enregistrent chaque contact au fil du temps.
    </p>

    <div class="flow">
        Prospect (Nouveau) → Opportunité → À convertir → Client
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:18%;">Menu</th>
                <th style="width:32%;">À utiliser quand…</th>
                <th style="width:50%;">Actions typiques</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="menu">Prospects</span></td>
                <td>Vous venez de découvrir / rencontrer une entreprise. Pas encore de vente active — vous capturez l’intérêt.</td>
                <td>Nouveau · fixer un suivi · → Opportunité · marquer Perdu</td>
            </tr>
            <tr>
                <td><span class="menu">Opportunités</span></td>
                <td>Il y a un vrai deal : démo, devis, négociation, closing.</td>
                <td>Avancer · Gagné · Convertir · Perdu</td>
            </tr>
            <tr>
                <td><span class="menu">Clients</span></td>
                <td>L’entreprise existe déjà sur la plateforme (tenant actif).</td>
                <td>Ouvrir la fiche · Facturer · suivre l’abonnement</td>
            </tr>
            <tr>
                <td><span class="menu">Activités</span></td>
                <td>Vous avez besoin de l’historique : appels, notes, rendez-vous, suivis.</td>
                <td>Nouvelle activité · date de prochain suivi</td>
            </tr>
        </tbody>
    </table>

    <div class="note">
        <strong>Règle simple :</strong> pas encore d’entreprise provisionnée → Prospect ou Opportunité.
        Déjà un code entreprise sur la plateforme → Client. Activités = le carnet de bord pour les deux.
    </div>

    <h2>2. Quand utiliser quoi (table de décision)</h2>
    <table>
        <thead>
            <tr>
                <th>Situation</th>
                <th>Aller dans</th>
                <th>Faire ceci</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Quelqu’un demande une démo sur WhatsApp</td>
                <td>Prospects</td>
                <td>Nouveau → entreprise + contact → Prochain suivi = demain</td>
            </tr>
            <tr>
                <td>Budget / produit d’intérêt confirmés</td>
                <td>Prospects → Opportunités</td>
                <td>Cliquer <strong>→ Opportunité</strong> (passe en Qualifié)</td>
            </tr>
            <tr>
                <td>Proposition / devis envoyé</td>
                <td>Opportunités</td>
                <td>Cliquer <strong>Avancer</strong> jusqu’à Proposition</td>
            </tr>
            <tr>
                <td>Discussion prix / contrat</td>
                <td>Opportunités</td>
                <td>Avancer vers Négociation</td>
            </tr>
            <tr>
                <td>Ils ont dit oui — prêt à démarrer</td>
                <td>Opportunités</td>
                <td><strong>Gagné</strong> → colonne À convertir → <strong>Convertir</strong></td>
            </tr>
            <tr>
                <td>Entreprise en ligne, besoin de facturer / plan</td>
                <td>Clients</td>
                <td>Facturer sur la ligne</td>
            </tr>
            <tr>
                <td>« Qu’est-ce qu’on leur a dit la dernière fois ? »</td>
                <td>Activités ou Fiche</td>
                <td>Lire la timeline · ajouter une activité</td>
            </tr>
            <tr>
                <td>Deal mort / plus de réponse</td>
                <td>Prospects ou Opportunités</td>
                <td><strong>Perdu</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <h2>3. Étapes du pipeline (Opportunités)</h2>
    <table>
        <thead>
            <tr>
                <th>Colonne</th>
                <th>Signification</th>
                <th>Prochaine action</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Qualifié</td>
                <td>Besoin réel + bon produit identifié</td>
                <td>Avancer → Proposition</td>
            </tr>
            <tr>
                <td>Proposition</td>
                <td>Offre / démo / proposition commerciale envoyée</td>
                <td>Avancer → Négociation</td>
            </tr>
            <tr>
                <td>Négociation</td>
                <td>Prix, conditions, date de démarrage</td>
                <td>Gagné (ou Perdu)</td>
            </tr>
            <tr>
                <td>À convertir</td>
                <td>Gagné commercialement — reste à créer l’entreprise plateforme</td>
                <td><strong>Convertir</strong> (crée le Client)</td>
            </tr>
        </tbody>
    </table>

    <div class="warn">
        <strong>Important :</strong> « Gagné » seul ne crée pas l’entreprise. Il faut <strong>Convertir</strong>
        (code + email admin + mot de passe). C’est à ce moment que le Client apparaît sous Relation client → Clients.
    </div>

    <h2>4. Scénario A — Première vraie vente (à suivre une fois)</h2>
    <div class="scenario">
        <h3>Histoire : « Boulangerie Duplex » veut Pressing / ERP</h3>
        <ol>
            <li>
                <span class="step-label">Jour 1 — Capturer</span><br>
                Menu <span class="menu">Prospects</span> → <strong>Nouveau</strong>.
                Saisir nom d’entreprise, contact, téléphone, app (produit), source, prochain suivi = demain.
                Enregistrer.
            </li>
            <li>
                <span class="step-label">Jour 2 — Qualifier</span><br>
                Après l’appel, dans la liste Prospects cliquer <strong>→ Opportunité</strong>.
                Vous arrivez sur le kanban en <strong>Qualifié</strong>.
            </li>
            <li>
                <span class="step-label">Jour 3 — Proposer</span><br>
                Logger la démo dans <span class="menu">Activités</span> (ou sur la fiche).
                Sur la carte cliquer <strong>Avancer</strong> → Proposition. Renseigner valeur estimée + probabilité sur la fiche si utile.
            </li>
            <li>
                <span class="step-label">Jour 5 — Négocier</span><br>
                Avancer → Négociation. Mettre à jour le prochain suivi.
            </li>
            <li>
                <span class="step-label">Jour 7 — Clôturer &amp; onboarder</span><br>
                Cliquer <strong>Gagné</strong> → la carte passe en <strong>À convertir</strong>.
                Cliquer <strong>Convertir</strong> : code client, app, email/mot de passe admin → <strong>Créer le client</strong>.
            </li>
            <li>
                <span class="step-label">Après conversion</span><br>
                Vous êtes sur la fiche Client. Utiliser <strong>Facturer</strong> pour rattacher un plan / enregistrer un paiement.
                Ensuite, gérer sous <span class="menu">Clients</span> (et Facturation).
            </li>
        </ol>
    </div>

    <h2>5. Scénario B — Lead froid qui ne répond plus</h2>
    <div class="scenario">
        <h3>Histoire : formulaire web, pas de réponse après 2 relances</h3>
        <ol>
            <li>Créer en <span class="menu">Prospects</span> (étape Nouveau).</li>
            <li>Logger chaque appel / WhatsApp dans <span class="menu">Activités</span> et fixer un prochain suivi.</li>
            <li>Si toujours mort après votre process : cliquer <strong>Perdu</strong> (ne pas convertir).</li>
            <li>Ne créez <em>pas</em> de Client pour un lead perdu.</li>
        </ol>
    </div>

    <h2>6. Scénario C — Client déjà existant (sans historique CRM)</h2>
    <div class="scenario">
        <h3>Histoire : entreprise créée manuellement plus tôt</h3>
        <ol>
            <li>Ouvrir <span class="menu">Clients</span> — elle apparaît même avec « Origine = Manuel ».</li>
            <li>Utiliser Fiche / Facturer comme d’habitude.</li>
            <li>Optionnel : si un Prospect lié a été converti, Origine affiche <strong>CRM</strong>.</li>
            <li>Nouvel upsell commercial ? Créer un <em>nouveau</em> Prospect/Opportunité seulement pour suivre un nouveau deal ; sinon travailler depuis Clients.</li>
        </ol>
    </div>

    <div class="page-break"></div>

    <h2>7. Scénario D — Routine du matin (15 min)</h2>
    <ol>
        <li><span class="menu">Activités</span> / KPIs Prospects — regarder <strong>Suivis dus</strong>.</li>
        <li><span class="menu">Opportunités</span> — avancer les cartes qui ont progressé ; convertir tout « À convertir ».</li>
        <li><span class="menu">Clients</span> — ouvrir ceux qui ont besoin de Facturer / d’attention.</li>
        <li>Logger chaque vraie conversation le jour même (le CRM reste fiable).</li>
    </ol>

    <h2>8. À faire / À éviter</h2>
    <table>
        <thead>
            <tr>
                <th>À faire</th>
                <th>À éviter</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Une entreprise = un Prospect qui devient un Client</td>
                <td>Créer un Client et un Prospect séparés pour le même deal</td>
            </tr>
            <tr>
                <td>Promouvoir en Opportunité seulement s’il y a un vrai process de vente</td>
                <td>Mettre chaque contact WhatsApp directement en Négociation</td>
            </tr>
            <tr>
                <td>Convertir seulement après un oui commercial</td>
                <td>Convertir « pour tester » sans vrai client</td>
            </tr>
            <tr>
                <td>Logger les activités avec une date de prochain suivi</td>
                <td>Compter uniquement sur la mémoire / l’historique WhatsApp</td>
            </tr>
            <tr>
                <td>Utiliser Clients pour les entreprises live + facturation</td>
                <td>Utiliser Clients comme liste de leads</td>
            </tr>
        </tbody>
    </table>

    <h2>9. Carte rapide des écrans</h2>
    <table>
        <thead>
            <tr>
                <th>Écran</th>
                <th>URL (local)</th>
                <th>Rôle</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Prospects</td>
                <td>/admin/prospects</td>
                <td>Boîte d’entrée des nouvelles entreprises</td>
            </tr>
            <tr>
                <td>Opportunités</td>
                <td>/admin/opportunities</td>
                <td>Pipeline commercial (kanban)</td>
            </tr>
            <tr>
                <td>Clients</td>
                <td>/admin/tenants</td>
                <td>Entreprises live (= anciennes « Companies »)</td>
            </tr>
            <tr>
                <td>Activités</td>
                <td>/admin/activities</td>
                <td>Timeline / carnet partagé</td>
            </tr>
            <tr>
                <td>Fiche prospect</td>
                <td>/admin/prospects/…/edit</td>
                <td>Détail complet + conversion si Gagné</td>
            </tr>
        </tbody>
    </table>

    <div class="note">
        <strong>À retenir :</strong> Client = entreprise sur la plateforme. Relation client est la vue commerciale ;
        Convertir provisionne le tenant comme « Nouvelle entreprise », tout en gardant l’historique CRM.
    </div>

    <h2>10. Aide-mémoire en une ligne</h2>
    <ul>
        <li><strong>Prospect</strong> = « Je pourrais leur vendre un jour. »</li>
        <li><strong>Opportunité</strong> = « Je suis en train de closer un deal. »</li>
        <li><strong>Client</strong> = « Ils sont en ligne sur Bproo. »</li>
        <li><strong>Activité</strong> = « Ce qu’on s’est dit / fait / quand on rappelle. »</li>
    </ul>

    <div class="footer">
        Bproo Control Center · Guide scénarios Relation client · Usage interne formation
    </div>
</body>
</html>
