import { useId } from 'react';
import { cn } from '@/lib/utils';

export type PatternVariant = 'steps' | 'dots' | 'grid' | 'rings';

interface PatternBackdropProps {
  variant?: PatternVariant;
  /** Which edge the pattern fades towards: 'left' (visible at the right) or 'bottom' (visible at the top). */
  fade?: 'left' | 'bottom';
  className?: string;
}

/**
 * A faint, code-drawn pattern for page headers and empty corners. It is drawn in the
 * current text color (so it follows light/dark mode and the theme automatically), fades out
 * towards the content side so it never competes with text, and costs no image download.
 *
 *  - steps: the ascending staircase from the HelaIQ logo (progress)
 *  - dots / grid: calm structure (practice, games)
 *  - rings: concentric focus circles (attention, achievements)
 */
export function PatternBackdrop({ variant = 'steps', fade = 'left', className }: PatternBackdropProps) {
  const id = useId();

  return (
    <div
      aria-hidden
      className={cn(
        'pointer-events-none absolute text-primary/15',
        fade === 'left' ? '[mask-image:linear-gradient(to_left,black,transparent)]' : '[mask-image:linear-gradient(to_bottom,black,transparent)]',
        className,
      )}
    >
      <svg className="h-full w-full" xmlns="http://www.w3.org/2000/svg">
        <defs>
          {variant === 'steps' && (
            <pattern id={id} width="48" height="48" patternUnits="userSpaceOnUse">
              <path d="M0 44 H12 V32 H24 V20 H36 V8 H48" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
            </pattern>
          )}
          {variant === 'dots' && (
            <pattern id={id} width="18" height="18" patternUnits="userSpaceOnUse">
              <circle cx="2" cy="2" r="1.3" fill="currentColor" />
            </pattern>
          )}
          {variant === 'grid' && (
            <pattern id={id} width="28" height="28" patternUnits="userSpaceOnUse">
              <path d="M28 0 H0 V28" fill="none" stroke="currentColor" strokeWidth="1" />
            </pattern>
          )}
          {variant === 'rings' && (
            <pattern id={id} width="80" height="80" patternUnits="userSpaceOnUse">
              <circle cx="40" cy="40" r="10" fill="none" stroke="currentColor" strokeWidth="1.2" />
              <circle cx="40" cy="40" r="22" fill="none" stroke="currentColor" strokeWidth="1.2" />
              <circle cx="40" cy="40" r="34" fill="none" stroke="currentColor" strokeWidth="1.2" />
            </pattern>
          )}
        </defs>
        <rect width="100%" height="100%" fill={`url(#${id})`} />
      </svg>
    </div>
  );
}
