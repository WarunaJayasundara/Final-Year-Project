import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Check, Loader2, Sparkles, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAdminCategories, useAdminLevels, useAiQuestionDrafts, useApproveAiQuestion, useGenerateAiQuestions, useRejectAiQuestion } from '@/features/admin/useAdmin';
import { useExamCategories } from '@/features/examProfile/useExamProfile';
import type { AiQuestionDraft } from '@/features/admin/types';

export function AdminAiQuestionsPage() {
  const { t, i18n } = useTranslation('admin');
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { data: categories } = useAdminCategories();
  const { data: levels } = useAdminLevels();
  const { data: examCategories } = useExamCategories();
  const { data: drafts, isLoading } = useAiQuestionDrafts('pending');

  const [categoryId, setCategoryId] = useState<number | undefined>();
  const [levelId, setLevelId] = useState<number | undefined>();
  const [count, setCount] = useState('3');
  const [examCategory, setExamCategory] = useState<string | undefined>();

  const generate = useGenerateAiQuestions();
  const approve = useApproveAiQuestion();
  const reject = useRejectAiQuestion();

  const handleGenerate = () => {
    if (!categoryId || !levelId) return;
    generate.mutate(
      { category_id: categoryId, level_id: levelId, count: Number(count), exam_category: examCategory },
      {
        onSuccess: (created) => toast.success(t('aiQuestions.generateSuccess', { count: created.length })),
        onError: () => toast.error(t('aiQuestions.generateError')),
      },
    );
  };

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('aiQuestions.title')}</h1>
        <p className="text-muted-foreground">{t('aiQuestions.subtitle')}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Sparkles className="h-4 w-4 text-primary" /> {t('aiQuestions.generateTitle')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="flex flex-col gap-1.5">
              <Label>{t('form.category')}</Label>
              <Select value={categoryId ? String(categoryId) : undefined} onValueChange={(v) => setCategoryId(Number(v))}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('form.selectCategory')} />
                </SelectTrigger>
                <SelectContent>
                  {categories?.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {locale === 'si' ? c.name_si : c.name_en}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1.5">
              <Label>{t('form.level')}</Label>
              <Select value={levelId ? String(levelId) : undefined} onValueChange={(v) => setLevelId(Number(v))}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('form.selectLevel')} />
                </SelectTrigger>
                <SelectContent>
                  {levels?.map((l) => (
                    <SelectItem key={l.id} value={String(l.id)}>
                      {locale === 'si' ? l.name_si : l.name_en}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="ai-count">{t('aiQuestions.count')}</Label>
              <Input id="ai-count" type="number" min={1} max={10} value={count} onChange={(e) => setCount(e.target.value)} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label>{t('aiQuestions.examContext')}</Label>
              <Select value={examCategory} onValueChange={setExamCategory}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('aiQuestions.examContextNone')} />
                </SelectTrigger>
                <SelectContent>
                  {examCategories?.map((c) => (
                    <SelectItem key={c.code} value={c.code}>
                      {c.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <Button className="mt-4" onClick={handleGenerate} disabled={!categoryId || !levelId || generate.isPending}>
            {generate.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('aiQuestions.generate')}
          </Button>
        </CardContent>
      </Card>

      <div className="flex flex-col gap-4">
        <h2 className="text-lg font-semibold">{t('aiQuestions.pendingTitle')}</h2>
        {isLoading && <p className="text-sm text-muted-foreground">-</p>}
        {!isLoading && drafts?.data.length === 0 && (
          <p className="text-sm text-muted-foreground">{t('aiQuestions.noneMatch')}</p>
        )}
        {drafts?.data.map((draft) => (
          <DraftCard
            key={draft.id}
            draft={draft}
            locale={locale}
            onApprove={() => approve.mutate(draft.id, { onSuccess: () => toast.success(t('aiQuestions.approveSuccess')) })}
            onReject={() => reject.mutate(draft.id, { onSuccess: () => toast.success(t('aiQuestions.rejectSuccess')) })}
            busy={approve.isPending || reject.isPending}
          />
        ))}
      </div>
    </div>
  );
}

function DraftCard({
  draft,
  locale,
  onApprove,
  onReject,
  busy,
}: {
  draft: AiQuestionDraft;
  locale: 'en' | 'si';
  onApprove: () => void;
  onReject: () => void;
  busy: boolean;
}) {
  const { t } = useTranslation('admin');

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 p-5">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <Badge variant="secondary">{draft.category?.name_en ?? draft.category_id}</Badge>
            <Badge variant="outline">{draft.level?.name_en ?? draft.level_id}</Badge>
            <Badge variant={draft.source === 'gemini' ? 'default' : 'secondary'}>{draft.source}</Badge>
          </div>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" onClick={onReject} disabled={busy}>
              <X className="h-3.5 w-3.5" /> {t('aiQuestions.reject')}
            </Button>
            <Button size="sm" onClick={onApprove} disabled={busy}>
              <Check className="h-3.5 w-3.5" /> {t('aiQuestions.approve')}
            </Button>
          </div>
        </div>

        <p className="font-medium">{locale === 'si' ? draft.question_text_si : draft.question_text_en}</p>

        <div className="grid gap-2 sm:grid-cols-2">
          {draft.options.map((option) => (
            <div
              key={option.key}
              className={`rounded-lg border px-3 py-2 text-sm ${
                option.key === draft.correct_option_key ? 'border-emerald-500 bg-emerald-500/10' : 'border-border'
              }`}
            >
              <span className="font-medium">{option.key}.</span> {locale === 'si' ? option.text_si : option.text_en}
            </div>
          ))}
        </div>

        {(draft.explanation_en || draft.explanation_si) && (
          <p className="text-xs text-muted-foreground">
            {locale === 'si' ? draft.explanation_si : draft.explanation_en}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
