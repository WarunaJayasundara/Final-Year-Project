import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, Sparkles, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { useExplainAnswer, useReport } from '@/features/sessions/useSessions';

export function SessionReportPage() {
  const { t } = useTranslation('sessions');
  const { id } = useParams();
  const sessionId = Number(id);
  const { data: report, isLoading } = useReport(sessionId);
  const explainAnswer = useExplainAnswer(sessionId);
  const [loadingAnswerId, setLoadingAnswerId] = useState<number | null>(null);

  if (isLoading || !report) {
    return <FullPageSpinner />;
  }

  const wrongAnswers = report.answers.filter((a) => !a.is_correct);

  const handleExplain = async (answerId: number) => {
    setLoadingAnswerId(answerId);
    await explainAnswer.mutateAsync(answerId);
    setLoadingAnswerId(null);
  };

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="text-2xl">{t('report.title')}</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex items-center justify-between rounded-xl bg-muted/40 p-4">
            <div>
              <p className="text-3xl font-semibold">{report.score_percent}%</p>
              <p className="text-sm text-muted-foreground">
                {t('report.scoreFraction', { correct: report.correct_count, total: report.total_questions })}
              </p>
            </div>
            {report.level_before_id !== report.level_after_id && (
              <Badge variant={report.session_type === 'daily' ? 'default' : 'secondary'}>{t('report.levelUpdated')}</Badge>
            )}
          </div>

          <div className="flex gap-3">
            <Button asChild className="flex-1">
              <Link to="/dashboard">{t('report.backToDashboard')}</Link>
            </Button>
            {report.session_type !== 'practice' && (
              <Button asChild variant="outline" className="flex-1">
                <Link to="/test/practice">{t('report.practiceWeakAreas')}</Link>
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {wrongAnswers.length > 0 && (
        <div className="flex flex-col gap-4">
          <h2 className="text-lg font-semibold">{t('report.reviewMistakes')}</h2>
          {wrongAnswers.map((answer) => (
            <Card key={answer.answer_id}>
              <CardContent className="flex flex-col gap-3 p-5">
                <p className="font-medium">{answer.question.question_text}</p>
                <div className="flex flex-wrap gap-2 text-sm">
                  <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2.5 py-1 text-destructive">
                    <XCircle className="h-3.5 w-3.5" />{' '}
                    {t('report.yourAnswer', { value: answer.selected_option_key ?? '-' })}
                  </span>
                  <span className="inline-flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-1 text-success">
                    <CheckCircle2 className="h-3.5 w-3.5" />{' '}
                    {t('report.correctAnswer', { value: answer.correct_option_key })}
                  </span>
                </div>

                {answer.question.explanation && (
                  <div className="rounded-lg border border-border bg-muted/30 p-3">
                    <p className="text-sm">{answer.question.explanation}</p>
                  </div>
                )}

                {answer.ai_feedback_text ? (
                  <div className="rounded-lg border border-primary/15 bg-primary/5 p-3">
                    <p className="text-sm text-muted-foreground">{answer.ai_feedback_text}</p>
                    <p className="mt-2 inline-flex items-center gap-1 text-[0.7rem] font-medium text-primary/80">
                      <Sparkles className="h-3 w-3" /> {t('footer.poweredBy', { ns: 'common' })}
                    </p>
                  </div>
                ) : (
                  <Button
                    variant="outline"
                    size="sm"
                    className="self-start"
                    disabled={loadingAnswerId === answer.answer_id}
                    onClick={() => handleExplain(answer.answer_id)}
                  >
                    <Sparkles className="h-4 w-4" />
                    {loadingAnswerId === answer.answer_id ? t('report.thinking') : t('report.explainMore')}
                  </Button>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
