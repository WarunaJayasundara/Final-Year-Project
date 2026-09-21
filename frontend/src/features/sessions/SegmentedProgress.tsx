import { cn } from '@/lib/utils';

interface SegmentedProgressProps {
  total: number;
  /** Zero-based index of the question being answered. */
  current: number;
  /** Per-question outcome so far: true = correct, false = wrong, null/undefined = not answered yet. */
  results: (boolean | null | undefined)[];
}

/**
 * One segment per question that turns green or red as the student answers.
 * Unlike a plain bar it shows how the run is going, and a growing streak of
 * green segments is its own small reward. No text, so it needs no translation.
 */
export function SegmentedProgress({ total, current, results }: SegmentedProgressProps) {
  const answered = results.filter((r) => r !== null && r !== undefined).length;

  return (
    <div role="progressbar" aria-valuemin={0} aria-valuemax={total} aria-valuenow={answered} className="flex gap-1">
      {Array.from({ length: total }, (_, i) => {
        const outcome = results[i];
        return (
          <span
            key={i}
            className={cn(
              'h-2 min-w-0 flex-1 rounded-full transition-colors duration-300',
              outcome === true && 'bg-success',
              outcome === false && 'bg-destructive',
              (outcome === null || outcome === undefined) && (i === current ? 'bg-primary/50' : 'bg-muted'),
            )}
          />
        );
      })}
    </div>
  );
}
