import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { AUTH_QUERY_KEY } from '@/features/auth/useAuth';
import { fetchMe } from '@/features/auth/api';
import { FullPageSpinner } from '@/components/auth/RequireAuth';

/**
 * Landing spot after the Laravel backend redirects the browser here once
 * Google OAuth + session login has completed server-side. We just need to
 * (re)fetch /auth/me to pick up the freshly-created session, then route the
 * student to the placement test (first login) or their dashboard.
 */
export function AuthCallbackPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  useEffect(() => {
    let cancelled = false;

    (async () => {
      const user = await fetchMe();
      if (cancelled) return;

      queryClient.setQueryData(AUTH_QUERY_KEY, user);

      if (!user) {
        navigate('/login?error=google_auth_failed', { replace: true });
        return;
      }

      if (!user.placement_completed_at) {
        navigate('/placement', { replace: true });
      } else {
        navigate('/dashboard', { replace: true });
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [navigate, queryClient]);

  return <FullPageSpinner />;
}
