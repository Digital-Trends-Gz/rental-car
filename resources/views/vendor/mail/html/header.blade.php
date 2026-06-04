@props(['url'])

@php
    $branding = \App\Core\AppBrandingSettings::load();
    $brandName = $branding['app_name'] ?? config('app.name', 'Car4u');
    $brandLogo = $branding['logo_url'] ?? null;

    if (is_string($brandLogo) && $brandLogo !== '' && str_starts_with($brandLogo, '/')) {
        $brandLogo = url($brandLogo);
    }
@endphp

<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if ($brandLogo)
<img src="{{ $brandLogo }}" class="logo" alt="{{ $brandName }} Logo" style="max-height: 64px; width: auto;">
@else
<span style="display: inline-block; font-size: 20px; font-weight: 700; color: #111827;">
{{ $brandName }}
</span>
@endif
</a>
</td>
</tr>
