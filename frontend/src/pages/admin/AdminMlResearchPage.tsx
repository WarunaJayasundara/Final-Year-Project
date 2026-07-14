import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Layers, ShieldCheck, Target, Trophy, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { useMlOverview, useMlResearchReports } from '@/features/admin/analytics';

function num(value: unknown, digits = 3): string | null {
  return typeof value === 'number' ? value.toFixed(digits) : null;
}

function get(obj: unknown, path: string[]): unknown {
  return path.reduce<unknown>((acc, key) => (acc && typeof acc === 'object' ? (acc as Record<string, unknown>)[key] : undefined), obj);
}

export function AdminMlResearchPage() {
  const { t } = useTranslation('admin');
  const [includeDemo, setIncludeDemo] = useState(true);
  const { data: overview, isLoading: overviewLoading } = useMlOverview(includeDemo);
  const { data: reports, isLoading: reportsLoading } = useMlResearchReports();

  if (overviewLoading || reportsLoading) {
    return <CardGridSkeleton count={4} />;
  }

  const model = overview?.model as Record<string, unknown> | null | undefined;
  const bestModel = (get(model, ['best_model']) as string) ?? null;
  const trainingRows = get(model, ['training_rows']) as number | undefined;
  const dataSourceCounts = get(model, ['data_source_counts']) as Record<string, number> | undefined;
  const version = get(model, ['version']) as string | undefined;

  const coreMetrics = get(reports?.evaluation, ['core_metrics']) as Record<string, number> | undefined;
  const overfitting = get(reports?.evaluation, ['overfitting_diagnosis', 'diagnosis']) as string | undefined;
  const perSource = get(reports?.evaluation, ['per_data_source_performance']) as Record<string, { n: number; accuracy: number; f1_macro: number }> | undefined;

  const globalImportance = get(reports?.explainability, ['global_shap_importance']) as [string, number][] | undefined;
  const limeOverlap = get(reports?.explainability, ['lime_cross_check', 'mean_top5_feature_overlap_with_shap']) as number | undefined;

  const versions = reports?.registry?.versions ?? [];
  const liveVersion = reports?.registry?.live_version ?? null;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{t('mlResearch.title')}</h1>
          <p className="text-muted-foreground">{t('mlResearch.subtitle')}</p>
        </div>
        <Button variant={includeDemo ? 'default' : 'outline'} size="sm" onClick={() => setIncludeDemo((v) => !v)}>
          {includeDemo ? t('feedback.includingDemo') : t('feedback.excludingDemo')}
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard icon={<Users className="h-5 w-5" />} label={t('mlResearch.studentsWithPrediction')} value={String(overview?.students_with_prediction ?? 0)} />
        <StatCard icon={<Target className="h-5 w-5" />} label={t('mlResearch.avgReadiness')} value={overview?.average_readiness_percent != null ? `${overview.average_readiness_percent}%` : '–'} />
        <StatCard icon={<Layers className="h-5 w-5" />} label={t('mlResearch.trainingRows')} value={trainingRows != null ? trainingRows.toLocaleString() : '–'} />
        <StatCard icon={<Trophy className="h-5 w-5" />} label={t('mlResearch.bestModel')} value={bestModel ?? '–'} />
      </div>

      {dataSourceCounts && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('mlResearch.dataComposition')}</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            {Object.entries(dataSourceCounts).map(([source, count]) => (
              <Badge key={source} variant="outline" className="text-sm">
                {source}: <span className="ml-1 font-mono font-medium">{count.toLocaleString()}</span>
              </Badge>
            ))}
            {version && <span className="ml-auto text-xs text-muted-foreground">{t('mlResearch.version')}: {version}</span>}
          </CardContent>
        </Card>
      )}

      {coreMetrics ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('mlResearch.evaluationMetrics')}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              {Object.entries(coreMetrics).map(([metric, value]) => (
                <div key={metric} className="flex flex-col gap-0.5 rounded-lg border border-border p-2.5">
                  <span className="text-[11px] text-muted-foreground">{metric.replace(/_/g, ' ')}</span>
                  <span className="text-sm font-semibold">{num(value)}</span>
                </div>
              ))}
            </div>
            {overfitting && <p className="mt-3 text-sm text-muted-foreground">{t('mlResearch.diagnosis')}: {overfitting}</p>}
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardContent className="py-6 text-sm text-muted-foreground">{t('mlResearch.noEvaluationYet')}</CardContent>
        </Card>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <ShieldCheck className="h-4 w-4" /> {t('mlResearch.globalImportance')}
            </CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-2">
            {globalImportance?.length ? (
              <>
                {globalImportance.slice(0, 8).map(([feature, value]) => (
                  <div key={feature} className="flex items-center justify-between gap-3 rounded-lg border border-border p-2.5 text-sm">
                    <span className="text-muted-foreground">{feature.replace(/_/g, ' ')}</span>
                    <span className="font-mono font-medium">{num(value, 4)}</span>
                  </div>
                ))}
                {limeOverlap != null && (
                  <p className="mt-1 text-xs text-muted-foreground">{t('mlResearch.limeOverlap')}: {(limeOverlap * 100).toFixed(0)}%</p>
                )}
              </>
            ) : (
              <p className="text-sm text-muted-foreground">{t('mlResearch.noExplainabilityYet')}</p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('mlResearch.perSourcePerformance')}</CardTitle>
          </CardHeader>
          <CardContent>
            {perSource && Object.keys(perSource).length ? (
              <div className="rounded-lg border border-border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('mlResearch.table.source')}</TableHead>
                      <TableHead className="text-right">n</TableHead>
                      <TableHead className="text-right">{t('mlResearch.table.accuracy')}</TableHead>
                      <TableHead className="text-right">F1</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {Object.entries(perSource).map(([source, stats]) => (
                      <TableRow key={source}>
                        <TableCell>{source}</TableCell>
                        <TableCell className="text-right font-mono">{stats.n}</TableCell>
                        <TableCell className="text-right font-mono">{num(stats.accuracy)}</TableCell>
                        <TableCell className="text-right font-mono">{num(stats.f1_macro)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">{t('mlResearch.noEvaluationYet')}</p>
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('mlResearch.versionHistory')}</CardTitle>
        </CardHeader>
        <CardContent>
          {versions.length ? (
            <div className="rounded-lg border border-border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('mlResearch.table.version')}</TableHead>
                    <TableHead>{t('mlResearch.table.model')}</TableHead>
                    <TableHead className="text-right">{t('mlResearch.table.score')}</TableHead>
                    <TableHead className="text-right">{t('mlResearch.table.rows')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {versions.map((v) => {
                    const versionId = String(v.version_id);
                    return (
                      <TableRow key={versionId}>
                        <TableCell className="font-mono text-xs">
                          {versionId}
                          {versionId === liveVersion && (
                            <Badge variant="success" className="ml-2">
                              {t('mlResearch.live')}
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell>{String(v.best_model ?? '–')}</TableCell>
                        <TableCell className="text-right font-mono">{num(v.gating_score)}</TableCell>
                        <TableCell className="text-right font-mono">{v.training_rows != null ? Number(v.training_rows).toLocaleString() : '–'}</TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">{t('mlResearch.noVersionsYet')}</p>
          )}
        </CardContent>
      </Card>
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
