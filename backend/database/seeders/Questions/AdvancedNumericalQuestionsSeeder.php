<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

/**
 * Harder numerical-ability questions modelled directly on real Sri Lankan
 * competitive exam papers (G.C.E. A/L Common General Test, SLAS aptitude
 * tests, university entrance tests) supplied as reference material: work &
 * time, simple interest, age problems, relative speed, and ratio-increase
 * problems. These are layered on top of the existing 80/level bank,
 * concentrated at levels 3-5 for the 20-30 year old competitive-exam
 * candidate audience.
 */
class AdvancedNumericalQuestionsSeeder extends Seeder
{
    use BuildsQuestions;

    private const PER_LEVEL = ['3' => 4, '4' => 5, '5' => 5];

    /** [a, b, c, pA, pB, pC, "newRatio"] - hand-verified: a*(1+pA/100) : b*(1+pB/100) : c*(1+pC/100) reduced. */
    private const RATIO_TUPLES = [
        [5, 7, 8, 40, 50, 75, '2:3:4'],
        [3, 5, 6, 100, 60, 50, '6:8:9'],
        [2, 3, 5, 50, 100, 20, '1:2:2'],
        [8, 5, 2, 25, 60, 150, '10:8:5'],
        [3, 4, 5, 100, 50, 20, '1:1:1'],
        [5, 6, 10, 20, 50, 40, '6:9:14'],
        [4, 10, 5, 50, 20, 40, '6:12:7'],
        [2, 5, 3, 150, 20, 100, '5:6:6'],
    ];

    private const RATIO_CONTEXTS = [
        ['noun_en' => 'school', 'noun_si' => 'පාසලක', 'group_en' => 'streams', 'group_si' => 'අංශවල',
            'labels_en' => ['Bio Science', 'Physical Science', 'Commerce'], 'labels_si' => ['ජීව විද්‍යා', 'භෞතික විද්‍යා', 'වාණිජ්‍ය']],
        ['noun_en' => 'college', 'noun_si' => 'විද්‍යාලයක', 'group_en' => 'streams', 'group_si' => 'අංශවල',
            'labels_en' => ['Arts', 'Science', 'Commerce'], 'labels_si' => ['කලා', 'විද්‍යා', 'වාණිජ්‍ය']],
        ['noun_en' => 'institute', 'noun_si' => 'ආයතනයක', 'group_en' => 'faculties', 'group_si' => 'පීඨවල',
            'labels_en' => ['Engineering', 'Medicine', 'Law'], 'labels_si' => ['ඉංජිනේරු', 'වෛද්‍ය', 'නීති']],
    ];

    public function run(): void
    {
        $rows = [];

        foreach (self::PER_LEVEL as $level => $count) {
            $level = (int) $level;
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->buildWorkAndTime($level, $i);
            }
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->buildSimpleInterest($level, $i);
            }
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->buildAgeProblem($level, $i);
            }
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->buildRelativeSpeed($level, $i);
            }
        }

        $ratioCombos = $this->buildRatioCombos();
        $ratioCursor = 0;
        foreach ([3, 4, 5] as $level) {
            $perLevel = (int) ceil(count($ratioCombos) / 3);
            for ($i = 0; $i < $perLevel && $ratioCursor < count($ratioCombos); $i++) {
                $rows[] = $this->renderRatioIncrease($level, $ratioCombos[$ratioCursor++]);
            }
        }

        // Independent per-level RNG streams can occasionally draw identical
        // parameters at two levels - keep the first occurrence of each text.
        $seen = [];
        $rows = array_values(array_filter($rows, function (array $row) use (&$seen) {
            if (isset($seen[$row[2]])) {
                return false;
            }
            $seen[$row[2]] = true;

            return true;
        }));

        $this->insertRows('numerical_ability', $rows, [
            'exam_tags' => ['numerical_reasoning', 'gov_aptitude', 'al_common_general'],
            'cognitive_skill' => 'quantitative-reasoning',
            'bloom_level' => 'analyze',
        ]);
    }

    private function distractorsAround(int $answer, int $spread): array
    {
        // When $spread is small (e.g. 1 for a small integer answer like a
        // 2-year loan term), answer±1 only yields 2 distinct candidate
        // values, so the loop could never reach 4 unique entries and would
        // silently under-fill the option set. Widening the delta range by
        // $guard once stuck guarantees termination with exactly 4 values.
        $set = [$answer];
        $guard = 0;
        while (count($set) < 4 && $guard < 50) {
            $guard++;
            $delta = mt_rand(1, max(1, $spread) + intdiv($guard, 3));
            $candidate = mt_rand(0, 1) ? $answer + $delta : max(0, $answer - $delta);
            if (! in_array($candidate, $set, true)) {
                $set[] = $candidate;
            }
        }
        while (count($set) < 4) {
            $candidate = $answer + count($set) + 1;
            if (! in_array($candidate, $set, true)) {
                $set[] = $candidate;
            }
        }
        shuffle($set);

        return $set;
    }

    private function optionsFromValues(array $values, $answer): array
    {
        $labels = array_map('strval', $values);
        $options = $this->options($labels, $labels);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

        return [$options, $key];
    }

    /** Two workers/pipes/carpenters together - answer is always a whole number by construction. */
    private function buildWorkAndTime(int $level, int $variant): array
    {
        mt_srand($level * 910000 + $variant * 89 + 31);

        // Search for a clean integer-days pair (a,b) whose combined rate is also
        // a whole number of days, scaled up a bit for higher levels.
        $maxDays = 10 + $level * 6;
        do {
            $a = mt_rand(4, $maxDays);
            $b = mt_rand(4, $maxDays);
        } while ($a === $b || ($a * $b) % ($a + $b) !== 0);
        $combined = intdiv($a * $b, $a + $b);

        $scenarios = [
            fn () => [
                "Carpenter A can finish a piece of furniture alone in {$a} days and carpenter B can finish the same job alone in {$b} days. Working together, how many days will they take to finish it?",
                "වඩුකාර A හට තනිව දින {$a}කින් වැඩක් නිම කළ හැකි අතර වඩුකාර B හට එම වැඩම දින {$b}කින් නිම කළ හැක. දෙදෙනා එකට වැඩ කළහොත් එය නිම කිරීමට ගතවන දින ගණන කීයද?",
            ],
            fn () => [
                "Pipe A can fill a tank alone in {$a} hours and pipe B can fill the same tank alone in {$b} hours. If both pipes are opened together, how many hours will it take to fill the tank?",
                "නළය A හට තනිව පැය {$a}කින් ටැංකියක් පිරවිය හැකි අතර නළය B හට එම ටැංකිය තනිව පැය {$b}කින් පිරවිය හැක. දෙකම එකවර විවෘත කළහොත් ටැංකිය පිරවීමට ගතවන පැය ගණන කීයද?",
            ],
        ];
        [$en, $si] = $scenarios[$variant % count($scenarios)]();

        $distractors = $this->distractorsAround($combined, 3);
        [$options, $key] = $this->optionsFromValues($distractors, $combined);

        return [$level, 'mcq_text', $en, $si, $options, $key,
            "Combined rate = 1/{$a} + 1/{$b} per unit time, so together they take {$combined} units of time.",
            "ඒකාබද්ධ වේගය = 1/{$a} + 1/{$b} වේ, එබැවින් එකට වැඩ කිරීමේදී ගතවන කාලය {$combined} වේ.", ];
    }

    /** Simple interest: Interest = P*R*T/100, all three of P/R/T chosen so the third is exact. */
    private function buildSimpleInterest(int $level, int $variant): array
    {
        mt_srand($level * 920000 + $variant * 79 + 37);

        $principal = mt_rand(10, 100) * 100;
        $rate = [2, 3, 4, 5, 6, 8, 10, 12][mt_rand(0, 7)];
        $time = mt_rand(1, 3 + intdiv($level, 2));
        $interest = intdiv($principal * $rate * $time, 100);

        $askWhich = $variant % 3;

        if ($askWhich === 0) {
            // Ask for time.
            $en = "If Rs. {$principal} is deposited in a savings account at {$rate}% per annum simple interest, how many years will it take to yield Rs. {$interest} as interest?";
            $si = "රු. {$principal}ක් වාර්ෂික සරල පොලී අනුපාතය {$rate}% ක ඉතිරි කිරීමේ ගිණුමක තැන්පත් කළහොත්, පොලිය රු. {$interest} ලබා ගැනීමට කොපමණ අවුරුදු ගණනක් ගතවේද?";
            $answer = $time;
            $explanationEn = "Time = Interest x 100 / (Principal x Rate) = {$interest} x 100 / ({$principal} x {$rate}) = {$time} years.";
            $explanationSi = "කාලය = පොලිය x 100 / (මුලික මුදල x අනුපාතය) = {$time} වර්ෂ වේ.";
        } elseif ($askWhich === 1) {
            // Ask for rate.
            $en = "Rs. {$principal} deposited for {$time} years yields Rs. {$interest} as simple interest. What is the annual interest rate?";
            $si = "රු. {$principal}ක් වර්ෂ {$time}ක් සඳහා තැන්පත් කිරීමෙන් රු. {$interest}ක සරල පොලියක් ලැබේ නම්, වාර්ෂික පොලී අනුපාතය කුමක්ද?";
            $answer = $rate;
            $explanationEn = "Rate = Interest x 100 / (Principal x Time) = {$rate}%.";
            $explanationSi = "අනුපාතය = පොලිය x 100 / (මුලික මුදල x කාලය) = {$rate}% වේ.";
        } else {
            // Ask for principal.
            $en = "A sum of money deposited at {$rate}% per annum simple interest for {$time} years yields Rs. {$interest} as interest. What was the sum deposited?";
            $si = "වාර්ෂික සරල පොලී අනුපාතය {$rate}% කින් වර්ෂ {$time}ක් සඳහා තැන්පත් කළ මුදලකින් රු. {$interest}ක පොලියක් ලැබේ නම්, තැන්පත් කළ මුදල කීයද?";
            $answer = $principal;
            $explanationEn = "Principal = Interest x 100 / (Rate x Time) = Rs. {$principal}.";
            $explanationSi = "මුලික මුදල = පොලිය x 100 / (අනුපාතය x කාලය) = රු. {$principal} වේ.";
        }

        $spread = $askWhich === 2 ? max(100, intdiv($principal, 5)) : max(1, intdiv($answer, 4) + 1);
        $distractors = $this->distractorsAround($answer, $spread);
        [$options, $key] = $this->optionsFromValues($distractors, $answer);

        return [$level, 'mcq_text', $en, $si, $options, $key, $explanationEn, $explanationSi];
    }

    /** Parent age-gap style problem (father/mother ages at children's births). */
    private function buildAgeProblem(int $level, int $variant): array
    {
        mt_srand($level * 930000 + $variant * 73 + 41);

        $fatherAgeAtBirth = mt_rand(28, 45);
        $motherAgeAtSiblingBirth = mt_rand(24, 40);
        $siblingYoungerBy = mt_rand(2, 6);
        $diff = abs($fatherAgeAtBirth - $motherAgeAtSiblingBirth + $siblingYoungerBy);

        $names = [['Kanchana', 'her', 'කාංචනා', 'ඇගේ'], ['Nimal', 'his', 'නිමල්', 'ඔහුගේ'], ['Priya', 'her', 'ප්‍රියා', 'ඇගේ']];
        [$name, $poss, $nameSi, $possSi] = $names[$variant % count($names)];

        $en = "{$name}'s father was {$fatherAgeAtBirth} years old when {$name} was born. {$name}'s mother was {$motherAgeAtSiblingBirth} years old when {$poss} sibling, {$siblingYoungerBy} years younger, was born. What is the age difference between {$poss} parents?";
        $si = "{$nameSi}ගේ පියා උපන් විට {$nameSi}ගේ පියාට වයස අවුරුදු {$fatherAgeAtBirth} විය. {$nameSi}ට වඩා අවුරුදු {$siblingYoungerBy}කින් බාල සොයුරා/සොයුරිය උපන් විට {$nameSi}ගේ මව්කගේ වයස අවුරුදු {$motherAgeAtSiblingBirth} විය. {$possSi} දෙමාපියන්ගේ වයස් වෙනස කීයද?";

        $distractors = $this->distractorsAround($diff, 3);
        [$options, $key] = $this->optionsFromValues($distractors, $diff);

        return [$level, 'mcq_text', $en, $si, $options, $key,
            "Father's age gap to {$name} is {$fatherAgeAtBirth} years; mother's age gap is {$motherAgeAtSiblingBirth} - {$siblingYoungerBy} = ".($motherAgeAtSiblingBirth - $siblingYoungerBy)." years. Difference = {$diff} years.",
            "පියාගේ වයස් පරතරය අවුරුදු {$fatherAgeAtBirth}ක් වන අතර මවගේ වයස් පරතරය අවුරුදු ".($motherAgeAtSiblingBirth - $siblingYoungerBy)."ක් වේ. වෙනස අවුරුදු {$diff}ක් වේ.", ];
    }

    /** Relative speed: trains/vehicles meeting or catching up - constructed backward for a clean answer. */
    private function buildRelativeSpeed(int $level, int $variant): array
    {
        mt_srand($level * 940000 + $variant * 67 + 43);

        $isMeeting = $variant % 2 === 0;
        $speedA = mt_rand(4, 8) * 10;
        $speedB = mt_rand(4, 8) * 10;
        $time = mt_rand(1, 3 + intdiv($level, 2));

        if ($isMeeting) {
            $distance = ($speedA + $speedB) * $time;
            $en = "Two trains start at the same time from stations A and B, {$distance} km apart, travelling towards each other at {$speedA} km/h and {$speedB} km/h respectively. After how many hours will they meet?";
            $si = "දුම්රිය දෙකක් A සහ B දුම්රියපොළවලින් කි.මී. {$distance}ක් දුරින් සිට, එකවර පිටත් වී පැයට කි.මී. {$speedA} සහ {$speedB} වේගයෙන් එකිනෙකා දෙසට ධාවනය වේ. ඒවා හමුවීමට කොපමණ පැය ගණනක් ගතවේද?";
            $answer = $time;
            $explanationEn = "Combined speed = {$speedA} + {$speedB} = ".($speedA + $speedB)." km/h. Time = Distance / Combined speed = {$time} hours.";
            $explanationSi = "ඒකාබද්ධ වේගය = ".($speedA + $speedB)." කි.මී./පැය. කාලය = දුර / ඒකාබද්ධ වේගය = පැය {$time}ක්.";
        } else {
            if ($speedA === $speedB) {
                $speedB += 10;
            }
            $fast = max($speedA, $speedB);
            $slow = min($speedA, $speedB);
            $lead = ($fast - $slow) * $time;
            $en = "Vehicle X travels at {$fast} km/h and vehicle Y travels at {$slow} km/h in the same direction. If Y has a {$lead} km head start, how many hours will it take X to catch up with Y?";
            $si = "වාහනය X පැයට කි.මී. {$fast} වේගයෙන් සහ වාහනය Y පැයට කි.මී. {$slow} වේගයෙන් එකම දිශාවට ධාවනය වේ. Y හට කි.මී. {$lead}ක ඉදිරි ආරම්භයක් ඇත්නම්, X හට Y ලඟා වීමට ගතවන පැය ගණන කීයද?";
            $answer = $time;
            $explanationEn = "Relative speed = {$fast} - {$slow} = ".($fast - $slow)." km/h. Time to catch up = Lead distance / Relative speed = {$time} hours.";
            $explanationSi = "සාපේක්ෂ වේගය = ".($fast - $slow)." කි.මී./පැය. ලඟා වීමට ගතවන කාලය = ඉදිරි දුර / සාපේක්ෂ වේගය = පැය {$time}ක්.";
        }

        $distractors = $this->distractorsAround($answer, 2);
        [$options, $key] = $this->optionsFromValues($distractors, $answer);

        return [$level, 'mcq_text', $en, $si, $options, $key, $explanationEn, $explanationSi];
    }

    /** @return array<int,array{0:int,1:int}> [tupleIdx, contextIdx] */
    private function buildRatioCombos(): array
    {
        $combos = [];
        foreach (array_keys(self::RATIO_TUPLES) as $tupleIdx) {
            foreach (array_keys(self::RATIO_CONTEXTS) as $contextIdx) {
                $combos[] = [$tupleIdx, $contextIdx];
            }
        }
        mt_srand(9500);
        shuffle($combos);

        return $combos;
    }

    private function renderRatioIncrease(int $level, array $combo): array
    {
        [$tupleIdx, $contextIdx] = $combo;
        [$a, $b, $c, $pA, $pB, $pC, $newRatio] = self::RATIO_TUPLES[$tupleIdx];
        $ctx = self::RATIO_CONTEXTS[$contextIdx];

        [$l1en, $l2en, $l3en] = $ctx['labels_en'];
        [$l1si, $l2si, $l3si] = $ctx['labels_si'];

        $en = "In a certain {$ctx['noun_en']}, students in the {$ctx['group_en']} of {$l1en}, {$l2en} and {$l3en} are in the ratio of {$a}:{$b}:{$c}. The {$ctx['noun_en']} plans to increase the number of students in these {$ctx['group_en']} by {$pA}%, {$pB}% and {$pC}% respectively next year. What will be the new ratio of the increased numbers?";
        $si = "{$ctx['noun_si']} {$l1si}, {$l2si} සහ {$l3si} {$ctx['group_si']} සිසුන් සංඛ්‍යාව {$a}:{$b}:{$c} අනුපාතයේ පවතී. ලබන වසරේදී මෙම {$ctx['group_si']} සිසුන් සංඛ්‍යාව පිළිවෙළින් {$pA}%, {$pB}% සහ {$pC}%කින් වැඩි කිරීමට සැලසුම් කර ඇත. වැඩි කළ සංඛ්‍යාවල නව අනුපාතය කුමක්ද?";

        $wrongRatios = [];
        foreach (self::RATIO_TUPLES as $idx => $tuple) {
            if ($idx !== $tupleIdx && count($wrongRatios) < 3) {
                $wrongRatios[] = $tuple[6];
            }
        }

        $values = array_merge([$newRatio], $wrongRatios);
        $order = range(0, 3);
        mt_srand(crc32(implode('-', $combo)));
        shuffle($order);
        $shuffled = array_map(fn ($i) => $values[$i], $order);
        $key = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        $options = $this->options($shuffled, $shuffled);

        return [$level, 'mcq_text', $en, $si, $options, $key,
            "{$a}x(1+{$pA}/100) : {$b}x(1+{$pB}/100) : {$c}x(1+{$pC}/100), simplified, gives {$newRatio}.",
            "{$a}x(1+{$pA}/100) : {$b}x(1+{$pB}/100) : {$c}x(1+{$pC}/100) සරල කළ විට {$newRatio} ලැබේ.", ];
    }
}
