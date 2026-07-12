import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router-dom';
import { Calculator, Eye, Puzzle, Shapes, Target } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { TestSkeleton } from '@/components/skeletons/TestSkeleton';
import { SessionRunner } from '@/features/sessions/SessionRunner';
import { useCategories, useStartPractice } from '@/features/sessions/useSessions';
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

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('nav.practice')}</h1>
        <p className="text-muted-foreground">{t('practice.subtitle', { ns: 'sessions' })}</p>
      </div>

      {isLoading ? (
        <CardGridSkeleton count={5} columns={{ base: 1, sm: 2, lg: 2 }} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {categories?.map((category) => {
            const Icon = ICONS[category.icon ?? ''] ?? Target;
            const name = locale === 'si' ? category.name_si : category.name_en;
            const description = locale === 'si' ? category.description_si : category.description_en;

            return (
              <Card
                key={category.id}
                className="cursor-pointer transition-shadow hover:shadow-md"
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
          })}
        </div>
      )}

      {isStarting && !errorMessage && <TestSkeleton />}
      {errorMessage && <p className="text-center text-sm text-destructive">{errorMessage}</p>}
    </div>
  );
}
