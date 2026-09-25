import { useEffect, useState } from 'react';

/**
 * Holds off updating the returned value until `value` has stopped changing for `delayMs`.
 * For a search box wired straight into a query key, this is the difference between one
 * request per keystroke (each one flashing the results to a loading state) and one request
 * after the student/admin actually pauses typing.
 */
export function useDebouncedValue<T>(value: T, delayMs = 350): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const id = window.setTimeout(() => setDebounced(value), delayMs);
    return () => window.clearTimeout(id);
  }, [value, delayMs]);

  return debounced;
}
