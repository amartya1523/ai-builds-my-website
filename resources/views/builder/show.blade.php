@extends('builder.layout')

@section('title', $site->business_name.' · AI Builds My Website')

@section('content')
<div class="site-head">
    <div>
        <a href="{{ route('sites.index') }}" class="back">← All sites</a>
        <h1>{{ $site->business_name }} <span class="muted">{{ $site->city }}</span></h1>
        <div class="chips">
            <span class="status status-{{ $site->status }}">{{ str_replace('_', ' ', $site->status) }}</span>
            @if ($site->ai_status === 'fallback')
                <span class="pill pill-warn" title="{{ $site->ai_error }}">AI unavailable · template copy</span>
            @elseif ($site->ai_status === 'ok')
                <span class="pill pill-ok">AI ok</span>
            @endif
            @if ($site->published_at)
                <a class="pill pill-ok" href="{{ $site->publicUrl() }}" target="_blank">Live: {{ $site->publicUrl() }}</a>
            @endif
        </div>
        @if ($site->ai_error)
            <p class="muted small">{{ $site->ai_error }}</p>
        @endif
    </div>
    <div class="actions">
        @if ($sections->isNotEmpty())
            <a class="btn" href="{{ route('sites.preview', $site) }}" target="_blank">Preview</a>
        @endif
        <form method="POST" action="{{ route('sites.rebuild', $site) }}" data-busy="Rewriting the draft from your confirmed facts…">@csrf
            <button class="btn" {{ $openQuestions->where('blocking', true)->isNotEmpty() ? 'disabled' : '' }}>Rewrite all</button>
        </form>
        @if ($site->published_at)
            <form method="POST" action="{{ route('sites.publish', $site) }}">@csrf<button class="btn btn-primary">Publish changes</button></form>
            <form method="POST" action="{{ route('sites.unpublish', $site) }}">@csrf<button class="btn btn-ghost">Unpublish</button></form>
        @else
            <form method="POST" action="{{ route('sites.publish', $site) }}">@csrf
                <button class="btn btn-primary" {{ ($openQuestions->where('blocking', true)->isNotEmpty() || $sections->isEmpty()) ? 'disabled' : '' }}>Publish</button>
            </form>
        @endif
    </div>
</div>

<div class="workspace">
    <aside class="side">
        {{-- QUESTIONS --}}
        @if ($openQuestions->isNotEmpty())
            <div class="card card-ask" id="questions">
                <h2>We need your help <span class="count">{{ $openQuestions->count() }}</span></h2>
                <p class="muted small">We never guess. Answer these and the draft updates.</p>
                @foreach ($openQuestions as $q)
                    <form class="question" method="POST" action="{{ route('questions.answer', [$site, $q]) }}" data-busy="Saving your answer…">
                        @csrf
                        <div class="q-head">
                            @if ($q->blocking)<span class="pill pill-warn">needed</span>@else<span class="pill pill-muted">optional</span>@endif
                            <span class="q-kind">{{ str_replace('_', ' ', $q->kind) }}</span>
                        </div>
                        <p class="q-prompt">{{ $q->prompt }}</p>

                        @if ($q->kind === 'choose_identity')
                            @foreach ($q->options as $i => $c)
                                <label class="option"><input type="radio" name="option" value="{{ $i }}">
                                    <span><b>{{ $c['name'] }}</b> <span class="muted">{{ $c['category'] }}</span><br><small>{{ $c['address'] }}</small>
                                    @if (!empty($c['osm_url']))<br><a href="{{ $c['osm_url'] }}" target="_blank" class="small">view on map ↗</a>@endif</span>
                                </label>
                            @endforeach
                            <label class="option"><input type="radio" name="option" value="none"><span>None of these is my business</span></label>
                        @elseif ($q->kind === 'resolve_conflict')
                            @foreach ($q->options as $i => $o)
                                <label class="option"><input type="radio" name="option" value="{{ $i }}">
                                    <span><b>{{ $o['value'] }}</b><br><small class="muted">from {{ implode(', ', $o['sources']) }}</small></span>
                                </label>
                            @endforeach
                            <input name="text" placeholder="…or type the correct value">
                        @elseif ($q->kind === 'confirm')
                            @php $o = $q->options[0] ?? [] @endphp
                            @if (!empty($o['evidence']))<blockquote>{{ \Illuminate\Support\Str::limit($o['evidence'], 220) }}</blockquote>@endif
                            @if (!empty($o['url']))<a href="{{ $o['url'] }}" target="_blank" class="small">source ↗</a>@endif
                            <div class="btn-row">
                                <button class="btn btn-sm btn-primary" name="option" value="yes">Yes, use it</button>
                                <button class="btn btn-sm" name="option" value="no">No</button>
                            </div>
                        @else
                            <textarea name="text" rows="2" placeholder="Type your answer"></textarea>
                        @endif

                        @if ($q->kind !== 'confirm')
                            <div class="btn-row">
                                <button class="btn btn-sm btn-primary">Save</button>
                                @unless ($q->blocking)<button class="btn btn-sm btn-ghost" name="skip" value="1">Skip</button>@endunless
                            </div>
                        @endif
                    </form>
                @endforeach
            </div>
        @endif

        {{-- SOURCES --}}
        <div class="card">
            <h2>Sources <span class="count">{{ $site->sources->count() }}</span></h2>
            @foreach ($site->sources as $s)
                <div class="source">
                    <div class="source-top">
                        <span class="kind kind-{{ $s->kind }}">{{ str_replace('_', ' ', $s->kind) }}</span>
                        <span class="state state-{{ $s->status }}">{{ $s->status }}</span>
                        @if ($s->injection_flagged)<span class="state state-blocked" title="Hidden instructions were removed">⚠ instructions removed</span>@endif
                    </div>
                    <div class="source-label">
                        @if ($s->url)<a href="{{ $s->url }}" target="_blank">{{ \Illuminate\Support\Str::limit($s->label, 60) }}</a>@else{{ \Illuminate\Support\Str::limit($s->label, 60) }}@endif
                    </div>
                    @if ($s->status_note)<p class="small muted">{{ $s->status_note }}</p>@endif
                    @if (!empty($s->meta['removed_instructions']))
                        <details class="small"><summary>What we ignored</summary>
                            @foreach ($s->meta['removed_instructions'] as $line)<blockquote class="bad">{{ \Illuminate\Support\Str::limit($line, 200) }}</blockquote>@endforeach
                        </details>
                    @endif
                </div>
            @endforeach

            <details class="add-more">
                <summary>+ Add more information</summary>
                <form method="POST" action="{{ route('sites.sources', $site) }}" enctype="multipart/form-data" data-busy="Reading the new information…">
                    @csrf
                    <textarea name="links" rows="2" placeholder="More links, one per line"></textarea>
                    <input type="file" name="files[]" multiple>
                    <textarea name="note" rows="3" placeholder="Paste your Instagram bio, hours, anything we missed"></textarea>
                    <button class="btn btn-sm">Add &amp; re-check</button>
                </form>
            </details>
        </div>

        {{-- FACTS --}}
        <div class="card">
            <h2>Facts <span class="count">{{ $facts->where('status', 'accepted')->count() }} used</span></h2>
            <p class="muted small">Only <b>used</b> facts can appear on the site. Hover a value to see its evidence.</p>
            <table class="facts">
                @foreach ($facts->whereIn('status', ['accepted', 'candidate']) as $f)
                    <tr class="fact-{{ $f->status }}">
                        <td class="f-field">{{ str_replace('_', ' ', $f->field) }}</td>
                        <td class="f-value" title="Evidence: {{ $f->evidence }}">{{ \Illuminate\Support\Str::limit($f->value, 90) }}
                            <div class="f-src">{{ $f->origin === 'owner' ? 'you' : ($f->source?->kind ? str_replace('_', ' ', $f->source->kind) : '') }}{{ $f->origin === 'owner_confirmed' ? ' · confirmed by you' : '' }}{{ $f->origin === 'ai' ? ' · AI, quote verified' : '' }}</div>
                        </td>
                        <td><span class="state state-{{ $f->status === 'accepted' ? 'ok' : 'pending' }}">{{ $f->status === 'accepted' ? 'used' : 'unconfirmed' }}</span></td>
                    </tr>
                @endforeach
            </table>
            @php $rejected = $facts->where('status', 'rejected')->count() @endphp
            @if ($rejected)<p class="small muted">{{ $rejected }} value(s) set aside after your answers.</p>@endif
        </div>

        @if ($aiCalls->isNotEmpty())
            <details class="card small">
                <summary><b>AI activity</b></summary>
                @foreach ($aiCalls as $c)
                    <div class="ai-call"><span class="state state-{{ $c->ok ? 'ok' : 'failed' }}">{{ $c->ok ? 'ok' : 'failed' }}</span> {{ $c->purpose }} · {{ $c->latency_ms }} ms @if($c->error)<span class="muted">— {{ \Illuminate\Support\Str::limit($c->error, 80) }}</span>@endif</div>
                @endforeach
            </details>
        @endif
    </aside>

    <section class="editor">
        @if ($sections->isEmpty())
            <div class="card empty">
                <h2>Draft not written yet</h2>
                <p class="muted">Answer the questions on the left. As soon as nothing essential is unclear, we write the site.</p>
            </div>
        @endif

        @foreach ($sections as $section)
            @php $c = $section->content; @endphp
            <article class="card section {{ $section->visible ? '' : 'is-hidden' }}" id="section-{{ $section->id }}">
                <header class="section-head">
                    <div>
                        <h3>{{ $section->label() }}</h3>
                        <span class="by by-{{ $section->written_by }}">{{ ['ai' => 'written by AI from your facts', 'template' => 'template (AI was down)', 'owner' => 'edited by you', 'facts' => 'copied from your facts'][$section->written_by] ?? $section->written_by }}</span>
                    </div>
                    <div class="section-tools">
                        <form method="POST" action="{{ route('sections.move', [$site, $section]) }}">@csrf<input type="hidden" name="dir" value="up"><button class="icon" title="Move up">↑</button></form>
                        <form method="POST" action="{{ route('sections.move', [$site, $section]) }}">@csrf<input type="hidden" name="dir" value="down"><button class="icon" title="Move down">↓</button></form>
                        <form method="POST" action="{{ route('sections.toggle', [$site, $section]) }}">@csrf<button class="icon" title="Show / hide">{{ $section->visible ? 'Hide' : 'Show' }}</button></form>
                    </div>
                </header>

                <div class="section-preview">
                    @include('builder.partials.section-preview', ['type' => $section->type, 'c' => $c])
                </div>

                @if (!empty($section->removed_claims))
                    <details class="removed">
                        <summary>{{ count($section->removed_claims) }} AI sentence(s) removed — not backed by your sources</summary>
                        @foreach ($section->removed_claims as $claim)<blockquote class="bad">{{ $claim }}</blockquote>@endforeach
                    </details>
                @endif

                <div class="section-actions">
                    <details>
                        <summary class="btn btn-sm">Edit</summary>
                        <form method="POST" action="{{ route('sections.update', [$site, $section]) }}" class="edit-form">
                            @csrf @method('PUT')
                            @foreach ($c as $key => $value)
                                @continue(in_array($key, ['image', 'images']))
                                <label>{{ ucfirst(str_replace('_', ' ', $key)) }}
                                    @if (is_array($value))
                                        <span class="hint">one per line{{ isset($value[0]) && is_array($value[0]) ? ' · '.implode(' | ', array_keys($value[0])) : '' }}</span>
                                        <textarea name="content[{{ $key }}]" rows="{{ min(12, max(3, count($value) + 1)) }}">{{ collect($value)->map(fn ($v) => is_array($v) ? implode(' | ', $v) : $v)->implode("\n") }}</textarea>
                                    @elseif (mb_strlen((string) $value) > 70 || $key === 'body')
                                        <textarea name="content[{{ $key }}]" rows="4">{{ $value }}</textarea>
                                    @else
                                        <input name="content[{{ $key }}]" value="{{ $value }}">
                                    @endif
                                </label>
                            @endforeach
                            <button class="btn btn-sm btn-primary">Save</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('sections.regenerate', [$site, $section]) }}" class="regen" data-busy="Regenerating {{ strtolower($section->label()) }}…">
                        @csrf
                        @if (in_array($section->type, ['hero', 'about', 'services']))
                            <input name="instruction" placeholder="Optional style: shorter, more formal…">
                        @endif
                        <button class="btn btn-sm">↻ Regenerate</button>
                    </form>
                </div>
            </article>
        @endforeach
    </section>
</div>
@endsection
