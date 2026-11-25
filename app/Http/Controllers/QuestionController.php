<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Site;
use App\Services\Build\BuildPipeline;
use App\Services\Facts\QuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function answer(Request $request, Site $site, Question $question, QuestionService $questions, BuildPipeline $pipeline): RedirectResponse
    {
        abort_unless($question->site_id === $site->id, 404);

        $data = $request->validate([
            'option' => ['nullable', 'string', 'max:20'],
            'text' => ['nullable', 'string', 'max:2000'],
            'skip' => ['nullable', 'boolean'],
        ]);

        if (empty($data['skip']) && blank($data['option'] ?? null) && blank($data['text'] ?? null)) {
            return back()->with('error', 'Pick an option or type an answer.');
        }
        if (! empty($data['skip']) && $question->blocking) {
            return back()->with('error', 'This one is needed before we can build the site.');
        }

        @set_time_limit(120);
        $questions->answer($question, $data);
        $pipeline->continue($site->fresh());

        return back()->with('status', 'Thanks, saved.');
    }
}
