import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { VisualSpatialMemory } from '@/features/games/VisualSpatialMemory/VisualSpatialMemory';
import { GameStartScreen } from '@/components/games/GameStartScreen';
import { GAME_ICONS, gameAccent } from '@/features/games/gameStyles';

export function VisualSpatialMemoryPage() {
  const { t } = useTranslation('games');
  const [started, setStarted] = useState(false);

  return (
    <div className="flex flex-col gap-6">
      <Link to="/games" className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" /> {t('common.backToGames')}
      </Link>
      {started ? (
        <>
          <h1 className="text-xl font-semibold">{t('visualSpatialMemory.title')}</h1>
          <VisualSpatialMemory />
        </>
      ) : (
        <GameStartScreen
          icon={GAME_ICONS.visual_spatial_memory}
          accent={gameAccent('visual_spatial_memory')}
          title={t('visualSpatialMemory.title')}
          instructions={t('visualSpatialMemory.instructions')}
          onStart={() => setStarted(true)}
        />
      )}
    </div>
  );
}
