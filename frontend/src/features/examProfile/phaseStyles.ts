import type { StudyPlanPhase } from './types';

export const PHASE_ORDER: StudyPlanPhase[] = ['foundation', 'practice', 'intensive', 'final_revision', 'exam_day'];

/** Urgency ramps from calm navy to alert red as the exam approaches. */
export const PHASE_COLORS: Record<StudyPlanPhase, string> = {
  foundation: 'border-primary/30 bg-primary/10 text-primary',
  practice: 'border-success/30 bg-success/10 text-success',
  intensive: 'border-warning/40 bg-warning/15 text-warning-foreground dark:text-warning',
  final_revision: 'border-[color:var(--chart-4)]/40 bg-[color:var(--chart-4)]/10 text-[color:var(--chart-4)]',
  exam_day: 'border-destructive/40 bg-destructive/15 text-destructive',
};
