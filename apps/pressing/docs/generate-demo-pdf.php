<?php

/**
 * Generate Pressing Excellence demo cases PDF.
 * Run: php docs/generate-demo-pdf.php
 */

require __DIR__.'/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 28px 32px; }
  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 10.5px;
    color: #1e293b;
    line-height: 1.45;
  }
  h1 {
    font-size: 20px;
    color: #0f766e;
    margin: 0 0 4px;
    border-bottom: 3px solid #3fa796;
    padding-bottom: 8px;
  }
  h2 {
    font-size: 13px;
    color: #0f766e;
    margin: 18px 0 8px;
    padding: 6px 10px;
    background: #ecfdf5;
    border-left: 4px solid #3fa796;
  }
  h3 {
    font-size: 11px;
    color: #0f172a;
    margin: 12px 0 6px;
  }
  .subtitle { color: #64748b; font-size: 11px; margin-bottom: 14px; }
  .meta {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 14px;
  }
  .meta strong { color: #0f766e; }
  table {
    width: 100%;
    border-collapse: collapse;
    margin: 6px 0 12px;
    font-size: 9.5px;
  }
  th {
    background: #0f766e;
    color: #fff;
    text-align: left;
    padding: 6px 7px;
    font-weight: 700;
  }
  td {
    border-bottom: 1px solid #e2e8f0;
    padding: 5px 7px;
    vertical-align: top;
  }
  tr:nth-child(even) td { background: #f8fafc; }
  .tag {
    display: inline-block;
    background: #ccfbf1;
    color: #0f766e;
    font-size: 8.5px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 10px;
  }
  .case {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 10px;
    margin-bottom: 8px;
    page-break-inside: avoid;
  }
  .case-title {
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 3px;
  }
  .case-steps { margin: 4px 0 0 14px; padding: 0; }
  .case-steps li { margin-bottom: 2px; }
  .note {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 6px;
    padding: 8px 10px;
    margin: 10px 0;
    font-size: 9.5px;
  }
  .footer {
    margin-top: 20px;
    padding-top: 8px;
    border-top: 1px solid #e2e8f0;
    color: #94a3b8;
    font-size: 8.5px;
    text-align: center;
  }
  .two-col { width: 100%; }
  .two-col td { width: 50%; border: 0; background: transparent !important; vertical-align: top; padding: 0 6px 0 0; }
  .kpi { font-size: 9px; color: #64748b; }
</style>
</head>
<body>

<h1>Pressing Excellence — Guide de démonstration</h1>
<p class="subtitle">Jeux de données et scénarios pour présenter tous les cas d’usage · Juillet 2026</p>

<div class="meta">
  <strong>Accès :</strong> http://127.0.0.1:8000/app?tenant=pressing<br>
  <strong>Admin :</strong> admin@pressing.com &nbsp;|&nbsp;
  <strong>Staff démo :</strong> *@demo.pressing.local &nbsp;/&nbsp; <strong>Pressing2026!</strong><br>
  <strong>Recharger les données :</strong> <code>php artisan pressing:seed-demo pressing --fresh</code><br>
  <strong>Langue :</strong> bascule FR / EN dans l’en-tête
</div>

<div class="note">
  <strong>Conseil démo :</strong> enchaînez les cas dans l’ordre (A → H). Chaque cas cite un client, une commande ou un écran précis déjà présents dans la base.
</div>

<h2>1. Comptes &amp; agences</h2>
<table>
  <thead>
    <tr><th>Rôle</th><th>Nom</th><th>Email</th><th>Agence</th></tr>
  </thead>
  <tbody>
    <tr><td>Admin</td><td>Admin Pressing</td><td>admin@pressing.com</td><td>Toutes</td></tr>
    <tr><td>Réception</td><td>Amina Ngozi</td><td>amina.ngozi@demo.pressing.local</td><td>Agence Akwa</td></tr>
    <tr><td>Réception</td><td>Paul Essomba</td><td>paul.essomba@demo.pressing.local</td><td>Agence Bonanjo</td></tr>
    <tr><td>Production</td><td>Jean Mbarga</td><td>jean.mbarga@demo.pressing.local</td><td>Akwa</td></tr>
    <tr><td>Repassage</td><td>Fatou Diallo</td><td>fatou.diallo@demo.pressing.local</td><td>Akwa</td></tr>
    <tr><td>Livraison</td><td>Eric Fokou</td><td>eric.fokou@demo.pressing.local</td><td>Akwa</td></tr>
  </tbody>
</table>
<p class="kpi">Agences : <strong>AG-001 Agence Akwa</strong> · <strong>AG-DEMO-002 Agence Bonanjo</strong> (Douala)</p>

<h2>2. Clients clés à utiliser</h2>
<table>
  <thead>
    <tr><th>Code</th><th>Client</th><th>WhatsApp</th><th>Profil / usage démo</th></tr>
  </thead>
  <tbody>
    <tr><td>CL-DEMO-001</td><td>Clarisse Ngono</td><td>690112233</td><td>VIP hôtel · fidélité (bon disponible) · acompte puis solde</td></tr>
    <tr><td>CL-DEMO-002</td><td>Michel Fotso</td><td>677445566</td><td>Corporate · crédit validé · bon fidélité</td></tr>
    <tr><td>CL-DEMO-004</td><td>Eric Kamga</td><td>699001122</td><td>Commande <em>Prête</em> — retrait agence</td></tr>
    <tr><td>CL-DEMO-005</td><td>Grace Bella</td><td>670334455</td><td>En <em>Lavage</em> · tissus sensibles</td></tr>
    <tr><td>CL-DEMO-009</td><td>Marie-Claire Etoa</td><td>673445566</td><td>Tri en cours · nouvelle cliente</td></tr>
    <tr><td>CL-DEMO-011</td><td>Julie Ateba</td><td>681990011</td><td>Commande <strong>en retard</strong> (prioriser)</td></tr>
    <tr><td>CL-DEMO-012</td><td>Rodrigue Fouda</td><td>694112233</td><td>Prêt · <em>crédit en attente</em></td></tr>
    <tr><td>CL-DEMO-014</td><td>Pierre Nguema</td><td>699334455</td><td>Linge restaurant · solde à encaisser · poids global</td></tr>
    <tr><td>CL-DEMO-017</td><td>Rose Tchamba</td><td>682112244</td><td>Tri <em>en attente</em> · Bonanjo</td></tr>
    <tr><td>CL-DEMO-019</td><td>Christelle Abega</td><td>677001122</td><td>Livraison <em>en transit</em> (chauffeur)</td></tr>
    <tr><td>CL-DEMO-021</td><td>Vanessa Ngo</td><td>655223344</td><td>Tri · <em>aucun paiement</em> encore</td></tr>
    <tr><td>CL-DEMO-022</td><td>Francis Kuete</td><td>670556677</td><td>Hôtel · linge hebdo · en lavage</td></tr>
  </tbody>
</table>
<p class="kpi">22 clients démo au total (CL-DEMO-001 → 022). Recherche multi-agence : un client Bonanjo est trouvable depuis Akwa.</p>

<h2>3. Cas de démonstration</h2>

<div class="case">
  <div class="case-title"><span class="tag">A</span> Réception — client existant</div>
  <ol class="case-steps">
    <li>Menu <strong>Réception / Nouvelle commande</strong>.</li>
    <li>Rechercher <strong>690112233</strong> ou « Clarisse » → sélectionner CL-DEMO-001.</li>
    <li>Montrer le passage automatique à l’étape commande + éventuelle récompense fidélité.</li>
    <li>Ajouter articles (ex. Chemise ×2, Costume ×1), enregistrer.</li>
  </ol>
</div>

<div class="case">
  <div class="case-title"><span class="tag">B</span> Réception — client introuvable → création rapide (modal)</div>
  <ol class="case-steps">
    <li>Rechercher un numéro inventé, ex. <strong>699000111</strong>.</li>
    <li>Cliquer <strong>Créer ce client et continuer</strong> → formulaire en <em>modal</em>.</li>
    <li>Saisir Nom / Prénom / WhatsApp → Enregistrer → continuer la commande.</li>
  </ol>
</div>

<div class="case">
  <div class="case-title"><span class="tag">C</span> Module Clients — création / modification (modal)</div>
  <ol class="case-steps">
    <li>Ouvrir <strong>Nos Clients</strong> → <strong>Nouveau client</strong> (modal centré).</li>
    <li>Éditer Francis Kuete (CL-DEMO-022) via <strong>Modifier</strong> — même modal.</li>
    <li>Montrer filtres agence + stats (commandes, CA, en cours).</li>
  </ol>
</div>

<div class="case">
  <div class="case-title"><span class="tag">D</span> Modes de facturation</div>
  <table>
    <thead><tr><th>Mode</th><th>Exemple client / note</th></tr></thead>
    <tbody>
      <tr><td>Prix fixe</td><td>CL-DEMO-007 Patricia Nana — prêt payé carte</td></tr>
      <tr><td>Mixte (fixe + kg)</td><td>CL-DEMO-001 Clarisse — acompte puis solde Mobile Money</td></tr>
      <tr><td>Poids global</td><td>CL-DEMO-014 Pierre Nguema — linge restaurant</td></tr>
      <tr><td>Poids par type</td><td>CL-DEMO-019 Christelle — livraison en cours</td></tr>
    </tbody>
  </table>
</div>

<div class="case">
  <div class="case-title"><span class="tag">E</span> Paiements &amp; crédit</div>
  <table>
    <thead><tr><th>Cas</th><th>Où le voir</th></tr></thead>
    <tbody>
      <tr><td>Payé intégralement</td><td>Majorité des commandes livrées (espèces, MM, carte, virement)</td></tr>
      <tr><td>Acompte puis solde</td><td>CL-DEMO-001 — note « Acompte puis solde Mobile Money »</td></tr>
      <tr><td>Paiement partiel / solde dû</td><td>CL-DEMO-014 (prêt) · CL-DEMO-008 (lavage)</td></tr>
      <tr><td>Acompte à la réception</td><td>CL-DEMO-009, 017, 020 (tri)</td></tr>
      <tr><td>Sans paiement</td><td>CL-DEMO-021 Vanessa Ngo (tri)</td></tr>
      <tr><td>Crédit approuvé</td><td>CL-DEMO-002 Michel Fotso — livré</td></tr>
      <tr><td>Crédit en attente</td><td>CL-DEMO-012 Rodrigue Fouda — prêt</td></tr>
    </tbody>
  </table>
</div>

<div class="case">
  <div class="case-title"><span class="tag">F</span> Workflow production (Kanban)</div>
  <ol class="case-steps">
    <li>Ouvrir le <strong>Kanban</strong> / workflow.</li>
    <li>Colonnes peuplées : Tri → Mise en prod. → Lavage → Séchage → Repassage → Fin prod. → Prêt → Livré.</li>
    <li>Exemples live : Grace Bella (Lavage), Thomas Biya (Séchage), Alain Tchoumi (Repassage), Hélène Simo (Fin production).</li>
    <li>Montrer la commande <strong>en retard</strong> : Julie Ateba (CL-DEMO-011).</li>
  </ol>
</div>

<div class="case">
  <div class="case-title"><span class="tag">G</span> Tri / constitution de lots</div>
  <ol class="case-steps">
    <li>Écran <strong>Tri</strong> : commandes CL-DEMO-009, 017, 020, 021.</li>
    <li>Montrer statut tri <em>en attente</em> vs <em>en cours</em> et constitution des pièces.</li>
  </ol>
</div>

<div class="case">
  <div class="case-title"><span class="tag">H</span> Livraisons</div>
  <ol class="case-steps">
    <li>Module <strong>Livraisons</strong> : onglets En attente / Terminées.</li>
    <li>Retrait agence : Eric Kamga (prêt).</li>
    <li>Domicile en transit : Christelle Abega (CL-DEMO-019) — chauffeur Eric Fokou.</li>
    <li>Historique : nombreuses livraisons juillet (agence + domicile).</li>
  </ol>
</div>

<div class="case">
  <div class="case-title"><span class="tag">I</span> Fidélité</div>
  <ol class="case-steps">
    <li>Réglages : 1 pt / commande + 1 pt / 2 000 XAF · seuil 10 pts → bon 2 000 XAF · validité 90 j.</li>
    <li>CL-DEMO-001 : bon <strong>LOY-DEMO-VIP1</strong> disponible (2 000 XAF) + bon déjà utilisé (10 %).</li>
    <li>CL-DEMO-002 : bon <strong>LOY-DEMO-CORP</strong> disponible.</li>
    <li>À la réception VIP : proposer d’appliquer la récompense.</li>
    <li>Écran Fidélité : historique points + ajustement manuel « Bonus fidélité juillet ».</li>
  </ol>
</div>

<div class="case">
  <div class="case-title"><span class="tag">J</span> Consommables</div>
  <ol class="case-steps">
    <li><strong>Atelier</strong> : bons de sortie lessive / savon / parfum liés aux commandes en production.</li>
    <li><strong>Livraison</strong> : cintres / emballages / étiquettes sur commandes livrées.</li>
    <li>Stock démo rempli (CONS-LESSIVE, SAVON, PARFUM, CINTRES, EMBALLAGES, ETIQUETTES).</li>
  </ol>
</div>

<div class="case">
  <div class="case-title"><span class="tag">K</span> Rapports &amp; tableau de bord</div>
  <ol class="case-steps">
    <li>Dashboard : commandes du jour, pipeline, alertes retard, notifications.</li>
    <li>Rapports : KPIs juillet 2026, CA, modes de paiement, répartition pipeline.</li>
    <li>Filtrer par agence Akwa vs Bonanjo.</li>
  </ol>
</div>

<div class="case">
  <div class="case-title"><span class="tag">L</span> Notifications &amp; impressions</div>
  <ol class="case-steps">
    <li>Cloche (nombreuses notifs démo : réception, paiement, prêt, livré).</li>
    <li>Impressions : ticket dépôt, facture, étiquette, QR commande.</li>
  </ol>
</div>

<h2>4. Catalogue tarifs (aperçu)</h2>
<table>
  <thead><tr><th>Article</th><th>Fixe (XAF)</th><th>/ kg (XAF)</th></tr></thead>
  <tbody>
    <tr><td>Chemise</td><td>1 500</td><td>1 200</td></tr>
    <tr><td>Pantalon</td><td>2 000</td><td>1 500</td></tr>
    <tr><td>Costume</td><td>5 000</td><td>2 500</td></tr>
    <tr><td>Robe</td><td>3 500</td><td>2 000</td></tr>
    <tr><td>Boubou</td><td>4 000</td><td>2 200</td></tr>
    <tr><td>Rideaux</td><td>6 000</td><td>2 500</td></tr>
    <tr><td>Couverture</td><td>4 500</td><td>2 000</td></tr>
    <tr><td>Tapis</td><td>10 000</td><td>3 000</td></tr>
    <tr><td>Chaussures</td><td>2 500</td><td>—</td></tr>
    <tr><td>Culotte</td><td>800</td><td>800</td></tr>
  </tbody>
</table>
<p class="kpi">Poids global par défaut : <strong>1 500 XAF / kg</strong> · Délai standard : <strong>48 h</strong></p>

<h2>5. Script démo rapide (15–20 min)</h2>
<table>
  <thead><tr><th>#</th><th>Durée</th><th>Action</th></tr></thead>
  <tbody>
    <tr><td>1</td><td>2 min</td><td>Login admin → dashboard → bascule FR/EN → 2 agences</td></tr>
    <tr><td>2</td><td>3 min</td><td>Cas A + B (réception existant + nouveau client modal)</td></tr>
    <tr><td>3</td><td>2 min</td><td>Cas C (liste clients + modal)</td></tr>
    <tr><td>4</td><td>3 min</td><td>Cas F + G (kanban + tri + retard)</td></tr>
    <tr><td>5</td><td>2 min</td><td>Cas E + H (paiements / crédit / livraisons)</td></tr>
    <tr><td>6</td><td>2 min</td><td>Cas I (fidélité + application bon)</td></tr>
    <tr><td>7</td><td>2 min</td><td>Cas J + K (consommables + rapports)</td></tr>
    <tr><td>8</td><td>1 min</td><td>Cas L (notifications + impression ticket)</td></tr>
  </tbody>
</table>

<div class="footer">
  Pressing Excellence · Données démo CMD-DEMO-* / CL-DEMO-* · Généré pour présentation client · Juillet 2026
</div>

</body>
</html>
HTML;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$out = __DIR__.'/Pressing-Demo-Cases.pdf';
file_put_contents($out, $dompdf->output());

echo "PDF written: {$out}\n";
echo 'Size: '.number_format(filesize($out) / 1024, 1)." KB\n";
