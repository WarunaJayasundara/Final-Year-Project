import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { SequencePuzzle } from '@/features/games/SequencePuzzle/SequencePuzzle';

export function SequencePuzzlePage() {
  const { t } = useTranslation('games');

  return (
    <div className="flex flex-col gap-6">
      <Link to="/games" className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" /> {t('common.backToGames')}
      </Link>
      <h1 className="text-xl font-semibold">{t('sequencePuzzle.title')}</h1>
      <SequencePuzzle />
    </div>
  );
}
