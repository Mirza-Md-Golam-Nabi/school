{{-- Up to two routine admit cards on one A4 page, one above the other, with a cut line between. --}}
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
    @foreach ($admitCards as $admitCard)
        @include('documents.partials.admit-card-routine-card', ['admitCard' => $admitCard, 'branding' => $branding])

        @if (! $loop->last)
            <div class="arc-cut"></div>
        @endif
    @endforeach
</body>

</html>
