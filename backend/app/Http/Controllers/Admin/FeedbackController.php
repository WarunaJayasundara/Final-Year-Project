<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeedbackController extends Controller
{
    private const RATING_DIMENSIONS = ['overall_rating', 'ui_rating', 'question_quality_rating', 'sinhala_quality_rating', 'usefulness_rating'];

    public function index(Request $request)
    {
        $query = Feedback::with('user:id,name,username')
            ->when(! $request->boolean('include_demo'), fn ($q) => $q->where('is_demo_feedback', false))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('min_rating'), fn ($q) => $q->where('overall_rating', '>=', (int) $request->input('min_rating')))
            ->when($request->filled('locale'), fn ($q) => $q->where('locale', $request->input('locale')))
            ->orderByDesc('created_at');

        $feedback = $query->paginate(20);

        return response()->json([
            'data' => collect($feedback->items())->map(fn (Feedback $f) => $this->present($f))->values(),
            'current_page' => $feedback->currentPage(),
            'last_page' => $feedback->lastPage(),
            'total' => $feedback->total(),
        ]);
    }

    /**
     * Averages per rating dimension + a 1-5 distribution of overall_rating.
     * "Common suggestions/complaints" is a real, honest word-frequency count
     * over comment/suggestion text (stopwords removed) - NOT sentiment
     * analysis or an LLM summary, since neither is something this project
     * can validate/claim. Labelled as such in the admin UI.
     */
    public function stats(Request $request)
    {
        $includeDemo = $request->boolean('include_demo');
        $base = Feedback::when(! $includeDemo, fn ($q) => $q->where('is_demo_feedback', false));

        $averages = [];
        foreach (self::RATING_DIMENSIONS as $dimension) {
            $avg = (clone $base)->whereNotNull($dimension)->avg($dimension);
            $averages[$dimension] = $avg !== null ? round((float) $avg, 2) : null;
        }

        $distribution = (clone $base)
            ->selectRaw('overall_rating, count(*) as total')
            ->groupBy('overall_rating')
            ->pluck('total', 'overall_rating');
        $distributionFilled = collect(range(1, 5))->mapWithKeys(fn ($star) => [$star => (int) ($distribution[$star] ?? 0)]);

        $totalCount = (clone $base)->count();
        $newCount = (clone $base)->where('status', 'new')->count();

        $texts = (clone $base)->whereNotNull('comment')->pluck('comment')
            ->merge((clone $base)->whereNotNull('suggestion')->pluck('suggestion'));

        return response()->json([
            'data' => [
                'total_count' => $totalCount,
                'new_count' => $newCount,
                'averages' => $averages,
                'distribution' => $distributionFilled,
                'top_terms' => $this->topTerms($texts),
            ],
        ]);
    }

    public function markReviewed(Request $request, Feedback $feedback)
    {
        $feedback->update([
            'status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($feedback->fresh())]);
    }

    /**
     * Anonymized by construction - user identity is never selected here at
     * all (no name/email/user_id column), matching the brief's "Export
     * anonymized feedback" requirement literally rather than redacting after
     * the fact. include_demo defaults to false, same convention as every
     * other research export (see ResearchExportService/§ task on demo-data
     * separation).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $includeDemo = $request->boolean('include_demo');

        $rows = Feedback::when(! $includeDemo, fn ($q) => $q->where('is_demo_feedback', false))
            ->orderBy('created_at')
            ->get();

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['created_at', 'locale', 'overall_rating', 'ui_rating', 'question_quality_rating', 'sinhala_quality_rating', 'usefulness_rating', 'comment', 'suggestion']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->created_at->toDateString(),
                    $row->locale,
                    $row->overall_rating,
                    $row->ui_rating,
                    $row->question_quality_rating,
                    $row->sinhala_quality_rating,
                    $row->usefulness_rating,
                    $row->comment,
                    $row->suggestion,
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, 'feedback-anonymized.csv', ['Content-Type' => 'text/csv']);
    }

    /** @param \Illuminate\Support\Collection<int, string> $texts */
    private function topTerms($texts, int $limit = 10): array
    {
        $stopwords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'and', 'or', 'but', 'to', 'of', 'in', 'on', 'for',
            'it', 'this', 'that', 'i', 'you', 'we', 'be', 'with', 'as', 'not', 'so', 'very', 'more', 'could', 'would',
            'my', 'me', 'have', 'has', 'had', 'can', 'do', 'does'];

        $counts = [];
        foreach ($texts as $text) {
            $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];
            foreach ($words as $word) {
                if (mb_strlen($word) < 4 || in_array($word, $stopwords, true)) {
                    continue;
                }
                $counts[$word] = ($counts[$word] ?? 0) + 1;
            }
        }

        arsort($counts);

        return collect($counts)->take($limit)->map(fn ($count, $word) => ['term' => $word, 'count' => $count])->values()->all();
    }

    private function present(Feedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'user_name' => $feedback->user?->name,
            'overall_rating' => $feedback->overall_rating,
            'ui_rating' => $feedback->ui_rating,
            'question_quality_rating' => $feedback->question_quality_rating,
            'sinhala_quality_rating' => $feedback->sinhala_quality_rating,
            'usefulness_rating' => $feedback->usefulness_rating,
            'comment' => $feedback->comment,
            'suggestion' => $feedback->suggestion,
            'locale' => $feedback->locale,
            'status' => $feedback->status,
            'is_demo_feedback' => $feedback->is_demo_feedback,
            'created_at' => $feedback->created_at->toIso8601String(),
        ];
    }
}
