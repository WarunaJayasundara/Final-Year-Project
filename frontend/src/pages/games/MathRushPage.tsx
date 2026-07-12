import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { MathRush } from '@/features/games/MathRush/MathRush';
import { GameStartScreen } from '@/components/games/GameStartScreen';
import { GAME_ICONS, gameAccent } from '@/features/games/gameStyles';

export function MathRushPage() {
  const { t } = useTranslation('games');
  const [started, setStarted] = useState(false);

  return (
    <div className="flex flex-col gap-6">
      <Link to="/games" className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" /> {t('common.backToGames')}
      </Link>
      {started ? (
        <>
          <h1 className="text-xl font-semibold">{t('mathRush.title')}</h1>
          <MathRush />
        </>
      ) : (
        <GameStartScreen
          icon={GAME_ICONS.math_rush}
          accent={gameAccent('math_rush')}
          title={t('mathRush.title')}
          instructions={t('mathRush.instructions')}
          onStart={() => setStarted(true)}
        />
      )}
    </div>
  );
}
