import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ArrowRight, Flame, Gamepad2, Target, TrendingUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { DashboardSkeleton } from '@/components/skeletons/DashboardSkeleton';
import { FadeInItem, FadeInStagger } from '@/components/motion/FadeIn';
import { MotionCard } from '@/components/motion/MotionCard';
import { useDashboardSummary, useProgressHistory } from '@/features/dashboard/useDashboard';
import type { IqClassification } from '@/features/dashboard/types';
import { ReadinessCard } from '@/features/readiness/ReadinessCard';
import { ExamCountdown } from '@/features/examProfile/ExamCountdown';
import { XpWidget } from '@/features/gamification/XpWidget';
import { MissionsCard } from '@/features/gamification/MissionsCard';

export function DashboardPage() {
  const { t, i18n } = useTranslation(['dashboard', 'common']);
  const { data: summary, isLoading } = useDashboardSummary();
  const { data: history } = useProgressHistory();

  if (isLoading || !summary) {
    return <DashboardSkeleton />;
  }

  const locale = i18n.language.startsWith('si') ? 'si' : 'en';

  const categoryChartData = summary.category_strengths.map((c) => ({
    name: locale === 'si' ? c.name_si : c.name_en,
    accuracy: c.accuracy_percent ?? 0,
  }));

  const levelChartData = (history?.level_history ?? []).map((point) => ({
    date: point.date.slice(5),
    level: point.level_number,
  }));

  const trend =
    levelChartData.length >= 2 ? levelChartData[levelChartData.length - 1].level - levelChartData[0].level : 0;

  return (
    <FadeInStagger className="flex flex-col gap-10">
      {/* Primary zone: exam countdown, readiness, current level/trend, one clear next action. */}
      <div className="flex flex-col gap-4">
        <FadeInItem className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">{t('common:nav.dashboard')}</h1>
            <p className="text-muted-foreground">{t('subtitle')}</p>
          </div>
          <div className="flex flex-col items-end gap-1">
            <Button asChild size="lg" className="shadow-lg shadow-primary/25">
              <Link to="/test/daily">
                {t('primaryCta')} <ArrowRight className="h-4 w-4" />
              </Link>
            </Button>
            <Link to="/test/practice" className="text-xs text-muted-foreground hover:text-foreground hover:underline">
              {t('secondaryLink')}
            </Link>
          </div>
        </FadeInItem>

        {summary.iq_estimate && (
          <FadeInItem>
            <Card className="border-primary/30">
              <CardContent className="flex flex-col gap-3 p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">{t('iqScore.label')}</p>
                    <div className="flex items-baseline gap-2">
                      <p className="text-4xl font-semibold tracking-tight">{summary.iq_estimate.iq_score}</p>
                      {trend !== 0 && (
                        <span
                          className={`flex items-center gap-0.5 text-sm font-medium ${trend > 0 ? 'text-success' : 'text-muted-foreground'}`}
                        >
                          <TrendingUp className="h-3.5 w-3.5" />
                          {trend > 0 ? `+${trend}` : trend}
                        </span>
                      )}
                    </div>
                  </div>
                  <Badge variant={classificationBadgeVariant(summary.iq_estimate.classification)} className="text-sm">
                    {t(`iqScore.classification.${summary.iq_estimate.classification}`)}
                  </Badge>
                </div>

                <details className="group text-xs text-muted-foreground">
                  <summary className="cursor-pointer select-none font-medium text-muted-foreground hover:text-foreground">
                    {t('iqScore.howEstimatedToggle')}
                  </summary>
                  <p className="mt-2 max-w-md">
                    {summary.iq_estimate.theta_se !== null
                      ? t('iqScore.methodIrt', { se: summary.iq_estimate.theta_se.toFixed(2) })
                      : t('iqScore.methodIrtNoSe')}
                  </p>
                </details>
              </CardContent>
            </Card>
          </FadeInItem>
        )}

        <FadeInItem className="grid gap-4 lg:grid-cols-2">
          <ExamCountdown />
          <ReadinessCard />
        </FadeInItem>
      </div>

      {/* Secondary zone: everything else, visually subordinate to the primary zone above. */}
      <div className="flex flex-col gap-4 border-t border-border pt-8">
        <FadeInItem>
          <h2 className="text-sm font-medium text-muted-foreground">{t('moreProgress')}</h2>
        </FadeInItem>

        <FadeInItem>
          <XpWidget />
        </FadeInItem>

        <FadeInItem>
          <MissionsCard />
        </FadeInItem>

        <FadeInItem className="grid gap-4 sm:grid-cols-3">
          <StatCard
            icon={<Target className="h-5 w-5" />}
            accent="var(--chart-3)"
            label={t('currentLevel')}
            value={summary.current_level ? (locale === 'si' ? summary.current_level.name_si : summary.current_level.name_en) : '-'}
          />
          <StatCard
            icon={<Flame className="h-5 w-5" />}
            accent="var(--chart-4)"
            label={t('practiceStreak')}
            value={t('streakDays', { count: summary.streak_days })}
          />
          <StatCard
            icon={<Gamepad2 className="h-5 w-5" />}
            accent="var(--chart-2)"
            label={t('gamesPlayed')}
            value={String(summary.game_scores.reduce((sum, g) => sum + g.plays, 0))}
          />
        </FadeInItem>

        <FadeInItem className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('levelHistory')}</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              {levelChartData.length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={levelChartData}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey="date" fontSize={12} />
                    <YAxis domain={[1, 5]} allowDecimals={false} fontSize={12} />
                    <Tooltip />
                    <Line type="monotone" dataKey="level" stroke="var(--primary)" strokeWidth={2} dot={{ r: 3 }} />
                  </LineChart>
                </ResponsiveContainer>
              ) : (
                <EmptyChartState message={t('notEnoughData')} />
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('categoryStrengths')}</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
              {categoryChartData.some((c) => c.accuracy > 0) ? (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={categoryChartData} layout="vertical" margin={{ left: 24 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                    <XAxis type="number" domain={[0, 100]} fontSize={12} />
                    <YAxis type="category" dataKey="name" width={110} fontSize={11} />
                    <Tooltip />
                    <Bar dataKey="accuracy" fill="var(--primary)" radius={4} />
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <EmptyChartState message={t('notEnoughData')} />
              )}
            </CardContent>
          </Card>
        </FadeInItem>

        <FadeInItem className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('recentSessions')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
              {summary.recent_sessions.length === 0 && (
                <p className="text-sm text-muted-foreground">{t('noSessionsYet')}</p>
              )}
              {summary.recent_sessions.map((session) => (
                <div
                  key={session.id}
                  className="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:border-primary/30 hover:bg-primary/5"
                >
                  <div>
                    <p className="text-sm font-medium capitalize">{session.category_name ?? session.session_type}</p>
                    <p className="text-xs text-muted-foreground">
                      {new Date(session.completed_at).toLocaleDateString()}
                    </p>
                  </div>
                  <Badge variant={session.score_percent >= 70 ? 'default' : 'secondary'}>
                    {session.score_percent}%
                  </Badge>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('gameHighScores')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
              {summary.game_scores.length === 0 && (
                <p className="text-sm text-muted-foreground">
                  {t('noGamesYet')}{' '}
                  <Link to="/games" className="text-primary underline-offset-4 hover:underline">
                    {t('tryOne')}
                  </Link>
                  .
                </p>
              )}
              {summary.game_scores.map((game) => (
                <div
                  key={game.game_code}
                  className="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:border-primary/30 hover:bg-primary/5"
                >
                  <p className="text-sm font-medium">{game.game_name}</p>
                  <span className="text-sm font-semibold">{game.best_score} pts</span>
                </div>
              ))}
            </CardContent>
          </Card>
        </FadeInItem>
      </div>
    </FadeInStagger>
  );
}

function StatCard({ icon, label, value, accent }: { icon: ReactNode; label: string; value: string; accent: string }) {
  return (
    <MotionCard>
      <CardContent className="flex items-center gap-4 p-5">
        <span
          className="flex h-11 w-11 items-center justify-center rounded-xl"
          style={{ backgroundColor: `color-mix(in oklch, ${accent}, transparent 85%)`, color: accent }}
        >
          {icon}
        </span>
        <div>
          <p className="text-xs text-muted-foreground">{label}</p>
          <p className="text-lg font-semibold">{value}</p>
        </div>
      </CardContent>
    </MotionCard>
  );
}

function EmptyChartState({ message }: { message: string }) {
  return <div className="flex h-full items-center justify-center text-sm text-muted-foreground">{message}</div>;
}

function classificationBadgeVariant(classification: IqClassification): 'success' | 'secondary' | 'warning' | 'destructive' {
  switch (classification) {
    case 'gifted':
    case 'above_average':
      return 'success';
    case 'average':
      return 'secondary';
    case 'below_average':
      return 'warning';
    case 'extremely_low':
      return 'destructive';
  }
}
