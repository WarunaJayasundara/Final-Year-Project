import { Navigate, Outlet } from 'react-router-dom';
import { useCurrentUser } from '@/features/auth/useAuth';
import { FullPageSpinner } from './RequireAuth';

/**
 * Wraps routes that need the user to have finished their placement test first
 * (daily practice, dashboard, games). Admin/super_admin accounts never take a
 * placement test, so they pass through untouched.
 */
export function RequirePlacement() {
  const { data: user, isLoading } = useCurrentUser();

  if (isLoading) {
    return <FullPageSpinner />;
  }

  if (user && user.role === 'user' && !user.placement_completed_at) {
    return <Navigate to="/placement" replace />;
  }

  return <Outlet />;
}
