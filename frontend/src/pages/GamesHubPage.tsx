import { Link } from 'react-router-dom';
import { ErrorState } from '@/components/ui/error-state';
import { useTranslation } from 'react-i18next';
import { Puzzle, Trophy } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { PageHeader } from '@/components/layout/PageHeader';
import { BalancedGrid } from '@/components/ui/balanced-grid';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { useGames } from '@/features/games/useGames';
import { useDashboardSummary } from '@/features/dashboard/useDashboard';
import { GAME_ICONS, GAME_ROUTES, gameAccent } from '@/features/games/gameStyles';

export function GamesHubPage() {
  const { i18n, t } = useTranslation(['common', 'games']);
  const { data: games, isLoading, isError, refetch } = useGames();
  const { data: summary } = useDashboardSummary();
  // Personal bests from the dashboard summary: seeing your own record is a reason to play again.
  const bestByGame = new Map((summary?.game_scores ?? []).map((g) => [g.game_code, g.best_score]));

  const locale = i18n.language.startsWith('si') ? 'si' : 'en';

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('nav.games')} subtitle={t('hub.subtitle', { ns: 'games' })} pattern="grid" />

      {isError ? (
        <ErrorState onRetry={() => refetch()} />
      ) : isLoading ? (
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
            const best = bestByGame.get(game.code);

            return (
              <Link key={game.code} to={GAME_ROUTES[game.code] ?? '/games'}>
                <Card
                  className="h-full overflow-hidden border-t-[3px]"
                  style={{ borderTopColor: accent }}
                >
                  <CardContent
                    className="flex h-full flex-col gap-3 p-6"
                    style={{ backgroundImage: `linear-gradient(160deg, color-mix(in oklch, ${accent}, transparent 94%), transparent 55%)` }}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <span
                        className="flex h-12 w-12 items-center justify-center rounded-xl"
                        style={{ backgroundColor: `color-mix(in oklch, ${accent}, transparent 85%)`, color: accent }}
                      >
                        <Icon className="h-6 w-6" />
                      </span>
                      {best !== undefined ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-[color:var(--brand-gold)]/15 px-2.5 py-1 text-xs font-medium">
                          <Trophy className="h-3.5 w-3.5 text-[color:var(--brand-gold-ink)]" />
                          {t('result.best', { ns: 'games', score: best })}
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                          {t('hub.notPlayedYet', { ns: 'games', defaultValue: 'Not played yet' })}
                        </span>
                      )}
                    </div>
                    <div className="mt-auto flex flex-col gap-1">
                      <p className="font-semibold">{name}</p>
                      <p className="line-clamp-2 text-sm text-muted-foreground">{description}</p>
                    </div>
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
