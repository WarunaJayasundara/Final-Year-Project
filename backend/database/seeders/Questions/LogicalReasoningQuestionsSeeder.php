<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

class LogicalReasoningQuestionsSeeder extends Seeder
{
    use BuildsQuestions;

    private const PER_LEVEL_ODD_ONE_OUT = 35;
    private const PER_LEVEL_ANALOGY = 35;
    private const PER_LEVEL_SYLLOGISM = 10;

    private const CATEGORIES = [
        ['en' => ['Apple', 'Mango', 'Banana', 'Grape'], 'si' => ['ඇපල්', 'අඹ', 'කෙසෙල්', 'මිදි'], 'name_en' => 'fruit', 'name_si' => 'පලතුරු'],
        ['en' => ['Car', 'Bus', 'Train', 'Bicycle'], 'si' => ['මෝටර් රථය', 'බස් රථය', 'දුම්රිය', 'බයිසිකලය'], 'name_en' => 'vehicle', 'name_si' => 'වාහන'],
        ['en' => ['Hammer', 'Screwdriver', 'Nail', 'Drill'], 'si' => ['මුගුරුව', 'ඉස්කුරුප්පු අණ්ටිය', 'ඇණය', 'විදුම් යන්ත්‍රය'], 'name_en' => 'tool', 'name_si' => 'මෙවලම්'],
        ['en' => ['Circle', 'Square', 'Triangle', 'Rectangle'], 'si' => ['රවුම', 'චතුරස්‍රය', 'ත්‍රිකෝණය', 'ආයතය'], 'name_en' => 'shape', 'name_si' => 'හැඩය'],
        ['en' => ['Doctor', 'Teacher', 'Engineer', 'Lawyer'], 'si' => ['වෛද්‍යවරයා', 'ගුරුවරයා', 'ඉංජිනේරු', 'නීතිඥයා'], 'name_en' => 'profession', 'name_si' => 'වෘත්තිය'],
        ['en' => ['Rose', 'Lily', 'Tulip', 'Jasmine'], 'si' => ['රෝස මල', 'ලිලී මල', 'ටියුලිප් මල', 'පිච්ච මල'], 'name_en' => 'flower', 'name_si' => 'මල්'],
        ['en' => ['Guitar', 'Piano', 'Violin', 'Drum'], 'si' => ['ගිටාරය', 'පියානෝව', 'වයලීනය', 'බෙරය'], 'name_en' => 'musical instrument', 'name_si' => 'සංගීත භාණ්ඩ'],
        ['en' => ['Cow', 'Goat', 'Sheep', 'Pig'], 'si' => ['එළදෙන', 'එළුවා', 'බැටළුවා', 'ඌරා'], 'name_en' => 'farm animal', 'name_si' => 'ගොවිපල සතුන්'],
        ['en' => ['Cricket', 'Football', 'Tennis', 'Rugby'], 'si' => ['ක්‍රිකට්', 'පාපන්දු', 'ටෙනිස්', 'රග්බි'], 'name_en' => 'sport', 'name_si' => 'ක්‍රීඩා'],
        ['en' => ['Tea', 'Coffee', 'Juice', 'Milk'], 'si' => ['තේ', 'කෝපි', 'යුෂ', 'කිරි'], 'name_en' => 'beverage', 'name_si' => 'පාන වර්ග'],
    ];

    private const OPPOSITES = [
        ['Hot', 'Cold', 'උණුසුම්', 'සීතල'],
        ['Big', 'Small', 'විශාල', 'කුඩා'],
        ['Fast', 'Slow', 'වේගවත්', 'මන්දගාමී'],
        ['Happy', 'Sad', 'සතුටු', 'දුක්බර'],
        ['Bright', 'Dark', 'දීප්තිමත්', 'අඳුරු'],
        ['Full', 'Empty', 'පිරුණු', 'හිස්'],
        ['Up', 'Down', 'උඩ', 'යට'],
        ['Old', 'New', 'පැරණි', 'අලුත්'],
        ['Rich', 'Poor', 'ධනවත්', 'දුප්පත්'],
        ['Strong', 'Weak', 'ශක්තිමත්', 'දුර්වල'],
        ['Wet', 'Dry', 'තෙත්', 'වියළි'],
        ['High', 'Low', 'උස', 'පහත්'],
        ['Thick', 'Thin', 'ඝන', 'තුනී'],
        ['Loud', 'Quiet', 'ඝෝෂාකාරී', 'නිශ්ශබ්ද'],
        ['Clean', 'Dirty', 'පිරිසිදු', 'අපිරිසිදු'],
        ['Early', 'Late', 'ඉක්මන්', 'ප්‍රමාද'],
        ['Open', 'Closed', 'විවෘත', 'වසා ඇති'],
        ['Wide', 'Narrow', 'පළල්', 'පටු'],
        ['Brave', 'Afraid', 'නිර්භීත', 'බියගුල්ල'],
        ['Sharp', 'Blunt', 'තියුණු', 'මොට'],
    ];

    private const CONCLUSION_PHRASE_EN = [
        'What can we conclude?',
        'Which statement must be true?',
        'What follows logically?',
        'What is the valid conclusion?',
    ];

    private const CONCLUSION_PHRASE_SI = [
        'අපට නිගමනය කළ හැක්කේ කුමක්ද?',
        'සත්‍ය විය යුත්තේ කුමන ප්‍රකාශයද?',
        'තාර්කිකව අනුගමනය වන්නේ කුමක්ද?',
        'වලංගු නිගමනය කුමක්ද?',
    ];

    private const SYLLOGISMS = [
        ['All birds can fly. A sparrow is a bird.', 'A sparrow can fly.', 'A sparrow cannot fly.', 'A sparrow is a mammal.', 'A sparrow lays eggs only.',
            'සියලුම කුරුල්ලන්ට පියාසර කළ හැක. ගිරාවෙක් යනු කුරුල්ලෙකි.', 'ගිරාවෙකුට පියාසර කළ හැක.', 'ගිරාවෙකුට පියාසර කළ නොහැක.', 'ගිරාවෙක් යනු ක්ෂීරපායියෙකි.', 'ගිරාවෙක් බිත්තර දමයි පමණි.'],
        ['All squares have four equal sides. Shape X is a square.', 'Shape X has four equal sides.', 'Shape X has three sides.', 'Shape X is round.', 'Shape X has five sides.',
            'සියලුම චතුරස්‍රවලට සමාන පැති හතරක් ඇත. හැඩය X යනු චතුරස්‍රයකි.', 'හැඩය X ට සමාන පැති හතරක් ඇත.', 'හැඩය X ට පැති තුනක් ඇත.', 'හැඩය X රවුම් ය.', 'හැඩය X ට පැති පහක් ඇත.'],
        ['All students in the class passed the exam. Kasun is a student in the class.', 'Kasun passed the exam.', 'Kasun failed the exam.', 'Kasun is a teacher.', 'Kasun did not take the exam.',
            'පන්තියේ සියලුම සිසුන් විභාගය සමත් විය. කසුන් පන්තියේ සිසුවෙකි.', 'කසුන් විභාගය සමත් විය.', 'කසුන් විභාගය අසමත් විය.', 'කසුන් ගුරුවරයෙකි.', 'කසුන් විභාගයට පෙනී නොසිටියේය.'],
        ['No fish can breathe air out of water. A shark is a fish.', 'A shark cannot breathe air out of water.', 'A shark can breathe air out of water.', 'A shark is a mammal.', 'A shark lives on land.',
            'මාළුවන්ට ජලයෙන් පිටත වාතය ආශ්වාස කළ නොහැක. මෝරෙකු යනු මාළුවෙකි.', 'මෝරෙකුට ජලයෙන් පිටත වාතය ආශ්වාස කළ නොහැක.', 'මෝරෙකුට ජලයෙන් පිටත වාතය ආශ්වාස කළ හැක.', 'මෝරෙකු යනු ක්ෂීරපායියෙකි.', 'මෝරෙකු ගොඩබිම ජීවත් වේ.'],
        ['All metals conduct electricity. Copper is a metal.', 'Copper conducts electricity.', 'Copper does not conduct electricity.', 'Copper is a gas.', 'Copper cannot be touched.',
            'සියලුම ලෝහ විදුලිය සන්නයනය කරයි. තඹ යනු ලෝහයකි.', 'තඹ විදුලිය සන්නයනය කරයි.', 'තඹ විදුලිය සන්නයනය නොකරයි.', 'තඹ වායුවකි.', 'තඹ ස්පර්ශ කළ නොහැක.'],
        ['All triangles have three sides. Shape Y is a triangle.', 'Shape Y has three sides.', 'Shape Y has four sides.', 'Shape Y is round.', 'Shape Y has six sides.',
            'සියලුම ත්‍රිකෝණවලට පැති තුනක් ඇත. හැඩය Y යනු ත්‍රිකෝණයකි.', 'හැඩය Y ට පැති තුනක් ඇත.', 'හැඩය Y ට පැති හතරක් ඇත.', 'හැඩය Y රවුම් ය.', 'හැඩය Y ට පැති හයක් ඇත.'],
        ['No reptiles are warm-blooded. A snake is a reptile.', 'A snake is not warm-blooded.', 'A snake is warm-blooded.', 'A snake is a bird.', 'A snake lives in water only.',
            'කිසිදු උරගයෙකු උණුසුම් රුධිර නොවේ. සර්පයෙක් යනු උරගයෙකි.', 'සර්පයෙක් උණුසුම් රුධිර නොවේ.', 'සර්පයෙක් උණුසුම් රුධිර වේ.', 'සර්පයෙක් කුරුල්ලෙකි.', 'සර්පයෙක් ජලයේ පමණක් ජීවත් වේ.'],
        ['All engineers study mathematics. Nadeesha is an engineer.', 'Nadeesha studies mathematics.', 'Nadeesha does not study mathematics.', 'Nadeesha is a nurse.', 'Nadeesha studies only art.',
            'සියලුම ඉංජිනේරුවන් ගණිතය හදාරති. නදීෂා යනු ඉංජිනේරුවෙකි.', 'නදීෂා ගණිතය හදාරයි.', 'නදීෂා ගණිතය හදාරන්නේ නැත.', 'නදීෂා හෙදියකි.', 'නදීෂා හදාරන්නේ කලාව පමණි.'],
        ['All laptops need electricity to run. This device is a laptop.', 'This device needs electricity to run.', 'This device does not need electricity.', 'This device runs on water.', 'This device is a book.',
            'සියලුම ලැප්ටොප් ක්‍රියාත්මක වීමට විදුලිය අවශ්‍යයි. මෙම උපකරණය ලැප්ටොප් එකකි.', 'මෙම උපකරණයට ක්‍රියාත්මක වීමට විදුලිය අවශ්‍යයි.', 'මෙම උපකරණයට විදුලිය අවශ්‍ය නැත.', 'මෙම උපකරණය ජලයෙන් ක්‍රියාත්මක වේ.', 'මෙම උපකරණය පොතකි.'],
        ['No mammals lay eggs, except the platypus. A dog is a mammal (not a platypus).', 'A dog does not lay eggs.', 'A dog lays eggs.', 'A dog is a reptile.', 'A dog can fly.',
            'ප්ලැටිපස් හැර කිසිදු ක්ෂීරපායියෙකු බිත්තර නොදමයි. බල්ලෙක් යනු ක්ෂීරපායියෙකි (ප්ලැටිපස් නොවේ).', 'බල්ලෙක් බිත්තර නොදමයි.', 'බල්ලෙක් බිත්තර දමයි.', 'බල්ලෙක් උරගයෙකි.', 'බල්ලෙකුට පියාසර කළ හැක.'],
        ['All prime numbers greater than 2 are odd. Number N is a prime number greater than 2.', 'Number N is odd.', 'Number N is even.', 'Number N is negative.', 'Number N is zero.',
            '2ට වඩා විශාල සියලුම ප්‍රථම සංඛ්‍යා ඔත්තේ වේ. N සංඛ්‍යාව 2ට වඩා විශාල ප්‍රථම සංඛ්‍යාවකි.', 'N සංඛ්‍යාව ඔත්තේ වේ.', 'N සංඛ්‍යාව ඉරට්ටේ වේ.', 'N සංඛ්‍යාව සෘණාත්මකයි.', 'N සංඛ්‍යාව බිංදුවයි.'],
        ['All members of the choir can sing well. Priya is a member of the choir.', 'Priya can sing well.', 'Priya cannot sing well.', 'Priya is not a member of any group.', 'Priya plays football.',
            'ගායක මණ්ඩලයේ සියලුම සාමාජිකයින්ට හොඳින් ගායනා කළ හැක. ප්‍රියා ගායක මණ්ඩලයේ සාමාජිකාවකි.', 'ප්‍රියාට හොඳින් ගායනා කළ හැක.', 'ප්‍රියාට හොඳින් ගායනා කළ නොහැක.', 'ප්‍රියා කිසිදු කණ්ඩායමක සාමාජිකාවක් නොවේ.', 'ප්‍රියා පාපන්දු ක්‍රීඩා කරයි.'],
        ['No insects have more than six legs. A spider has eight legs.', 'A spider is not an insect.', 'A spider is an insect.', 'A spider has six legs.', 'A spider cannot walk.',
            'කිසිදු කෘමියෙකුට පාද හයකට වඩා නැත. මකුළුවෙකුට පාද අටක් ඇත.', 'මකුළුවෙක් කෘමියෙකු නොවේ.', 'මකුළුවෙක් කෘමියෙකි.', 'මකුළුවෙකුට පාද හයක් ඇත.', 'මකුළුවෙකුට ඇවිදීමට නොහැක.'],
        ['All the shops on this street close at 9pm. Shop Z is on this street.', 'Shop Z closes at 9pm.', 'Shop Z closes at 6pm.', 'Shop Z never closes.', 'Shop Z is not on this street.',
            'මෙම වීදියේ සියලුම වෙළඳසැල් රාත්‍රී 9ට වසා දමයි. Z වෙළඳසැල මෙම වීදියේ පිහිටා ඇත.', 'Z වෙළඳසැල රාත්‍රී 9ට වසා දමයි.', 'Z වෙළඳසැල සවස 6ට වසා දමයි.', 'Z වෙළඳසැල කිසිදා වසා නොදමයි.', 'Z වෙළඳසැල මෙම වීදියේ නොපිහිටයි.'],
        ['All squares are rectangles. Shape W is a square.', 'Shape W is a rectangle.', 'Shape W is a circle.', 'Shape W is not a rectangle.', 'Shape W has three sides.',
            'සියලුම චතුරස්‍ර ආයත වේ. හැඩය W චතුරස්‍රයකි.', 'හැඩය W ආයතයකි.', 'හැඩය W රවුම් ය.', 'හැඩය W ආයතයක් නොවේ.', 'හැඩය W ට පැති තුනක් ඇත.'],
    ];

    public function run(): void
    {
        $rows = [];

        $oddCombos = $this->buildOddOneOutCombos();
        $analogyCombos = $this->buildAnalogyCombos();
        $syllogismCombos = $this->buildSyllogismCombos();

        $oddCursor = 0;
        $analogyCursor = 0;
        $syllogismCursor = 0;

        foreach (range(1, 5) as $level) {
            for ($i = 0; $i < self::PER_LEVEL_ODD_ONE_OUT; $i++) {
                $rows[] = $this->renderOddOneOut($level, $oddCombos[$oddCursor++]);
            }
            for ($i = 0; $i < self::PER_LEVEL_ANALOGY; $i++) {
                $rows[] = $this->renderAnalogy($level, $analogyCombos[$analogyCursor++]);
            }
            for ($i = 0; $i < self::PER_LEVEL_SYLLOGISM; $i++) {
                $rows[] = $this->renderSyllogism($level, $syllogismCombos[$syllogismCursor++]);
            }
        }

        $this->insertRows('logical_reasoning', $rows);
    }

    /** @return array<int,array{0:int,1:int,2:int,3:int}> [mainCategoryIdx, otherCategoryIdx, excludedWordIdx, oddWordIdx] */
    private function buildOddOneOutCombos(): array
    {
        $combos = [];
        $n = count(self::CATEGORIES);

        for ($main = 0; $main < $n; $main++) {
            for ($other = 0; $other < $n; $other++) {
                if ($main === $other) {
                    continue;
                }
                for ($excludeIdx = 0; $excludeIdx < 4; $excludeIdx++) {
                    for ($oddIdx = 0; $oddIdx < 4; $oddIdx++) {
                        $combos[] = [$main, $other, $excludeIdx, $oddIdx];
                    }
                }
            }
        }

        mt_srand(4201);
        shuffle($combos);

        return $combos;
    }

    /** @return array<int,array{0:int,1:int}> [firstPairIdx, secondPairIdx] into OPPOSITES, always distinct */
    private function buildAnalogyCombos(): array
    {
        $combos = [];
        $n = count(self::OPPOSITES);

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i !== $j) {
                    $combos[] = [$i, $j];
                }
            }
        }

        mt_srand(4202);
        shuffle($combos);

        return $combos;
    }

    /** @return array<int,array{0:int,1:int}> [templateIdx, phraseIdx] */
    private function buildSyllogismCombos(): array
    {
        $combos = [];
        foreach (range(0, count(self::SYLLOGISMS) - 1) as $templateIdx) {
            foreach (range(0, count(self::CONCLUSION_PHRASE_EN) - 1) as $phraseIdx) {
                $combos[] = [$templateIdx, $phraseIdx];
            }
        }

        mt_srand(4203);
        shuffle($combos);

        return $combos;
    }

    private function renderOddOneOut(int $level, array $combo): array
    {
        [$mainIdx, $otherIdx, $excludeIdx, $oddIdx] = $combo;
        $main = self::CATEGORIES[$mainIdx];
        $other = self::CATEGORIES[$otherIdx];

        $picks = array_values(array_diff([0, 1, 2, 3], [$excludeIdx]));
        $wordsEn = array_map(fn ($i) => $main['en'][$i], $picks);
        $wordsSi = array_map(fn ($i) => $main['si'][$i], $picks);
        $wordsEn[] = $other['en'][$oddIdx];
        $wordsSi[] = $other['si'][$oddIdx];

        mt_srand(crc32(implode('-', $combo).'-'.$level));
        $order = [0, 1, 2, 3];
        shuffle($order);
        $shuffledEn = array_map(fn ($i) => $wordsEn[$i], $order);
        $shuffledSi = array_map(fn ($i) => $wordsSi[$i], $order);
        $correctPos = array_search(3, $order, true);

        $options = $this->options($shuffledEn, $shuffledSi);
        $key = ['A', 'B', 'C', 'D'][$correctPos];

        return [$level, 'mcq_text',
            'Which one of these does not belong with the others?',
            'මේවායින් අනෙක් ඒවාට වඩා වෙනස් වන්නේ කුමක්ද?',
            $options, $key,
            "The others are all {$main['name_en']}s, but \"{$shuffledEn[$correctPos]}\" is a {$other['name_en']}.",
            "අනෙක් ඒවා සියල්ල {$main['name_si']} වන අතර, \"{$shuffledSi[$correctPos]}\" යනු {$other['name_si']} කි.", ];
    }

    private function renderAnalogy(int $level, array $combo): array
    {
        [$firstIdx, $secondIdx] = $combo;
        [$aEn, $bEn, $aSi, $bSi] = self::OPPOSITES[$firstIdx];
        [$cEn, $answerEn, $cSi, $answerSi] = self::OPPOSITES[$secondIdx];

        $wrongPool = array_values(array_filter(self::OPPOSITES, fn ($i) => $i !== $firstIdx && $i !== $secondIdx, ARRAY_FILTER_USE_KEY));

        mt_srand(crc32(implode('-', $combo).'-'.$level));
        shuffle($wrongPool);
        $wrongEn = array_map(fn ($p) => $p[1], array_slice($wrongPool, 0, 3));
        $wrongSi = array_map(fn ($p) => $p[3], array_slice($wrongPool, 0, 3));

        $valuesEn = array_merge([$answerEn], $wrongEn);
        $valuesSi = array_merge([$answerSi], $wrongSi);
        $order = range(0, 3);
        shuffle($order);
        $shuffledEn = array_map(fn ($i) => $valuesEn[$i], $order);
        $shuffledSi = array_map(fn ($i) => $valuesSi[$i], $order);
        $correctPos = array_search(0, $order, true);

        $options = $this->options($shuffledEn, $shuffledSi);
        $key = ['A', 'B', 'C', 'D'][$correctPos];

        return [$level, 'mcq_text',
            "{$aEn} is to {$bEn} as {$cEn} is to ___?",
            "{$aSi} යනු {$bSi} ට සමාන ලෙස, {$cSi} යනු ___ ට සමානය?",
            $options, $key,
            "The relationship is opposites: just as {$aEn} is the opposite of {$bEn}, {$cEn} is the opposite of {$answerEn}.",
            "සම්බන්ධතාවය ප්‍රතිවිරුද්ධතාවයි: {$aSi} සහ {$bSi} ප්‍රතිවිරුද්ධ වන පරිදිම, {$cSi} සහ {$answerSi} ද ප්‍රතිවිරුද්ධ වේ.", ];
    }

    private function renderSyllogism(int $level, array $combo): array
    {
        [$templateIdx, $phraseIdx] = $combo;
        $t = self::SYLLOGISMS[$templateIdx];
        [$premiseEn, $correctEn, $wrong1En, $wrong2En, $wrong3En, $premiseSi, $correctSi, $wrong1Si, $wrong2Si, $wrong3Si] = $t;

        $valuesEn = [$correctEn, $wrong1En, $wrong2En, $wrong3En];
        $valuesSi = [$correctSi, $wrong1Si, $wrong2Si, $wrong3Si];

        mt_srand(crc32(implode('-', $combo).'-'.$level));
        $order = range(0, 3);
        shuffle($order);
        $shuffledEn = array_map(fn ($i) => $valuesEn[$i], $order);
        $shuffledSi = array_map(fn ($i) => $valuesSi[$i], $order);
        $correctPos = array_search(0, $order, true);

        $options = $this->options($shuffledEn, $shuffledSi);
        $key = ['A', 'B', 'C', 'D'][$correctPos];

        $phraseEn = self::CONCLUSION_PHRASE_EN[$phraseIdx];
        $phraseSi = self::CONCLUSION_PHRASE_SI[$phraseIdx];

        return [$level, 'mcq_text',
            "{$premiseEn} {$phraseEn}",
            "{$premiseSi} {$phraseSi}",
            $options, $key,
            'This follows logically from the two statements given (a valid deduction).',
            'මෙය ලබා දී ඇති ප්‍රකාශ දෙකෙන් තාර්කිකව අනුගමනය වේ.', ];
    }
}
