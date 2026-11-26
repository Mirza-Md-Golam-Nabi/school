{{-- Styles shared by the student and teacher ID card sheets. Expects $theme. --}}
<style>
    @page {
        margin: 0;
    }

    body {
        font-family: 'SolaimanLipi', sans-serif;
        font-size: 7pt;
        color: {{ $theme['text'] }};
    }

    .layer {
        position: absolute;
        overflow: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    /* ---------- Front ---------- */
    td.logo {
        width: 10mm;
        vertical-align: middle;
    }

    td.school {
        vertical-align: middle;
    }

    .school-name {
        font-size: 8.5pt;
        font-weight: bold;
        color: {{ $theme['schoolName'] }};
        line-height: 1.15;
    }

    .school-address {
        font-size: 5.2pt;
        color: {{ $theme['schoolAddress'] }};
    }

    .student-name {
        font-size: 10pt;
        font-weight: bold;
        color: {{ $theme['studentName'] }};
        text-align: center;
        line-height: 1.15;
    }

    .role {
        width: 20mm;
        margin: 1mm auto 0 auto;
        padding: 0.5mm 0;
        border-radius: 2mm;
        background-color: {{ $theme['roleBackground'] }};
        color: {{ $theme['roleText'] }};
        font-size: 5.2pt;
        font-weight: bold;
        letter-spacing: 0.5mm;
        text-align: center;
    }

    table.details td {
        border-bottom: 0.15mm solid {{ $theme['rowBorder'] }};
        vertical-align: middle;
    }

    table.details td.label {
        width: 16mm;
        font-size: 5.2pt;
        font-weight: bold;
        letter-spacing: 0.15mm;
        color: {{ $theme['label'] }};
    }

    table.details td.value {
        font-size: 6.8pt;
        font-weight: bold;
        color: {{ $theme['value'] }};
    }

    .footer {
        font-size: 6pt;
        font-weight: bold;
        letter-spacing: 0.4mm;
        color: {{ $theme['footer'] }};
        text-align: center;
    }

    .footer .accent {
        color: {{ $theme['footerAccent'] }};
    }

    /* ---------- Back ---------- */
    .back-title {
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 0.5mm;
        color: {{ $theme['backTitle'] }};
        text-align: center;
    }

    table.guardian td {
        padding: 0.7mm 0;
        vertical-align: top;
    }

    .field-label {
        font-size: 4.8pt;
        font-weight: bold;
        letter-spacing: 0.15mm;
        color: {{ $theme['fieldLabel'] }};
    }

    .field-value {
        font-size: 6.8pt;
        font-weight: bold;
        color: {{ $theme['fieldValue'] }};
        line-height: 1.2;
    }

    table.qr-block td {
        vertical-align: middle;
    }

    td.qr-cell {
        width: 13.6mm;
        padding: 0.8mm;
        background-color: #ffffff;
    }

    td.qr-text {
        padding-left: 2mm;
    }

    .qr-id {
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 0.3mm;
        color: {{ $theme['fieldValue'] }};
    }

    .qr-caption {
        margin-top: 0.6mm;
        font-size: 4.6pt;
        color: {{ $theme['muted'] }};
        line-height: 1.25;
    }

    .card-number {
        padding-top: 4mm;
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 0.6mm;
        text-align: center;
        color: {{ $theme['fieldValue'] }};
    }

    table.signing td {
        vertical-align: bottom;
        font-size: 5pt;
        color: {{ $theme['muted'] }};
    }

    table.signing td.authority {
        text-align: center;
        width: 22mm;
    }

    .signature-line {
        border-top: 0.2mm solid {{ $theme['fieldValue'] }};
        padding-top: 0.4mm;
        font-weight: bold;
        color: {{ $theme['fieldValue'] }};
    }

    .validity {
        font-size: 6.4pt;
        font-weight: bold;
        color: {{ $theme['fieldValue'] }};
    }

    .return-note {
        font-size: 4.6pt;
        color: {{ $theme['returnNote'] }};
        text-align: center;
        line-height: 1.3;
    }
</style>
