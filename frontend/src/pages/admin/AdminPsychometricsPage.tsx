import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Activity, Gauge, RefreshCw, Ruler, Target, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { usePsychometrics, useRecalibrate } from '@/features/admin/analytics';

export function AdminPsychometricsPage() {
  const { t, i18n } = useTranslation('admin');
  const { data, isLoading } = usePsychometrics();
  const recalibrate = useRecalibrate({
    onSuccess: (result) => {
      toast.success(t('psychometrics.recalibrateSuccess', { count: result.calibrated_items }));
    },
  });

  if (isLoading || !data) {
    return <CardGridSkeleton count={4} />;
  }

  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { summary, category_difficulty: categoryDifficulty, discrimination } = data;

  const fmt = (value: number | null, digits = 2) => (value === null ? '–' : value.toFixed(digits));

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{t('psychometrics.title')}</h1>
          <p className="text-muted-foreground">{t('psychometrics.subtitle')}</p>
        </div>
        <Button variant="outline" onClick={() => recalibrate.mutate()} disabled={recalibrate.isPending}>
          <RefreshCw className={`h-4 w-4 ${recalibrate.isPending ? 'animate-spin' : ''}`} />
          {t('psychometrics.recalibrate')}
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          icon={<Target className="h-5 w-5" />}
          label={t('psychometrics.calibratedItems')}
          value={`${summary.calibrated_items} / ${summary.total_items}`}
        />
        <StatCard icon={<Users className="h-5 w-5" />} label={t('psychometrics.cohortSize')} value={String(summary.cohort_size)} />
        <StatCard
          icon={<Gauge className="h-5 w-5" />}
          label={t('psychometrics.abilitySpread')}
          value={summary.theta_mean !== null ? `${fmt(summary.theta_mean)} ± ${fmt(summary.theta_sd)}` : '–'}
        />
        <StatCard
          icon={<Ruler className="h-5 w-5" />}
          label={t('psychometrics.reliability')}
          value={summary.marginal_reliability !== null ? fmt(summary.marginal_reliability, 3) : '–'}
        />
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('psychometrics.categoryDifficulty')}</CardTitle>
        </CardHeader>
        <CardContent>
          {categoryDifficulty.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('psychometrics.noCalibratedItems')}</p>
          ) : (
            <div className="rounded-lg border border-border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('psychometrics.table.category')}</TableHead>
                    <TableHead>{t('psychometrics.table.calibratedCount')}</TableHead>
                    <TableHead>{t('psychometrics.table.meanDifficulty')}</TableHead>
                    <TableHead>{t('psychometrics.table.range')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {categoryDifficulty.map((row) => (
                    <TableRow key={row.name_en}>
                      <TableCell>{locale === 'si' ? row.name_si : row.name_en}</TableCell>
                      <TableCell>{row.calibrated_count}</TableCell>
                      <TableCell>{fmt(row.mean_difficulty)}</TableCell>
                      <TableCell>
                        {fmt(row.min_difficulty)} &ndash; {fmt(row.max_difficulty)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Activity className="h-4 w-4" /> {t('psychometrics.discrimination')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {discrimination.items_analyzed === 0 ? (
            <p className="text-sm text-muted-foreground">{t('psychometrics.noDiscriminationData')}</p>
          ) : (
            <div className="grid gap-4 lg:grid-cols-2">
              <DiscriminationList title={t('psychometrics.bestDiscriminating')} entries={discrimination.top} />
              <DiscriminationList title={t('psychometrics.worstDiscriminating')} entries={discrimination.bottom} />
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function DiscriminationList({ title, entries }: { title: string; entries: { question_id: number; question_text_en: string; discrimination: number; responses: number }[] }) {
  return (
    <div className="flex flex-col gap-2">
      <p className="text-sm font-medium">{title}</p>
      <div className="flex flex-col gap-2">
        {entries.map((entry) => (
          <div key={entry.question_id} className="flex items-center justify-between gap-3 rounded-lg border border-border p-2.5 text-sm">
            <span className="truncate text-muted-foreground">{entry.question_text_en}</span>
            <span className="shrink-0 font-mono font-medium">{entry.discrimination.toFixed(2)}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function StatCard({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
  return (
    <Card>
      <CardContent className="flex items-center gap-4 p-5">
        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">{icon}</span>
        <div>
          <p className="text-xs text-muted-foreground">{label}</p>
          <p className="text-lg font-semibold">{value}</p>
        </div>
      </CardContent>
    </Card>
  );
}
