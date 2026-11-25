<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\Build\Publisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class PublishController extends Controller
{
    public function preview(Site $site, Publisher $publisher): Response
    {
        return response($publisher->render($site, preview: true));
    }

    public function publish(Site $site, Publisher $publisher): RedirectResponse
    {
        if ($site->openBlockingQuestions()->exists()) {
            return back()->with('error', 'Answer the open questions before publishing.');
        }
        if ($site->sections()->doesntExist()) {
            return back()->with('error', 'Nothing to publish yet.');
        }
        $publisher->publish($site);

        return back()->with('status', 'Published at '.$site->publicUrl());
    }

    public function unpublish(Site $site, Publisher $publisher): RedirectResponse
    {
        $publisher->unpublish($site);

        return back()->with('status', 'Site taken offline.');
    }

    public function show(string $slug): Response
    {
        $site = Site::where('slug', $slug)->whereNotNull('published_html')->firstOrFail();

        return response($site->published_html)->header('Cache-Control', 'public, max-age=60');
    }
}
