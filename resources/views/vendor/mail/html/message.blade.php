@php
    $branding = \App\Core\AppBrandingSettings::load();
    $brandName = $branding['app_name'] ?? config('app.name', 'Car4u');
@endphp

<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $brandName }}
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $brandName }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
