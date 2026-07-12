import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { SequencePuzzle } from '@/features/games/SequencePuzzle/SequencePuzzle';
import { GameStartScreen } from '@/components/games/GameStartScreen';
import { GAME_ICONS, gameAccent } from '@/features/games/gameStyles';

export function SequencePuzzlePage() {
  const { t } = useTranslation('games');
  const [started, setStarted] = useState(false);

  return (
    <div className="flex flex-col gap-6">
      <Link to="/games" className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" /> {t('common.backToGames')}
      </Link>
      {started ? (
        <>
          <h1 className="text-xl font-semibold">{t('sequencePuzzle.title')}</h1>
          <SequencePuzzle />
        </>
      ) : (
        <GameStartScreen
          icon={GAME_ICONS.sequence_puzzle}
          accent={gameAccent('sequence_puzzle')}
          title={t('sequencePuzzle.title')}
          instructions={t('sequencePuzzle.instructions')}
          onStart={() => setStarted(true)}
        />
      )}
    </div>
  );
}
