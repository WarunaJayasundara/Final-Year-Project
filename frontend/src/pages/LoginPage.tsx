import type { SVGProps } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FadeIn } from '@/components/motion/FadeIn';
import { HelaIQMark } from '@/components/brand/HelaIQMark';
import { useCurrentUser, useGoogleLogin, useStudentLogin } from '@/features/auth/useAuth';

const schema = z.object({
  identifier: z.string().min(1),
  password: z.string().min(1),
});
type FormValues = z.infer<typeof schema>;

export function LoginPage() {
  const { t } = useTranslation('auth');
  const { data: user } = useCurrentUser();
  const googleLogin = useGoogleLogin();
  const studentLogin = useStudentLogin();
  const navigate = useNavigate();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  if (user?.role === 'user') {
    return <Navigate to="/dashboard" replace />;
  }

  const onSubmit = handleSubmit(async (values) => {
    try {
      await studentLogin.mutateAsync(values);
      navigate('/dashboard');
    } catch {
      // surfaced via studentLogin.isError below
    }
  });

  return (
    <div className="relative mx-auto flex max-w-md flex-col items-center gap-6 overflow-hidden py-16">
      <div className="gradient-orb -left-20 top-0 h-56 w-56 bg-primary/25" />
      <div className="gradient-orb -right-16 bottom-0 h-48 w-48 bg-[color:var(--chart-2)]/20" />

      <FadeIn className="flex w-full flex-col items-center gap-6">
        <HelaIQMark variant="compact" />

        <Card className="glass w-full shadow-xl">
          <CardHeader className="text-center">
            <CardTitle className="text-2xl">{t('loginTitle')}</CardTitle>
            <CardDescription>{t('loginSubtitle')}</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <Button
              size="lg"
              variant="outline"
              className="w-full"
              onClick={() => googleLogin.mutate()}
              disabled={googleLogin.isPending}
            >
              <GoogleIcon className="h-4 w-4" />
              {googleLogin.isPending ? t('signingIn') : t('continueWithGoogle')}
            </Button>

            <div className="flex items-center gap-3 text-xs text-muted-foreground">
              <span className="h-px flex-1 bg-border" />
              {t('or')}
              <span className="h-px flex-1 bg-border" />
            </div>

            <form className="flex flex-col gap-4" onSubmit={onSubmit}>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="identifier">{t('emailOrUsername')}</Label>
                <Input id="identifier" autoComplete="username" {...register('identifier')} />
                {errors.identifier && <p className="text-xs text-destructive">{t('fieldRequired')}</p>}
              </div>
              <div className="flex flex-col gap-1.5">
                <div className="flex items-center justify-between">
                  <Label htmlFor="password">{t('password')}</Label>
                  <Link to="/forgot-password" className="text-xs text-muted-foreground hover:text-primary hover:underline">
                    {t('forgotPassword')}
                  </Link>
                </div>
                <Input id="password" type="password" autoComplete="current-password" {...register('password')} />
                {errors.password && <p className="text-xs text-destructive">{t('fieldRequired')}</p>}
              </div>

              {studentLogin.isError && (
                <p className="text-sm text-destructive">
                  {(studentLogin.error as { response?: { data?: { message?: string } } })?.response?.data?.message ??
                    t('invalidCredentials')}
                </p>
              )}

              <Button type="submit" size="lg" className="w-full" disabled={studentLogin.isPending}>
                {studentLogin.isPending ? t('signingIn') : t('signIn')}
              </Button>
            </form>

            <p className="text-center text-sm text-muted-foreground">
              {t('noAccount')}{' '}
              <Link to="/register" className="font-medium text-primary underline-offset-4 hover:underline">
                {t('createAccount')}
              </Link>
            </p>

            <p className="text-center text-xs text-muted-foreground">
              {t('notAStudent')}{' '}
              <Link to="/admin/login" className="font-medium text-foreground/70 underline-offset-4 hover:text-primary hover:underline">
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
