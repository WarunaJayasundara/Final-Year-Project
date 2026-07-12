<?php

namespace App\Services\QuestionBank;

use Smalot\PdfParser\Parser;

/**
 * Extracts raw text from admin-uploaded reference PDFs (smalot/pdfparser -
 * pure PHP, no system binary dependency, unlike poppler/pdftoppm) and
 * suggests topics via a documented keyword-frequency match against
 * MindRise's existing taxonomy.
 *
 * IMPORTANT: `suggestTopics()` is an explainable heuristic, not an NLP/ML
 * claim. It counts occurrences of a fixed keyword list per taxonomy topic
 * and ranks by count. It cannot understand the document; it only tells the
 * admin "these keywords appeared this often," which the admin then uses to
 * decide what to generate questions about. This is deliberate - the project
 * does not claim automated deep topic extraction it hasn't actually built.
 */
class PdfIngestionService
{
    /**
     * category/subcategory => keyword list. Keys mirror `questions.subcategory`
     * values already in use (Bank2) plus the new Bank3 archetypes this
     * session adds (blood_relations, direction_sense, coding_decoding,
     * calendar_clock, seating_arrangement, data_interpretation,
     * statement_sufficiency).
     */
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
     * Segments extracted text into chapter-sized chunks using structural
     * heading markers actually observed across this project's uploaded
     * reference PDFs (Sinhala "පරිච්ඡේදය N:", Sinhala "N කොටස:", and
     * English "Chapter N"), then re-runs suggestTopics() per chunk instead
     * of once for the whole document.
     *
     * IMPORTANT: same honesty constraint as suggestTopics() - this is
     * regex-based structural segmentation, not a semantic table-of-contents
     * extraction. A document with no recognizable heading pattern degrades
     * to a single whole-document entry rather than fabricating structure
     * that isn't there.
     *
     * Deliberately narrower than an earlier version of this method: a
     * generic "N.N Title" numbered-subsection pattern and a standalone
     * "(N) Title" pattern were tried first and dropped after live testing
     * against this project's own uploaded PDFs showed them matching
     * mid-sentence numeric fragments (e.g. "2.5 miles long..." from a word
     * problem, or "(4) ANURADAPURA..." from a coding-decoding question) as
     * if they were real headings - PDF text extraction doesn't reliably
     * preserve "this is a new visual line" as "this is a new logical
     * line," so those two patterns had a real false-positive rate this
     * feature's honesty requirement can't accept. Only the 3 markers below
     * were confirmed low-false-positive-risk against real uploaded
     * documents.
     *
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

    /**
     * Very lightweight structural pattern detection - counts of common
     * question-paper markers (question numbering, MCQ option letters, answer
     * key sections) so the admin gets a rough sense of document structure
     * before deciding whether/how to use it. Not a claim of question
     * extraction.
     */
    public function detectPatterns(string $text): array
    {
        $unicodeSinhalaCharCount = preg_match_all('/[\x{0D80}-\x{0DFF}]/u', $text);

        return [
            'numbered_question_markers' => preg_match_all('/\b\d{1,4}[\.\)]\s/u', $text),
            'mcq_option_markers' => preg_match_all('/\b[a-dA-D][\.\)]\s/u', $text),
            'answer_key_mentions' => preg_match_all('/answer key|correct answer|පිළිතුරු/iu', $text),
            'approx_word_count' => str_word_count(preg_replace('/[^\x20-\x7E]/', ' ', $text)),
            // Real Unicode Sinhala codepoints found in the extracted text. Some
            // older Sri Lankan PDFs (seen in this project's own reference set)
            // use legacy non-Unicode Sinhala fonts (e.g. FM/Kaputa-style glyph
            // fonts) where the underlying character codes map to Latin-range
            // bytes - extraction then produces readable-looking but meaningless
            // mojibake instead of real Sinhala text. A near-zero count here on a
            // document known/titled to be in Sinhala is a strong signal that
            // extracted text is NOT reliable for that document and needs manual
            // review rather than automated topic/theory use.
            'unicode_sinhala_char_count' => $unicodeSinhalaCharCount,
        ];
    }
}
