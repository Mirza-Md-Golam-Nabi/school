@php
    /**
     * The marksheet's verification QR code (opens the public result page),
     * with the short link and code printed under it for anyone who can't scan.
     * Rendered in the marksheet's document language.
     *
     * @var string $verificationUrl
     * @var \App\Models\Marksheet $marksheet
     */
    $shortVerifyLink = preg_replace('#^https?://#', '', route('verify'));
@endphp
<barcode code="{{ $verificationUrl }}" type="QR" error="M" size="0.72" disableborder="1" /><br>
<span class="qr-caption">{{ __('Scan to verify') }}</span><br>
<span class="qr-link">{{ $shortVerifyLink }}</span><br>
<span class="qr-code">{{ __('Code: :code', ['code' => $marksheet->formattedVerificationCode()]) }}</span>
