import { useTranslation } from 'react-i18next';

export function Footer() {
  const { t } = useTranslation();

  return (
    <footer className="mt-16 border-t border-border/60 bg-background/60 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-center px-4 py-8 sm:px-6">
        <p className="text-xs tracking-wide text-muted-foreground">{t('footer.copyright')}</p>
      </div>
    </footer>
  );
}
