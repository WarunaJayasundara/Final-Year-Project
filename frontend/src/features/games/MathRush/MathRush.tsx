import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { GameResultCard } from '../GameResultCard';
import { useSubmitGameScore } from '../useGames';
import { generateQuestion, type MathQuestion } from './generator';

const DURATION_SECONDS = 60;

export function MathRush() {
  const { t } = useTranslation('games');
  const [question, setQuestion] = useState<MathQuestion>(generateQuestion);
  const [secondsLeft, setSecondsLeft] = useState(DURATION_SECONDS);
  const [score, setScore] = useState(0);
  const [correctCount, setCorrectCount] = useState(0);
  const [wrongCount, setWrongCount] = useState(0);
  const [feedback, setFeedback] = useState<'correct' | 'wrong' | null>(null);
  const [finished, setFinished] = useState(false);
  const [result, setResult] = useState<{ bestScore?: number; isNewBest?: boolean } | null>(null);
  const submittedRef = useRef(false);

  const submitScore = useSubmitGameScore('math_rush', {
    onSuccess: (data) => setResult({ bestScore: data.best_score, isNewBest: data.is_new_best }),
  });

  useEffect(() => {
    if (finished) return;
    const timer = setInterval(() => {
      setSecondsLeft((s) => {
        if (s <= 1) {
          clearInterval(timer);
          setFinished(true);
          return 0;
        }
        return s - 1;
      });
    }, 1000);
    return () => clearInterval(timer);
  }, [finished]);

  useEffect(() => {
    if (finished && !submittedRef.current) {
      submittedRef.current = true;
      setResult({});
      submitScore.mutate({ score, durationSeconds: DURATION_SECONDS, metadata: { correctCount, wrongCount } });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [finished]);

  const handleAnswer = (option: number) => {
    if (feedback) return;
    const isCorrect = option === question.answer;
    setFeedback(isCorrect ? 'correct' : 'wrong');

    if (isCorrect) {
      setScore((s) => s + 10);
      setCorrectCount((c) => c + 1);
    } else {
      setScore((s) => Math.max(0, s - 5));
      setWrongCount((c) => c + 1);
    }

    setTimeout(() => {
      setFeedback(null);
      setQuestion(generateQuestion());
    }, 350);
  };

  const reset = () => {
    setQuestion(generateQuestion());
    setSecondsLeft(DURATION_SECONDS);
    setScore(0);
    setCorrectCount(0);
    setWrongCount(0);
    setFeedback(null);
    setFinished(false);
    setResult(null);
    submittedRef.current = false;
  };

  if (finished && result) {
    return <GameResultCard score={score} bestScore={result.bestScore} isNewBest={result.isNewBest} onPlayAgain={reset} />;
  }

  return (
    <div className="mx-auto flex max-w-lg flex-col gap-6">
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span>{t('mathRush.timeLeft', { seconds: secondsLeft })}</span>
          <span>{t('mathRush.score', { score })}</span>
        </div>
        <Progress value={(secondsLeft / DURATION_SECONDS) * 100} />
      </div>

      <Card>
        <CardContent className="flex flex-col items-center gap-6 p-10">
          <p className="text-4xl font-semibold">{question.prompt} = ?</p>
          <div className="grid w-full grid-cols-2 gap-3">
            {question.options.map((option) => {
              const isSelectedCorrect = feedback === 'correct' && option === question.answer;
              const isSelectedWrong = feedback === 'wrong' && option !== question.answer;
              return (
                <button
                  key={option}
                  type="button"
                  disabled={!!feedback}
                  onClick={() => handleAnswer(option)}
                  className={`rounded-xl border p-5 text-xl font-semibold transition-colors ${
                    isSelectedCorrect
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-900'
                      : 'border-border hover:bg-muted disabled:opacity-70'
                  } ${isSelectedWrong ? 'opacity-40' : ''}`}
                >
                  {option}
                </button>
              );
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
