import { AlertCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface ErrorStateProps {
  /** Defaults to the translated "We couldn't load this". */
  title?: string;
  /** Defaults to the translated connection hint. Pass `null` to hide it. */
  description?: string | null;
  onRetry?: () => void;
  className?: string;
}

/** What a page shows when its data could not be loaded: says so in the user's language and offers a retry. */
export function ErrorState({ title, description, onRetry, className }: ErrorStateProps) {
  const { t } = useTranslation('common');

  return (
    <div
      role="alert"
      className={cn('mx-auto flex max-w-md flex-col items-center gap-2 rounded-lg border border-border bg-card px-6 py-10 text-center', className)}
    >
      <AlertCircle className="h-6 w-6 text-muted-foreground" />
      <p className="text-sm font-medium text-foreground">{title ?? t('errorState.defaultTitle')}</p>
      {description !== null && <p className="text-sm text-muted-foreground">{description ?? t('errorState.defaultDescription')}</p>}
      {onRetry && (
        <Button variant="outline" size="sm" className="mt-2" onClick={onRetry}>
          {t('actions.retry')}
        </Button>
      )}
    </div>
  );
}
