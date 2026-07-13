import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useSearchParams } from 'react-router-dom';
import { Calculator, CalendarCheck, ClipboardList, Eye, Puzzle, Shapes, Target, TrendingDown } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { BalancedGrid } from '@/components/ui/balanced-grid';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { TestSkeleton } from '@/components/skeletons/TestSkeleton';
import { SessionRunner } from '@/features/sessions/SessionRunner';
import { useCategories, useStartPractice } from '@/features/sessions/useSessions';
import { useStudyPlan } from '@/features/examProfile/useExamProfile';
import type { SessionData } from '@/features/sessions/types';

const ICONS: Record<string, typeof Target> = {
  brain: Target,
  puzzle: Puzzle,
  calculator: Calculator,
  eye: Eye,
  shapes: Shapes,
};

export function PracticeTestPage() {
  const { t, i18n } = useTranslation(['common', 'sessions']);
  const { data: categories, isLoading } = useCategories();
  const { data: plan } = useStudyPlan();
  const [session, setSession] = useState<SessionData | null>(null);
  const [isStarting, setIsStarting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [searchParams] = useSearchParams();

  const startPractice = useStartPractice({
    onSuccess: setSession,
    onError: () => {
      setIsStarting(false);
      setErrorMessage(t('practice.startError', { ns: 'sessions' }));
    },
  });

  const handleStart = (categoryId: number) => {
    setIsStarting(true);
    setErrorMessage(null);
    startPractice.mutate({ categoryId });
  };

  // Deep link from the study plan's "Practice Numerical Reasoning Now"
  // buttons (see StudyPlanPage.tsx's ACTIVITY_LINK) - skips the picker and
  // jumps straight into practicing the recommended weak category.
  const deepLinkCategoryId = searchParams.get('category');
  useEffect(() => {
    if (deepLinkCategoryId && !session && !isStarting && !startPractice.isPending) {
      handleStart(Number(deepLinkCategoryId));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [deepLinkCategoryId]);

  if (session) {
    return <SessionRunner session={session} />;
  }

  if (deepLinkCategoryId && (isStarting || startPractice.isPending)) {
    return <TestSkeleton />;
  }

  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const weakestCategory = plan?.weak_categories?.[0] ?? null;

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-8">
      <div>
        <h1 className="text-2xl font-semibold">{t('nav.practice')}</h1>
        <p className="text-muted-foreground">{t('practice.subtitle', { ns: 'sessions' })}</p>
      </div>

      <div>
        <p className="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
          {t('practice.quickStart', { ns: 'sessions' })}
        </p>
        <div className="grid gap-4 sm:grid-cols-3">
          <Link to="/test/daily">
            <Card className="h-full cursor-pointer border-primary/20 transition-shadow hover:shadow-md">
              <CardContent className="flex flex-col gap-3 p-5">
                <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <CalendarCheck className="h-5 w-5" />
                </span>
                <p className="font-semibold">{t('nav.dailyPractice')}</p>
                <p className="text-sm text-muted-foreground">{t('practice.dailyHint', { ns: 'sessions' })}</p>
              </CardContent>
            </Card>
          </Link>

          <Link to={weakestCategory ? `/test/practice?category=${weakestCategory.category_id}` : '#'}>
            <Card className={`h-full transition-shadow ${weakestCategory ? 'cursor-pointer border-warning/30 hover:shadow-md' : 'cursor-not-allowed opacity-60'}`}>
              <CardContent className="flex flex-col gap-3 p-5">
                <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/15 text-warning-foreground">
                  <TrendingDown className="h-5 w-5" />
                </span>
                <p className="font-semibold">{t('practice.weakArea', { ns: 'sessions' })}</p>
                <p className="text-sm text-muted-foreground">
                  {weakestCategory
                    ? (locale === 'si' ? weakestCategory.name_si : weakestCategory.name_en)
                    : t('practice.weakAreaUnavailable', { ns: 'sessions' })}
                </p>
              </CardContent>
            </Card>
          </Link>

          <Link to="/test/mock">
            <Card className="h-full cursor-pointer border-brand-gold/40 bg-brand-gold/5 transition-shadow hover:shadow-md">
              <CardContent className="flex flex-col gap-3 p-5">
                <div className="flex items-center justify-between">
                  <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-gold/15 text-brand-gold">
                    <ClipboardList className="h-5 w-5" />
                  </span>
                  <Badge variant="outline" className="border-brand-gold/40 text-brand-gold">
                    {t('practice.timed', { ns: 'sessions' })}
                  </Badge>
                </div>
                <p className="font-semibold">{t('nav.mockExam')}</p>
                <p className="text-sm text-muted-foreground">{t('practice.mockHint', { ns: 'sessions' })}</p>
              </CardContent>
            </Card>
          </Link>
        </div>
      </div>

      <div>
        <p className="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
          {t('practice.byCategory', { ns: 'sessions' })}
        </p>
        {isLoading ? (
          <CardGridSkeleton count={5} columns={{ base: 1, sm: 2, lg: 2 }} />
        ) : (
          <BalancedGrid
            items={categories ?? []}
            columns={{ base: 1, sm: 2, lg: 3 }}
            itemWidth="14rem"
            renderItem={(category) => {
              const Icon = ICONS[category.icon ?? ''] ?? Target;
              const name = locale === 'si' ? category.name_si : category.name_en;
              const description = locale === 'si' ? category.description_si : category.description_en;

              return (
                <Card
                  key={category.id}
                  className="h-full cursor-pointer transition-shadow hover:shadow-md"
                  onClick={() => handleStart(category.id)}
                >
                  <CardContent className="flex flex-col gap-3 p-6">
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <Icon className="h-5 w-5" />
                    </span>
                    <p className="font-semibold">{name}</p>
                    <p className="text-sm text-muted-foreground">{description}</p>
                  </CardContent>
                </Card>
              );
            }}
          />
        )}
      </div>

      {isStarting && !errorMessage && <TestSkeleton />}
      {errorMessage && <p className="text-center text-sm text-destructive">{errorMessage}</p>}
    </div>
  );
}
