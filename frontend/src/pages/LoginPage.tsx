import type { SVGProps } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Brain } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FadeIn } from '@/components/motion/FadeIn';
import { useCurrentUser, useGoogleLogin } from '@/features/auth/useAuth';

export function LoginPage() {
  const { t } = useTranslation('auth');
  const { data: user } = useCurrentUser();
  const googleLogin = useGoogleLogin();

  if (user?.role === 'user') {
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <div className="relative mx-auto flex max-w-md flex-col items-center gap-6 overflow-hidden py-16">
      <div className="gradient-orb -left-20 top-0 h-56 w-56 bg-primary/25" />
      <div className="gradient-orb -right-16 bottom-0 h-48 w-48 bg-[color:var(--chart-2)]/20" />

      <FadeIn className="flex w-full flex-col items-center gap-6">
        <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-primary/70 text-primary-foreground shadow-lg shadow-primary/30">
          <Brain className="h-6 w-6" />
        </span>

        <Card className="glass w-full shadow-xl">
          <CardHeader className="text-center">
            <CardTitle className="text-2xl">{t('loginTitle')}</CardTitle>
            <CardDescription>{t('loginSubtitle')}</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <Button
              size="lg"
              className="w-full"
              onClick={() => googleLogin.mutate()}
              disabled={googleLogin.isPending}
            >
              <GoogleIcon className="h-4 w-4" />
              {googleLogin.isPending ? t('signingIn') : t('continueWithGoogle')}
            </Button>

            <p className="text-center text-sm text-muted-foreground">
              {t('notAStudent')}{' '}
              <Link to="/admin/login" className="font-medium text-primary underline-offset-4 hover:underline">
                {t('adminLoginTitle')}
              </Link>
            </p>
          </CardContent>
        </Card>
      </FadeIn>
    </div>
  );
}

function GoogleIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" {...props}>
      <path
        fill="#4285F4"
        d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.08 3.56-5.14 3.56-8.82z"
      />
      <path
        fill="#34A853"
        d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"
      />
      <path
        fill="#FBBC05"
        d="M5.27 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62H1.29A11.96 11.96 0 000 12c0 1.93.46 3.76 1.29 5.38l3.98-3.09z"
      />
      <path
        fill="#EA4335"
        d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"
      />
    </svg>
  );
}
