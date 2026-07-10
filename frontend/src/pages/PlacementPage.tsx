import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Brain } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { AdaptivePlacementRunner } from '@/features/sessions/AdaptivePlacementRunner';
import { useStartPlacement } from '@/features/sessions/useSessions';
import type { AdaptiveSessionData } from '@/features/sessions/types';

export function PlacementPage() {
  const { t } = useTranslation('sessions');
  const [session, setSession] = useState<AdaptiveSessionData | null>(null);
  const [started, setStarted] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const requested = useRef(false);

  const startPlacement = useStartPlacement({
    onSuccess: setSession,
    onError: () => setErrorMessage(t('placement.startError')),
  });

  useEffect(() => {
    if (!started || requested.current) return;
    requested.current = true;
    startPlacement.mutate();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [started]);

  if (session) {
    return <AdaptivePlacementRunner session={session} />;
  }

  if (started) {
    if (errorMessage) {
      return (
        <div className="mx-auto max-w-md py-16 text-center">
          <p className="text-destructive">{errorMessage}</p>
          <Button asChild className="mt-4">
            <Link to="/dashboard">{t('placement.backToDashboard')}</Link>
          </Button>
        </div>
      );
    }
    return <FullPageSpinner />;
  }

  return (
    <div className="mx-auto flex max-w-lg flex-col items-center gap-6 py-16 text-center">
      <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
        <Brain className="h-7 w-7" />
      </span>
      <Card className="w-full">
        <CardHeader>
          <CardTitle className="text-2xl">{t('placement.title')}</CardTitle>
          <CardDescription>{t('placement.description')}</CardDescription>
        </CardHeader>
        <CardContent>
          <Button size="lg" className="w-full" onClick={() => setStarted(true)}>
            {t('placement.start')}
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
