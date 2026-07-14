import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ClipboardList } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { InlineLoader } from '@/components/brand/BrandLoader';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { MockExamRunner } from '@/features/sessions/MockExamRunner';
import { useCategories, useStartMockExam } from '@/features/sessions/useSessions';
import type { SessionData } from '@/features/sessions/types';

/**
 * Mock exam builder: the student picks question count/duration/scope/
 * difficulty-mode, and MockExamController (backed by
 * QuestionSamplingService::sampleForMockExam()) generates a set that
 * over-represents weak categories while still covering the requested scope
 * - not a plain random draw.
 */
export function MockExamSetupPage() {
  const { t, i18n } = useTranslation(['common', 'sessions']);
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { data: categories, isLoading } = useCategories();
  const [session, setSession] = useState<SessionData | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const [totalQuestions, setTotalQuestions] = useState('25');
  const [durationMinutes, setDurationMinutes] = useState('30');
  const [scope, setScope] = useState<'full_syllabus' | 'selected_categories'>('full_syllabus');
  const [selectedCategoryIds, setSelectedCategoryIds] = useState<number[]>([]);
  const [difficultyMode, setDifficultyMode] = useState<'standard' | 'adaptive'>('standard');

  const startMock = useStartMockExam({
    onSuccess: setSession,
    onError: () => setErrorMessage(t('mockExam.startError', { ns: 'sessions' })),
  });

  if (session) {
    return <MockExamRunner session={session} />;
  }

  if (isLoading) {
    return <CardGridSkeleton count={4} columns={{ base: 1, sm: 2, lg: 2 }} />;
  }

  const toggleCategory = (id: number) => {
    setSelectedCategoryIds((prev) => (prev.includes(id) ? prev.filter((c) => c !== id) : [...prev, id]));
  };

  const canSubmit = scope === 'full_syllabus' || selectedCategoryIds.length > 0;

  const handleStart = () => {
    setErrorMessage(null);
    startMock.mutate({
      total_questions: Number(totalQuestions),
      duration_minutes: Number(durationMinutes),
      scope,
      category_ids: scope === 'selected_categories' ? selectedCategoryIds : undefined,
      difficulty_mode: difficultyMode,
    });
  };

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-semibold">
          <ClipboardList className="h-6 w-6" /> {t('mockExam.title', { ns: 'sessions' })}
        </h1>
        <p className="text-muted-foreground">{t('mockExam.subtitle', { ns: 'sessions' })}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('mockExam.setupTitle', { ns: 'sessions' })}</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-5">
          <div className="grid grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="mock-total-questions">{t('mockExam.totalQuestions', { ns: 'sessions' })}</Label>
              <Input
                id="mock-total-questions"
                type="number"
                min={10}
                max={150}
                value={totalQuestions}
                onChange={(e) => setTotalQuestions(e.target.value)}
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="mock-duration">{t('mockExam.durationMinutes', { ns: 'sessions' })}</Label>
              <Input
                id="mock-duration"
                type="number"
                min={5}
                max={240}
                value={durationMinutes}
                onChange={(e) => setDurationMinutes(e.target.value)}
              />
            </div>
          </div>

          <div className="flex flex-col gap-1.5">
            <Label>{t('mockExam.scope', { ns: 'sessions' })}</Label>
            <Select value={scope} onValueChange={(v) => setScope(v as typeof scope)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="full_syllabus">{t('mockExam.scopeFull', { ns: 'sessions' })}</SelectItem>
                <SelectItem value="selected_categories">{t('mockExam.scopeSelected', { ns: 'sessions' })}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {scope === 'selected_categories' && (
            <div className="grid grid-cols-2 gap-2">
              {categories?.map((category) => (
                <label
                  key={category.id}
                  className="flex items-center gap-2 rounded-lg border border-border p-2 text-sm"
                >
                  <Checkbox
                    checked={selectedCategoryIds.includes(category.id)}
                    onCheckedChange={() => toggleCategory(category.id)}
                  />
                  {locale === 'si' ? category.name_si : category.name_en}
                </label>
              ))}
            </div>
          )}

          <div className="flex flex-col gap-1.5">
            <Label>{t('mockExam.difficultyMode', { ns: 'sessions' })}</Label>
            <Select value={difficultyMode} onValueChange={(v) => setDifficultyMode(v as typeof difficultyMode)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="standard">{t('mockExam.difficultyStandard', { ns: 'sessions' })}</SelectItem>
                <SelectItem value="adaptive">{t('mockExam.difficultyAdaptive', { ns: 'sessions' })}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <Button onClick={handleStart} disabled={!canSubmit || startMock.isPending}>
            {startMock.isPending ? <InlineLoader /> : t('mockExam.start', { ns: 'sessions' })}
          </Button>
          {errorMessage && <p className="text-center text-sm text-destructive">{errorMessage}</p>}
        </CardContent>
      </Card>
    </div>
  );
}
