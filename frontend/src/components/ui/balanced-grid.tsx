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

/**
 * Splits `items` into evenly-sized, centered rows instead of leaving an
 * orphaned tail on a fixed `grid-cols-N`. For N items and a preferred
 * column count C: rows = ceil(N/C), then each row actually gets
 * ceil(N/rows) items (the balancing step) - e.g. 5 items/preferred 4
 * becomes 3+2 (not 4+1), 7 items/preferred 4 becomes 4+3, 8 items/
 * preferred 4 becomes 4+4. Plain CSS grid can't do this because it has no
 * way to know the total item count while laying out a single row.
 */
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

  // Every card - in a full row or a shorter trailing row - targets the exact same width: the width
  // `perRow` equal columns would take up in the row's own available space. A full row's cards fill it
  // edge to edge by construction (perRow * share + gaps = 100%); a shorter trailing row's cards come out
  // the same size as the row above it and simply leave the leftover space as centered margin, instead of
  // being capped to a smaller, unrelated `itemWidth` and looking like a different card size.
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
