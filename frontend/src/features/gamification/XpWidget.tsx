import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Coins, Flame, Trophy } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useGamificationSummary } from './useGamification';

export function XpWidget() {
  const { t } = useTranslation('gamification');
  const { data: summary, isLoading } = useGamificationSummary();

  if (isLoading || !summary) {
    return null;
  }

  return (
    <Card className="border-primary/30">
      <CardContent className="flex flex-col gap-3 p-5">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <p className="text-sm font-medium text-muted-foreground">{t('widget.title')}</p>
            <p className="text-lg font-semibold">
              {t('widget.level', { level: summary.level })} · {summary.level_title}
            </p>
          </div>
          <div className="flex items-center gap-4 text-sm font-medium">
            <span className="flex items-center gap-1.5 text-amber-500">
              <Coins className="h-4 w-4" /> {summary.coins}
            </span>
            <span className="flex items-center gap-1.5 text-orange-500">
              <Flame className="h-4 w-4" /> {summary.streak_days}
            </span>
          </div>
        </div>
        <Progress value={summary.progress_percent} />
        <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
          <span>{t('widget.xpToNext', { current: summary.xp_into_level, target: summary.xp_for_next_level })}</span>
          <Link to="/badges" className="flex items-center gap-1 hover:text-foreground">
            <Trophy className="h-3.5 w-3.5" />
            {t('widget.badgesEarned', { earned: summary.badges_earned, total: summary.badges_total })}
          </Link>
        </div>
      </CardContent>
    </Card>
  );
}
