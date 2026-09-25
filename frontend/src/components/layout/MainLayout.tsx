import { Suspense } from 'react';
import { RouteErrorBoundary } from '@/components/RouteErrorBoundary';
import { Outlet, useLocation } from 'react-router-dom';
import { Navbar } from './Navbar';
import { Footer } from './Footer';
import { ChatWidget } from '@/features/coach/ChatWidget';
import { RouteFallback } from './RouteFallback';
import { AmbientBackground } from '@/components/brand/AmbientBackground';
import { useCardSpotlight } from '@/lib/useCardSpotlight';

export function MainLayout() {
  const { pathname } = useLocation();
  useCardSpotlight();

  return (
    <div className="relative isolate flex min-h-screen flex-col bg-background">
      <div aria-hidden className="spectrum-bar fixed inset-x-0 top-0 z-[60] h-[3px]" />
      <AmbientBackground />
      <Navbar />
      <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-10">
        <RouteErrorBoundary>
          <Suspense fallback={<RouteFallback />}>
            <div key={pathname} className="page-enter">
              <Outlet />
            </div>
          </Suspense>
        </RouteErrorBoundary>
      </main>
      <Footer />
      <ChatWidget />
    </div>
  );
}
