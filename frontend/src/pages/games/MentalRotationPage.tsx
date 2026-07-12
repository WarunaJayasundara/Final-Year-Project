import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { MentalRotation } from '@/features/games/MentalRotation/MentalRotation';
import { GameStartScreen } from '@/components/games/GameStartScreen';
import { GAME_ICONS, gameAccent } from '@/features/games/gameStyles';

export function MentalRotationPage() {
  const { t } = useTranslation('games');
  const [started, setStarted] = useState(false);

  return (
    <div className="flex flex-col gap-6">
      <Link to="/games" className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" /> {t('common.backToGames')}
      </Link>
      {started ? (
        <>
          <h1 className="text-xl font-semibold">{t('mentalRotation.title')}</h1>
          <MentalRotation />
        </>
      ) : (
        <GameStartScreen
          icon={GAME_ICONS.mental_rotation}
          accent={gameAccent('mental_rotation')}
          title={t('mentalRotation.title')}
          instructions={t('mentalRotation.instructions')}
          onStart={() => setStarted(true)}
        />
      )}
    </div>
  );
}
