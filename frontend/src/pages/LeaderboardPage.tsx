import { useTranslation } from 'react-i18next';
import { Medal, Trophy } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { useLeaderboard } from '@/features/gamification/useGamification';

const MEDAL_COLORS = ['text-amber-500', 'text-slate-400', 'text-amber-700'];

export function LeaderboardPage() {
  const { t } = useTranslation('gamification');
  const { data: leaderboard, isLoading } = useLeaderboard();

  if (isLoading || !leaderboard) {
    return <FullPageSpinner />;
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('leaderboard.title')}</h1>
        <p className="text-muted-foreground">{t('leaderboard.subtitle')}</p>
      </div>

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
                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-sm font-semibold">
                  {entry.rank <= 3 ? <Medal className={`h-4 w-4 ${MEDAL_COLORS[entry.rank - 1]}`} /> : entry.rank}
                </span>
                <div>
                  <p className="text-sm font-medium">{entry.name}</p>
                  <p className="text-xs text-muted-foreground">{t('widget.level', { level: entry.level })}</p>
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
