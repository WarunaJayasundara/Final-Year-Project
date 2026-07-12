import { useCallback, useEffect, useRef } from 'react';

/**
 * Records when a question became visible (performance.now(), monotonic and
 * unaffected by system-clock changes) and returns the elapsed milliseconds
 * at submit time. Reset on every question id change so each item gets its
 * own clock. This is the first response-time capture of any kind in the
 * test-taking UI - previously only answered_at (server timestamp) existed,
 * which supports inter-answer deltas but not true per-item timing.
 */
export function useQuestionTimer(questionId: number | null | undefined) {
  const shownAtRef = useRef<number>(performance.now());

  useEffect(() => {
    shownAtRef.current = performance.now();
  }, [questionId]);

  const elapsedMs = useCallback(() => Math.round(performance.now() - shownAtRef.current), []);

  return { elapsedMs };
}
