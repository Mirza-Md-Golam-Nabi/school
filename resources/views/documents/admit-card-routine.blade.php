<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 8mm;
        }
    </style>
    @include('documents.partials.admit-card-routine-styles')
</head>

<body>
    @include('documents.partials.admit-card-routine-card', [
        'admitCard' => $admitCard,
        'branding' => \App\Support\AdmitCardBranding::fromSettings(),
    ])
</body>

</html>
