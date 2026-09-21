import { useLocation } from 'react-router-dom';
import { cn } from '@/lib/utils';

export type AmbientTone = 'focus' | 'play' | 'reward' | 'study';

/**
 * Page-section mood: the background takes the colors of what the student is doing.
 * The palettes live in index.css (saturated for light mode, the soft chart tokens for dark).
 */
const TONE_BY_PATH: Array<[RegExp, AmbientTone]> = [
  [/^\/games/, 'play'],
  [/^\/(badges|leaderboard)/, 'reward'],
  [/^\/(study-notes|study-plan)/, 'study'],
];

function toneFor(pathname: string): AmbientTone {
  return TONE_BY_PATH.find(([re]) => re.test(pathname))?.[1] ?? 'focus';
}

/** Seven ascending treads, the HelaIQ logo's staircase, drawn in a 1000 x 600 box. */
const STAIRS = 'M0 600 H140 V510 H280 V420 H420 V330 H560 V240 H700 V150 H840 V60 H1000';

interface AmbientBackgroundProps {
  /** Fixed tone. Omit to follow the current route. */
  tone?: AmbientTone;
  /** Quieter variant for dense work areas (admin): no staircase, softer grid and glow. */
  subtle?: boolean;
}

/**
 * The page backdrop: a tinted grid that fades downwards, two horizon glows, film grain, and a
 * staircase that draws itself while a light climbs it (progress, the platform's core loop).
 * Decorative only: hidden from assistive tech, ignores the pointer, sits below all content, and
 * is static under `prefers-reduced-motion`. Place it inside an element with `relative isolate`.
 */
export function AmbientBackground({ tone, subtle = false }: AmbientBackgroundProps) {
  const { pathname } = useLocation();
  const active = tone ?? toneFor(pathname);

  return (
    <div
      aria-hidden
      data-tone={active}
      className={cn('ambient pointer-events-none fixed inset-0 -z-10 overflow-hidden', subtle && 'ambient-subtle')}
    >
      <span className="ambient-glow" />
      <span className="ambient-glow ambient-glow-b hidden sm:block" />
      <div className="ambient-lines">
        <span className="ambient-grid" />
      </div>
      <svg className="ambient-stairs" viewBox="0 0 1000 600" preserveAspectRatio="xMaxYMax meet">
        <path id="ambient-stairs-path" className="ambient-stairs-path" d={STAIRS} pathLength={1} vectorEffect="non-scaling-stroke" />
        <circle className="ambient-stairs-dot" r="7">
          <animateMotion
            dur="14s"
            repeatCount="indefinite"
            calcMode="spline"
            keyTimes="0;0.55;1"
            keyPoints="0;1;1"
            keySplines="0.42 0 0.58 1;0 0 1 1"
          >
            <mpath href="#ambient-stairs-path" />
          </animateMotion>
          <animate attributeName="opacity" dur="14s" repeatCount="indefinite" values="0;1;1;0;0" keyTimes="0;0.03;0.6;0.7;1" />
        </circle>
      </svg>
      <span className="ambient-grain" />
    </div>
  );
}
