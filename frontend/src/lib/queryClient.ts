import { MutationCache, QueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import i18n from '@/lib/i18n';
import { apiErrorMessage } from '@/lib/apiError';

/** Client errors (bad request, not found, unauthenticated ...) will not fix themselves, so retrying only delays the message. */
function shouldRetry(failureCount: number, error: unknown): boolean {
  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status && status < 500 && status !== 408 && status !== 429) return false;
  return failureCount < 2;
}

/**
 * Mutations tagged `meta: { toastOnError: true }` are ones whose failure would otherwise be completely
 * silent (a game score that was not saved, a mission that was not claimed ...). Forms and runners that
 * show their own error message are deliberately NOT tagged, so nobody sees the same failure twice.
 */
const mutationCache = new MutationCache({
  onError: (error, _variables, _context, mutation) => {
    if (mutation.meta?.toastOnError !== true || mutation.options.onError) return;
    toast.error(apiErrorMessage(error, i18n.t.bind(i18n)));
  },
});

export const queryClient = new QueryClient({
  mutationCache,
  defaultOptions: {
    queries: {
      retry: shouldRetry,
      refetchOnWindowFocus: false,
      staleTime: 30_000,
    },
  },
});
