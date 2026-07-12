import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Download, ListChecks, Loader2, TrendingUp, UserCheck, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { Skeleton } from '@/components/ui/skeleton';
import { useCohortOverview, useDownloadPairedScoresCsv } from '@/features/admin/analytics';

export function AdminDashboardPage() {
  const { t } = useTranslation('admin');
  const { data: overview, isLoading } = useCohortOverview();
  const downloadCsv = useDownloadPairedScoresCsv({
    onError: () => toast.error(t('dashboard.exportCsvFailed')),
  });

  if (isLoading || !overview) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-8 w-64" />
        <CardGridSkeleton count={4} />
        <div className="grid gap-4 lg:grid-cols-2">
          <Skeleton className="h-72 rounded-xl" />
          <Skeleton className="h-72 rounded-xl" />
        </div>
      </div>
    );
  }

  const categoryData = overview.category_accuracy.map((c) => ({
    name: c.category_name,
    accuracy: Number(c.accuracy_percent),
  }));

  const levelData = overview.level_distribution.map((l) => ({
    name: `L${l.level_number}`,
    students: l.total,
  }));

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{t('dashboard.title')}</h1>
          <p className="text-muted-foreground">{t('dashboard.subtitle')}</p>
        </div>
        <Button variant="outline" onClick={() => downloadCsv.mutate()} disabled={downloadCsv.isPending}>
          {downloadCsv.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
          {t('dashboard.exportCsv')}
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard icon={<Users className="h-5 w-5" />} label={t('dashboard.students')} value={String(overview.total_students)} />
        <StatCard
          icon={<UserCheck className="h-5 w-5" />}
          label={t('dashboard.placementCompleted')}
          value={String(overview.placement_completed)}
        />
        <StatCard
          icon={<ListChecks className="h-5 w-5" />}
          label={t('dashboard.sessionsCompleted')}
          value={String(overview.sessions_completed)}
        />
        <StatCard
          icon={<TrendingUp className="h-5 w-5" />}
          label={t('dashboard.averageScore')}
          value={overview.average_score_percent !== null ? `${overview.average_score_percent}%` : '-'}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('dashboard.accuracyByCategory')}</CardTitle>
          </CardHeader>
          <CardContent className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={categoryData} layout="vertical" margin={{ left: 24 }}>
                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                <XAxis type="number" domain={[0, 100]} fontSize={12} />
                <YAxis type="category" dataKey="name" width={130} fontSize={11} />
                <Tooltip />
                <Bar dataKey="accuracy" fill="var(--primary)" radius={4} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('dashboard.studentsPerLevel')}</CardTitle>
          </CardHeader>
          <CardContent className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={levelData}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="name" fontSize={12} />
                <YAxis allowDecimals={false} fontSize={12} />
                <Tooltip />
                <Bar dataKey="students" fill="var(--primary)" radius={4} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function StatCard({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
  return (
    <Card>
      <CardContent className="flex items-center gap-4 p-5">
        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
          {icon}
        </span>
        <div>
          <p className="text-xs text-muted-foreground">{label}</p>
          <p className="text-lg font-semibold">{value}</p>
        </div>
      </CardContent>
    </Card>
  );
}
