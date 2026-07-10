import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useAdminCategories, useAdminLevels } from './useAdmin';
import type { AdminQuestion, QuestionOptionInput } from './types';
import type { QuestionPayload } from './api';

const OPTION_KEYS = ['A', 'B', 'C', 'D', 'E', 'F'];

interface Props {
  initialValues?: AdminQuestion;
  submitLabel: string;
  isSubmitting?: boolean;
  onSubmit: (payload: QuestionPayload) => Promise<void> | void;
}

export function QuestionForm({ initialValues, submitLabel, isSubmitting, onSubmit }: Props) {
  const { t } = useTranslation('admin');
  const { data: categories } = useAdminCategories();
  const { data: levels } = useAdminLevels();

  const [categoryId, setCategoryId] = useState(initialValues?.category_id ?? 0);
  const [levelId, setLevelId] = useState(initialValues?.level_id ?? 0);
  const [questionType, setQuestionType] = useState<'mcq_text' | 'mcq_image'>(
    initialValues?.question_type ?? 'mcq_text',
  );
  const [textEn, setTextEn] = useState(initialValues?.question_text_en ?? '');
  const [textSi, setTextSi] = useState(initialValues?.question_text_si ?? '');
  const [options, setOptions] = useState<QuestionOptionInput[]>(
    initialValues?.options ?? [
      { key: 'A', text_en: '', text_si: '' },
      { key: 'B', text_en: '', text_si: '' },
    ],
  );
  const [correctKey, setCorrectKey] = useState(initialValues?.correct_option_key ?? 'A');
  const [explanationEn, setExplanationEn] = useState(initialValues?.explanation_en ?? '');
  const [explanationSi, setExplanationSi] = useState(initialValues?.explanation_si ?? '');
  const [difficulty, setDifficulty] = useState(initialValues?.difficulty_weight ?? 1);
  const [isActive, setIsActive] = useState(initialValues?.is_active ?? true);
  const [error, setError] = useState<string | null>(null);

  const addOption = () => {
    if (options.length >= OPTION_KEYS.length) return;
    setOptions([...options, { key: OPTION_KEYS[options.length], text_en: '', text_si: '' }]);
  };

  const removeOption = (key: string) => {
    if (options.length <= 2) return;
    const next = options.filter((o) => o.key !== key).map((o, i) => ({ ...o, key: OPTION_KEYS[i] }));
    setOptions(next);
    if (correctKey === key) setCorrectKey(next[0]?.key ?? 'A');
  };

  const updateOption = (key: string, field: 'text_en' | 'text_si', value: string) => {
    setOptions(options.map((o) => (o.key === key ? { ...o, [field]: value } : o)));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!categoryId || !levelId) {
      setError(t('form.chooseCategoryLevel'));
      return;
    }
    if (options.some((o) => !o.text_en.trim() || !o.text_si.trim())) {
      setError(t('form.optionsRequireBoth'));
      return;
    }

    await onSubmit({
      category_id: categoryId,
      level_id: levelId,
      question_type: questionType,
      question_text_en: textEn,
      question_text_si: textSi,
      options,
      correct_option_key: correctKey,
      explanation_en: explanationEn,
      explanation_si: explanationSi,
      difficulty_weight: difficulty,
      is_active: isActive,
    });
  };

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-6">
      <div className="grid gap-4 sm:grid-cols-3">
        <div className="flex flex-col gap-1.5">
          <Label>{t('form.category')}</Label>
          <Select value={categoryId ? String(categoryId) : undefined} onValueChange={(v) => setCategoryId(Number(v))}>
            <SelectTrigger>
              <SelectValue placeholder={t('form.selectCategory')} />
            </SelectTrigger>
            <SelectContent>
              {categories?.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.name_en}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-1.5">
          <Label>{t('form.level')}</Label>
          <Select value={levelId ? String(levelId) : undefined} onValueChange={(v) => setLevelId(Number(v))}>
            <SelectTrigger>
              <SelectValue placeholder={t('form.selectLevel')} />
            </SelectTrigger>
            <SelectContent>
              {levels?.map((l) => (
                <SelectItem key={l.id} value={String(l.id)}>
                  {l.name_en}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-1.5">
          <Label>{t('form.questionType')}</Label>
          <Select value={questionType} onValueChange={(v) => setQuestionType(v as 'mcq_text' | 'mcq_image')}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="mcq_text">{t('form.typeText')}</SelectItem>
              <SelectItem value="mcq_image">{t('form.typeImage')}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <Label>{t('form.questionTextEn')}</Label>
          <Textarea value={textEn} onChange={(e) => setTextEn(e.target.value)} rows={3} required />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label>{t('form.questionTextSi')}</Label>
          <Textarea value={textSi} onChange={(e) => setTextSi(e.target.value)} rows={3} required />
        </div>
      </div>

      <div className="flex flex-col gap-3">
        <div className="flex items-center justify-between">
          <Label>{t('form.answerOptions')}</Label>
          <Button type="button" size="sm" variant="outline" onClick={addOption} disabled={options.length >= 6}>
            <Plus className="h-3.5 w-3.5" /> {t('form.addOption')}
          </Button>
        </div>

        {options.map((option) => (
          <div key={option.key} className="grid grid-cols-[auto_1fr_1fr_auto] items-center gap-2 rounded-lg border border-border p-3">
            <button
              type="button"
              onClick={() => setCorrectKey(option.key)}
              className={`flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-medium ${
                correctKey === option.key ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-border'
              }`}
              title="Mark as correct answer"
            >
              {option.key}
            </button>
            <Input
              placeholder={t('form.optionTextEn')}
              value={option.text_en}
              onChange={(e) => updateOption(option.key, 'text_en', e.target.value)}
            />
            <Input
              placeholder={t('form.optionTextSi')}
              value={option.text_si}
              onChange={(e) => updateOption(option.key, 'text_si', e.target.value)}
            />
            <Button
              type="button"
              size="icon-sm"
              variant="ghost"
              onClick={() => removeOption(option.key)}
              disabled={options.length <= 2}
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          </div>
        ))}
        <p className="text-xs text-muted-foreground">{t('form.markCorrectHint')}</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <Label>{t('form.explanationEn')}</Label>
          <Textarea value={explanationEn} onChange={(e) => setExplanationEn(e.target.value)} rows={2} />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label>{t('form.explanationSi')}</Label>
          <Textarea value={explanationSi} onChange={(e) => setExplanationSi(e.target.value)} rows={2} />
        </div>
      </div>

      <div className="flex items-center gap-6">
        <div className="flex flex-col gap-1.5">
          <Label>{t('form.difficulty')}</Label>
          <Select value={String(difficulty)} onValueChange={(v) => setDifficulty(Number(v))}>
            <SelectTrigger className="w-28">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="1">1</SelectItem>
              <SelectItem value="2">2</SelectItem>
              <SelectItem value="3">3</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <label className="flex items-center gap-2 text-sm font-medium">
          <Checkbox checked={isActive} onCheckedChange={(v) => setIsActive(!!v)} />
          {t('form.active')}
        </label>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}

      <Button type="submit" disabled={isSubmitting} className="self-start">
        {isSubmitting ? t('form.saving') : submitLabel}
      </Button>
    </form>
  );
}
