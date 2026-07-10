import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CalendarClock, Settings2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useExamProfile, useStudyPlan } from './useExamProfile';
import { ExamProfileDialog } from './ExamProfileDialog';
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
  const { t, i18n } = useTranslation('dashboard');
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { data: profile, isLoading } = useExamProfile();
  const { data: plan } = useStudyPlan();
  const now = useNow(60_000);

  if (isLoading) {
    return null;
  }

  if (!profile) {
    return (
      <Card className="border-dashed">
        <CardContent className="flex flex-col items-center gap-3 p-6 text-center">
          <CalendarClock className="h-8 w-8 text-muted-foreground" />
          <p className="text-sm text-muted-foreground">{t('examProfile.noProfileYet')}</p>
          <ExamProfileDialog trigger={<Button size="sm">{t('examProfile.setup')}</Button>} />
        </CardContent>
      </Card>
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
    <Card className="border-primary/30">
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle className="flex items-center gap-2 text-base">
          <CalendarClock className="h-4 w-4" /> {profile.exam_category_label}
        </CardTitle>
        <ExamProfileDialog
          trigger={
            <Button size="icon-sm" variant="ghost" aria-label={t('examProfile.edit')}>
              <Settings2 className="h-4 w-4" />
            </Button>
          }
        />
      </CardHeader>
      <CardContent className="flex flex-wrap items-center gap-6">
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
        </div>
      </CardContent>
    </Card>
  );
}
