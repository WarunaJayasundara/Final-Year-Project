import { useTranslation } from 'react-i18next';
import { CalendarRange, ListChecks, Target } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { useStudyPlan } from '@/features/examProfile/useExamProfile';
import { ExamProfileDialog } from '@/features/examProfile/ExamProfileDialog';
import type { CategoryRef, WeeklyDayFocus } from '@/features/examProfile/types';

export function StudyPlanPage() {
  const { t, i18n } = useTranslation(['studyPlan', 'dashboard']);
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { data: plan, isLoading } = useStudyPlan();

  if (isLoading) {
    return <FullPageSpinner />;
  }

  const categoryLabel = (category: CategoryRef | null) =>
    category ? (locale === 'si' ? category.name_si : category.name_en) : null;

  const focusLabel = (day: { focus: WeeklyDayFocus; category: CategoryRef | null }) =>
    categoryLabel(day.category) ?? t(`focus.${day.focus}`);

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{t('title')}</h1>
          <p className="text-muted-foreground">{t('subtitle')}</p>
        </div>
        <ExamProfileDialog trigger={<Button variant="outline">{t('dashboard:examProfile.edit')}</Button>} />
      </div>

      {!plan || !plan.exam_category ? (
        <Card className="border-dashed">
          <CardContent className="flex flex-col items-center gap-3 p-8 text-center">
            <CalendarRange className="h-8 w-8 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">{t('dashboard:examProfile.noProfileYet')}</p>
            <ExamProfileDialog trigger={<Button size="sm">{t('dashboard:examProfile.setup')}</Button>} />
          </CardContent>
        </Card>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-3">
            <StatCard
              icon={<Target className="h-5 w-5" />}
              label={t('recommendedDailyQuestions')}
              value={String(plan.recommended_daily_questions)}
            />
            <StatCard
              icon={<ListChecks className="h-5 w-5" />}
              label={t('recommendedWeeklyMockTests')}
              value={String(plan.recommended_weekly_mock_tests)}
            />
            <StatCard
              icon={<CalendarRange className="h-5 w-5" />}
              label={t('weeksRemaining')}
              value={plan.weeks_remaining !== null ? String(plan.weeks_remaining) : '-'}
            />
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('phaseTimeline')}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex flex-wrap gap-3">
                {plan.phase_timeline.map((phase) => (
                  <div
                    key={phase.phase}
                    className={`flex-1 min-w-[140px] rounded-xl border p-4 ${
                      phase.is_current ? 'border-primary bg-primary/10' : 'border-border'
                    }`}
                  >
                    <p className="text-sm font-medium">{locale === 'si' ? phase.label_si : phase.label_en}</p>
                    {phase.from_days_remaining !== null && (
                      <p className="mt-1 text-xs text-muted-foreground">
                        {t('phaseRange', { from: phase.from_days_remaining, to: phase.to_days_remaining })}
                      </p>
                    )}
                    {phase.is_current && (
                      <Badge className="mt-2" variant="default">
                        {t('currentPhase')}
                      </Badge>
                    )}
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('weeklySchedule')}</CardTitle>
              </CardHeader>
              <CardContent className="flex flex-col gap-2">
                {plan.weekly_schedule.map((day) => (
                  <div key={day.day} className="flex items-center justify-between rounded-lg border border-border p-3">
                    <p className="text-sm font-medium capitalize">{t(`days.${day.day}`)}</p>
                    <Badge variant="secondary">{focusLabel(day)}</Badge>
                  </div>
                ))}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('todaysPlan')}</CardTitle>
              </CardHeader>
              <CardContent className="flex flex-col gap-2">
                {plan.daily_plan.map((block, index) => (
                  <div key={index} className="flex items-center justify-between rounded-lg border border-border p-3">
                    <div>
                      <p className="text-sm font-medium">{t(`dashboard:examProfile.activity.${block.activity}`)}</p>
                      {block.category && <p className="text-xs text-muted-foreground">{categoryLabel(block.category)}</p>}
                    </div>
                    {block.minutes !== null && <Badge variant="outline">{t('minutes', { count: block.minutes })}</Badge>}
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>
        </>
      )}
    </div>
  );
}

function StatCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return (
    <Card>
      <CardContent className="flex items-center gap-4 p-5">
        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">{icon}</span>
        <div>
          <p className="text-xs text-muted-foreground">{label}</p>
          <p className="text-lg font-semibold">{value}</p>
        </div>
      </CardContent>
    </Card>
  );
}
