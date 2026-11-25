@switch($type)
    @case('hero')
        <div class="pv-hero">
            <div class="pv-h1">{{ $c['headline'] ?? '' }}</div>
            <div class="pv-sub">{{ $c['subheadline'] ?? '' }}</div>
            @if (!empty($c['cta_label']))<span class="pv-cta">{{ $c['cta_label'] }}</span>@endif
        </div>
        @break
    @case('about')
        <div class="pv-title">{{ $c['title'] ?? '' }}</div>
        <p>{{ $c['body'] ?? '' }}</p>
        @break
    @case('services')
        <div class="pv-title">{{ $c['title'] ?? '' }}</div>
        <ul class="pv-list">@foreach ($c['items'] ?? [] as $i)<li><b>{{ $i['name'] }}</b>@if(!empty($i['description'])) — {{ $i['description'] }}@endif</li>@endforeach</ul>
        @break
    @case('pricing')
        <div class="pv-title">{{ $c['title'] ?? '' }}</div>
        <table class="pv-prices">@foreach ($c['items'] ?? [] as $i)<tr><td>{{ $i['name'] }}</td><td>{{ $i['price'] }}</td></tr>@endforeach</table>
        @break
    @case('gallery')
        <div class="pv-gallery">@foreach ($c['images'] ?? [] as $img)<img src="{{ asset('storage/'.$img) }}" alt="">@endforeach</div>
        @break
    @case('hours')
        <div class="pv-title">{{ $c['title'] ?? '' }}</div>
        @foreach ($c['lines'] ?? [] as $l)<div>{{ $l }}</div>@endforeach
        @break
    @case('contact')
        <div class="pv-title">{{ $c['title'] ?? '' }}</div>
        @foreach (['phone' => '☎', 'email' => '✉', 'address' => '⌖', 'website' => '↗'] as $k => $icon)
            @if (!empty($c[$k]))<div>{{ $icon }} {{ $c[$k] }}</div>@endif
        @endforeach
        @break
    @case('social')
        <div class="pv-social">@foreach ($c['links'] ?? [] as $l)<a href="{{ $l['url'] }}" target="_blank">{{ $l['label'] }}</a>@endforeach</div>
        @break
@endswitch
