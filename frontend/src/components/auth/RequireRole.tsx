import { Navigate, Outlet } from 'react-router-dom';
import { useCurrentUser } from '@/features/auth/useAuth';
import type { UserRole } from '@/features/auth/types';
import { FullPageSpinner } from './RequireAuth';

export function RequireRole({ roles }: { roles: UserRole[] }) {
  const { data: user, isLoading } = useCurrentUser();

  if (isLoading) {
    return <FullPageSpinner />;
  }

  if (!user) {
    return <Navigate to="/admin/login" replace />;
  }

  if (!roles.includes(user.role)) {
    return <Navigate to="/dashboard" replace />;
  }

  return <Outlet />;
}
