import { BalancedGrid } from '@/components/ui/balanced-grid';
import { Skeleton } from '@/components/ui/skeleton';

interface CardGridSkeletonProps {
  count?: number;
  columns?: { base?: number; sm?: number; lg?: number };
}

/** Placeholder cards laid out through the same BalancedGrid real content uses, so skeleton and real grid never disagree on row balancing. */
export function CardGridSkeleton({ count = 6, columns = { base: 1, sm: 2, lg: 4 } }: CardGridSkeletonProps) {
  return (
    <BalancedGrid
      items={Array.from({ length: count })}
      columns={columns}
      renderItem={(_, i) => <Skeleton key={i} className="h-36 rounded-xl" />}
    />
  );
}
