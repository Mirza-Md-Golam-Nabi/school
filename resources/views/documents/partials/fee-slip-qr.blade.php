@php
    /**
     * The payment slip's verification QR code (opens the public receipt page),
     * with the short link and code printed under it for anyone who can't scan.
     *
     * @var \App\Models\FeePayment $firstPayment
     */
    $shortVerifyLink = preg_replace('#^https?://#', '', route('verify'));
@endphp
<barcode code="{{ $firstPayment->verificationUrl() }}" type="QR" error="M" size="0.6" disableborder="1" /><br>
<span class="qr-caption">Scan to verify</span><br>
<span class="qr-link">{{ $shortVerifyLink }}</span><br>
<span class="qr-code">Code: {{ $firstPayment->formattedVerificationCode() }}</span>
