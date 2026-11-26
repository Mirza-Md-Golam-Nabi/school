{{--
    The on-screen copy of the QR block printed on the back of an ID card
    (documents.partials.id-card-qr), at the same position and size. Without a
    QR image only the ID number is shown, as on a card printed with the QR off.

    @var \Closure $box  (left, top, width, ?height) in mm → CSS
    @var \Closure $px   mm → CSS length
    @var \Closure $pt   points → CSS length
    @var array<string, string> $theme
    @var string $idNumber
    @var string|null $qrImage  data URI of the QR code
--}}
@if ($qrImage)
    <div style="{{ $box(5, 47.6, 13.6, 13.6) }} box-sizing:border-box; padding:{{ $px(0.8) }}; background:#ffffff;">
        <img src="{{ $qrImage }}" alt="QR" style="display:block; width:100%; height:100%;">
    </div>
    <div style="{{ $box(20.6, 49.6, 28.4) }}">
        <div style="font-size:{{ $pt(4.8) }}; font-weight:700; color:{{ $theme['fieldLabel'] }};">ID NO</div>
        <div style="font-size:{{ $pt(7) }}; font-weight:700; letter-spacing:{{ $px(0.3) }}; color:{{ $theme['fieldValue'] }};">{{ $idNumber }}</div>
        <div style="margin-top:{{ $px(0.6) }}; font-size:{{ $pt(4.6) }}; line-height:1.25; color:{{ $theme['muted'] }};">Scan the QR code to verify this card</div>
    </div>
@else
    <div style="{{ $box(5, 51.6, 44) }} text-align:center; font-size:{{ $pt(7) }}; font-weight:700; letter-spacing:{{ $px(0.6) }}; color:{{ $theme['fieldValue'] }};">{{ $idNumber }}</div>
@endif
