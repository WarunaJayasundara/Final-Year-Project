import { cn } from '@/lib/utils';

export type HelaIQMarkVariant = 'full' | 'compact' | 'mono' | 'onDark';

interface HelaIQMarkProps {
  variant?: HelaIQMarkVariant;
  className?: string;
  markClassName?: string;
}

/**
 * The HelaIQ mark: two vertical strokes (an abstract "H") joined by a
 * 3-step ascending crossbar - reads simultaneously as the H's crossbar and
 * as the app's own 5-level progression system, with one connection-point
 * dot at the crossbar's terminus. Deliberately avoids every excluded
 * cliche (brain, robot, sparkle, lightbulb, graduation cap, generic
 * multi-node neural web) - a single dot, never a web.
 */
export function HelaIQMark({ variant = 'full', className, markClassName }: HelaIQMarkProps) {
  const isCompact = variant === 'compact';
  const strokeColor =
    variant === 'mono' ? 'currentColor' : variant === 'onDark' ? 'var(--primary-foreground)' : 'var(--primary)';

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <svg
        viewBox="0 0 48 48"
        className={cn('h-8 w-8 shrink-0', markClassName)}
        fill="none"
        role="img"
        aria-label="HelaIQ"
      >
        <path d="M12,10 L12,38" stroke={strokeColor} strokeWidth="5" strokeLinecap="round" />
        <path d="M36,6 L36,38" stroke={strokeColor} strokeWidth="5" strokeLinecap="round" />
        <path
          d="M12,30 L20,30 L20,24 L28,24 L28,18 L36,18"
          stroke={strokeColor}
          strokeWidth="5"
          strokeLinecap="round"
          strokeLinejoin="round"
          fill="none"
        />
        {!isCompact && <circle cx="36" cy="18" r="3" fill={strokeColor} />}
      </svg>
      {variant === 'full' && <span className="text-lg font-semibold tracking-tight">HelaIQ</span>}
    </div>
  );
}
