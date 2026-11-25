@php
    $by = $sections->keyBy('type');
    $hero = $by['hero']->content ?? [];
    $contact = $by['contact']->content ?? [];
    $accent = $site->accent_color ?: '#0f766e';
    $tel = !empty($contact['phone']) ? preg_replace('/[^\d+]/', '', $contact['phone']) : null;
    $mapQuery = $contact['address'] ?? trim($site->business_name.' '.$site->city);
    $lat = $site->identity['lat'] ?? null; $lng = $site->identity['lng'] ?? null;
    $heroImg = !empty($hero['image']) ? asset('storage/'.$hero['image']) : null;
    $nav = collect(['about' => 'About', 'services' => 'Services', 'pricing' => 'Prices', 'gallery' => 'Gallery', 'hours' => 'Hours', 'contact' => 'Contact'])->filter(fn ($l, $t) => $by->has($t));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $site->business_name }}{{ $site->city ? ' · '.$site->city : '' }}</title>
<meta name="description" content="{{ \Illuminate\Support\Str::limit($hero['subheadline'] ?? ($by['about']->content['body'] ?? $site->business_name), 155) }}">
<meta property="og:title" content="{{ $site->business_name }}">
<meta property="og:description" content="{{ \Illuminate\Support\Str::limit($hero['subheadline'] ?? '', 155) }}">
@if ($heroImg)<meta property="og:image" content="{{ $heroImg }}">@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script type="application/ld+json">{!! json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $site->business_name,
    'telephone' => $contact['phone'] ?? null, 'email' => $contact['email'] ?? null, 'address' => $contact['address'] ?? null,
    'url' => $site->published_at ? $site->publicUrl() : null,
    'openingHours' => $by['hours']->content['lines'] ?? null,
    'sameAs' => collect($by['social']->content['links'] ?? [])->pluck('url')->all() ?: null,
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
<style>
:root{--accent:{{ $accent }};--ink:#14201f;--muted:#5b6866;--bg:#fbfaf7;--card:#fff;--line:#e8e4dc;--radius:18px}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font-family:Inter,system-ui,sans-serif;color:var(--ink);background:var(--bg);line-height:1.65;-webkit-font-smoothing:antialiased}
a{color:var(--accent)}
.wrap{max-width:1080px;margin:0 auto;padding:0 24px}
.preview-bar{background:#111;color:#fff;font-size:13px;text-align:center;padding:8px}
nav.top{position:sticky;top:0;z-index:10;background:rgba(251,250,247,.86);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
nav.top .wrap{display:flex;align-items:center;justify-content:space-between;height:64px;gap:16px}
.logo{font-family:Fraunces,Georgia,serif;font-weight:700;font-size:20px;color:var(--ink);text-decoration:none;white-space:nowrap}
.links{display:flex;gap:22px;font-size:14px}.links a{color:var(--muted);text-decoration:none}.links a:hover{color:var(--ink)}
.btn{display:inline-flex;align-items:center;gap:8px;background:var(--accent);color:#fff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:600;font-size:15px;box-shadow:0 6px 20px -8px var(--accent)}
.btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--line);box-shadow:none}
.hero{position:relative;padding:96px 0 88px;overflow:hidden}
.hero:before{content:"";position:absolute;inset:-40% -10% auto auto;width:620px;height:620px;border-radius:50%;background:radial-gradient(circle,color-mix(in srgb,var(--accent) 22%,transparent),transparent 70%)}
.hero.has-img{color:#fff;background:#111 center/cover no-repeat}
.hero.has-img:after{content:"";position:absolute;inset:0;background:linear-gradient(100deg,rgba(0,0,0,.72),rgba(0,0,0,.25))}
.hero .wrap{position:relative;z-index:1}
.eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:12px;font-weight:600;color:var(--accent)}
.hero.has-img .eyebrow{color:#fff;opacity:.85}
h1{font-family:Fraunces,Georgia,serif;font-size:clamp(38px,6vw,68px);line-height:1.04;margin:14px 0 18px;max-width:14ch;letter-spacing:-.01em}
.lead{font-size:clamp(17px,2vw,20px);max-width:56ch;color:var(--muted);margin:0 0 30px}
.hero.has-img .lead{color:#eee}
.cta{display:flex;gap:12px;flex-wrap:wrap}
section.block{padding:80px 0;border-top:1px solid var(--line)}
h2{font-family:Fraunces,Georgia,serif;font-size:clamp(28px,3.4vw,40px);margin:0 0 24px;line-height:1.15}
.about p{font-size:19px;max-width:62ch;color:#2b3634}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:22px 22px 20px}
.card h3{margin:0 0 6px;font-size:17px}.card p{margin:0;color:var(--muted);font-size:15px}
.card .dot{width:34px;height:34px;border-radius:10px;background:color-mix(in srgb,var(--accent) 14%,#fff);display:grid;place-items:center;color:var(--accent);font-weight:700;margin-bottom:14px}
.prices{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden}
.prices .row{display:flex;justify-content:space-between;gap:16px;padding:14px 22px;border-bottom:1px dashed var(--line)}
.prices .row:last-child{border:0}.prices .price{font-weight:600;white-space:nowrap}
.note{font-size:13px;color:var(--muted);margin-top:12px}
.gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
.gallery img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:14px;display:block}
.two{display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:start}
.hours div{padding:10px 0;border-bottom:1px solid var(--line)}
.contact-list{display:grid;gap:14px;font-size:17px}
.contact-list .k{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}
.map{width:100%;height:300px;border:0;border-radius:var(--radius);background:#eee}
.social{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
.social a{border:1px solid var(--line);padding:8px 16px;border-radius:999px;text-decoration:none;color:var(--ink);font-size:14px;background:#fff}
footer{padding:36px 0;color:var(--muted);font-size:13px;border-top:1px solid var(--line)}
footer .wrap{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.float-call{position:fixed;right:18px;bottom:18px;z-index:20;display:none}
@media (max-width:760px){.links{display:none}.two{grid-template-columns:1fr}.hero{padding:64px 0}.float-call{display:inline-flex}section.block{padding:56px 0}}
</style>
</head>
<body>
@if ($preview)<div class="preview-bar">Preview — not public yet. Publish from the editor to go live.</div>@endif

<nav class="top"><div class="wrap">
    <a class="logo" href="#top">{{ $site->business_name }}</a>
    <div class="links">@foreach ($nav as $t => $l)<a href="#{{ $t }}">{{ $l }}</a>@endforeach</div>
    @if ($tel)<a class="btn" href="tel:{{ $tel }}">Call</a>@elseif(!empty($contact['email']))<a class="btn" href="mailto:{{ $contact['email'] }}">Email</a>@endif
</div></nav>

@foreach ($sections as $section)
    @php $c = $section->content; @endphp
    @switch($section->type)
        @case('hero')
            <header id="top" class="hero {{ $heroImg ? 'has-img' : '' }}" @if($heroImg) style="background-image:url('{{ $heroImg }}')" @endif>
                <div class="wrap">
                    @if ($site->city)<div class="eyebrow">{{ $site->city }}</div>@endif
                    <h1>{{ $c['headline'] ?? $site->business_name }}</h1>
                    @if (!empty($c['subheadline']))<p class="lead">{{ $c['subheadline'] }}</p>@endif
                    <div class="cta">
                        @if ($tel)<a class="btn" href="tel:{{ $tel }}">{{ $c['cta_label'] ?? 'Call now' }}</a>
                        @elseif (!empty($contact['email']))<a class="btn" href="mailto:{{ $contact['email'] }}">{{ $c['cta_label'] ?? 'Email us' }}</a>@endif
                        @if (!empty($contact['address']))<a class="btn ghost" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapQuery) }}" target="_blank" rel="noopener">Get directions</a>@endif
                    </div>
                </div>
            </header>
            @break
        @case('about')
            <section class="block about" id="about"><div class="wrap">
                <h2>{{ $c['title'] ?? 'About us' }}</h2>
                <p>{{ $c['body'] ?? '' }}</p>
            </div></section>
            @break
        @case('services')
            <section class="block" id="services"><div class="wrap">
                <h2>{{ $c['title'] ?? 'What we offer' }}</h2>
                <div class="grid">
                    @foreach ($c['items'] ?? [] as $i => $item)
                        <div class="card"><div class="dot">{{ mb_strtoupper(mb_substr($item['name'], 0, 1)) }}</div><h3>{{ $item['name'] }}</h3>@if(!empty($item['description']))<p>{{ $item['description'] }}</p>@endif</div>
                    @endforeach
                </div>
            </div></section>
            @break
        @case('pricing')
            <section class="block" id="pricing"><div class="wrap">
                <h2>{{ $c['title'] ?? 'Prices' }}</h2>
                <div class="prices">@foreach ($c['items'] ?? [] as $item)<div class="row"><span>{{ $item['name'] }}</span><span class="price">{{ $item['price'] }}</span></div>@endforeach</div>
                @if (!empty($c['note']))<p class="note">{{ $c['note'] }}</p>@endif
            </div></section>
            @break
        @case('gallery')
            <section class="block" id="gallery"><div class="wrap">
                <h2>{{ $c['title'] ?? 'Gallery' }}</h2>
                <div class="gallery">@foreach ($c['images'] ?? [] as $img)<img src="{{ asset('storage/'.$img) }}" alt="{{ $site->business_name }}" loading="lazy">@endforeach</div>
            </div></section>
            @break
        @case('hours')
            <section class="block" id="hours"><div class="wrap">
                <h2>{{ $c['title'] ?? 'Opening hours' }}</h2>
                <div class="hours">@foreach ($c['lines'] ?? [] as $l)<div>{{ $l }}</div>@endforeach</div>
            </div></section>
            @break
        @case('contact')
            <section class="block" id="contact"><div class="wrap two">
                <div>
                    <h2>{{ $c['title'] ?? 'Get in touch' }}</h2>
                    <div class="contact-list">
                        @if (!empty($c['phone']))<div><span class="k">Phone</span><a href="tel:{{ preg_replace('/[^\d+]/', '', $c['phone']) }}">{{ $c['phone'] }}</a></div>@endif
                        @if (!empty($c['email']))<div><span class="k">Email</span><a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></div>@endif
                        @if (!empty($c['address']))<div><span class="k">Address</span>{{ $c['address'] }}</div>@endif
                        @if (!empty($c['website']))<div><span class="k">Website</span><a href="{{ $c['website'] }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($c['website'], '/')) }}</a></div>@endif
                    </div>
                    @if ($by->has('social') && $by['social']->visible)
                        <div class="social">@foreach ($by['social']->content['links'] ?? [] as $l)<a href="{{ $l['url'] }}" target="_blank" rel="noopener">{{ $l['label'] }}</a>@endforeach</div>
                    @endif
                </div>
                @if ($lat && $lng)
                    <iframe class="map" loading="lazy" src="https://www.openstreetmap.org/export/embed.html?bbox={{ $lng - 0.006 }},{{ $lat - 0.004 }},{{ $lng + 0.006 }},{{ $lat + 0.004 }}&layer=mapnik&marker={{ $lat }},{{ $lng }}"></iframe>
                @elseif (!empty($c['address']))
                    <iframe class="map" loading="lazy" src="https://maps.google.com/maps?q={{ urlencode($mapQuery) }}&output=embed"></iframe>
                @endif
            </div></section>
            @break
        @case('social')
            @unless ($by->has('contact'))
                <section class="block"><div class="wrap"><div class="social">@foreach ($c['links'] ?? [] as $l)<a href="{{ $l['url'] }}" target="_blank" rel="noopener">{{ $l['label'] }}</a>@endforeach</div></div></section>
            @endunless
            @break
    @endswitch
@endforeach

<footer><div class="wrap">
    <span>© {{ now()->year }} {{ $site->business_name }}{{ $site->city ? ', '.$site->city : '' }}</span>
    <span>Every detail on this page comes from the business's own information.</span>
</div></footer>
@if ($tel)<a class="btn float-call" href="tel:{{ $tel }}">Call</a>@endif
</body>
</html>
