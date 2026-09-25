import type { ReactNode } from 'react';
import { useBreakpoint } from '@/hooks/use-breakpoint';
import { cn } from '@/lib/utils';

interface BalancedGridProps<T> {
  items: T[];
  renderItem: (item: T, index: number) => ReactNode;
  /** Preferred column count per breakpoint - the actual row split may use fewer to balance rows. */
  columns?: { base?: number; sm?: number; md?: number; lg?: number };
  /** Fixed width for each item, so incomplete rows visually center instead of stretching. */
  itemWidth?: string;
  className?: string;
  gap?: string;
}

/** Splits `items` into evenly-sized, centered rows instead of leaving an orphaned tail on a fixed `grid-cols-N`. */
export function BalancedGrid<T>({
  items,
  renderItem,
  columns = { base: 1, sm: 2, lg: 4 },
  itemWidth = '16rem',
  className,
  gap = 'gap-4',
}: BalancedGridProps<T>) {
  const breakpoint = useBreakpoint();
  const preferred =
    (breakpoint === 'lg' || breakpoint === 'xl' ? columns.lg : breakpoint === 'md' ? (columns.md ?? columns.sm) : breakpoint === 'sm' ? columns.sm : columns.base) ??
    columns.base ??
    1;

  const itemCount = items.length;
  if (itemCount === 0) return null;

  const columnCount = Math.max(1, Math.min(preferred, itemCount));
  const rowCount = Math.ceil(itemCount / columnCount);
  const perRow = Math.ceil(itemCount / rowCount);

  const rows: T[][] = [];
  for (let i = 0; i < itemCount; i += perRow) {
    rows.push(items.slice(i, i + perRow));
  }

  // Every card - in a full row or a shorter trailing row - targets the exact same width.
  const gapRem = parseFloat(/gap-(\d+(?:\.\d+)?)/.exec(gap)?.[1] ?? '4') * 0.25;
  const share = `calc((100% - ${gapRem * (perRow - 1)}rem) / ${perRow})`;

  return (
    <div className={cn('flex flex-col', gap, className)}>
      {rows.map((row, rowIndex) => (
        <div key={rowIndex} className={cn('flex flex-wrap justify-center', gap)}>
          {row.map((item, indexInRow) => {
            const globalIndex = rowIndex * perRow + indexInRow;
            return (
              <div
                key={globalIndex}
                style={
                  columnCount === 1
                    ? { flexBasis: '100%', flexGrow: 1, maxWidth: '36rem' }
                    : { flexBasis: share, flexGrow: 1, maxWidth: share, minWidth: `min(${itemWidth}, 100%)` }
                }
              >
                {renderItem(item, globalIndex)}
              </div>
            );
          })}
        </div>
      ))}
    </div>
  );
}
