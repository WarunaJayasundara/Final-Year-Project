import { useTranslation } from 'react-i18next';

export function Footer() {
  const { t } = useTranslation();

  return (
    <footer className="mt-16 border-t border-border bg-card">
      <div className="mx-auto flex max-w-6xl items-center justify-center px-4 pb-24 pt-8 sm:px-6 sm:pb-8">
        <p className="text-xs tracking-wide text-muted-foreground">{t('footer.copyright')}</p>
      </div>
    </footer>
  );
}
