@if ($src)
    <script src="{{ $src }}" @if ($nonce) nonce="{{ $nonce }}" @endif></script>
@endif
