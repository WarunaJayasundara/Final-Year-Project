import { useEffect, useState } from 'react';

/** Matches Tailwind's default breakpoints. */
const BREAKPOINTS = { sm: 640, md: 768, lg: 1024, xl: 1280 } as const;

export type BreakpointName = keyof typeof BREAKPOINTS;

/**
 * Tracks the widest matching Tailwind breakpoint below `xl`, so components
 * can pick a per-viewport value that plain CSS grid can't compute for them
 * (e.g. BalancedGrid's preferred-column count, which depends on item count
 * as well as viewport width).
 */
export function useBreakpoint(): BreakpointName | 'base' {
  const [breakpoint, setBreakpoint] = useState<BreakpointName | 'base'>('base');

  useEffect(() => {
    const queries = (Object.entries(BREAKPOINTS) as [BreakpointName, number][]).map(
      ([name, px]) => [name, window.matchMedia(`(min-width: ${px}px)`)] as const,
    );

    const update = () => {
      let current: BreakpointName | 'base' = 'base';
      for (const [name, mql] of queries) {
        if (mql.matches) current = name;
      }
      setBreakpoint(current);
    };

    update();
    queries.forEach(([, mql]) => mql.addEventListener('change', update));
    return () => queries.forEach(([, mql]) => mql.removeEventListener('change', update));
  }, []);

  return breakpoint;
}
