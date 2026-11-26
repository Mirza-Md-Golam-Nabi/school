@php
    /**
     * The card's verification QR code (opens the public "is this card real?"
     * page), with a one-line caption in the card's student_info_language.
     * Renders nothing when the school turned the QR off or the card has no
     * token yet.
     *
     * @var \App\Models\AdmitCard $admitCard
     * @var float $size  mPDF barcode size: 1 ≈ 25mm square
     * @var string $captionSize  CSS font size of the caption
     */
    use App\Models\AdmitCard;
    use App\Support\DocumentLocale;

    $verificationUrl = AdmitCard::showsQrCode() ? $admitCard->verificationUrl() : null;
@endphp
@if ($verificationUrl)
    <barcode code="{{ $verificationUrl }}" type="QR" error="M" size="{{ $size }}" disableborder="1" /><br>
    <span style="font-size: {{ $captionSize }}; color: #555555;">{{ DocumentLocale::using($admitCard->student_info_language, fn (): string => __('Scan to verify')) }}</span>
@endif
