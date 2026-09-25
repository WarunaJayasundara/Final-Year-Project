<?php

namespace App\Services\QuestionBank;

/** Script-level integrity checks for text that is meant to be Sinhala. */
class SinhalaTextGuard
{
    /** Every script a language model has been seen to slip into Sinhala text: the Indic blocks below Sinhala (Devanagari .. Malayalam). */
    private const STRAY_SCRIPT = '/[\x{0900}-\x{0D7F}\x{0E00}-\x{0EFF}\x{0400}-\x{052F}\x{0590}-\x{08FF}\x{1100}-\x{11FF}\x{1200}-\x{139F}\x{2E80}-\x{9FFF}\x{AC00}-\x{D7AF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}\x{FFFD}]/u';

    /** A dependent sign (virama, vowel sign, anusvara) needs a consonant before it, never a space/digit/punctuation. */
    private const ORPHAN_SIGN = '/(?:^|[\s\d\p{P}])[\x{0D82}\x{0D83}\x{0DCA}\x{0DCF}-\x{0DDF}\x{0DF2}\x{0DF3}]/u';

    /**
     * @param  bool  $requireSinhala  true for sentences (question, explanation); false for short answer options,
     *                                which may legitimately be a number, a letter or an English loanword.
     * @return array<int, array{severity:string, code:string, message:string}>
     */
    public function inspect(string $text, ?string $english = null, bool $requireSinhala = true): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $issues = [];

        if (preg_match(self::STRAY_SCRIPT, $text)) {
            $issues[] = $this->issue('error', 'stray_script', 'Contains characters from another script or a broken character. The text is corrupted.');
        }

        if (preg_match('/\?{3,}/', $text)) {
            $issues[] = $this->issue('error', 'encoding_loss', 'Contains a run of question marks, which usually means characters were lost.');
        }

        if (preg_match(self::ORPHAN_SIGN, $text)) {
            $issues[] = $this->issue('error', 'orphan_sign', 'A vowel sign or hal mark is not attached to a letter. The text is likely garbled.');
        }

        // Quoted material and ALL-CAPS tokens (a word list to count letters in, "AND", "XOR") are
        // legitimately English inside a Sinhala sentence, so they don't count against it.
        $prose = preg_replace(['/"[^"]*"/u', "/'[^']{2,}'/u", '/\b[A-Z]{2,}\b/u'], ' ', $text);

        $letters = preg_match_all('/\p{L}/u', $text);
        $sinhala = preg_match_all('/[\x{0D85}-\x{0DC6}]/u', $text);
        $proseLetters = preg_match_all('/\p{L}/u', $prose);
        $latin = preg_match_all('/[A-Za-z]/u', $prose);

        if ($requireSinhala && $letters > 0 && $sinhala === 0) {
            $issues[] = $this->issue('error', 'no_sinhala', 'Contains no Sinhala letters.');
        } elseif ($requireSinhala && $proseLetters > 0 && $latin / $proseLetters > 0.6) {
            $issues[] = $this->issue('warning', 'mostly_latin', 'Mostly English letters. Check that the Sinhala was actually written.');
        }

        if ($english !== null && $requireSinhala && mb_strtolower(trim($english)) === mb_strtolower($text)) {
            $issues[] = $this->issue('warning', 'same_as_english', 'Identical to the English text; it was probably not translated.');
        }

        return $issues;
    }

    /** @param  array<int, array{severity:string}>  $issues */
    public static function hasError(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'error') {
                return true;
            }
        }

        return false;
    }

    private function issue(string $severity, string $code, string $message): array
    {
        return ['severity' => $severity, 'code' => $code, 'message' => $message];
    }
}
