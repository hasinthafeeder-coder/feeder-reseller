@php
    $size = (int) ($size ?? 40);
    $fontSize = $size >= 64 ? '22px' : ($size >= 48 ? '16px' : '13px');
@endphp
<div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-medium flex-shrink-0"
    style="width: {{ $size }}px; height: {{ $size }}px; background-color: {{ $agent['avatar_color'] }}; font-size: {{ $fontSize }};">
    {{ $agent['initials'] }}
</div>
