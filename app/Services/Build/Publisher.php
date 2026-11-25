<?php

namespace App\Services\Build;

use App\Models\Site;

class Publisher
{
    public function render(Site $site, bool $preview = false): string
    {
        return view('public.site', [
            'site' => $site,
            'sections' => $site->sections()->where('visible', true)->get(),
            'preview' => $preview,
        ])->render();
    }

    /** Freeze the current draft into a snapshot; later edits stay private until the next publish. */
    public function publish(Site $site): void
    {
        $site->update([
            'published_html' => $this->render($site),
            'published_at' => now(),
            'status' => 'published',
        ]);
    }

    public function unpublish(Site $site): void
    {
        $site->update(['published_html' => null, 'published_at' => null, 'status' => 'ready']);
    }
}
