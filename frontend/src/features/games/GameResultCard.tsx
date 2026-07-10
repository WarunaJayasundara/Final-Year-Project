import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Trophy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export function GameResultCard({
  score,
  bestScore,
  isNewBest,
  onPlayAgain,
}: {
  score: number;
  bestScore?: number;
  isNewBest?: boolean;
  onPlayAgain: () => void;
}) {
  const { t } = useTranslation('games');

  return (
    <Card className="mx-auto max-w-md">
      <CardContent className="flex flex-col items-center gap-4 p-8 text-center">
        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
          <Trophy className="h-7 w-7" />
        </span>
        <div>
          <p className="text-3xl font-semibold">{t('result.points', { score })}</p>
          {isNewBest ? (
            <p className="text-sm font-medium text-emerald-600">{t('result.newBest')}</p>
          ) : bestScore !== undefined ? (
            <p className="text-sm text-muted-foreground">{t('result.best', { score: bestScore })}</p>
          ) : null}
        </div>
        <div className="flex gap-3">
          <Button onClick={onPlayAgain}>{t('result.playAgain')}</Button>
          <Button asChild variant="outline">
            <Link to="/games">{t('result.backToGames')}</Link>
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
