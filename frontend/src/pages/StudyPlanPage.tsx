import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import {
  AlertTriangle,
  BookOpenCheck,
  CalendarRange,
  Check,
  Flame,
  Gamepad2,
  Gauge,
  ListChecks,
  Moon,
  ShieldCheck,
  Target,
  Trophy,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { FadeInItem, FadeInStagger } from '@/components/motion/FadeIn';
import { useGamificationSummary } from '@/features/gamification/useGamification';
import { useStudyPlan } from '@/features/examProfile/useExamProfile';
import { ExamProfileDialog } from '@/features/examProfile/ExamProfileDialog';
import { PHASE_COLORS, PHASE_ORDER } from '@/features/examProfile/phaseStyles';
import type { CategoryRef, DailyPlanBlock, ReadinessGap, WeeklyDayFocus } from '@/features/examProfile/types';

const ACTIVITY_LINK: Record<string, string> = {
  weak_category_practice: '/test/practice',
  timed_mock_practice: '/test/mock',
  cognitive_game_warmup: '/games',
  strong_category_maintenance: '/test/practice',
  confidence_review: '/test/practice',
};

const ACTIVITY_ICON: Record<string, typeof Target> = {
  weak_category_practice: Target,
  timed_mock_practice: ListChecks,
  cognitive_game_warmup: Gamepad2,
  strong_category_maintenance: ShieldCheck,
  confidence_review: BookOpenCheck,
  rest: Moon,
};

const DAY_ORDER = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

export function StudyPlanPage() {
  const { t, i18n } = useTranslation(['studyPlan', 'dashboard']);
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { data: plan, isLoading } = useStudyPlan();
  const { data: gami } = useGamificationSummary();

  if (isLoading) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-40 w-full rounded-xl" />
        <div className="grid gap-4 sm:grid-cols-3">
          <Skeleton className="h-24 rounded-xl" />
          <Skeleton className="h-24 rounded-xl" />
          <Skeleton className="h-24 rounded-xl" />
        </div>
        <Skeleton className="h-64 w-full rounded-xl" />
      </div>
    );
  }

  const categoryLabel = (category: CategoryRef | null) =>
    category ? (locale === 'si' ? category.name_si : category.name_en) : null;

  const focusLabel = (day: { focus: WeeklyDayFocus; category: CategoryRef | null }) =>
    categoryLabel(day.category) ?? t(`focus.${day.focus}`);

  const todayKey = DAY_ORDER[(new Date().getDay() + 6) % 7]; // getDay(): Sun=0 -> map to Monday-first index

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{t('title')}</h1>
          <p className="text-muted-foreground">{t('subtitle')}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="ghost" asChild>
            <Link to="/dashboard">{t('dashboard:examProfile.backToDashboard')}</Link>
          </Button>
          <ExamProfileDialog trigger={<Button variant="outline">{t('dashboard:examProfile.edit')}</Button>} />
        </div>
      </div>

      {!plan ? null : !plan.exam_category ? (
        <NoExamWeakAreaPanel weakCategories={plan.weak_categories} locale={locale} t={t} />
      ) : (
        <>
          {/* Hero: motivational headline + streak/XP context, urgency-colored by phase */}
          <Card className={`relative overflow-hidden border ${PHASE_COLORS[plan.phase]}`}>
            <div className="gradient-orb -right-12 -top-16 h-48 w-48 bg-current opacity-10" />
            <CardContent className="relative flex flex-col gap-3 p-6 sm:p-8">
              <Badge variant="outline" className={`w-fit ${PHASE_COLORS[plan.phase]}`}>
                {t(`phaseNames.${plan.phase}`)}
              </Badge>
              <h2 className="text-xl font-semibold sm:text-2xl">{t(`motivation.${plan.phase}`)}</h2>
              <p className="text-sm text-muted-foreground">
                {plan.exam_name ?? t('dashboard:examProfile.title')}
                {plan.days_remaining !== null && (
                  <> &middot; {t('daysLeft', { count: plan.days_remaining })}</>
                )}
              </p>
              {plan.prep_day_number !== null && plan.prep_total_days !== null && (
                <div className="flex flex-col gap-1.5">
                  <p className="text-xs text-muted-foreground">
                    {t('prepDayOfTotal', { day: plan.prep_day_number, total: plan.prep_total_days })}
                  </p>
                  <Progress value={plan.prep_progress_percent ?? 0} className="h-1.5" />
                </div>
              )}
              {gami && (
                <div className="mt-1 flex flex-wrap gap-3 text-sm">
                  <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-background/60 px-3 py-1">
                    <Flame className="h-3.5 w-3.5 text-[color:var(--brand-gold)]" /> {t('streakChip', { count: gami.streak_days })}
                  </span>
                  <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-background/60 px-3 py-1">
                    <Trophy className="h-3.5 w-3.5 text-[color:var(--brand-gold)]" /> {t('rankChip', { level: gami.level })}
                  </span>
                </div>
              )}
            </CardContent>
          </Card>

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

          {/* Milestone roadmap: connected stepper instead of plain wrapped boxes */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('phaseTimeline')}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-start gap-1 overflow-x-auto pb-2">
                {PHASE_ORDER.map((phaseKey, idx) => {
                  const phaseData = plan.phase_timeline.find((p) => p.phase === phaseKey);
                  const currentIdx = PHASE_ORDER.indexOf(plan.phase);
                  const isPast = idx < currentIdx;
                  const isCurrent = phaseKey === plan.phase;

                  return (
                    <div key={phaseKey} className="flex min-w-[110px] flex-1 flex-col items-center gap-2">
                      <div className="flex w-full items-center">
                        <div
                          className={`h-0.5 flex-1 ${idx === 0 ? 'opacity-0' : isPast || isCurrent ? 'bg-primary' : 'bg-border'}`}
                        />
                        <span
                          className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold ${
                            isCurrent
                              ? 'border-primary bg-primary text-primary-foreground'
                              : isPast
                                ? 'border-primary bg-primary/20 text-primary'
                                : 'border-border bg-background text-muted-foreground'
                          }`}
                        >
                          {isPast ? <Check className="h-4 w-4" /> : idx + 1}
                        </span>
                        <div
                          className={`h-0.5 flex-1 ${idx === PHASE_ORDER.length - 1 ? 'opacity-0' : isPast ? 'bg-primary' : 'bg-border'}`}
                        />
                      </div>
                      <p className={`text-center text-xs font-medium ${isCurrent ? 'text-primary' : 'text-muted-foreground'}`}>
                        {phaseData ? (locale === 'si' ? phaseData.label_si : phaseData.label_en) : t(`phaseNames.${phaseKey}`)}
                      </p>
                      {phaseData?.from_days_remaining !== null && phaseData?.to_days_remaining !== null && phaseData && (
                        <p className="text-center text-[10px] text-muted-foreground">
                          {t('phaseRange', { from: phaseData.from_days_remaining, to: phaseData.to_days_remaining })}
                        </p>
                      )}
                    </div>
                  );
                })}
              </div>
            </CardContent>
          </Card>

          <ReadinessGapPanel gap={plan.readiness_gap} locale={locale} t={t} />

          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('weeklySchedule')}</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-7 gap-1.5">
                  {plan.weekly_schedule.map((day) => {
                    const isToday = day.day === todayKey;
                    const Icon = ACTIVITY_ICON[day.focus === 'mock' ? 'timed_mock_practice' : day.focus === 'rest' || day.focus === 'rest_light' ? 'rest' : 'weak_category_practice'] ?? Target;
                    return (
                      <div
                        key={day.day}
                        className={`flex flex-col items-center gap-1.5 rounded-xl border p-2 text-center ${
                          isToday ? 'border-primary bg-primary/10 ring-1 ring-primary/40' : 'border-border'
                        }`}
                      >
                        <span className="text-[10px] font-medium uppercase text-muted-foreground">
                          {t(`days.${day.day}`).slice(0, 3)}
                        </span>
                        <Icon className={`h-4 w-4 ${isToday ? 'text-primary' : 'text-muted-foreground'}`} />
                        <span className="line-clamp-2 text-[10px] leading-tight text-muted-foreground">{focusLabel(day)}</span>
                      </div>
                    );
                  })}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('todaysPlan')}</CardTitle>
              </CardHeader>
              <CardContent>
                <FadeInStagger className="flex flex-col gap-2">
                  {plan.daily_plan.map((block, index) => (
                    <FadeInItem key={index}>
                      <DailyPlanRow block={block} categoryLabel={categoryLabel} t={t} />
                    </FadeInItem>
                  ))}
                </FadeInStagger>
              </CardContent>
            </Card>
          </div>
        </>
      )}
    </div>
  );
}

function DailyPlanRow({
  block,
  categoryLabel,
  t,
}: {
  block: DailyPlanBlock;
  categoryLabel: (category: CategoryRef | null) => string | null;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const Icon = ACTIVITY_ICON[block.activity] ?? Target;
  const baseLink = ACTIVITY_LINK[block.activity];
  // Deep-links into the recommended category so the student doesn't have to
  // search for it manually - only practice-page activities support this,
  // via PracticeTestPage's ?category= param.
  const link = baseLink && block.category && baseLink === '/test/practice'
    ? `${baseLink}?category=${block.category.category_id}`
    : baseLink;

  return (
    <div className="flex items-center justify-between gap-2 rounded-lg border border-border p-3">
      <div className="flex items-center gap-3">
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <Icon className="h-4 w-4" />
        </span>
        <div>
          <p className="text-sm font-medium">{t(`dashboard:examProfile.activity.${block.activity}`)}</p>
          {block.category && <p className="text-xs text-muted-foreground">{categoryLabel(block.category)}</p>}
        </div>
      </div>
      <div className="flex items-center gap-2">
        {block.minutes !== null && <Badge variant="outline">{t('minutes', { count: block.minutes })}</Badge>}
        {link && (
          <Button size="sm" variant="secondary" asChild>
            <Link to={link}>{t('start')}</Link>
          </Button>
        )}
      </div>
    </div>
  );
}

function ReadinessGapPanel({
  gap,
  locale,
  t,
}: {
  gap: ReadinessGap;
  locale: 'en' | 'si';
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const hasReadiness = gap.current_readiness_percent !== null;
  const hasPace = gap.current_pace_seconds !== null;
  const hasTargetPace = gap.target_pace_seconds !== null;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Gauge className="h-4 w-4" /> {t('readinessGap.title')}
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <GapStat label={t('readinessGap.currentReadiness')} value={hasReadiness ? `${gap.current_readiness_percent}%` : '-'} />
          <GapStat label={t('readinessGap.targetReadiness')} value={`${gap.target_readiness_percent}%`} />
          <GapStat
            label={t('readinessGap.currentPace')}
            value={hasPace ? t('readinessGap.secondsPerQuestion', { count: gap.current_pace_seconds }) : '-'}
          />
          <GapStat
            label={t('readinessGap.targetPace')}
            value={hasTargetPace ? t('readinessGap.secondsPerQuestion', { count: gap.target_pace_seconds }) : '-'}
          />
        </div>
        {!hasReadiness && <p className="text-xs text-muted-foreground">{t('readinessGap.noPredictionYet')}</p>}
        {!hasTargetPace && <p className="text-xs text-muted-foreground">{t('readinessGap.noExamPaceYet')}</p>}

        {gap.warning && (
          <div
            className={`flex items-start gap-2 rounded-lg border p-3 text-sm ${
              gap.warning.severity === 'high'
                ? 'border-destructive/40 bg-destructive/10 text-destructive'
                : 'border-warning/40 bg-warning/15 text-warning-foreground'
            }`}
          >
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
            <span>{locale === 'si' ? gap.warning.message_si : gap.warning.message_en}</span>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function GapStat({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-0.5 rounded-lg border border-border p-2.5">
      <span className="text-[11px] text-muted-foreground">{label}</span>
      <span className="text-sm font-semibold">{value}</span>
    </div>
  );
}

/**
 * Shown instead of the full exam-phase plan when the student has no exam
 * profile, since the phase/mock-test/pace-gap machinery below assumes an
 * exam date. weak_categories doesn't depend on an exam profile, so a
 * student training generally still gets a concrete "practice this" nudge
 * instead of just a "set up an exam" prompt.
 */
function NoExamWeakAreaPanel({
  weakCategories,
  locale,
  t,
}: {
  weakCategories: CategoryRef[];
  locale: 'en' | 'si';
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const top = weakCategories.slice(0, 2);

  return (
    <div className="flex flex-col gap-4">
      <Card className="border-primary/30">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Target className="h-4 w-4" /> {t('noExamProfile.title')}
          </CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          <p className="text-sm text-muted-foreground">{t('noExamProfile.subtitle')}</p>
          {top.map((category) => (
            <div
              key={category.category_id}
              className="flex items-center justify-between gap-3 rounded-lg border border-border p-3"
            >
              <div>
                <p className="text-sm font-medium">{locale === 'si' ? category.name_si : category.name_en}</p>
                <p className="text-xs text-muted-foreground">
                  {t('noExamProfile.accuracy', { percent: Math.round(category.accuracy_percent) })}
                </p>
              </div>
              <Button size="sm" asChild>
                <Link to={`/test/practice?category=${category.category_id}`}>{t('start')}</Link>
              </Button>
            </div>
          ))}

          <div className="flex items-center justify-between gap-3 rounded-lg border border-border p-3">
            <div>
              <p className="text-sm font-medium">{t('noExamProfile.mockExamTitle')}</p>
              <p className="text-xs text-muted-foreground">{t('noExamProfile.mockExamHint')}</p>
            </div>
            <Button size="sm" variant="outline" asChild>
              <Link to="/test/mock">{t('start')}</Link>
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card className="border-dashed">
        <CardContent className="flex flex-col items-center gap-3 p-6 text-center">
          <CalendarRange className="h-6 w-6 text-muted-foreground" />
          <p className="text-sm text-muted-foreground">{t('noExamProfile.setupHint')}</p>
          <ExamProfileDialog trigger={<Button size="sm" variant="outline">{t('dashboard:examProfile.setup')}</Button>} />
        </CardContent>
      </Card>
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
