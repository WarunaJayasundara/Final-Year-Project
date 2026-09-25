import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { CalendarClock, CalendarDays, Check, ChevronRight, History, ListChecks, Settings2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CountUp } from '@/components/motion/CountUp';
import { useExamHistory, useExamProfile, useStudyPlan } from './useExamProfile';
import { ExamProfileDialog } from './ExamProfileDialog';
import { ExamOutcomeDialog } from './ExamOutcomeDialog';
import { PHASE_COLORS, PHASE_ORDER, PHASE_RING_COLOR } from './phaseStyles';
import { ACTIVITY_ICON, DAY_ORDER } from './activityStyles';
import type { CategoryRef, StudyPlan } from './types';

function useNow(intervalMs: number) {
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), intervalMs);
    return () => clearInterval(id);
  }, [intervalMs]);
  return now;
}

export function ExamCountdown() {
  const { t, i18n } = useTranslation(['dashboard', 'studyPlan']);
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { data: profile, isLoading } = useExamProfile();
  const { data: plan } = useStudyPlan();
  const now = useNow(60_000);
  const [ringReady, setRingReady] = useState(false);
  useEffect(() => {
    const id = requestAnimationFrame(() => setRingReady(true));
    return () => cancelAnimationFrame(id);
  }, []);

  if (isLoading) {
    return null;
  }

  if (!profile) {
    return (
      <div className="flex flex-col gap-3">
        <Card className="border-dashed">
          <CardContent className="flex flex-col items-center gap-3 p-6 text-center">
            <CalendarClock className="h-8 w-8 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">{t('examProfile.noProfileYet')}</p>
            <ExamProfileDialog trigger={<Button size="sm">{t('examProfile.setup')}</Button>} />
          </CardContent>
        </Card>
        <PastExamsList />
      </div>
    );
  }

  if (profile.needs_outcome) {
    return (
      <div className="flex flex-col gap-3">
        <ExamOutcomeDialog profile={profile} />
        <PastExamsList />
      </div>
    );
  }

  // Append a local-midnight time component (backend sends a plain YYYY-MM-DD
  // date, with no timezone meaning) so the countdown is relative to local
  // midnight, not UTC midnight.
  const examDate = profile.exam_date ? new Date(`${profile.exam_date}T00:00:00`) : null;
  const diffMs = examDate ? examDate.getTime() - now.getTime() : null;
  const days = diffMs !== null ? Math.max(0, Math.floor(diffMs / 86_400_000)) : null;
  const hours = diffMs !== null ? Math.max(0, Math.floor((diffMs / 3_600_000) % 24)) : null;
  const minutes = diffMs !== null ? Math.max(0, Math.floor((diffMs / 60_000) % 60)) : null;

  const progress = profile.prep_progress_percent ?? 0;
  const radius = 42;
  const circumference = 2 * Math.PI * radius;
  const dashOffset = circumference * (1 - progress / 100);
  const ringColor = plan ? PHASE_RING_COLOR[plan.phase] : 'var(--primary)';

  // daily_plan now matches whatever buildWeeklySchedule() says for today (see StudyPlanService::buildDailyPlan
  // on the backend) - a pure rest day is a single 'rest' block with no minutes, so it never matches this
  // find() and "First focus" correctly stays hidden; isRestDay below swaps the goal line for it instead.
  const todaysBlock = plan?.daily_plan?.find((b) => b.minutes && b.minutes > 0);
  const isRestDay = plan?.daily_plan?.length === 1 && plan.daily_plan[0].activity === 'rest';
  const categoryLabel = (category: CategoryRef | null) =>
    category ? (locale === 'si' ? category.name_si : category.name_en) : null;

  return (
    <Card
      className="flex h-full flex-col overflow-hidden border-t-[3px]"
      style={{ borderTopColor: ringColor }}
    >
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle className="flex items-center gap-2 text-base">
          <CalendarClock className="h-4 w-4" /> {profile.exam_name || t('examProfile.title')}
        </CardTitle>
        <ExamProfileDialog
          trigger={
            <Button size="icon-sm" variant="ghost" aria-label={t('examProfile.edit')}>
              <Settings2 className="h-4 w-4" />
            </Button>
          }
        />
      </CardHeader>
      <CardContent className="flex h-full flex-col justify-between gap-4">
        <div className="flex flex-col gap-4">
          {plan && (
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="outline" className={PHASE_COLORS[plan.phase]}>
                {t(`studyPlan:phaseNames.${plan.phase}`)}
              </Badge>
              <p className="text-sm font-medium">{t(`studyPlan:motivation.${plan.phase}`)}</p>
            </div>
          )}

          <div className="flex flex-wrap items-center gap-6">
            {examDate && (
              <div className="relative flex h-28 w-28 shrink-0 items-center justify-center">
                <svg viewBox="0 0 100 100" className="h-28 w-28 -rotate-90">
                  <circle cx="50" cy="50" r={radius} fill="none" stroke="var(--muted)" strokeWidth="8" />
                  <circle
                    cx="50"
                    cy="50"
                    r={radius}
                    fill="none"
                    stroke={ringColor}
                    strokeWidth="8"
                    strokeDasharray={circumference}
                    strokeDashoffset={ringReady ? dashOffset : circumference}
                    strokeLinecap="round"
                    className="motion-safe:transition-[stroke-dashoffset,stroke] motion-safe:duration-1000 motion-safe:ease-out"
                  />
                </svg>
                <div className="absolute flex flex-col items-center">
                  <span className="text-2xl font-bold">
                    <CountUp value={String(days ?? 0)} />
                  </span>
                  <span className="text-[10px] text-muted-foreground">{t('examProfile.daysLeft')}</span>
                </div>
              </div>
            )}
            <div className="flex flex-col gap-1 text-sm">
              {examDate ? (
                <p className="font-medium">{t('examProfile.countdown', { days, hours, minutes })}</p>
              ) : (
                <p className="text-muted-foreground">{t('examProfile.noDateSet')}</p>
              )}
              {plan && (
                <p className="text-muted-foreground">
                  {isRestDay ? t('examProfile.activity.rest') : t('examProfile.todaysGoal', { count: plan.recommended_daily_questions })}
                </p>
              )}
              {todaysBlock && (
                <p className="text-muted-foreground">
                  {t('examProfile.firstFocus')}:{' '}
                  {categoryLabel(todaysBlock.category) ?? t(`examProfile.activity.${todaysBlock.activity}`)}
                </p>
              )}
            </div>
          </div>

          {plan && examDate && plan.phase_timeline.length > 0 && <PhaseTimelineTrack plan={plan} />}

          {plan && plan.weekly_schedule.length > 0 && <WeeklyScheduleStrip plan={plan} />}
        </div>

        {plan && (
          <div className="flex flex-wrap items-center gap-4 border-t border-border/60 pt-3">
            <div className="flex items-center gap-2">
              <span
                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                style={{ backgroundColor: `color-mix(in oklch, ${ringColor}, transparent 88%)`, color: ringColor }}
              >
                <ListChecks className="h-4 w-4" />
              </span>
              <div>
                <p className="text-xs text-muted-foreground">{t('studyPlan:recommendedWeeklyMockTests')}</p>
                <p className="text-sm font-semibold">
                  <CountUp value={String(plan.recommended_weekly_mock_tests)} />
                </p>
              </div>
            </div>
            {profile.prep_day_number !== null && profile.prep_total_days !== null && (
              <div className="flex items-center gap-2">
                <span
                  className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                  style={{ backgroundColor: `color-mix(in oklch, ${ringColor}, transparent 88%)`, color: ringColor }}
                >
                  <CalendarDays className="h-4 w-4" />
                </span>
                <p className="text-xs text-muted-foreground">
                  {t('studyPlan:prepDayOfTotal', { day: profile.prep_day_number, total: profile.prep_total_days })}
                </p>
              </div>
            )}
            <Button asChild variant="outline" size="sm" className="ml-auto">
              <Link to="/study-plan">
                {t('examProfile.viewPlan')} <ChevronRight className="h-3 w-3" />
              </Link>
            </Button>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

/**
 * A compact version of StudyPlanPage's own numbered phase stepper (same 5
 * phases, same checkmark/current/upcoming states) - sized for the narrower
 * half-width dashboard card instead of the full-width study-plan page, so
 * circles/gaps/text shrink and every node stays flex-1/min-w-0 (no per-node
 * minimum width) to actually fit instead of overflowing or forcing scroll.
 */
function PhaseTimelineTrack({ plan }: { plan: StudyPlan }) {
  const { t } = useTranslation('studyPlan');
  const currentIdx = PHASE_ORDER.indexOf(plan.phase);
  const currentPhaseData = plan.phase_timeline.find((p) => p.phase === plan.phase);

  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex w-full items-start gap-0.5">
        {PHASE_ORDER.map((phaseKey, idx) => {
          const isPast = idx < currentIdx;
          const isCurrent = phaseKey === plan.phase;
          return (
            <div key={phaseKey} className="flex min-w-0 flex-1 flex-col items-center gap-1">
              <div className="flex w-full items-center">
                <div className={`h-0.5 flex-1 ${idx === 0 ? 'opacity-0' : isPast || isCurrent ? 'bg-primary' : 'bg-border'}`} />
                <span className="relative flex h-6 w-6 shrink-0 items-center justify-center">
                  {isCurrent && (
                    <span aria-hidden className="absolute inset-0 rounded-full bg-primary/40 motion-safe:animate-ping" />
                  )}
                  <span
                    className={`relative flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 text-[10px] font-semibold ${
                      isCurrent
                        ? 'border-primary bg-primary text-primary-foreground'
                        : isPast
                          ? 'border-primary bg-primary/20 text-primary'
                          : 'border-border bg-background text-muted-foreground'
                    }`}
                  >
                    {isPast ? <Check className="h-3 w-3" /> : idx + 1}
                  </span>
                </span>
                <div className={`h-0.5 flex-1 ${idx === PHASE_ORDER.length - 1 ? 'opacity-0' : isPast ? 'bg-primary' : 'bg-border'}`} />
              </div>
              <p className={`text-center text-[10px] font-medium leading-tight ${isCurrent ? 'text-primary' : 'text-muted-foreground'}`}>
                {t(`phaseNames.${phaseKey}`)}
              </p>
            </div>
          );
        })}
      </div>
      {currentPhaseData && currentPhaseData.from_days_remaining !== null && currentPhaseData.to_days_remaining !== null && (
        <p className="text-center text-[11px] text-muted-foreground">
          {t('phaseRange', { from: currentPhaseData.from_days_remaining, to: currentPhaseData.to_days_remaining })}
        </p>
      )}
    </div>
  );
}

/**
 * A week-at-a-glance strip: one icon per day (same ACTIVITY_ICON mapping
 * StudyPlanPage's own weekly-schedule list uses for the same focus values),
 * today highlighted. Real data (plan.weekly_schedule), not decoration - and
 * icon-only by design so it never needs new translated day-name text.
 */
function WeeklyScheduleStrip({ plan }: { plan: StudyPlan }) {
  const { t } = useTranslation('studyPlan');
  const todayKey = DAY_ORDER[(new Date().getDay() + 6) % 7];

  return (
    <div className="flex flex-col gap-1.5">
      <p className="text-xs font-medium text-muted-foreground">{t('weeklySchedule')}</p>
      <div className="flex items-center gap-1.5">
        {plan.weekly_schedule.map((day) => {
          const isToday = day.day === todayKey;
          const Icon =
            ACTIVITY_ICON[
              day.focus === 'mock' ? 'timed_mock_practice' : day.focus === 'rest' || day.focus === 'rest_light' ? 'rest' : 'weak_category_practice'
            ] ?? ACTIVITY_ICON.weak_category_practice;
          return (
            <span
              key={day.day}
              title={`${t(`days.${day.day}`)}: ${t(`focus.${day.focus}`)}`}
              className={`flex h-8 flex-1 items-center justify-center rounded-lg border ${
                isToday ? 'border-primary bg-primary/10 text-primary' : 'border-border/60 text-muted-foreground'
              }`}
            >
              <Icon className="h-3.5 w-3.5" />
            </span>
          );
        })}
      </div>
    </div>
  );
}

/**
 * Collapsed by default - only shown expanded when there's no active exam
 * profile (i.e. the student just finished one and hasn't started another),
 * since that's the moment "what happened last time" is most relevant.
 */
function PastExamsList() {
  const { t } = useTranslation('dashboard');
  const { data: history } = useExamHistory();
  const [open, setOpen] = useState(false);

  if (!history || history.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader className="cursor-pointer select-none" onClick={() => setOpen((o) => !o)}>
        <CardTitle className="flex items-center gap-2 text-sm text-muted-foreground">
          <History className="h-4 w-4" /> {t('examProfile.outcome.pastExams', { count: history.length })}
        </CardTitle>
      </CardHeader>
      {open && (
        <CardContent className="flex flex-col gap-2">
          {history.map((exam, idx) => (
            <div key={idx} className="flex items-center justify-between gap-3 rounded-lg border border-border p-2.5 text-sm">
              <div>
                <p className="font-medium">{exam.exam_name}</p>
                <p className="text-xs text-muted-foreground">{exam.exam_date}</p>
              </div>
              {exam.outcome_attended === null ? (
                <Badge variant="outline">{t('examProfile.outcome.noOutcomeRecorded')}</Badge>
              ) : !exam.outcome_attended ? (
                <Badge variant="outline">{t('examProfile.outcome.didNotAttend')}</Badge>
              ) : (
                <Badge variant={exam.outcome_passed ? 'success' : 'outline'}>
                  {exam.outcome_passed ? t('examProfile.outcome.passed') : t('examProfile.outcome.attended')}
                  {exam.outcome_score != null ? ` · ${exam.outcome_score}%` : ''}
                </Badge>
              )}
            </div>
          ))}
        </CardContent>
      )}
    </Card>
  );
}
