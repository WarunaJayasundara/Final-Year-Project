import { useTranslation } from 'react-i18next';
import { Mail, ShieldCheck, User as UserIcon } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { FeedbackForm } from '@/features/feedback/FeedbackForm';
import { useCurrentUser } from '@/features/auth/useAuth';

export function ProfilePage() {
  const { t } = useTranslation('profile');
  const { data: user } = useCurrentUser();

  const initials = user?.name
    ?.split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('title')}</h1>
        <p className="text-muted-foreground">{t('subtitle')}</p>
      </div>

      {user && (
        <Card>
          <CardContent className="flex flex-wrap items-center gap-4 p-5">
            <Avatar className="h-14 w-14 ring-2 ring-primary/20">
              <AvatarImage src={user.avatar_url ?? undefined} alt={user.name} />
              <AvatarFallback>{initials || <UserIcon className="h-5 w-5" />}</AvatarFallback>
            </Avatar>
            <div className="flex flex-col gap-1">
              <p className="text-lg font-semibold">{user.name}</p>
              {user.username && <p className="text-sm text-muted-foreground">@{user.username}</p>}
              <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                <span className="flex items-center gap-1">
                  <Mail className="h-3.5 w-3.5" /> {user.email}
                </span>
                <Badge variant="outline" className="flex items-center gap-1">
                  <ShieldCheck className="h-3 w-3" />
                  {user.auth_provider === 'google' ? t('authGoogle') : t('authPassword')}
                </Badge>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      <FeedbackForm />
    </div>
  );
}
