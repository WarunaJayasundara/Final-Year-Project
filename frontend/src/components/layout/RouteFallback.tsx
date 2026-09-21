import { InlineLoader } from '@/components/brand/BrandLoader';

/** Shown inside a layout while a lazily-loaded route chunk downloads. */
export function RouteFallback() {
  return (
    <div className="flex min-h-[40vh] items-center justify-center" role="status" aria-live="polite">
      <InlineLoader className="scale-[2]" />
    </div>
  );
}
