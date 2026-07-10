import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Shield } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FadeIn } from '@/components/motion/FadeIn';
import { useAdminLogin, useCurrentUser } from '@/features/auth/useAuth';

const schema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
});

type FormValues = z.infer<typeof schema>;

export function AdminLoginPage() {
  const { t } = useTranslation('auth');
  const { data: user } = useCurrentUser();
  const adminLogin = useAdminLogin();
  const navigate = useNavigate();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  if (user?.role === 'admin' || user?.role === 'super_admin') {
    return <Navigate to="/admin/dashboard" replace />;
  }

  const onSubmit = handleSubmit(async (values) => {
    try {
      await adminLogin.mutateAsync(values);
      navigate('/admin/dashboard');
    } catch {
      // surfaced via adminLogin.isError below
    }
  });

  return (
    <div className="relative mx-auto flex max-w-md flex-col items-center gap-6 overflow-hidden py-16">
      <div className="gradient-orb -left-16 top-4 h-52 w-52 bg-foreground/10" />
      <div className="gradient-orb -right-20 bottom-0 h-48 w-48 bg-[color:var(--chart-1)]/15" />

      <FadeIn className="flex w-full flex-col items-center gap-6">
        <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-foreground text-background shadow-lg">
          <Shield className="h-6 w-6" />
        </span>

        <Card className="glass w-full shadow-xl">
          <CardHeader className="text-center">
            <CardTitle className="text-2xl">{t('adminLoginTitle')}</CardTitle>
            <CardDescription>{t('adminLoginSubtitle')}</CardDescription>
          </CardHeader>
          <CardContent>
            <form className="flex flex-col gap-4" onSubmit={onSubmit}>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="email">{t('email')}</Label>
                <Input id="email" type="email" autoComplete="username" {...register('email')} />
                {errors.email && <p className="text-xs text-destructive">{errors.email.message}</p>}
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="password">{t('password')}</Label>
                <Input id="password" type="password" autoComplete="current-password" {...register('password')} />
                {errors.password && <p className="text-xs text-destructive">{errors.password.message}</p>}
              </div>

              {adminLogin.isError && <p className="text-sm text-destructive">{t('invalidCredentials')}</p>}

              <Button type="submit" className="w-full" disabled={adminLogin.isPending}>
                {adminLogin.isPending ? t('signingIn') : t('signIn')}
              </Button>

              <p className="text-center text-sm text-muted-foreground">
                {t('notAnAdmin')}{' '}
                <Link to="/login" className="font-medium text-primary underline-offset-4 hover:underline">
                  {t('backToStudentLogin')}
                </Link>
              </p>
            </form>
          </CardContent>
        </Card>
      </FadeIn>
    </div>
  );
}
