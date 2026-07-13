import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSubmitExamOutcome } from './useExamProfile';
import type { ExamProfile } from './types';

/**
 * Shown in place of the countdown once an exam's date has passed and no
 * outcome has been recorded yet. Submitting (even a bare "no, I skipped it")
 * archives the profile so the student can start a new one - see
 * ExamProfileController::outcome()'s docblock for why this never blocks.
 */
export function ExamOutcomeDialog({ profile }: { profile: ExamProfile }) {
  const { t } = useTranslation('dashboard');
  const [attended, setAttended] = useState<'yes' | 'no' | null>(null);
  const [passed, setPassed] = useState<'yes' | 'no' | null>(null);
  const [score, setScore] = useState('');

  const submit = useSubmitExamOutcome();

  const handleSubmit = () => {
    if (attended === null) return;
    submit.mutate({
      attended: attended === 'yes',
      passed: attended === 'yes' && passed !== null ? passed === 'yes' : null,
      score: attended === 'yes' && score ? Number(score) : null,
    });
  };

  return (
    <Card className="border-primary/30">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <CheckCircle2 className="h-4 w-4" /> {t('examProfile.outcome.title', { name: profile.exam_name })}
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <p className="text-sm text-muted-foreground">{t('examProfile.outcome.subtitle')}</p>

        <div className="flex flex-col gap-2">
          <Label>{t('examProfile.outcome.attendedQuestion')}</Label>
          <div className="flex gap-2">
            <Button type="button" size="sm" variant={attended === 'yes' ? 'default' : 'outline'} onClick={() => setAttended('yes')}>
              {t('examProfile.outcome.yes')}
            </Button>
            <Button type="button" size="sm" variant={attended === 'no' ? 'default' : 'outline'} onClick={() => setAttended('no')}>
              {t('examProfile.outcome.no')}
            </Button>
          </div>
        </div>

        {attended === 'yes' && (
          <>
            <div className="flex flex-col gap-2">
              <Label>{t('examProfile.outcome.passedQuestion')}</Label>
              <div className="flex gap-2">
                <Button type="button" size="sm" variant={passed === 'yes' ? 'default' : 'outline'} onClick={() => setPassed('yes')}>
                  {t('examProfile.outcome.yes')}
                </Button>
                <Button type="button" size="sm" variant={passed === 'no' ? 'default' : 'outline'} onClick={() => setPassed('no')}>
                  {t('examProfile.outcome.no')}
                </Button>
              </div>
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="score">{t('examProfile.outcome.scoreLabel')}</Label>
              <Input
                id="score"
                type="number"
                min={0}
                max={100}
                placeholder={t('examProfile.outcome.scorePlaceholder')}
                value={score}
                onChange={(e) => setScore(e.target.value)}
              />
            </div>
          </>
        )}

        <Button onClick={handleSubmit} disabled={attended === null || submit.isPending} className="self-start">
          {submit.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('examProfile.outcome.submit')}
        </Button>
      </CardContent>
    </Card>
  );
}
