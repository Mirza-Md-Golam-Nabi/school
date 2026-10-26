{{-- Styles of the admit card with the exam routine — light fills, clear colourful text. mPDF:
     rounded corners only on divs, layout with tables. --}}
<style>
    body {
        font-family: 'SolaimanLipi', sans-serif;
        font-size: 10.5px;
        color: #22384f;
    }

    .arc-card {
        border: 1px solid #b9d4ea;
        border-radius: 10px;
        background-color: #ffffff;
        page-break-inside: avoid;
    }

    table.arc-stripe {
        width: 100%;
        border-collapse: collapse;
    }

    table.arc-stripe td {
        height: 6px;
    }

    table.arc-head {
        width: 100%;
        border-collapse: collapse;
        background-color: #d6e8fa;
        background: linear-gradient(to right, #c9ecd9, #d2e4fa, #e1d6f8);
    }

    table.arc-head td {
        padding: 8px 12px 7px 12px;
        vertical-align: middle;
    }

    td.arc-logo-cell {
        width: 58px;
    }

    .arc-school-name {
        font-size: 17px;
        font-weight: bold;
        color: #1f6fb2;
        letter-spacing: 0.4px;
    }

    .arc-school-address {
        font-size: 9.5px;
        color: #36536f;
        margin-top: 2px;
    }

    .arc-title {
        margin-top: 9px;
        text-align: center;
    }

    .arc-pill {
        width: 130px;
        margin: 0 auto;
        background-color: #ffe9d9;
        border: 1px solid #f4b98e;
        border-radius: 14px;
        color: #d9661f;
        font-size: 12px;
        font-weight: bold;
        letter-spacing: 3px;
        padding: 4px 0;
    }

    .arc-exam-name {
        font-size: 12.5px;
        font-weight: bold;
        color: #6b46c1;
        margin-top: 5px;
    }

    table.arc-body {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }

    table.arc-body td.arc-info-cell {
        padding: 0 12px;
        vertical-align: top;
    }

    table.arc-body td.arc-photo-cell {
        width: 92px;
        padding-right: 12px;
        vertical-align: top;
        text-align: right;
    }

    table.arc-info {
        width: 100%;
        border-collapse: collapse;
    }

    table.arc-info td {
        padding: 6px 4px;
        vertical-align: top;
        border-bottom: 1px dotted #c9d9e8;
    }

    table.arc-info td.arc-label {
        color: #2f5f94;
        font-weight: bold;
        width: 22%;
        font-size: 10px;
    }

    table.arc-info td.arc-value {
        color: #16304d;
        font-weight: bold;
        width: 28%;
    }

    .arc-photo {
        border: 2px solid #a9cbe8;
        border-radius: 6px;
        padding: 2px;
        background-color: #ffffff;
    }

    table.arc-photo-placeholder {
        border-collapse: collapse;
    }

    table.arc-photo-placeholder td {
        border: 2px dashed #a9cbe8;
        color: #7f9bb8;
        font-size: 9px;
        text-align: center;
        vertical-align: middle;
    }

    .arc-section-title {
        margin: 9px 12px 5px 12px;
        background-color: #e3f6ec;
        border: 1px solid #a8e0c2;
        border-radius: 8px;
        color: #1f8a5b;
        font-size: 10.5px;
        font-weight: bold;
        letter-spacing: 1.5px;
        text-align: center;
        padding: 3px 0;
    }

    table.arc-routine-wrap {
        width: 100%;
        border-collapse: collapse;
    }

    table.arc-routine-wrap td.arc-routine-col {
        width: 50%;
        vertical-align: top;
        padding: 0 12px;
    }

    table.arc-routine {
        width: 100%;
        border-collapse: collapse;
    }

    table.arc-routine th {
        background-color: #e8f1fb;
        color: #2f6fae;
        font-size: 9px;
        font-weight: bold;
        letter-spacing: 0.6px;
        text-align: left;
        padding: 3px 6px;
        border-bottom: 1px solid #bcd5ee;
    }

    table.arc-routine td {
        padding: 3px 6px;
        border-bottom: 1px solid #e1eaf3;
        color: #1e3550;
    }

    table.arc-routine tr.arc-even td {
        background-color: #f5f9fe;
    }

    table.arc-routine td.arc-serial {
        width: 24px;
        color: #2f74d0;
        font-weight: bold;
    }

    table.arc-routine td.arc-date {
        width: 78px;
        color: #0f8b6f;
        font-weight: bold;
        white-space: nowrap;
    }

    table.arc-signatures {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    table.arc-signatures td {
        width: 50%;
        text-align: center;
        vertical-align: bottom;
        padding: 0 30px;
        color: #4f6680;
        font-size: 9.5px;
    }

    .arc-sign-line {
        border-top: 1px dashed #9fb6cc;
        padding-top: 3px;
    }

    .arc-footer {
        margin-top: 6px;
        padding: 4px 12px 7px 12px;
        text-align: center;
        font-size: 8.5px;
        color: #6f8399;
    }

    .arc-footer .arc-brand {
        color: #2f74d0;
        font-weight: bold;
    }

    .arc-cut {
        border-top: 1px dashed #b8c8d8;
        margin: 5mm 0;
    }
</style>
