import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { WorkingMemorySpan } from '@/features/games/WorkingMemorySpan/WorkingMemorySpan';
import { GameStartScreen } from '@/components/games/GameStartScreen';
import { GAME_ICONS, gameAccent } from '@/features/games/gameStyles';

export function WorkingMemorySpanPage() {
  const { t } = useTranslation('games');
  const [started, setStarted] = useState(false);

  return (
    <div className="flex flex-col gap-6">
      <Link to="/games" className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" /> {t('common.backToGames')}
      </Link>
      {started ? (
        <>
          <h1 className="text-xl font-semibold">{t('workingMemorySpan.title')}</h1>
          <WorkingMemorySpan />
        </>
      ) : (
        <GameStartScreen
          icon={GAME_ICONS.working_memory_span}
          accent={gameAccent('working_memory_span')}
          title={t('workingMemorySpan.title')}
          instructions={t('workingMemorySpan.instructions')}
          onStart={() => setStarted(true)}
        />
      )}
    </div>
  );
}
