import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { FilePlus2, Plus, Shapes, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { useAdminCategories, useAdminLevels, useAdminQuestions, useDeleteQuestion } from '@/features/admin/useAdmin';

export function AdminQuestionsListPage() {
  const { t } = useTranslation('admin');
  const [categoryId, setCategoryId] = useState<number | undefined>();
  const [levelId, setLevelId] = useState<number | undefined>();
  const [page, setPage] = useState(1);

  const { data: categories } = useAdminCategories();
  const { data: levels } = useAdminLevels();
  const { data: questions, isLoading } = useAdminQuestions({ category_id: categoryId, level_id: levelId, page });
  const deleteQuestion = useDeleteQuestion();

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{t('questions.title')}</h1>
          <p className="text-muted-foreground">{t('questions.subtitle')}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button asChild variant="outline">
            <Link to="/admin/questions/visual-generator">
              <Shapes className="h-4 w-4" /> {t('questions.modeVisual')}
            </Link>
          </Button>
          <Button asChild variant="outline">
            <Link to="/admin/ai-questions">
              <FilePlus2 className="h-4 w-4" /> {t('questions.modeAi')}
            </Link>
          </Button>
          <Button asChild>
            <Link to="/admin/questions/new">
              <Plus className="h-4 w-4" /> {t('questions.new')}
            </Link>
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap gap-3">
        <Select
          value={categoryId ? String(categoryId) : 'all'}
          onValueChange={(v) => {
            setCategoryId(v === 'all' ? undefined : Number(v));
            setPage(1);
          }}
        >
          <SelectTrigger className="w-56">
            <SelectValue placeholder={t('questions.allCategories')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('questions.allCategories')}</SelectItem>
            {categories?.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.name_en}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select
          value={levelId ? String(levelId) : 'all'}
          onValueChange={(v) => {
            setLevelId(v === 'all' ? undefined : Number(v));
            setPage(1);
          }}
        >
          <SelectTrigger className="w-48">
            <SelectValue placeholder={t('questions.allLevels')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('questions.allLevels')}</SelectItem>
            {levels?.map((l) => (
              <SelectItem key={l.id} value={String(l.id)}>
                {l.name_en}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {isLoading || !questions ? (
        <FullPageSpinner />
      ) : (
        <>
          <div className="rounded-lg border border-border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('questions.table.question')}</TableHead>
                  <TableHead>{t('questions.table.category')}</TableHead>
                  <TableHead>{t('questions.table.level')}</TableHead>
                  <TableHead>{t('questions.table.type')}</TableHead>
                  <TableHead>{t('questions.table.status')}</TableHead>
                  <TableHead className="text-right">{t('questions.table.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {questions.data.map((q) => (
                  <TableRow key={q.id}>
                    <TableCell className="max-w-xs truncate">{q.question_text_en}</TableCell>
                    <TableCell>{q.category?.name_en}</TableCell>
                    <TableCell>{q.level?.name_en}</TableCell>
                    <TableCell className="capitalize">{q.question_type.replace('mcq_', '')}</TableCell>
                    <TableCell>
                      <Badge variant={q.is_active ? 'default' : 'secondary'}>
                        {q.is_active ? t('questions.active') : t('questions.inactive')}
                      </Badge>
                    </TableCell>
                    <TableCell className="flex justify-end gap-2 text-right">
                      <Button asChild size="sm" variant="outline">
                        <Link to={`/admin/questions/${q.id}/edit`}>{t('questions.edit')}</Link>
                      </Button>
                      <Button
                        size="icon-sm"
                        variant="ghost"
                        onClick={() => {
                          if (confirm(t('questions.deleteConfirm'))) deleteQuestion.mutate(q.id);
                        }}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
                {questions.data.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center text-muted-foreground">
                      {t('questions.noneMatch')}
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>

          <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
              {t('questions.pagination', {
                current: questions.current_page,
                last: questions.last_page,
                total: questions.total,
              })}
            </span>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                {t('questions.previous')}
              </Button>
              <Button
                size="sm"
                variant="outline"
                disabled={page >= questions.last_page}
                onClick={() => setPage((p) => p + 1)}
              >
                {t('questions.next')}
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
