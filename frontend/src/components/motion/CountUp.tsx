import { useEffect, useRef, useState } from 'react';
import { useReducedMotion } from 'framer-motion';

interface CountUpProps {
  /** Text such as "112", "5/14", "1,250 XP". The first whole number in it counts up; the rest is kept. */
  value: string;
  durationMs?: number;
}

const NUMBER = /\d[\d,]*/;

/** Counts the leading number of a display string up from zero once, when it first appears. */
export function CountUp({ value, durationMs = 900 }: CountUpProps) {
  const reduced = useReducedMotion();
  const match = NUMBER.exec(value);
  const target = match ? Number(match[0].replace(/,/g, '')) : null;
  const [shown, setShown] = useState<number | null>(null);
  const played = useRef<string | null>(null);

  useEffect(() => {
    if (target === null || reduced || played.current === value) return;
    played.current = value;

    let frame = 0;
    const start = performance.now();
    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / durationMs);
      setShown(Math.round(target * (1 - Math.pow(1 - t, 3))));
      if (t < 1) frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [value, target, reduced, durationMs]);

  if (!match || target === null || shown === null || reduced) return <>{value}</>;

  const grouped = match[0].includes(',') ? shown.toLocaleString('en-US') : String(shown);
  return <>{value.replace(NUMBER, grouped)}</>;
}
