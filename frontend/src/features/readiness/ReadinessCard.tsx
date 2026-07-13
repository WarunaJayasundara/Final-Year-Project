import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { AlertTriangle, CalendarClock, Gauge, Loader2, Timer, TrendingDown, TrendingUp } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { useExamProfile } from '@/features/examProfile/useExamProfile';
import { useRunReadinessPrediction, useSubmitCheckin, useTodayCheckin, useLatestReadiness } from './useReadiness';
import type { ReadinessLabel } from './types';

const LABEL_STYLES: Record<ReadinessLabel, string> = {
  ready: 'border-success/30 bg-success/15 text-success',
  almost_ready: 'border-primary/30 bg-primary/15 text-primary',
  needs_improvement: 'border-warning/30 bg-warning/15 text-warning-foreground',
  high_risk: 'border-destructive/30 bg-destructive/15 text-destructive',
};

export function ReadinessCard() {
  const { t } = useTranslation('dashboard');
  const { data: prediction, isLoading } = useLatestReadiness();
  const predict = useRunReadinessPrediction({
    onSuccess: () => toast.success(t('readiness.predictionUpdated')),
    onError: () => toast.error(t('readiness.predictionFailed')),
  });

  return (
    <Card className="border-primary/30">
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle className="flex items-center gap-2 text-base">
          <Gauge className="h-4 w-4" />
          {prediction?.readiness_type === 'exam_specific' && prediction.exam_name
            ? t('readiness.titleForExam', { exam: prediction.exam_name })
            : t('readiness.titleGeneral')}
        </CardTitle>
        <div className="flex gap-2">
          <CheckinDialog />
          <Button size="sm" onClick={() => predict.mutate()} disabled={predict.isPending}>
            {predict.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('readiness.refresh')}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        {isLoading ? null : !prediction ? (
          <p className="text-sm text-muted-foreground">{t('readiness.noPredictionYet')}</p>
        ) : (
          <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-4">
              <p className="text-3xl font-semibold tracking-tight">{prediction.readiness_percent}%</p>
              <Badge variant="outline" className={LABEL_STYLES[prediction.readiness_label]}>
                {t(`readiness.labels.${prediction.readiness_label}`)}
              </Badge>
            </div>
            <Progress value={prediction.readiness_percent} />

            {prediction.plain_english_explanation && (
              <p className="rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                {prediction.plain_english_explanation}
              </p>
            )}

            {(prediction.risk_of_dropping_practice ||
              prediction.predicted_next_assessment_score != null ||
              prediction.predicted_score_change != null ||
              prediction.time_management_readiness_percent != null) && (
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {prediction.risk_of_dropping_practice && (
                  <div className={`flex flex-col gap-0.5 rounded-lg border p-2.5 ${prediction.risk_of_dropping_practice.at_risk ? 'border-destructive/30 bg-destructive/10' : 'border-border'}`}>
                    <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
                      {prediction.risk_of_dropping_practice.at_risk && <AlertTriangle className="h-3 w-3 text-destructive" />}
                      {t('readiness.dropRisk')}
                    </span>
                    <span className="text-sm font-semibold">
                      {Math.round(prediction.risk_of_dropping_practice.probability * 100)}%
                    </span>
                  </div>
                )}
                {prediction.predicted_next_assessment_score != null && (
                  <div className="flex flex-col gap-0.5 rounded-lg border border-border p-2.5">
                    <span className="text-[11px] text-muted-foreground">{t('readiness.predictedNextScore')}</span>
                    <span className="text-sm font-semibold">
                      {prediction.predicted_next_assessment_score.toFixed(0)}%
                      {prediction.predicted_score_range && (
                        <span className="ml-1 text-[11px] font-normal text-muted-foreground">
                          ({prediction.predicted_score_range.low.toFixed(0)}-{prediction.predicted_score_range.high.toFixed(0)})
                        </span>
                      )}
                    </span>
                  </div>
                )}
                {prediction.predicted_score_change != null && (
                  <div className="flex flex-col gap-0.5 rounded-lg border border-border p-2.5">
                    <span className="text-[11px] text-muted-foreground">{t('readiness.predictedChange')}</span>
                    <span className={`text-sm font-semibold ${prediction.predicted_score_change >= 0 ? 'text-success' : 'text-destructive'}`}>
                      {prediction.predicted_score_change >= 0 ? '+' : ''}
                      {prediction.predicted_score_change.toFixed(1)}
                    </span>
                  </div>
                )}
                {prediction.time_management_readiness_percent != null && (
                  <div className="flex flex-col gap-0.5 rounded-lg border border-border p-2.5">
                    <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
                      <Timer className="h-3 w-3" /> {t('readiness.timeManagementReadiness')}
                    </span>
                    <span className="text-sm font-semibold">{prediction.time_management_readiness_percent.toFixed(0)}%</span>
                  </div>
                )}
              </div>
            )}

            <p className="text-[11px] text-muted-foreground/80">{t('readiness.confidenceNote')}</p>

            <div className="flex flex-col gap-2">
              <p className="text-xs font-medium text-muted-foreground">{t('readiness.reasonsTitle')}</p>
              {prediction.reasons.map((reason) => (
                <div key={reason.feature} className="flex items-start gap-2 text-sm">
                  {reason.direction === 'positive' ? (
                    <TrendingUp className="mt-0.5 h-3.5 w-3.5 shrink-0 text-success" />
                  ) : (
                    <TrendingDown className="mt-0.5 h-3.5 w-3.5 shrink-0 text-destructive" />
                  )}
                  <span>{reason.message}</span>
                </div>
              ))}
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

/**
 * Same-day-only signals (motivation, attendance) - "how long can you use the
 * system" is intentionally NOT asked here. That capacity question is asked
 * once during exam-profile setup (ExamProfileDialog's dailyHours field) and
 * reused automatically for every check-in's study_hours value below, so the
 * student is never re-asked it on a daily basis.
 */
function CheckinDialog() {
  const { t } = useTranslation('dashboard');
  const [open, setOpen] = useState(false);
  const { data: checkin } = useTodayCheckin();
  const { data: examProfile } = useExamProfile();
  const [motivation, setMotivation] = useState('5');
  const [attended, setAttended] = useState(true);

  useEffect(() => {
    if (checkin) {
      setMotivation(String(checkin.motivation_score));
      setAttended(checkin.attended);
    }
  }, [checkin]);

  const submit = useSubmitCheckin({
    onSuccess: () => {
      toast.success(t('readiness.checkinSaved'));
      setOpen(false);
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <CalendarClock className="h-4 w-4" />
          {t('readiness.checkinButton')}
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('readiness.checkinTitle')}</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="motivation">{t('readiness.motivation')}</Label>
            <Input
              id="motivation"
              type="number"
              min={1}
              max={10}
              value={motivation}
              onChange={(e) => setMotivation(e.target.value)}
            />
          </div>
          <div className="flex items-center gap-2">
            <Checkbox
              id="attended"
              checked={attended}
              onCheckedChange={(checked) => setAttended(checked === true)}
            />
            <Label htmlFor="attended" className="font-normal">
              {t('readiness.attendedToday')}
            </Label>
          </div>
        </div>
        <DialogFooter>
          <Button
            onClick={() =>
              submit.mutate({
                study_hours: checkin?.study_hours ?? examProfile?.daily_study_hours_target ?? 1,
                motivation_score: Number(motivation),
                attended,
              })
            }
            disabled={submit.isPending}
          >
            {submit.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('readiness.saveCheckin')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
