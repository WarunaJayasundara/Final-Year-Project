import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Puzzle } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { BalancedGrid } from '@/components/ui/balanced-grid';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { useGames } from '@/features/games/useGames';
import { GAME_ICONS, GAME_ROUTES, gameAccent } from '@/features/games/gameStyles';

export function GamesHubPage() {
  const { i18n, t } = useTranslation(['common', 'games']);
  const { data: games, isLoading } = useGames();

  const locale = i18n.language.startsWith('si') ? 'si' : 'en';

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('nav.games')}</h1>
        <p className="text-muted-foreground">{t('hub.subtitle', { ns: 'games' })}</p>
      </div>

      {isLoading ? (
        <CardGridSkeleton count={8} />
      ) : (
        <BalancedGrid
          items={games ?? []}
          columns={{ base: 1, sm: 2, lg: 4 }}
          renderItem={(game) => {
            const Icon = GAME_ICONS[game.code] ?? Puzzle;
            const accent = gameAccent(game.code);
            const name = locale === 'si' ? game.name_si : game.name_en;
            const description = locale === 'si' ? game.description_si : game.description_en;

            return (
              <Link key={game.code} to={GAME_ROUTES[game.code] ?? '/games'}>
                <Card className="h-full transition-shadow hover:shadow-md">
                  <CardContent className="flex flex-col gap-3 p-6">
                    <span
                      className="flex h-12 w-12 items-center justify-center rounded-xl"
                      style={{ backgroundColor: `color-mix(in oklch, ${accent}, transparent 85%)`, color: accent }}
                    >
                      <Icon className="h-6 w-6" />
                    </span>
                    <p className="font-semibold">{name}</p>
                    <p className="text-sm text-muted-foreground">{description}</p>
                  </CardContent>
                </Card>
              </Link>
            );
          }}
        />
      )}
    </div>
  );
}
