import { motion, useReducedMotion } from 'framer-motion';
import { cn } from '@/lib/utils';

const strokeTransition = (delay: number, duration: number) => ({
  pathLength: { delay, duration, ease: [0.65, 0, 0.35, 1] as const },
});

/**
 * The animated form of HelaIQMark: the two strokes and 3-step crossbar
 * draw themselves in sequence, then the connection dot springs in - "the
 * mark assembles itself, one connection at a time." Falls back to a
 * static mark under prefers-reduced-motion. Same paths as HelaIQMark, so
 * there's only one visual asset to maintain.
 */
function AnimatedMark({ size = 40 }: { size?: number }) {
  const reduceMotion = useReducedMotion();

  if (reduceMotion) {
    return (
      <svg viewBox="0 0 48 48" width={size} height={size} fill="none" role="img" aria-label="HelaIQ">
        <path d="M12,10 L12,38" stroke="var(--primary)" strokeWidth="5" strokeLinecap="round" />
        <path d="M36,6 L36,38" stroke="var(--primary)" strokeWidth="5" strokeLinecap="round" />
        <path
          d="M12,30 L20,30 L20,24 L28,24 L28,18 L36,18"
          stroke="var(--primary)"
          strokeWidth="5"
          strokeLinecap="round"
          strokeLinejoin="round"
          fill="none"
        />
        <circle cx="36" cy="18" r="3" fill="var(--primary)" />
      </svg>
    );
  }

  return (
    <svg viewBox="0 0 48 48" width={size} height={size} fill="none" role="img" aria-label="HelaIQ">
      <motion.path
        d="M12,10 L12,38"
        stroke="var(--primary)"
        strokeWidth="5"
        strokeLinecap="round"
        initial={{ pathLength: 0 }}
        animate={{ pathLength: 1 }}
        transition={strokeTransition(0, 0.25)}
      />
      <motion.path
        d="M12,30 L20,30 L20,24 L28,24 L28,18 L36,18"
        stroke="var(--primary)"
        strokeWidth="5"
        strokeLinecap="round"
        strokeLinejoin="round"
        fill="none"
        initial={{ pathLength: 0 }}
        animate={{ pathLength: 1 }}
        transition={strokeTransition(0.25, 0.35)}
      />
      <motion.path
        d="M36,6 L36,38"
        stroke="var(--primary)"
        strokeWidth="5"
        strokeLinecap="round"
        initial={{ pathLength: 0 }}
        animate={{ pathLength: 1 }}
        transition={strokeTransition(0.6, 0.2)}
      />
      <motion.circle
        cx="36"
        cy="18"
        r="3"
        fill="var(--primary)"
        initial={{ scale: 0 }}
        animate={{ scale: 1 }}
        transition={{ delay: 0.8, type: 'spring', stiffness: 400, damping: 15 }}
      />
    </svg>
  );
}

/** Full-screen loader shown once per app boot. */
export function AppBootLoader() {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-background">
      <AnimatedMark size={56} />
    </div>
  );
}

/** Small inline loader (~20px) replacing ad-hoc Loader2/animate-spin usage. */
export function InlineLoader({ className }: { className?: string }) {
  return (
    <span className={cn('inline-flex items-center justify-center', className)}>
      <AnimatedMark size={20} />
    </span>
  );
}
