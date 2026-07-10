import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { SessionRunner } from '@/features/sessions/SessionRunner';
import { useStartDaily } from '@/features/sessions/useSessions';
import type { SessionData } from '@/features/sessions/types';

export function DailyTestPage() {
  const { t } = useTranslation('sessions');
  const [session, setSession] = useState<SessionData | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const requested = useRef(false);

  const startDaily = useStartDaily({
    onSuccess: setSession,
    onError: (error) => {
      const message =
        (error as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        t('daily.startError');
      setErrorMessage(message);
    },
  });

  useEffect(() => {
    if (requested.current) return;
    requested.current = true;
    startDaily.mutate(undefined);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (session) {
    return <SessionRunner session={session} />;
  }

  if (errorMessage) {
    return (
      <div className="mx-auto max-w-md py-16 text-center">
        <p className="text-destructive">{errorMessage}</p>
        <Button asChild className="mt-4">
          <Link to="/dashboard">{t('daily.backToDashboard')}</Link>
        </Button>
      </div>
    );
  }

  return <FullPageSpinner />;
}
