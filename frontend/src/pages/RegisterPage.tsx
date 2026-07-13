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
import { useCurrentUser, useRegister } from '@/features/auth/useAuth';

const schema = z
  .object({
    name: z.string().min(2).max(120),
    username: z
      .string()
      .min(3)
      .max(30)
      .regex(/^[a-zA-Z0-9_]+$/, 'auth:usernameFormat'),
    email: z.string().email(),
    date_of_birth: z.string().min(1),
    password: z.string().min(8),
    password_confirmation: z.string().min(8),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'auth:passwordMismatch',
  });

type FormValues = z.infer<typeof schema>;

export function RegisterPage() {
  const { t } = useTranslation('auth');
  const { data: user } = useCurrentUser();
  const registerUser = useRegister();
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
      await registerUser.mutateAsync(values);
      navigate('/placement');
    } catch {
      // surfaced via registerUser.isError below
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
            <CardTitle className="text-2xl">{t('registerTitle')}</CardTitle>
            <CardDescription>{t('registerSubtitle')}</CardDescription>
          </CardHeader>
          <CardContent>
            <form className="flex flex-col gap-4" onSubmit={onSubmit}>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="name">{t('fullName')}</Label>
                <Input id="name" autoComplete="name" {...register('name')} />
                {errors.name && <p className="text-xs text-destructive">{t('fieldRequired')}</p>}
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="username">{t('username')}</Label>
                <Input id="username" autoComplete="username" {...register('username')} />
                {errors.username && (
                  <p className="text-xs text-destructive">
                    {errors.username.message === 'auth:usernameFormat' ? t('usernameFormat') : t('fieldRequired')}
                  </p>
                )}
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="email">{t('email')}</Label>
                <Input id="email" type="email" autoComplete="email" {...register('email')} />
                {errors.email && <p className="text-xs text-destructive">{t('invalidEmail')}</p>}
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="date_of_birth">{t('dateOfBirth')}</Label>
                <Input id="date_of_birth" type="date" {...register('date_of_birth')} />
                {errors.date_of_birth && <p className="text-xs text-destructive">{t('fieldRequired')}</p>}
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="password">{t('password')}</Label>
                <Input id="password" type="password" autoComplete="new-password" {...register('password')} />
                {errors.password && <p className="text-xs text-destructive">{t('passwordTooShort')}</p>}
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="password_confirmation">{t('confirmPassword')}</Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  autoComplete="new-password"
                  {...register('password_confirmation')}
                />
                {errors.password_confirmation && (
                  <p className="text-xs text-destructive">{t('passwordMismatch')}</p>
                )}
              </div>

              {registerUser.isError && (
                <p className="text-sm text-destructive">
                  {(registerUser.error as { response?: { data?: { message?: string } } })?.response?.data?.message ??
                    t('registerFailed')}
                </p>
              )}

              <Button type="submit" className="w-full" disabled={registerUser.isPending}>
                {registerUser.isPending ? t('signingIn') : t('createAccount')}
              </Button>

              <p className="text-center text-sm text-muted-foreground">
                {t('alreadyHaveAccount')}{' '}
                <Link to="/login" className="font-medium text-primary underline-offset-4 hover:underline">
                  {t('signIn')}
                </Link>
              </p>
            </form>
          </CardContent>
        </Card>
      </FadeIn>
    </div>
  );
}
