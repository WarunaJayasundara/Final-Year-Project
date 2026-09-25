<?php

namespace App\Services\QuestionBank;

use Smalot\PdfParser\Parser;

/** Extracts raw text from admin-uploaded reference PDFs (smalot/pdfparser - pure PHP, no system binary dependency. */
class PdfIngestionService
{
    /** category/subcategory => keyword list. */
    private const TAXONOMY_KEYWORDS = [
        'numerical_ability' => ['percentage', 'ratio', 'average', 'profit', 'loss', 'discount', 'interest', 'speed', 'distance', 'time', 'work', 'number series', 'fraction', 'decimal'],
        'data_interpretation' => ['table', 'bar chart', 'pie chart', 'graph', 'data interpretation', 'line chart', 'statistics'],
        'logical_reasoning' => ['syllogism', 'venn diagram', 'logic', 'deduction', 'induction', 'if then', 'premise', 'conclusion'],
        'verbal' => ['analogy', 'synonym', 'antonym', 'classification', 'odd one out', 'comprehension', 'vocabulary'],
        'blood_relations' => ['blood relation', 'family tree', 'father', 'mother', 'brother', 'sister', 'nephew', 'niece', 'in-law', 'kinship'],
        'direction_sense' => ['direction', 'north', 'south', 'east', 'west', 'compass', 'shortest distance', 'displacement'],
        'coding_decoding' => ['coding', 'decoding', 'cipher', 'code word', 'letter shift', 'substitution'],
        'calendar_clock' => ['calendar', 'day of the week', 'clock', 'angle between', 'leap year', 'hour hand', 'minute hand'],
        'seating_arrangement' => ['seating arrangement', 'sit around', 'row', 'rank from', 'circular arrangement', 'linear arrangement'],
        'statement_sufficiency' => ['statement', 'sufficient', 'conclusion follows', 'course of action', 'assumption'],
        'spatial_pattern' => ['mirror image', 'rotation', 'paper folding', 'cube', 'net', 'matrix', 'figure series', 'embedded figure'],
        'memory' => ['memory', 'recall', 'memorize', 'digit span', 'working memory'],
        'attention' => ['attention', 'concentration', 'spot the difference', 'selective attention'],
        'truth_teller_logic' => ['at least', 'more than', 'how many statements', 'true statements', 'interrogation'],
        'multi_constraint_seating' => ['taller than', 'older than', 'tallest', 'shortest', 'oldest', 'youngest'],
        'venn_consistency' => ['venn', 'does not contradict', 'all are', 'no are', 'some are'],
        'critical_reasoning_passage' => ['weaken', 'strengthen', 'credibility', 'confound', 'correlation', 'causation'],
    ];

    public function extractText(string $absolutePath): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($absolutePath);

        return $pdf->getText();
    }

    /**
     * @return array<int, array{topic: string, keyword_matches: int, matched_keywords: string[]}>
     */
    public function suggestTopics(string $text): array
    {
        $haystack = mb_strtolower($text);
        $results = [];

        foreach (self::TAXONOMY_KEYWORDS as $topic => $keywords) {
            $matched = [];
            $totalMatches = 0;

            foreach ($keywords as $keyword) {
                $count = substr_count($haystack, mb_strtolower($keyword));
                if ($count > 0) {
                    $matched[] = $keyword;
                    $totalMatches += $count;
                }
            }

            if ($totalMatches > 0) {
                $results[] = [
                    'topic' => $topic,
                    'keyword_matches' => $totalMatches,
                    'matched_keywords' => $matched,
                ];
            }
        }

        usort($results, fn (array $a, array $b) => $b['keyword_matches'] <=> $a['keyword_matches']);

        return $results;
    }

    /**
     * Segments extracted text into chapter-sized chunks using structural heading markers actually observed across this project's...
     * @return array<int, array{chapter: string, topics: array, excerpt_char_count: int}>
     */
    public function buildKnowledgeMap(string $text): array
    {
        $headingPattern = '/(?:^|\n)[ \t]*(?:'
            .'පරිච්ඡේදය\s*\d+[:\.]?\s*[^\n]{2,100}'
            .'|\d+\s+කොටස\s*[:\.]?\s*[^\n]{2,100}'
            .'|Chapter\s+\d+[:\.]?\s*[^\n]{2,100}'
            .')/u';

        preg_match_all($headingPattern, $text, $matches, PREG_OFFSET_CAPTURE);
        $headings = $matches[0];

        if (count($headings) < 2) {
            return [[
                'chapter' => 'Document (no chapter structure detected)',
                'topics' => array_slice($this->suggestTopics($text), 0, 5),
                'excerpt_char_count' => mb_strlen($text),
            ]];
        }

        $map = [];
        $total = count($headings);

        for ($i = 0; $i < $total; $i++) {
            [$headingText, $byteOffset] = $headings[$i];
            $bodyStart = $byteOffset + strlen($headingText);
            $bodyEnd = $i + 1 < $total ? $headings[$i + 1][1] : strlen($text);
            $body = substr($text, $bodyStart, max(0, $bodyEnd - $bodyStart));

            $map[] = [
                'chapter' => trim(preg_replace('/\s+/u', ' ', $headingText)),
                'topics' => array_slice($this->suggestTopics($body), 0, 5),
                'excerpt_char_count' => mb_strlen($body),
            ];
        }

        // Drop fragments too short to be a real chapter body - filters out
        // false-positive heading matches (e.g. two headings landing back to
        // back with no real content between them).
        $map = array_values(array_filter($map, fn (array $c) => $c['excerpt_char_count'] >= 80));

        // Cap to a sane number - a false-positive-heavy match on a document
        // with unusual formatting should degrade gracefully, not flood the
        // admin UI with hundreds of near-duplicate "chapters."
        return array_slice($map, 0, 40);
    }

    /** Very lightweight structural pattern detection - counts of common question-paper markers (question numbering, MCQ option letters. */
    public function detectPatterns(string $text): array
    {
        $unicodeSinhalaCharCount = preg_match_all('/[\x{0D80}-\x{0DFF}]/u', $text);

        return [
            'numbered_question_markers' => preg_match_all('/\b\d{1,4}[\.\)]\s/u', $text),
            'mcq_option_markers' => preg_match_all('/\b[a-dA-D][\.\)]\s/u', $text),
            'answer_key_mentions' => preg_match_all('/answer key|correct answer|පිළිතුරු/iu', $text),
            'approx_word_count' => str_word_count(preg_replace('/[^\x20-\x7E]/', ' ', $text)),
            // Real Unicode Sinhala codepoints found in the extracted text.
            'unicode_sinhala_char_count' => $unicodeSinhalaCharCount,
        ];
    }
}
