import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { CountUp } from '@/components/motion/CountUp';

interface StatTileProps {
  icon: ReactNode;
  label: string;
  value: string;
  /** CSS color for the icon chip; defaults to the primary color. */
  accent?: string;
}

/** A single headline number. */
export function StatTile({ icon, label, value, accent = 'var(--primary)' }: StatTileProps) {
  return (
    <Card>
      <CardContent className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:gap-4 sm:p-5">
        <span
          className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg sm:h-11 sm:w-11 sm:rounded-xl"
          style={{ backgroundColor: `color-mix(in oklch, ${accent}, transparent 88%)`, color: accent }}
        >
          {icon}
        </span>
        <div className="min-w-0">
          <p className="text-xs leading-tight text-muted-foreground">{label}</p>
          <p className="mt-0.5 text-base font-semibold leading-tight sm:text-lg"><CountUp value={value} /></p>
        </div>
      </CardContent>
    </Card>
  );
}
