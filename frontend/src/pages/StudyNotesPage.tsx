import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  BarChart3,
  BookOpen,
  Clock3,
  Compass,
  GitBranch,
  Grid3x3,
  KeyRound,
  LayoutGrid,
  ListChecks,
  MessageSquare,
  Percent,
  Target,
  Timer,
  TrendingUp,
  Users,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { BalancedGrid } from '@/components/ui/balanced-grid';
import { CardGridSkeleton } from '@/components/skeletons/CardGridSkeleton';
import { FadeInItem, FadeInStagger } from '@/components/motion/FadeIn';
import {
  useDueToday,
  usePracticeQuestions,
  useStudyNoteRecommendation,
  useStudyNotes,
  useSubmitReview,
} from '@/features/studyNotes/useStudyNotes';
import type { StudyNote } from '@/features/studyNotes/types';

type TopicStyle = { icon: typeof BookOpen; accent: string };

// Icons stay literal/meaningful per topic; color cycles through the app's
// own 5-color chart palette instead of 19 separate ad-hoc named Tailwind
// colors, so topic tiles stay visually consistent with the rest of HelaIQ.
const TOPIC_ICONS: Record<string, typeof BookOpen> = {
  iq_theory: Target,
  number_series: TrendingUp,
  blood_relations: Users,
  speed_distance: Clock3,
  syllogism: GitBranch,
  statement_sufficiency: ListChecks,
  calendar_clock: Clock3,
  direction_sense: Compass,
  coding_decoding: KeyRound,
  seating_arrangement: LayoutGrid,
  multi_constraint_seating: LayoutGrid,
  data_interpretation: BarChart3,
  work_time: Timer,
  percentages: Percent,
  verbal: MessageSquare,
  matrix: Grid3x3,
  test_strategy: BookOpen,
  truth_teller_logic: ListChecks,
  venn_consistency: GitBranch,
  critical_reasoning_passage: MessageSquare,
  boolean_overlay: Grid3x3,
};

const CHART_ACCENTS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
const TOPIC_ORDER = Object.keys(TOPIC_ICONS);

const DEFAULT_STYLE: TopicStyle = { icon: BookOpen, accent: 'var(--primary)' };

function styleFor(subcategory: string | null): TopicStyle {
  if (!subcategory || !TOPIC_ICONS[subcategory]) return DEFAULT_STYLE;
  const accent = CHART_ACCENTS[TOPIC_ORDER.indexOf(subcategory) % CHART_ACCENTS.length];
  return { icon: TOPIC_ICONS[subcategory], accent };
}

/**
 * The "self-learning" reading list: teaching notes generated from admin-
 * uploaded theory books (see AdminKnowledgeLibraryPage), shown here only
 * once an admin has explicitly published them. Also surfaces the spaced-
 * repetition due-today queue and a weak-area-triggered lesson
 * recommendation.
 */
export function StudyNotesPage() {
  const { t, i18n } = useTranslation('dashboard');
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { data: notes, isLoading } = useStudyNotes();
  const { data: dueToday } = useDueToday();
  const { data: recommendation } = useStudyNoteRecommendation();
  const [activeNote, setActiveNote] = useState<StudyNote | null>(null);

  return (
    <div className="flex flex-col gap-6">
      <div className="relative overflow-hidden rounded-2xl border border-border p-6 sm:p-8">
        <div className="gradient-orb -right-16 -top-24 h-64 w-64 bg-primary/25" />
        <div className="gradient-orb -bottom-24 -left-10 h-56 w-56 bg-[color:var(--chart-2)]/20" />
        <div className="relative flex flex-col gap-2">
          <span className="inline-flex w-fit items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
            <BookOpen className="h-3.5 w-3.5" /> {t('studyNotes.eyebrow')}
          </span>
          <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{t('studyNotes.title')}</h1>
          <p className="max-w-2xl text-muted-foreground">{t('studyNotes.subtitle')}</p>
        </div>
      </div>

      {recommendation && (
        <Card className="border-primary/30 bg-primary/5">
          <CardContent className="flex flex-wrap items-center justify-between gap-3 p-5">
            <div className="flex items-center gap-3">
              <Target className="h-5 w-5 text-primary" />
              <div>
                <p className="text-sm font-medium">
                  {t('studyNotes.recommendation.title', { accuracy: recommendation.accuracy })}
                </p>
                <p className="text-sm text-muted-foreground">
                  {locale === 'si' ? recommendation.study_note.title_si : recommendation.study_note.title_en}
                </p>
              </div>
            </div>
            <Button size="sm" onClick={() => setActiveNote(recommendation.study_note)}>
              {t('studyNotes.recommendation.action')}
            </Button>
          </CardContent>
        </Card>
      )}

      {dueToday && dueToday.length > 0 && (
        <Card>
          <CardContent className="flex flex-col gap-3 p-5">
            <p className="text-sm font-medium">{t('studyNotes.dueToday.title', { count: dueToday.length })}</p>
            <div className="flex flex-wrap gap-2">
              {dueToday.map(
                (review) =>
                  review.study_note && (
                    <Button
                      key={review.id}
                      size="sm"
                      variant="outline"
                      onClick={() => setActiveNote(review.study_note as StudyNote)}
                    >
                      {locale === 'si' ? review.study_note.title_si : review.study_note.title_en}
                    </Button>
                  ),
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {isLoading ? (
        <CardGridSkeleton count={6} />
      ) : !notes?.data.length ? (
        <Card className="border-dashed">
          <CardContent className="flex flex-col items-center gap-3 p-8 text-center">
            <BookOpen className="h-8 w-8 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">{t('studyNotes.none')}</p>
          </CardContent>
        </Card>
      ) : (
        <FadeInStagger>
          <BalancedGrid
            items={notes.data}
            columns={{ base: 1, sm: 2, lg: 3 }}
            itemWidth="18rem"
            renderItem={(note) => (
              <FadeInItem>
                <StudyNoteTile note={note} locale={locale} onOpen={() => setActiveNote(note)} />
              </FadeInItem>
            )}
          />
        </FadeInStagger>
      )}

      <Dialog open={activeNote !== null} onOpenChange={(open) => !open && setActiveNote(null)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
          {activeNote && <StudyNoteReader note={activeNote} locale={locale} onClose={() => setActiveNote(null)} />}
        </DialogContent>
      </Dialog>
    </div>
  );
}

function StudyNoteTile({ note, locale, onOpen }: { note: StudyNote; locale: 'en' | 'si'; onOpen: () => void }) {
  const { icon: Icon, accent } = styleFor(note.subcategory);
  const content = locale === 'si' ? note.content_si : note.content_en;
  const preview = content.replace(/\s+/g, ' ').slice(0, 110);

  return (
    <button type="button" onClick={onOpen} className="h-full w-full text-left">
      <Card className="h-full transition-colors hover:border-primary/40">
        <CardContent className="flex h-full flex-col gap-3 p-5">
          <span
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
            style={{ backgroundColor: `color-mix(in oklch, ${accent}, transparent 85%)`, color: accent }}
          >
            <Icon className="h-5 w-5" />
          </span>
          <div className="flex flex-col gap-1">
            <p className="font-semibold leading-snug">{locale === 'si' ? note.title_si : note.title_en}</p>
            <p className="line-clamp-3 text-sm text-muted-foreground">{preview}...</p>
          </div>
          <div className="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
            {note.category && (
              <Badge variant="outline" className="text-xs">
                {locale === 'si' ? note.category.name_si : note.category.name_en}
              </Badge>
            )}
            {note.key_concepts?.slice(0, 2).map((concept) => (
              <Badge key={concept} variant="secondary" className="text-xs">
                {concept}
              </Badge>
            ))}
          </div>
        </CardContent>
      </Card>
    </button>
  );
}

function StudyNoteReader({ note, locale, onClose }: { note: StudyNote; locale: 'en' | 'si'; onClose: () => void }) {
  const { t } = useTranslation('dashboard');
  const { icon: Icon, accent } = styleFor(note.subcategory);
  const [practiceOpen, setPracticeOpen] = useState(false);
  const submitReview = useSubmitReview();

  const learningObjective = locale === 'si' ? note.learning_objective_si : note.learning_objective_en;
  const workedExample = locale === 'si' ? note.worked_example_si : note.worked_example_en;
  const keyTechnique = locale === 'si' ? note.key_technique_si : note.key_technique_en;
  const commonMistakes = locale === 'si' ? note.common_mistakes_si : note.common_mistakes_en;

  return (
    <>
      <DialogHeader>
        <div className="flex items-center gap-3">
          <span
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
            style={{ backgroundColor: `color-mix(in oklch, ${accent}, transparent 85%)`, color: accent }}
          >
            <Icon className="h-5 w-5" />
          </span>
          <div className="flex flex-col gap-1">
            <DialogTitle className="text-left">{locale === 'si' ? note.title_si : note.title_en}</DialogTitle>
            {note.category && (
              <Badge variant="outline" className="w-fit text-xs">
                {locale === 'si' ? note.category.name_si : note.category.name_en}
              </Badge>
            )}
          </div>
        </div>
      </DialogHeader>
      <div className="flex flex-col gap-4 pt-2">
        {learningObjective && (
          <div className="rounded-lg border border-primary/20 bg-primary/5 p-3">
            <p className="text-xs font-medium uppercase tracking-wide text-primary">{t('studyNotes.sections.objective')}</p>
            <p className="text-sm">{learningObjective}</p>
          </div>
        )}

        <div>
          <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {t('studyNotes.sections.concept')}
          </p>
          <p className="whitespace-pre-wrap text-sm leading-relaxed text-foreground/90">
            {locale === 'si' ? note.content_si : note.content_en}
          </p>
        </div>

        {workedExample && (
          <div className="rounded-lg border border-border bg-muted/30 p-3">
            <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {t('studyNotes.sections.workedExample')}
            </p>
            <p className="whitespace-pre-wrap text-sm leading-relaxed">{workedExample}</p>
          </div>
        )}

        {keyTechnique && (
          <div>
            <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {t('studyNotes.sections.technique')}
            </p>
            <p className="whitespace-pre-wrap text-sm leading-relaxed">{keyTechnique}</p>
          </div>
        )}

        {commonMistakes && (
          <div>
            <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {t('studyNotes.sections.mistakes')}
            </p>
            <p className="whitespace-pre-wrap text-sm leading-relaxed">{commonMistakes}</p>
          </div>
        )}

        {note.key_concepts && note.key_concepts.length > 0 && (
          <div className="flex flex-wrap gap-1.5 border-t border-border pt-3">
            {note.key_concepts.map((concept) => (
              <Badge key={concept} variant="secondary" className="text-xs">
                {concept}
              </Badge>
            ))}
          </div>
        )}

        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
          <Button size="sm" variant="outline" onClick={() => setPracticeOpen((v) => !v)}>
            {t('studyNotes.testYourself')}
          </Button>
          <div className="flex gap-1.5">
            {(['again', 'hard', 'good', 'easy'] as const).map((result) => (
              <Button
                key={result}
                size="sm"
                variant="outline"
                disabled={submitReview.isPending}
                onClick={() => submitReview.mutate({ studyNoteId: note.id, result }, { onSuccess: onClose })}
              >
                {t(`studyNotes.review.${result}`)}
              </Button>
            ))}
          </div>
        </div>

        {practiceOpen && <RetrievalPractice studyNoteId={note.id} />}
      </div>
    </>
  );
}

function RetrievalPractice({ studyNoteId }: { studyNoteId: number }) {
  const { t } = useTranslation('dashboard');
  const { data: questions, isLoading } = usePracticeQuestions(studyNoteId);
  const [selected, setSelected] = useState<Record<number, string>>({});

  if (isLoading) {
    return <p className="text-sm text-muted-foreground">{t('studyNotes.loadingPractice')}</p>;
  }
  if (!questions || questions.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('studyNotes.noPracticeQuestions')}</p>;
  }

  return (
    <div className="flex flex-col gap-4 rounded-lg border border-border p-4">
      {questions.map((q) => {
        const picked = selected[q.id];
        return (
          <div key={q.id} className="flex flex-col gap-2">
            <p className="text-sm font-medium">{q.question_text}</p>
            <div className="flex flex-wrap gap-1.5">
              {q.options.map((opt) => {
                const isPicked = picked === opt.key;
                const isCorrect = opt.key === q.correct_option_key;
                const showState = picked !== undefined;
                return (
                  <Button
                    key={opt.key}
                    size="sm"
                    variant="outline"
                    className={
                      showState && isCorrect
                        ? 'border-success bg-success/10'
                        : showState && isPicked && !isCorrect
                          ? 'border-destructive bg-destructive/10'
                          : ''
                    }
                    onClick={() => setSelected((prev) => ({ ...prev, [q.id]: opt.key }))}
                  >
                    {opt.text}
                  </Button>
                );
              })}
            </div>
            {picked && q.explanation && <p className="text-xs text-muted-foreground">{q.explanation}</p>}
          </div>
        );
      })}
    </div>
  );
}
