import type { StudyPlanPhase } from './types';

export const PHASE_ORDER: StudyPlanPhase[] = ['foundation', 'practice', 'intensive', 'final_revision', 'exam_day'];

/** Urgency ramps from calm blue to alert red as the exam approaches. */
export const PHASE_COLORS: Record<StudyPlanPhase, string> = {
  foundation: 'border-blue-500/40 bg-blue-500/15 text-blue-600 dark:text-blue-400',
  practice: 'border-emerald-500/40 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
  intensive: 'border-amber-500/40 bg-amber-500/15 text-amber-600 dark:text-amber-400',
  final_revision: 'border-orange-500/40 bg-orange-500/15 text-orange-600 dark:text-orange-400',
  exam_day: 'border-destructive/40 bg-destructive/15 text-destructive',
};
