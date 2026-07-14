import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { RefreshCw, Save, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAdminCategories, useAdminLevels, useCreateQuestion, useGenerateVisualQuestionPreview } from '@/features/admin/useAdmin';
import type { VisualQuestionPreview } from '@/features/admin/types';

const PATTERN_TYPES = [{ value: 'shape_rotation' as const, labelKey: 'questions.visualGen.patternShapeRotation' }];

/**
 * Pattern/visual question generator: generates from an explicit logical
 * rule (VisualQuestionGeneratorService, reusing the same chirality-verified
 * correctness check the seeders use), not decorative random shapes. Only
 * "shape rotation" is wired up - other SvgFigureBuilder archetypes (matrix
 * reasoning, paper folding, cube nets, counting) are seeder-only for now;
 * shown as disabled options below rather than hidden, so the scope cut is
 * visible.
 */
export function AdminVisualGeneratorPage() {
  const { t } = useTranslation('admin');
  const navigate = useNavigate();
  const { data: categories } = useAdminCategories();
  const { data: levels } = useAdminLevels();
  const generatePreview = useGenerateVisualQuestionPreview();
  const createQuestion = useCreateQuestion();

  const [patternType, setPatternType] = useState<'shape_rotation'>('shape_rotation');
  const [categoryId, setCategoryId] = useState<number | undefined>();
  const [levelId, setLevelId] = useState<number | undefined>();
  const [preview, setPreview] = useState<VisualQuestionPreview | null>(null);
  const [explanationEn, setExplanationEn] = useState('');

  const handleGenerate = async () => {
    if (!levelId) return;
    const result = await generatePreview.mutateAsync({ pattern_type: patternType, level_id: levelId });
    setPreview(result);
    setExplanationEn(result.explanation_en);
  };

  const handleSave = async () => {
    if (!preview || !categoryId || !levelId) return;
    await createQuestion.mutateAsync({
      category_id: categoryId,
      level_id: levelId,
      question_type: 'mcq_image',
      subcategory: preview.subcategory,
      question_text_en: preview.question_text_en,
      question_text_si: preview.question_text_si,
      image_path: preview.image_path,
      options: preview.options,
      correct_option_key: preview.correct_option_key,
      explanation_en: explanationEn,
      explanation_si: preview.explanation_si,
      difficulty_weight: preview.difficulty_weight,
      solving_time_seconds: preview.solving_time_seconds,
      bloom_level: preview.bloom_level,
      cognitive_skill: preview.cognitive_skill,
      generation_rule: preview.generation_rule,
      transformation_steps: preview.transformation_steps,
      visual_complexity_score: preview.visual_complexity_score,
      is_active: true,
    });
    toast.success(t('questions.visualGen.saved'));
    navigate('/admin/questions');
  };

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('questions.visualGen.title')}</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
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
              <Label>{t('questions.visualGen.patternType')}</Label>
              <Select value={patternType} onValueChange={(v) => setPatternType(v as 'shape_rotation')}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {PATTERN_TYPES.map((p) => (
                    <SelectItem key={p.value} value={p.value}>
                      {t(p.labelKey)}
                    </SelectItem>
                  ))}
                  <SelectItem value="matrix_reasoning" disabled>
                    {t('questions.visualGen.patternMatrixSoon')}
                  </SelectItem>
                  <SelectItem value="paper_folding" disabled>
                    {t('questions.visualGen.patternFoldingSoon')}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <Button onClick={handleGenerate} disabled={!levelId || generatePreview.isPending} className="self-start">
            <Sparkles className="h-4 w-4" /> {generatePreview.isPending ? t('questions.visualGen.generating') : t('questions.visualGen.generate')}
          </Button>
        </CardContent>
      </Card>

      {preview && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('questions.visualGen.previewTitle')}</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <img
              src={`/storage/${preview.image_path}`}
              alt=""
              className="mx-auto max-h-72 rounded-lg border border-border bg-card object-contain"
            />
            <p className="text-sm font-medium">{preview.question_text_en}</p>
            <p className="text-xs text-muted-foreground">
              {t('questions.visualGen.correctAnswer')}: {preview.correct_option_key}
            </p>

            <div className="flex flex-col gap-1.5">
              <Label>{t('form.explanationEn')}</Label>
              <Textarea value={explanationEn} onChange={(e) => setExplanationEn(e.target.value)} rows={2} />
            </div>

            <div className="flex gap-2">
              <Button variant="outline" onClick={handleGenerate} disabled={generatePreview.isPending}>
                <RefreshCw className="h-4 w-4" /> {t('questions.visualGen.regenerate')}
              </Button>
              <Button onClick={handleSave} disabled={!categoryId || createQuestion.isPending}>
                <Save className="h-4 w-4" /> {createQuestion.isPending ? t('form.saving') : t('questions.visualGen.saveQuestion')}
              </Button>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
