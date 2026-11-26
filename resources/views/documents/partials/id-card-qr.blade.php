@php
    /**
     * The block on the back of an ID card, below the details: the
     * verification QR code (opens the public "is this card valid?" page)
     * beside the card's ID number. When the school turned the QR off, or the
     * profile has no token yet, only the ID number is printed.
     *
     * Sits inside a 44mm wide layer; the QR is 12mm plus a white quiet zone so
     * it scans on every design's artwork.
     *
     * @var string|null $verificationUrl
     * @var string $idNumber
     */
@endphp
@if ($verificationUrl)
    <table class="qr-block">
        <tr>
            <td class="qr-cell">
                <barcode code="{{ $verificationUrl }}" type="QR" error="M" size="0.48" disableborder="1" />
            </td>
            <td class="qr-text">
                <div class="field-label">ID NO</div>
                <div class="qr-id">{{ $idNumber }}</div>
                <div class="qr-caption">Scan the QR code to verify this card</div>
            </td>
        </tr>
    </table>
@else
    <div class="card-number">{{ $idNumber }}</div>
@endif
