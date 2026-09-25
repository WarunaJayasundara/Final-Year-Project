import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useCurrentUser } from '@/features/auth/useAuth';
import { ErrorState } from '@/components/ui/error-state';

export function RequireAuth() {
  const { data: user, isLoading, isError, refetch } = useCurrentUser();
  const location = useLocation();

  if (isLoading) {
    return <FullPageSpinner />;
  }

  // The server could not be reached: that says nothing about whether the student is signed in, so do not
  // send them to the login page (it would look like they were logged out at random).
  if (isError) {
    return <ErrorState className="my-16" onRetry={() => refetch()} />;
  }

  if (!user) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  return <Outlet />;
}

export function FullPageSpinner() {
  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
    </div>
  );
}
