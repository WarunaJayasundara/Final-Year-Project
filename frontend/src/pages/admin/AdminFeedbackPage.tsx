import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Download, MessageSquareHeart, Star } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { useAdminFeedback, useFeedbackStats, useMarkFeedbackReviewed } from '@/features/feedback/useFeedback';

function Stars({ value }: { value: number | null }) {
  if (value === null) return <span className="text-xs text-muted-foreground">–</span>;
  return (
    <span className="flex items-center gap-0.5">
      {[1, 2, 3, 4, 5].map((star) => (
        <Star key={star} className={`h-3.5 w-3.5 ${star <= Math.round(value) ? 'fill-brand-gold text-brand-gold' : 'text-muted-foreground/40'}`} />
      ))}
    </span>
  );
}

export function AdminFeedbackPage() {
  const { t } = useTranslation('admin');
  const [status, setStatus] = useState<'all' | 'new' | 'reviewed'>('all');
  const [page, setPage] = useState(1);
  const [includeDemo, setIncludeDemo] = useState(true);

  const { data: stats, isLoading: statsLoading } = useFeedbackStats(includeDemo);
  const { data: feedback, isLoading: feedbackLoading } = useAdminFeedback({
    page,
    status: status === 'all' ? undefined : status,
    include_demo: includeDemo,
  });
  const markReviewed = useMarkFeedbackReviewed();

  if (statsLoading || !stats) {
    return <CardGridSkeleton count={4} />;
  }

  const dimensionLabels: Record<string, string> = {
    overall_rating: t('feedback.dimensions.overall'),
    ui_rating: t('feedback.dimensions.ui'),
    question_quality_rating: t('feedback.dimensions.questionQuality'),
    sinhala_quality_rating: t('feedback.dimensions.sinhalaQuality'),
    usefulness_rating: t('feedback.dimensions.usefulness'),
  };

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{t('feedback.title')}</h1>
          <p className="text-muted-foreground">{t('feedback.subtitle')}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button
            variant={includeDemo ? 'default' : 'outline'}
            size="sm"
            onClick={() => setIncludeDemo((v) => !v)}
          >
            {includeDemo ? t('feedback.includingDemo') : t('feedback.excludingDemo')}
          </Button>
          <Button asChild variant="outline" size="sm">
            <a href={`/api/admin/feedback/export.csv?include_demo=${includeDemo ? '1' : '0'}`}>
              <Download className="h-4 w-4" /> {t('feedback.exportCsv')}
            </a>
          </Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardContent className="flex flex-col gap-1 p-5">
            <span className="text-xs text-muted-foreground">{t('feedback.totalResponses')}</span>
            <span className="text-2xl font-semibold">{stats.total_count}</span>
            <span className="text-xs text-muted-foreground">{t('feedback.newCount', { count: stats.new_count })}</span>
          </CardContent>
        </Card>
        {Object.entries(stats.averages).map(([dimension, avg]) => (
          <Card key={dimension}>
            <CardContent className="flex flex-col gap-1 p-5">
              <span className="text-xs text-muted-foreground">{dimensionLabels[dimension]}</span>
              <span className="text-2xl font-semibold">{avg != null ? avg.toFixed(1) : '–'}</span>
              <Stars value={avg} />
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('feedback.distribution')}</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-2">
            {[5, 4, 3, 2, 1].map((star) => {
              const count = stats.distribution[String(star)] ?? 0;
              const pct = stats.total_count > 0 ? (count / stats.total_count) * 100 : 0;
              return (
                <div key={star} className="flex items-center gap-2 text-sm">
                  <span className="w-10 shrink-0 text-muted-foreground">{star}★</span>
                  <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                    <div className="h-full bg-primary" style={{ width: `${pct}%` }} />
                  </div>
                  <span className="w-8 shrink-0 text-right text-xs text-muted-foreground">{count}</span>
                </div>
              );
            })}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('feedback.topTerms')}</CardTitle>
          </CardHeader>
          <CardContent>
            {stats.top_terms.length ? (
              <div className="flex flex-wrap gap-2">
                {stats.top_terms.map((term) => (
                  <Badge key={term.term} variant="outline">
                    {term.term} <span className="ml-1 font-mono text-[10px] text-muted-foreground">{term.count}</span>
                  </Badge>
                ))}
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">{t('feedback.noTerms')}</p>
            )}
            <p className="mt-3 text-[11px] text-muted-foreground/80">{t('feedback.topTermsNote')}</p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-2">
          <CardTitle className="flex items-center gap-2 text-base">
            <MessageSquareHeart className="h-4 w-4" /> {t('feedback.recent')}
          </CardTitle>
          <Select value={status} onValueChange={(v) => { setStatus(v as typeof status); setPage(1); }}>
            <SelectTrigger className="w-40">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('feedback.statusAll')}</SelectItem>
              <SelectItem value="new">{t('feedback.statusNew')}</SelectItem>
              <SelectItem value="reviewed">{t('feedback.statusReviewed')}</SelectItem>
            </SelectContent>
          </Select>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          {feedbackLoading || !feedback ? (
            <p className="text-sm text-muted-foreground">{t('feedback.loading')}</p>
          ) : feedback.data.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('feedback.none')}</p>
          ) : (
            feedback.data.map((entry) => (
              <div key={entry.id} className="flex flex-col gap-2 rounded-lg border border-border p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <Stars value={entry.overall_rating} />
                    <span className="text-xs text-muted-foreground">{entry.user_name}</span>
                    {entry.locale && <Badge variant="outline" className="text-[10px] uppercase">{entry.locale}</Badge>}
                    {entry.is_demo_feedback && <Badge variant="secondary" className="text-[10px]">{t('feedback.demoTag')}</Badge>}
                  </div>
                  {entry.status === 'new' ? (
                    <Button size="sm" variant="outline" onClick={() => markReviewed.mutate(entry.id)} disabled={markReviewed.isPending}>
                      {t('feedback.markReviewed')}
                    </Button>
                  ) : (
                    <Badge variant="success">{t('feedback.statusReviewed')}</Badge>
                  )}
                </div>
                {entry.comment && <p className="text-sm">{entry.comment}</p>}
                {entry.suggestion && (
                  <p className="text-sm text-muted-foreground">
                    <span className="font-medium text-foreground">{t('feedback.suggestionLabel')}:</span> {entry.suggestion}
                  </p>
                )}
              </div>
            ))
          )}

          {feedback && feedback.last_page > 1 && (
            <div className="flex items-center justify-between text-sm text-muted-foreground">
              <span>{t('feedback.pagination', { current: feedback.current_page, last: feedback.last_page })}</span>
              <div className="flex gap-2">
                <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  {t('feedback.previous')}
                </Button>
                <Button size="sm" variant="outline" disabled={page >= feedback.last_page} onClick={() => setPage((p) => p + 1)}>
                  {t('feedback.next')}
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
