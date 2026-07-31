@php
    $appName = config('app.name', 'Room Bidding');
    $ogTitle = $appName.': fair rent split for shared houses';
    $ogDesc = 'Split shared-house rent fairly. Each room is priced by demand, and the amounts always add up to exactly the total rent.';
    $ogImage = rtrim(config('app.url'), '/').'/og.png';
@endphp

{{-- SEO --}}
<meta name="description" content="{{ $ogDesc }}">
<meta name="robots" content="index, follow">
<meta name="theme-color" content="#2563eb">

{{-- Icons --}}
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Open Graph --}}
<meta property="og:site_name" content="{{ $appName }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDesc }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDesc }}">
<meta name="twitter:image" content="{{ $ogImage }}">
