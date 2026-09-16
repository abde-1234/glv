<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat {{ $contrat->displayReference() }}</title>
    <style>
        @page { margin: 14mm 13mm 15mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #102a46;
            background: #ffffff;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            line-height: 1.45;
        }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; overflow-wrap: break-word; }
        .header { padding-bottom: 10px; border-bottom: 2px solid #0879f9; }
        .brand-cell { width: 45%; }
        .agency-cell { width: 55%; text-align: right; }
        .logo {
            display: inline-block;
            max-width: 96px;
            max-height: 48px;
            margin-right: 10px;
            vertical-align: middle;
        }
        .glv-mark {
            display: inline-block;
            color: #0879f9;
            font-size: 29px;
            font-weight: 800;
            letter-spacing: -1.5px;
            vertical-align: middle;
        }
        .brand-subtitle { margin-top: 2px; color: #5e7590; font-size: 8px; }
        .agency-cell strong { display: block; margin-bottom: 2px; color: #0c213a; font-size: 12px; }
        .agency-line { color: #50677f; line-height: 1.55; }
        .title { margin: 14px 0 2px; text-align: center; color: #0c2440; font-size: 19px; letter-spacing: .3px; }
        .reference { margin: 0 0 11px; text-align: center; color: #526a84; font-size: 9px; }
        .reference strong { color: #0879f9; font-size: 10px; }
        .summary {
            margin-bottom: 10px;
            border: 1px solid #d9e7f6;
            border-radius: 6px;
            background: #f7fbff;
        }
        .summary td { width: 33.333%; padding: 7px 9px; border-right: 1px solid #d9e7f6; }
        .summary td:last-child { border-right: 0; }
        .label { display: block; margin-bottom: 2px; color: #6a7e94; font-size: 7.5px; text-transform: uppercase; letter-spacing: .35px; }
        .value { color: #122c49; font-weight: 700; }
        .status { display: inline-block; padding: 2px 7px; border-radius: 20px; font-size: 8px; font-weight: 700; }
        .status-active { color: #087746; background: #dff8ec; }
        .status-finished { color: #125fa7; background: #e2f0ff; }
        .status-cancelled { color: #bc2940; background: #ffe7eb; }
        .two-columns { table-layout: fixed; }
        .two-columns > tbody > tr > td { width: 50%; padding-bottom: 9px; }
        .two-columns > tbody > tr > td:first-child { padding-right: 5px; }
        .two-columns > tbody > tr > td:last-child { padding-left: 5px; }
        .panel { min-height: 129px; border: 1px solid #dce7f2; border-radius: 7px; page-break-inside: avoid; }
        .panel-title {
            padding: 6px 9px;
            color: #0f2b49;
            background: #edf5fd;
            border-bottom: 1px solid #dce7f2;
            font-size: 10px;
            font-weight: 700;
        }
        .panel-body { padding: 8px 9px; }
        .info-table td { padding: 2px 0; }
        .info-table td:first-child { width: 37%; color: #6a7e94; }
        .info-table td:last-child { color: #152f4d; font-weight: 600; }
        .vehicle-layout td:first-child { width: 31%; padding-right: 8px; vertical-align: middle; }
        .vehicle-photo { width: 92px; max-height: 64px; border-radius: 5px; object-fit: contain; }
        .vehicle-placeholder {
            width: 82px;
            padding: 16px 4px;
            border-radius: 5px;
            color: #0879f9;
            background: #edf6ff;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
        }
        .full-panel { margin-bottom: 9px; page-break-inside: avoid; }
        .reservation-row { padding: 8px 9px; }
        .reservation-row table td { width: 50%; }
        .notes { color: #344d67; white-space: normal; overflow-wrap: break-word; }
        .conditions { margin-bottom: 9px; }
        .conditions .panel-title { page-break-after: avoid; }
        .signatures { margin-top: 12px; table-layout: fixed; page-break-inside: avoid; }
        .signatures td { width: 50%; padding: 0 13px; text-align: center; }
        .signature-title { color: #17334f; font-weight: 700; }
        .approval { margin-top: 3px; color: #7890a7; font-size: 7.5px; }
        .signature-space { height: 41px; border-bottom: 1px solid #7890a7; }
        .signature-name { margin-top: 4px; color: #3e5872; font-size: 8px; }
        .footer {
            position: fixed;
            right: 0;
            bottom: -9mm;
            left: 0;
            padding-top: 5px;
            border-top: 1px solid #d8e3ee;
            color: #71859a;
            font-size: 7px;
        }
        .footer td:last-child { text-align: right; }
    </style>
</head>
<body>
    <footer class="footer">
        <table><tr>
            <td>GLV — Contrat {{ $contrat->displayReference() }}</td>
            <td>Généré le {{ $generatedAt->format('d/m/Y à H:i') }}</td>
        </tr></table>
    </footer>

    <header class="header">
        <table><tr>
            <td class="brand-cell">
                @if ($agencyLogoDataUri)
                    <img class="logo" src="{{ $agencyLogoDataUri }}" alt="Logo {{ $contrat->agence->nom }}">
                @else
                    <span class="glv-mark">GLV</span>
                @endif
                <div class="brand-subtitle">Location de voitures</div>
            </td>
            <td class="agency-cell">
                <strong>{{ $contrat->agence->nom }}</strong>
                @if ($contrat->agence->adresse)<div class="agency-line">{{ $contrat->agence->adresse }}</div>@endif
                @if ($contrat->agence->ville)<div class="agency-line">{{ $contrat->agence->ville }}</div>@endif
                @if ($contrat->agence->telephone)<div class="agency-line">Tél. : {{ $contrat->agence->telephone }}</div>@endif
                @if ($contrat->agence->email)<div class="agency-line">Email : {{ $contrat->agence->email }}</div>@endif
            </td>
        </tr></table>
    </header>

    <h1 class="title">CONTRAT DE LOCATION DE VÉHICULE</h1>
    <p class="reference">N° <strong>{{ $contrat->displayReference() }}</strong></p>

    <table class="summary"><tr>
        <td><span class="label">Référence</span><span class="value">{{ $contrat->displayReference() }}</span></td>
        <td><span class="label">Date de création</span><span class="value">{{ $contrat->created_at?->format('d/m/Y') ?? 'Non renseignée' }}</span></td>
        <td>
            <span class="label">Statut</span>
            <span class="status {{ $contrat->statut === 'actif' ? 'status-active' : ($contrat->statut === 'termine' ? 'status-finished' : 'status-cancelled') }}">{{ $statusLabel }}</span>
        </td>
    </tr></table>

    <table class="two-columns"><tr>
        <td>
            <section class="panel">
                <div class="panel-title">1. Informations du client</div>
                <div class="panel-body">
                    <table class="info-table">
                        <tr><td>Nom complet</td><td>{{ $contrat->client->nom }}</td></tr>
                        <tr><td>Téléphone</td><td>{{ $contrat->client->telephone ?: 'Non renseigné' }}</td></tr>
                        <tr><td>Email</td><td>{{ $contrat->client->email ?: 'Non renseigné' }}</td></tr>
                        <tr><td>CIN</td><td>{{ $contrat->client->cin ?: 'Non renseignée' }}</td></tr>
                        <tr><td>Ville</td><td>{{ $contrat->client->ville ?: 'Non renseignée' }}</td></tr>
                        <tr><td>Adresse</td><td>{{ $contrat->client->adresse ?: 'Non renseignée' }}</td></tr>
                    </table>
                </div>
            </section>
        </td>
        <td>
            <section class="panel">
                <div class="panel-title">2. Informations du véhicule</div>
                <div class="panel-body">
                    <table class="vehicle-layout"><tr>
                        <td>
                            @if ($vehiclePhotoDataUri)
                                <img class="vehicle-photo" src="{{ $vehiclePhotoDataUri }}" alt="{{ $contrat->voiture->marque }} {{ $contrat->voiture->modele }}">
                            @else
                                <div class="vehicle-placeholder">GLV</div>
                            @endif
                        </td>
                        <td>
                            <table class="info-table">
                                <tr><td>Marque</td><td>{{ $contrat->voiture->marque }}</td></tr>
                                <tr><td>Modèle</td><td>{{ $contrat->voiture->modele }}</td></tr>
                                <tr><td>Immatriculation</td><td>{{ $contrat->voiture->immatriculation }}</td></tr>
                                <tr><td>Catégorie</td><td>{{ $contrat->voiture->categorie ?: 'Non renseignée' }}</td></tr>
                                <tr><td>Année</td><td>{{ $contrat->voiture->annee ?: 'Non renseignée' }}</td></tr>
                                @if ($contrat->voiture->kilometrage !== null)
                                    <tr><td>Kilométrage</td><td>{{ number_format($contrat->voiture->kilometrage, 0, ',', ' ') }} km</td></tr>
                                @endif
                            </table>
                        </td>
                    </tr></table>
                </div>
            </section>
        </td>
    </tr></table>

    <table class="two-columns"><tr>
        <td>
            <section class="panel">
                <div class="panel-title">3. Période de location</div>
                <div class="panel-body">
                    <table class="info-table">
                        <tr><td>Date de début</td><td>{{ $contrat->date_debut->format('d/m/Y') }}</td></tr>
                        <tr><td>Date de fin</td><td>{{ $contrat->date_fin->format('d/m/Y') }}</td></tr>
                        <tr><td>Durée</td><td>{{ $contrat->durationInDays() }} jour(s)</td></tr>
                    </table>
                </div>
            </section>
        </td>
        <td>
            <section class="panel">
                <div class="panel-title">4. Détails financiers</div>
                <div class="panel-body">
                    <table class="info-table">
                        <tr><td>Prix par jour</td><td>{{ $dailyPrice !== null ? number_format((float) $dailyPrice, 2, ',', ' ').' MAD' : 'Non renseigné' }}</td></tr>
                        <tr><td>Montant total</td><td>{{ $contrat->montant !== null ? number_format((float) $contrat->montant, 2, ',', ' ').' MAD' : 'Non renseigné' }}</td></tr>
                    </table>
                </div>
            </section>
        </td>
    </tr></table>

    @if ($contrat->reservation)
        <section class="panel full-panel">
            <div class="panel-title">5. Réservation liée</div>
            <div class="reservation-row">
                <table><tr>
                    <td><span class="label">Référence</span><span class="value">{{ $contrat->reservation->displayReference() }}</span></td>
                    <td><span class="label">Statut</span><span class="value">{{ ucfirst(str_replace('_', ' ', $contrat->reservation->statut)) }}</span></td>
                </tr></table>
            </div>
        </section>
    @endif

        <section class="conditions">
            <div class="panel-title">Conditions et observations</div>
            @if (trim($contrat->notes ?? '') !== '')
            <div class="panel-body notes">{!! nl2br(e($contrat->notes)) !!}</div>
            @else
            <div class="panel-body notes">
                <ul>
                    <li>Le véhicule doit être restitué à la date et à l’heure convenues.</li>
                    <li>Le locataire est responsable du véhicule pendant toute la durée de location.</li>
                    <li>Tout dommage ou incident doit être signalé immédiatement à l’agence.</li>
                    <li>Le véhicule doit être restitué dans l’état prévu lors de sa remise.</li>
                </ul>
            </div>
            @endif
        </section>

    <table class="signatures"><tr>
        <td>
            <div class="signature-title">Le locataire</div>
            <div class="approval">Lu et approuvé</div>
            <div class="signature-space"></div>
            <div class="signature-name">{{ $contrat->client->nom }}</div>
        </td>
        <td>
            <div class="signature-title">L’agence</div>
            <div class="approval">Signature et cachet</div>
            <div class="signature-space"></div>
            <div class="signature-name">{{ $contrat->agence->nom }}</div>
        </td>
    </tr></table>
</body>
</html>
