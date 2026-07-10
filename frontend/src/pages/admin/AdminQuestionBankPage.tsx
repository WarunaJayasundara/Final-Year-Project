import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, Image, LayoutGrid, ListChecks } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { useQuestionBankStats } from '@/features/admin/analytics';

export function AdminQuestionBankPage() {
  const { t } = useTranslation('admin');
  const { data, isLoading } = useQuestionBankStats();

  if (isLoading || !data) {
    return <FullPageSpinner />;
  }

  const mcqText = data.by_type.mcq_text ?? 0;
  const mcqImage = data.by_type.mcq_image ?? 0;
  const categories = Object.entries(data.by_category);
  const levelNumbers = ['1', '2', '3', '4', '5'];

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('questionBank.title')}</h1>
        <p className="text-muted-foreground">{t('questionBank.subtitle')}</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard icon={<ListChecks className="h-5 w-5" />} label={t('questionBank.totalActive')} value={String(data.total_active)} />
        <StatCard icon={<LayoutGrid className="h-5 w-5" />} label={t('questionBank.textQuestions')} value={String(mcqText)} />
        <StatCard icon={<Image className="h-5 w-5" />} label={t('questionBank.imageQuestions')} value={String(mcqImage)} />
        <StatCard icon={<AlertTriangle className="h-5 w-5" />} label={t('questionBank.untagged')} value={String(data.untagged_count)} />
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('questionBank.byCategoryLevel')}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="rounded-lg border border-border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('questionBank.table.category')}</TableHead>
                  {levelNumbers.map((n) => (
                    <TableHead key={n} className="text-right">
                      L{n}
                    </TableHead>
                  ))}
                  <TableHead className="text-right">{t('questionBank.table.total')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {categories.map(([code, row]) => (
                  <TableRow key={code}>
                    <TableCell>{row.name_en}</TableCell>
                    {levelNumbers.map((n) => (
                      <TableCell key={n} className="text-right font-mono">
                        {row.by_level[n] ?? 0}
                      </TableCell>
                    ))}
                    <TableCell className="text-right font-mono font-medium">{row.total}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('questionBank.bySubcategory')}</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            {Object.entries(data.by_subcategory).map(([category, subcats]) => (
              <div key={category} className="flex flex-col gap-1.5">
                <p className="text-xs font-medium uppercase text-muted-foreground">{category}</p>
                <div className="flex flex-wrap gap-1.5">
                  {Object.entries(subcats).map(([sub, count]) => (
                    <span key={sub} className="rounded-full border border-border px-2.5 py-1 text-xs">
                      {sub} <span className="font-mono font-medium">{count}</span>
                    </span>
                  ))}
                </div>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('questionBank.byBloomLevel')}</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-2">
            {Object.entries(data.by_bloom_level).map(([level, count]) => (
              <div key={level} className="flex items-center justify-between gap-3 rounded-lg border border-border p-2.5 text-sm">
                <span className="capitalize text-muted-foreground">{level}</span>
                <span className="font-mono font-medium">{count}</span>
              </div>
            ))}
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
        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">{icon}</span>
        <div>
          <p className="text-xs text-muted-foreground">{label}</p>
          <p className="text-lg font-semibold">{value}</p>
        </div>
      </CardContent>
    </Card>
  );
}
