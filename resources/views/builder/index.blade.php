@extends('builder.layout')

@section('content')
<section class="intro">
    <h1>Turn what you already have into a website.</h1>
    <p>Share your business name, links and files. We read them, search the web, ask you about anything unclear, and write a site using only facts we can point to.</p>
</section>

<div class="grid-2">
    <form class="card" method="POST" action="{{ route('sites.store') }}" enctype="multipart/form-data" data-busy="Reading files, links and the web… this takes 20–60 seconds">
        @csrf
        <h2>New website</h2>
        <div class="row-2">
            <label>Business name
                <input name="business_name" required value="{{ old('business_name') }}" placeholder="e.g. Sharma Sweets">
            </label>
            <label>City
                <input name="city" value="{{ old('city') }}" placeholder="e.g. Jaipur">
            </label>
        </div>
        <label>Links <span class="hint">website, Instagram / Facebook, Google Business listing — one per line</span>
            <textarea name="links" rows="4" placeholder="https://example.com&#10;https://instagram.com/yourshop&#10;https://maps.app.goo.gl/…">{{ old('links') }}</textarea>
        </label>
        <label>Files <span class="hint">PDF brochure, price list (PDF/CSV/TXT), photos</span>
            <input type="file" name="files[]" multiple accept=".pdf,.csv,.tsv,.txt,.md,.html,.htm,image/*">
        </label>
        <label>Anything else? <span class="hint">optional note in your own words</span>
            <textarea name="note" rows="3" placeholder="Opening hours, what makes you different, …">{{ old('note') }}</textarea>
        </label>
        <button class="btn btn-primary btn-lg" type="submit">Build my website</button>
        <p class="fineprint">Golden rule: nothing goes on your site unless a source says it or you confirm it.</p>
    </form>

    <div class="card">
        <h2>Your sites</h2>
        @forelse ($sites as $site)
            <a class="site-row" href="{{ route('sites.show', $site) }}">
                <div>
                    <strong>{{ $site->business_name }}</strong>
                    <span class="muted">{{ $site->city }}</span>
                </div>
                <span class="status status-{{ $site->status }}">{{ str_replace('_', ' ', $site->status) }}</span>
            </a>
        @empty
            <p class="muted">No sites yet. Build your first one on the left.</p>
        @endforelse

        <div class="how">
            <h3>How it works</h3>
            <ol>
                <li><b>Read</b> files, links and public web results. Blocked social pages are reported, not guessed.</li>
                <li><b>Check</b> every fact against its source. Hidden instructions in files are removed.</li>
                <li><b>Ask</b> when sources disagree or several businesses share your name.</li>
                <li><b>Write, edit, publish</b> to a public URL. Regenerate any section any time.</li>
            </ol>
        </div>
    </div>
</div>
@endsection
