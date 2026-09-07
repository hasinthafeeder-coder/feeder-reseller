@php
    $size = (int) ($size ?? 40);
    $fontSize = $size >= 64 ? '22px' : ($size >= 48 ? '16px' : '13px');
    $photoUrl = $agent['profile_photo_url'] ?? null;
@endphp
@if (! empty($photoUrl))
    <img src="{{ $photoUrl }}"
        alt="{{ $agent['full_name'] ?? 'Agent' }}"
        class="rounded-circle flex-shrink-0 object-fit-cover"
        style="width: {{ $size }}px; height: {{ $size }}px;"
        loading="lazy">
@else
    <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-medium flex-shrink-0"
        style="width: {{ $size }}px; height: {{ $size }}px; background-color: {{ $agent['avatar_color'] ?? '#64748B' }}; font-size: {{ $fontSize }};">
        {{ $agent['initials'] ?? 'CA' }}
    </div>
@endif
