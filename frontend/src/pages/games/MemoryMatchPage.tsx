import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { MemoryMatch } from '@/features/games/MemoryMatch/MemoryMatch';
import { GameStartScreen } from '@/components/games/GameStartScreen';
import { GAME_ICONS, gameAccent } from '@/features/games/gameStyles';

export function MemoryMatchPage() {
  const { t } = useTranslation('games');
  const [started, setStarted] = useState(false);

  return (
    <div className="flex flex-col gap-6">
      <Link to="/games" className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" /> {t('common.backToGames')}
      </Link>
      {started ? (
        <>
          <h1 className="text-xl font-semibold">{t('memoryMatch.title')}</h1>
          <MemoryMatch />
        </>
      ) : (
        <GameStartScreen
          icon={GAME_ICONS.memory_match}
          accent={gameAccent('memory_match')}
          title={t('memoryMatch.title')}
          instructions={t('memoryMatch.instructions')}
          onStart={() => setStarted(true)}
        />
      )}
    </div>
  );
}
