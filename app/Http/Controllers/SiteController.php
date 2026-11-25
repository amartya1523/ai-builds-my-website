<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Source;
use App\Services\Build\BuildPipeline;
use App\Services\Ingest\LinkReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        return view('builder.index', ['sites' => Site::latest()->withCount('sections')->get()]);
    }

    public function store(Request $request, BuildPipeline $pipeline): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'links' => ['nullable', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:12'],
            'files.*' => ['file', 'max:10240', 'mimes:pdf,csv,tsv,txt,md,html,htm,jpg,jpeg,png,webp,gif'],
        ]);

        $site = Site::create([
            'business_name' => trim($data['business_name']),
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'slug' => Site::uniqueSlug($data['business_name'], $data['city'] ?? null),
        ]);

        $this->addSources($site, $data['links'] ?? '', $request->file('files', []), $data['note'] ?? null);

        @set_time_limit(180);
        $pipeline->run($site);

        return redirect()->route('sites.show', $site)->with('status', 'Sources read. Review the questions and the draft below.');
    }

    public function show(Site $site): View
    {
        $site->load(['sources' => fn ($q) => $q->orderBy('id'), 'questions' => fn ($q) => $q->orderByDesc('blocking')->orderBy('id')]);

        return view('builder.show', [
            'site' => $site,
            'sections' => $site->sections()->get(),
            'facts' => $site->facts()->with('source')->orderBy('field')->orderBy('id')->get(),
            'openQuestions' => $site->questions->where('status', 'open'),
            'aiCalls' => \App\Models\AiCall::where('site_id', $site->id)->latest()->limit(8)->get(),
        ]);
    }

    public function addSource(Request $request, Site $site, BuildPipeline $pipeline): RedirectResponse
    {
        $data = $request->validate([
            'links' => ['nullable', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:12'],
            'files.*' => ['file', 'max:10240', 'mimes:pdf,csv,tsv,txt,md,html,htm,jpg,jpeg,png,webp,gif'],
        ]);

        $this->addSources($site, $data['links'] ?? '', $request->file('files', []), $data['note'] ?? null);
        @set_time_limit(180);
        $pipeline->refreshFacts($site);

        return back()->with('status', 'New information read. Facts and questions were updated; regenerate sections to use it.');
    }

    public function rebuild(Site $site, \App\Services\Build\SiteWriter $writer): RedirectResponse
    {
        if ($site->openBlockingQuestions()->exists()) {
            return back()->with('error', 'Please answer the open questions first. We never guess facts.');
        }
        @set_time_limit(120);
        $writer->build($site);

        return back()->with('status', $site->fresh()->ai_status === 'ok'
            ? 'Draft rewritten from your confirmed facts.'
            : 'The AI is unavailable, so a plain template was used. Your facts are safe; try again later.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $site->delete();

        return redirect()->route('sites.index')->with('status', 'Site deleted.');
    }

    /** @param  UploadedFile[]  $files */
    private function addSources(Site $site, string $links, array $files, ?string $note): void
    {
        foreach (preg_split('/\s+/', $links) as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            if (! preg_match('#^https?://#i', $url)) {
                $url = 'https://'.$url;
            }
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $kind = LinkReader::kindFor($url);
            $site->sources()->create([
                'kind' => $kind,
                'label' => match ($kind) { 'google_business' => 'Google Business listing', 'social' => 'Social: '.parse_url($url, PHP_URL_HOST), default => 'Website: '.parse_url($url, PHP_URL_HOST) },
                'url' => $url,
            ]);
        }

        foreach ($files as $file) {
            $path = $file->store("uploads/{$site->id}", 'public');
            $site->sources()->create([
                'kind' => 'file',
                'label' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
            ]);
        }

        if ($note && trim($note) !== '') {
            $site->sources()->create(['kind' => 'owner_note', 'label' => 'Your note', 'content' => trim($note)]);
        }
    }
}
