import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { CalendarClock, ChevronRight, History, Settings2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useExamHistory, useExamProfile, useStudyPlan } from './useExamProfile';
import { ExamProfileDialog } from './ExamProfileDialog';
import { ExamOutcomeDialog } from './ExamOutcomeDialog';
import { PHASE_COLORS } from './phaseStyles';
import type { CategoryRef } from './types';

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

  const todaysBlock = plan?.daily_plan?.find((b) => b.minutes && b.minutes > 0);
  const categoryLabel = (category: CategoryRef | null) =>
    category ? (locale === 'si' ? category.name_si : category.name_en) : null;

  return (
    <Card className={plan ? `border ${PHASE_COLORS[plan.phase]}` : 'border-primary/30'}>
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
      <CardContent className="flex flex-col gap-4">
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
                  stroke="var(--primary)"
                  strokeWidth="8"
                  strokeDasharray={circumference}
                  strokeDashoffset={dashOffset}
                  strokeLinecap="round"
                />
              </svg>
              <div className="absolute flex flex-col items-center">
                <span className="text-2xl font-bold">{days}</span>
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
                {t('examProfile.todaysGoal', { count: plan.recommended_daily_questions })}
              </p>
            )}
            {todaysBlock && (
              <p className="text-muted-foreground">
                {t('examProfile.firstFocus')}:{' '}
                {categoryLabel(todaysBlock.category) ?? t(`examProfile.activity.${todaysBlock.activity}`)}
              </p>
            )}
            <Link
              to="/study-plan"
              className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
            >
              {t('examProfile.viewPlan')} <ChevronRight className="h-3 w-3" />
            </Link>
          </div>
        </div>
      </CardContent>
    </Card>
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
