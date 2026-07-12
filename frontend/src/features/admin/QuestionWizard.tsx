import { useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, ImagePlus, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { QuestionCard } from '@/features/sessions/QuestionCard';
import { useAdminCategories, useAdminLevels } from './useAdmin';
import type { QuestionOptionInput } from './types';
import type { QuestionPayload } from './api';

const OPTION_KEYS = ['A', 'B', 'C', 'D', 'E', 'F'];
const STEP_COUNT = 7;

interface Props {
  onSubmit: (payload: QuestionPayload, imageFile: File | null) => Promise<void> | void;
  isSubmitting?: boolean;
}

/**
 * Step-based question creation wizard (brief #12): one focused screen per
 * step instead of every field on one long form, plus a live preview using
 * the exact same QuestionCard component students see in a real session -
 * not a separate mockup that could drift from the real UI.
 */
export function QuestionWizard({ onSubmit, isSubmitting }: Props) {
  const { t } = useTranslation('admin');
  const { data: categories } = useAdminCategories();
  const { data: levels } = useAdminLevels();

  const [step, setStep] = useState(0);
  const [categoryId, setCategoryId] = useState(0);
  const [levelId, setLevelId] = useState(0);
  const [subcategory, setSubcategory] = useState('');
  const [examTags, setExamTags] = useState('');
  const [questionType, setQuestionType] = useState<'mcq_text' | 'mcq_image'>('mcq_text');
  const [textEn, setTextEn] = useState('');
  const [textSi, setTextSi] = useState('');
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreviewUrl, setImagePreviewUrl] = useState<string | null>(null);
  const [options, setOptions] = useState<QuestionOptionInput[]>([
    { key: 'A', text_en: '', text_si: '' },
    { key: 'B', text_en: '', text_si: '' },
  ]);
  const [correctKey, setCorrectKey] = useState('A');
  const [explanationEn, setExplanationEn] = useState('');
  const [explanationSi, setExplanationSi] = useState('');
  const [difficulty, setDifficulty] = useState(1);
  const fileInputRef = useRef<HTMLInputElement>(null);

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

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setImageFile(file);
    setImagePreviewUrl(URL.createObjectURL(file));
  };

  const validation = {
    hasCategoryLevel: categoryId > 0 && levelId > 0,
    hasText: textEn.trim() !== '' && textSi.trim() !== '',
    hasImage: questionType === 'mcq_text' || imageFile !== null,
    optionsFilled: options.every((o) => o.text_en.trim() !== '' && o.text_si.trim() !== ''),
    hasCorrectAnswer: options.some((o) => o.key === correctKey),
  };
  const isValid = Object.values(validation).every(Boolean);

  const previewQuestion = useMemo(
    () => ({
      id: 0,
      category_id: categoryId,
      level_id: levelId,
      question_type: questionType,
      question_text: textEn || t('form.wizard.previewPlaceholderText'),
      image_path: null,
      options: (options.length > 0 ? options : [{ key: 'A', text_en: '', text_si: '' }]).map((o) => ({
        key: o.key,
        text: o.text_en || `(${o.key})`,
        image_path: null,
      })),
      answer_id: 0,
      answered: false,
      expected_time_seconds: 0,
    }),
    [categoryId, levelId, questionType, textEn, options, t],
  );

  const handleSave = async () => {
    if (!isValid) return;
    await onSubmit(
      {
        category_id: categoryId,
        level_id: levelId,
        subcategory: subcategory.trim() || null,
        exam_tags: examTags.split(',').map((x) => x.trim()).filter(Boolean),
        question_type: questionType,
        question_text_en: textEn,
        question_text_si: textSi,
        options,
        correct_option_key: correctKey,
        explanation_en: explanationEn,
        explanation_si: explanationSi,
        difficulty_weight: difficulty,
        is_active: true,
      },
      imageFile,
    );
  };

  const steps = [
    t('form.wizard.step1'),
    t('form.wizard.step2'),
    t('form.wizard.step3'),
    t('form.wizard.step4'),
    t('form.wizard.step5'),
    t('form.wizard.step6'),
    t('form.wizard.step7'),
  ];

  return (
    <div className="flex flex-col gap-6">
      {/* Stepper */}
      <div className="flex items-center gap-1 overflow-x-auto pb-1">
        {steps.map((label, i) => (
          <button
            key={label}
            type="button"
            onClick={() => setStep(i)}
            className={`flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors ${
              i === step
                ? 'border-primary bg-primary/10 text-primary'
                : i < step
                  ? 'border-success/40 bg-success/10 text-success'
                  : 'border-border text-muted-foreground'
            }`}
          >
            <span className="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px]">
              {i < step ? <Check className="h-2.5 w-2.5" /> : i + 1}
            </span>
            {label}
          </button>
        ))}
      </div>

      <Card>
        <CardContent className="flex flex-col gap-4 p-6">
          {step === 0 && (
            <div className="flex flex-col gap-3">
              <Label>{t('form.questionType')}</Label>
              <div className="grid gap-3 sm:grid-cols-2">
                {(['mcq_text', 'mcq_image'] as const).map((type) => (
                  <button
                    key={type}
                    type="button"
                    onClick={() => setQuestionType(type)}
                    className={`rounded-xl border p-4 text-left text-sm font-medium transition-colors ${
                      questionType === type ? 'border-primary bg-primary/5 text-primary' : 'border-border hover:bg-muted'
                    }`}
                  >
                    {type === 'mcq_text' ? t('form.typeText') : t('form.typeImage')}
                  </button>
                ))}
              </div>
            </div>
          )}

          {step === 1 && (
            <div className="flex flex-col gap-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="flex flex-col gap-1.5">
                  <Label>{t('form.questionTextEn')}</Label>
                  <Textarea value={textEn} onChange={(e) => setTextEn(e.target.value)} rows={3} />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label>{t('form.questionTextSi')}</Label>
                  <Textarea value={textSi} onChange={(e) => setTextSi(e.target.value)} rows={3} />
                </div>
              </div>

              {questionType === 'mcq_image' && (
                <div className="flex flex-col gap-2">
                  <Label>{t('questions.pattern.title')}</Label>
                  <div className="flex items-center gap-4">
                    {imagePreviewUrl && (
                      <img
                        src={imagePreviewUrl}
                        alt=""
                        className="h-24 w-24 rounded-lg border border-border object-contain"
                      />
                    )}
                    <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={handleFileChange} />
                    <Button type="button" variant="outline" onClick={() => fileInputRef.current?.click()}>
                      <ImagePlus className="h-4 w-4" /> {imageFile ? t('questions.pattern.replace') : t('questions.pattern.upload')}
                    </Button>
                  </div>
                </div>
              )}
            </div>
          )}

          {step === 2 && (
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
                      correctKey === option.key ? 'border-success bg-success/10 text-success' : 'border-border'
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
                  <Button type="button" size="icon-sm" variant="ghost" onClick={() => removeOption(option.key)} disabled={options.length <= 2}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              ))}
              <p className="text-xs text-muted-foreground">{t('form.markCorrectHint')}</p>
            </div>
          )}

          {step === 3 && (
            <div className="grid gap-4 sm:grid-cols-2">
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
                <Label>{t('form.subcategory')}</Label>
                <Input placeholder={t('form.subcategoryPlaceholder')} value={subcategory} onChange={(e) => setSubcategory(e.target.value)} />
              </div>
              <div className="flex flex-col gap-1.5">
                <Label>{t('form.examTags')}</Label>
                <Input placeholder={t('form.examTagsPlaceholder')} value={examTags} onChange={(e) => setExamTags(e.target.value)} />
              </div>
              <div className="flex flex-col gap-1.5">
                <Label>{t('form.difficulty')}</Label>
                <Select value={String(difficulty)} onValueChange={(v) => setDifficulty(Number(v))}>
                  <SelectTrigger className="w-28">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {[1, 2, 3, 4, 5].map((d) => (
                      <SelectItem key={d} value={String(d)}>
                        {d}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          )}

          {step === 4 && (
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="flex flex-col gap-1.5">
                <Label>{t('form.explanationEn')}</Label>
                <Textarea value={explanationEn} onChange={(e) => setExplanationEn(e.target.value)} rows={3} />
              </div>
              <div className="flex flex-col gap-1.5">
                <Label>{t('form.explanationSi')}</Label>
                <Textarea value={explanationSi} onChange={(e) => setExplanationSi(e.target.value)} rows={3} />
              </div>
            </div>
          )}

          {step === 5 && (
            <div className="flex flex-col gap-3">
              <p className="text-xs text-muted-foreground">{t('form.wizard.previewHint')}</p>
              <QuestionCard
                question={previewQuestion}
                selected={correctKey}
                revealed={{ isCorrect: true, correctKey }}
                onSelect={() => {}}
                onAdvance={() => {}}
                advanceDisabled
                advanceLabel={t('form.wizard.previewAdvance')}
              />
            </div>
          )}

          {step === 6 && (
            <div className="flex flex-col gap-3">
              <ChecklistRow ok={validation.hasCategoryLevel} label={t('form.wizard.checkCategoryLevel')} />
              <ChecklistRow ok={validation.hasText} label={t('form.wizard.checkText')} />
              <ChecklistRow ok={validation.hasImage} label={t('form.wizard.checkImage')} />
              <ChecklistRow ok={validation.optionsFilled} label={t('form.wizard.checkOptions')} />
              <ChecklistRow ok={validation.hasCorrectAnswer} label={t('form.wizard.checkCorrectAnswer')} />

              <div className="flex items-center gap-2 pt-2">
                <Checkbox checked disabled />
                <span className="text-sm text-muted-foreground">{t('form.active')}</span>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      <div className="flex items-center justify-between">
        <Button type="button" variant="outline" onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0}>
          {t('form.wizard.back')}
        </Button>
        {step < STEP_COUNT - 1 ? (
          <Button type="button" onClick={() => setStep((s) => Math.min(STEP_COUNT - 1, s + 1))}>
            {t('form.wizard.continue')}
          </Button>
        ) : (
          <Button type="button" onClick={handleSave} disabled={!isValid || isSubmitting}>
            {isSubmitting ? t('form.saving') : t('questions.createSubmit')}
          </Button>
        )}
      </div>
    </div>
  );
}

function ChecklistRow({ ok, label }: { ok: boolean; label: string }) {
  return (
    <div className={`flex items-center gap-2 rounded-lg border p-3 text-sm ${ok ? 'border-success/30 bg-success/5 text-success' : 'border-border text-muted-foreground'}`}>
      <span className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border ${ok ? 'border-success' : 'border-border'}`}>
        {ok && <Check className="h-3 w-3" />}
      </span>
      {label}
    </div>
  );
}
