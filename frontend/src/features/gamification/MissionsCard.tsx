import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Check, CalendarDays, CalendarRange, Loader2, Sparkles } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useClaimMission, useMissions } from './useGamification';
import type { Mission } from './types';

export function MissionsCard() {
  const { t } = useTranslation('gamification');
  const { data: missions, isLoading } = useMissions();
  const claim = useClaimMission({
    onSuccess: () => toast.success(t('missions.claimSuccess')),
  });

  if (isLoading || !missions) {
    return null;
  }

  const daily = missions.filter((m) => m.type === 'daily');
  const weekly = missions.filter((m) => m.type === 'weekly');

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Sparkles className="h-4 w-4 text-primary" /> {t('missions.title')}
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-5">
        <MissionGroup
          label={t('missions.daily')}
          icon={<CalendarDays className="h-3.5 w-3.5" />}
          missions={daily}
          onClaim={(code) => claim.mutate(code)}
          claimingCode={claim.isPending ? claim.variables : undefined}
        />
        <MissionGroup
          label={t('missions.weekly')}
          icon={<CalendarRange className="h-3.5 w-3.5" />}
          missions={weekly}
          onClaim={(code) => claim.mutate(code)}
          claimingCode={claim.isPending ? claim.variables : undefined}
        />
      </CardContent>
    </Card>
  );
}

function MissionGroup({
  label,
  icon,
  missions,
  onClaim,
  claimingCode,
}: {
  label: string;
  icon: ReactNode;
  missions: Mission[];
  onClaim: (code: string) => void;
  claimingCode: string | undefined;
}) {
  const { t } = useTranslation('gamification');

  return (
    <div className="flex flex-col gap-2">
      <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
        {icon}
        {label}
      </p>
      {missions.map((mission) => (
        <div key={mission.code} className="flex items-center justify-between gap-3 rounded-lg border border-border p-3">
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-medium">{t(`missions.codes.${mission.code}`)}</p>
            <Progress value={Math.min(100, (mission.progress / mission.target) * 100)} className="mt-1.5" />
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <span className="text-xs whitespace-nowrap text-muted-foreground">+{mission.xp_reward} XP</span>
            {mission.claimed ? (
              <Badge variant="secondary">
                <Check className="h-3 w-3" />
              </Badge>
            ) : (
              <Button
                size="sm"
                disabled={!mission.completed || claimingCode === mission.code}
                onClick={() => onClaim(mission.code)}
              >
                {claimingCode === mission.code ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : t('missions.claim')}
              </Button>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
