import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useCurrentUser, useUpdateLocale } from '@/features/auth/useAuth';

export function LanguageSwitcher() {
  const { i18n, t } = useTranslation();
  const { data: user } = useCurrentUser();
  const updateLocale = useUpdateLocale();

  const setLanguage = (lng: 'en' | 'si') => {
    if (user) {
      updateLocale.mutate(lng);
    } else {
      i18n.changeLanguage(lng);
    }
  };

  const current = i18n.language?.startsWith('si') ? 'si' : 'en';

  return (
    <div className="flex items-center gap-1 rounded-full border border-border bg-background p-0.5 text-xs">
      <Button
        type="button"
        size="sm"
        variant={current === 'en' ? 'default' : 'ghost'}
        className="h-6 rounded-full px-1.5 text-xs sm:px-2.5"
        onClick={() => setLanguage('en')}
      >
        {t('language.en')}
      </Button>
      <Button
        type="button"
        size="sm"
        variant={current === 'si' ? 'default' : 'ghost'}
        className="h-6 rounded-full px-1.5 text-xs sm:px-2.5"
        onClick={() => setLanguage('si')}
      >
        {t('language.si')}
      </Button>
    </div>
  );
}
