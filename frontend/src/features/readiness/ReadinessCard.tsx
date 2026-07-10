import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { CalendarClock, Loader2, Sparkles, TrendingDown, TrendingUp } from 'lucide-react';
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
import { useRunReadinessPrediction, useSubmitCheckin, useTodayCheckin, useLatestReadiness } from './useReadiness';
import type { ReadinessLabel } from './types';

const LABEL_STYLES: Record<ReadinessLabel, string> = {
  ready: 'border-emerald-500/30 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
  almost_ready: 'border-blue-500/30 bg-blue-500/15 text-blue-600 dark:text-blue-400',
  needs_improvement: 'border-amber-500/30 bg-amber-500/15 text-amber-600 dark:text-amber-400',
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
          <Sparkles className="h-4 w-4" /> {t('readiness.title')}
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
            <div className="flex flex-col gap-2">
              <p className="text-xs font-medium text-muted-foreground">{t('readiness.reasonsTitle')}</p>
              {prediction.reasons.map((reason) => (
                <div key={reason.feature} className="flex items-start gap-2 text-sm">
                  {reason.direction === 'positive' ? (
                    <TrendingUp className="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-500" />
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

function CheckinDialog() {
  const { t } = useTranslation('dashboard');
  const [open, setOpen] = useState(false);
  const { data: checkin } = useTodayCheckin();
  const [studyHours, setStudyHours] = useState('1');
  const [motivation, setMotivation] = useState('5');
  const [attended, setAttended] = useState(true);

  useEffect(() => {
    if (checkin) {
      setStudyHours(String(checkin.study_hours));
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
            <Label htmlFor="study-hours">{t('readiness.studyHours')}</Label>
            <Input
              id="study-hours"
              type="number"
              min={0}
              max={16}
              step={0.5}
              value={studyHours}
              onChange={(e) => setStudyHours(e.target.value)}
            />
          </div>
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
                study_hours: Number(studyHours),
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
