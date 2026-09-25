import { useCallback, useEffect, useRef } from 'react';

/** Records when a question became visible (performance.now(). */
export function useQuestionTimer(questionId: number | null | undefined) {
  const shownAtRef = useRef<number>(performance.now());

  useEffect(() => {
    shownAtRef.current = performance.now();
  }, [questionId]);

  const elapsedMs = useCallback(() => Math.round(performance.now() - shownAtRef.current), []);

  return { elapsedMs };
}
