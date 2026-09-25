import { Navigate, Outlet } from 'react-router-dom';
import { useCurrentUser } from '@/features/auth/useAuth';
import { FullPageSpinner } from './RequireAuth';
import { ErrorState } from '@/components/ui/error-state';

/** Wraps routes that need the user to have finished their placement test first (daily practice, dashboard, games). */
export function RequirePlacement() {
  const { data: user, isLoading, isError, refetch } = useCurrentUser();

  if (isLoading) {
    return <FullPageSpinner />;
  }

  if (isError) {
    return <ErrorState className="my-16" onRetry={() => refetch()} />;
  }

  if (user && user.role === 'user' && !user.placement_completed_at) {
    return <Navigate to="/placement" replace />;
  }

  return <Outlet />;
}
