import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { GameResultCard } from '../GameResultCard';
import { useSubmitGameScore } from '../useGames';

const SYMBOL_POOL = [
  '🍎', '🍌', '🍇', '🍓', '🍒', '🍉', '🥝', '🍑',
  '🥑', '🍍', '🥕', '🌽', '🍄', '🍩', '🍪', '🎲',
  '⚽', '🎧', '🚀', '⭐',
];

const PAIRS_COUNT = 8;

function pickSymbols() {
  const shuffled = [...SYMBOL_POOL];
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }
  return shuffled.slice(0, PAIRS_COUNT);
}

function buildDeck(symbols: string[]) {
  const pairs = [...symbols, ...symbols];
  for (let i = pairs.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [pairs[i], pairs[j]] = [pairs[j], pairs[i]];
  }
  return pairs.map((symbol, index) => ({ id: index, symbol, matched: false }));
}

export function MemoryMatch() {
  const { t } = useTranslation('games');
  const [symbols, setSymbols] = useState(pickSymbols);
  const [deck, setDeck] = useState(() => buildDeck(symbols));
  const [flipped, setFlipped] = useState<number[]>([]);
  const [moves, setMoves] = useState(0);
  const [startedAt, setStartedAt] = useState<number | null>(null);
  const [finished, setFinished] = useState(false);
  const [result, setResult] = useState<{ score: number; bestScore?: number; isNewBest?: boolean } | null>(null);

  const submitScore = useSubmitGameScore('memory_match');

  const matchedCount = useMemo(() => deck.filter((c) => c.matched).length, [deck]);

  useEffect(() => {
    if (flipped.length !== 2) return;

    const [firstId, secondId] = flipped;
    const first = deck.find((c) => c.id === firstId)!;
    const second = deck.find((c) => c.id === secondId)!;

    setMoves((m) => m + 1);

    if (first.symbol === second.symbol) {
      setDeck((d) => d.map((c) => (c.id === firstId || c.id === secondId ? { ...c, matched: true } : c)));
      setFlipped([]);
    } else {
      const timer = setTimeout(() => setFlipped([]), 700);
      return () => clearTimeout(timer);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [flipped]);

  useEffect(() => {
    if (matchedCount === symbols.length && !finished) {
      setFinished(true);
      const seconds = startedAt ? Math.round((Date.now() - startedAt) / 1000) : 0;
      const score = Math.max(0, 1000 - moves * 10 - seconds * 2);
      submitScore.mutate(
        { score, durationSeconds: seconds, metadata: { moves, seconds } },
        { onSuccess: (data) => setResult({ score, bestScore: data.best_score, isNewBest: data.is_new_best }) },
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [matchedCount]);

  const handleFlip = (id: number) => {
    if (flipped.length === 2) return;
    const card = deck.find((c) => c.id === id)!;
    if (card.matched || flipped.includes(id)) return;
    if (!startedAt) setStartedAt(Date.now());
    setFlipped((f) => [...f, id]);
  };

  const reset = () => {
    const nextSymbols = pickSymbols();
    setSymbols(nextSymbols);
    setDeck(buildDeck(nextSymbols));
    setFlipped([]);
    setMoves(0);
    setStartedAt(null);
    setFinished(false);
    setResult(null);
  };

  if (finished && result) {
    return <GameResultCard score={result.score} bestScore={result.bestScore} isNewBest={result.isNewBest} onPlayAgain={reset} />;
  }

  return (
    <div className="mx-auto flex max-w-md flex-col gap-4">
      <div className="flex items-center justify-between text-sm text-muted-foreground">
        <span>{t('memoryMatch.moves', { count: moves })}</span>
        <span>{t('memoryMatch.matched', { matched: matchedCount / 2, total: symbols.length })}</span>
      </div>
      <div className="grid grid-cols-4 gap-3">
        {deck.map((card) => {
          const isVisible = card.matched || flipped.includes(card.id);
          return (
            <button
              key={card.id}
              type="button"
              onClick={() => handleFlip(card.id)}
              className={`flex aspect-square items-center justify-center rounded-xl border text-2xl transition-all ${
                card.matched
                  ? 'border-emerald-400 bg-emerald-50'
                  : isVisible
                    ? 'border-primary bg-primary/5'
                    : 'border-border bg-muted hover:bg-muted/70'
              }`}
            >
              {isVisible ? card.symbol : ''}
            </button>
          );
        })}
      </div>
    </div>
  );
}
