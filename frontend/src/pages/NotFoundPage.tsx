import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

/** Shown for any address that matches no route, instead of a blank page. */
export function NotFoundPage() {
  const { t } = useTranslation('common');

  return (
    <div className="mx-auto flex max-w-md flex-col items-center gap-4 py-16 text-center">
      <p aria-hidden className="spectrum-text text-7xl font-bold tracking-tight">
        404
      </p>
      <h1 className="text-2xl font-semibold">{t('notFound.title')}</h1>
      <p className="text-muted-foreground">{t('notFound.body')}</p>
      <Button asChild>
        <Link to="/">{t('notFound.home')}</Link>
      </Button>
    </div>
  );
}
