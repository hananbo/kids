<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Support\Catalog\QuestionContent;
use App\Support\Catalog\QuestionMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionMediaController extends Controller
{
    public function __invoke(Request $request, Question $question, string $field): BinaryFileResponse
    {
        abort_unless($request->user()?->current_team_id === $question->team_id, 404);
        Gate::authorize('update', $question->team);

        return QuestionMedia::response(QuestionContent::field($question->content[$field] ?? null));
    }
}
