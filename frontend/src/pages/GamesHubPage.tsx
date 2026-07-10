import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Brain, Calculator, Eye, Grid3x3, RotateCw } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { useGames } from '@/features/games/useGames';

const ICONS: Record<string, typeof Brain> = {
  memory_match: Grid3x3,
  sequence_puzzle: Brain,
  math_rush: Calculator,
  mental_rotation: RotateCw,
  selective_attention: Eye,
};

const ROUTES: Record<string, string> = {
  memory_match: '/games/memory-match',
  sequence_puzzle: '/games/sequence-puzzle',
  math_rush: '/games/math-rush',
  mental_rotation: '/games/mental-rotation',
  selective_attention: '/games/selective-attention',
};

export function GamesHubPage() {
  const { i18n, t } = useTranslation(['common', 'games']);
  const { data: games, isLoading } = useGames();

  if (isLoading) {
    return <FullPageSpinner />;
  }

  const locale = i18n.language.startsWith('si') ? 'si' : 'en';

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('nav.games')}</h1>
        <p className="text-muted-foreground">{t('hub.subtitle', { ns: 'games' })}</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        {games?.map((game) => {
          const Icon = ICONS[game.code] ?? Brain;
          const name = locale === 'si' ? game.name_si : game.name_en;
          const description = locale === 'si' ? game.description_si : game.description_en;

          return (
            <Link key={game.code} to={ROUTES[game.code] ?? '/games'}>
              <Card className="h-full transition-shadow hover:shadow-md">
                <CardContent className="flex flex-col gap-3 p-6">
                  <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Icon className="h-6 w-6" />
                  </span>
                  <p className="font-semibold">{name}</p>
                  <p className="text-sm text-muted-foreground">{description}</p>
                </CardContent>
              </Card>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
