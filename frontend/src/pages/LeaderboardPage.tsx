import { useTranslation } from 'react-i18next';
import { ErrorState } from '@/components/ui/error-state';
import { Medal, Trophy } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/badge';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { useLeaderboard } from '@/features/gamification/useGamification';

// Real gold/silver/bronze - 2nd place used to reuse text-muted-foreground (indistinguishable from
// disabled UI text) and 3rd reused the ruby category color; both now have their own dedicated tokens.
const MEDAL_COLORS = ['text-brand-gold-ink', 'text-medal-silver', 'text-medal-bronze'];
const MEDAL_BG = ['var(--brand-gold)', 'var(--medal-silver)', 'var(--medal-bronze)'];

export function LeaderboardPage() {
  const { t } = useTranslation('gamification');
  const { data: leaderboard, isLoading, isError, refetch } = useLeaderboard();

  if (isError) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  if (isLoading || !leaderboard) {
    return <FullPageSpinner />;
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('leaderboard.title')} subtitle={t('leaderboard.subtitle')} pattern="steps" />

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Trophy className="h-4 w-4 text-primary" />
            {t('leaderboard.title')}
          </CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-2">
          {leaderboard.top.map((entry) => (
            <div
              key={entry.user_id}
              className={`flex items-center justify-between gap-3 rounded-lg border p-3 ${
                entry.is_you ? 'border-primary/40 bg-primary/5' : 'border-border'
              }`}
            >
              <div className="flex items-center gap-3">
                <span
                  className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold ${entry.rank > 3 ? 'bg-muted' : ''}`}
                  style={
                    entry.rank <= 3
                      ? { backgroundColor: `color-mix(in oklch, ${MEDAL_BG[entry.rank - 1]}, transparent 85%)` }
                      : undefined
                  }
                >
                  {entry.rank <= 3 ? <Medal className={`h-4 w-4 ${MEDAL_COLORS[entry.rank - 1]}`} /> : entry.rank}
                </span>
                <div>
                  <p className="text-sm font-medium">{entry.name}</p>
                  <p className="text-xs text-muted-foreground">
                    {t(`widget.levelTitle.${entry.level}`, { defaultValue: `Level ${entry.level}` })}
                  </p>
                </div>
              </div>
              <Badge variant={entry.is_you ? 'default' : 'secondary'}>{entry.xp} XP</Badge>
            </div>
          ))}

          {leaderboard.top.length === 0 && <p className="text-sm text-muted-foreground">-</p>}

          {leaderboard.your_rank !== null && (
            <p className="pt-2 text-center text-sm text-muted-foreground">
              {t('leaderboard.yourRank', { rank: leaderboard.your_rank })}
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
