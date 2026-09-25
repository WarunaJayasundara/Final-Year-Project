import type { ReactNode } from 'react';
import { PatternBackdrop, type PatternVariant } from '@/components/brand/PatternBackdrop';

interface PageHeaderProps {
  title: string;
  subtitle?: string;
  /** Faint themed pattern behind the header (see PatternBackdrop). */
  pattern?: PatternVariant;
  /** Buttons or links shown at the right (below the text on phones). */
  actions?: ReactNode;
}

/**
 * The shared page title block for student pages: title, one line of context, optional
 * actions, on a bordered surface with a faint pattern. One component means every page
 * opens with the same rhythm instead of each inventing its own header.
 */
export function PageHeader({ title, subtitle, pattern = 'steps', actions }: PageHeaderProps) {
  return (
    <header className="relative isolate overflow-hidden rounded-xl border border-border bg-card px-5 py-5 sm:px-6 sm:py-6">
      <PatternBackdrop variant={pattern} className="inset-y-0 right-0 w-3/4 sm:w-1/2" />
      <div className="relative flex max-w-3xl flex-wrap items-center gap-x-8 gap-y-4">
        <div className="min-w-0">
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          {subtitle && <p className="mt-1 text-muted-foreground">{subtitle}</p>}
        </div>
        {actions && <div className="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">{actions}</div>}
      </div>
    </header>
  );
}
