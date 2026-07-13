import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
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
import { useForgotPassword, useResetPassword } from '@/features/auth/useAuth';

const requestSchema = z.object({ email: z.string().email() });
type RequestValues = z.infer<typeof requestSchema>;

const resetSchema = z
  .object({
    password: z.string().min(8),
    password_confirmation: z.string().min(8),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'mismatch',
  });
type ResetValues = z.infer<typeof resetSchema>;

export function ForgotPasswordPage() {
  const [params] = useSearchParams();
  const token = params.get('token');
  const email = params.get('email');

  if (token && email) {
    return <ResetPasswordForm token={token} email={email} />;
  }

  return <RequestResetForm />;
}

function RequestResetForm() {
  const { t } = useTranslation('auth');
  const forgotPassword = useForgotPassword();
  const [sent, setSent] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RequestValues>({ resolver: zodResolver(requestSchema) });

  const onSubmit = handleSubmit(async (values) => {
    await forgotPassword.mutateAsync(values.email);
    setSent(true);
  });

  return (
    <div className="relative mx-auto flex max-w-md flex-col items-center gap-6 overflow-hidden py-16">
      <div className="gradient-orb -left-20 top-0 h-56 w-56 bg-primary/25" />
      <FadeIn className="flex w-full flex-col items-center gap-6">
        <HelaIQMark variant="compact" />
        <Card className="glass w-full shadow-xl">
          <CardHeader className="text-center">
            <CardTitle className="text-2xl">{t('forgotPasswordTitle')}</CardTitle>
            <CardDescription>{t('forgotPasswordSubtitle')}</CardDescription>
          </CardHeader>
          <CardContent>
            {sent ? (
              <p className="text-center text-sm text-muted-foreground">{t('resetLinkSent')}</p>
            ) : (
              <form className="flex flex-col gap-4" onSubmit={onSubmit}>
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="email">{t('email')}</Label>
                  <Input id="email" type="email" autoComplete="email" {...register('email')} />
                  {errors.email && <p className="text-xs text-destructive">{t('invalidEmail')}</p>}
                </div>
                <Button type="submit" className="w-full" disabled={forgotPassword.isPending}>
                  {forgotPassword.isPending ? t('sending') : t('sendResetLink')}
                </Button>
              </form>
            )}
            <p className="mt-4 text-center text-sm text-muted-foreground">
              <Link to="/login" className="font-medium text-primary underline-offset-4 hover:underline">
                {t('backToStudentLogin')}
              </Link>
            </p>
          </CardContent>
        </Card>
      </FadeIn>
    </div>
  );
}

function ResetPasswordForm({ token, email }: { token: string; email: string }) {
  const { t } = useTranslation('auth');
  const resetPassword = useResetPassword();
  const [done, setDone] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ResetValues>({ resolver: zodResolver(resetSchema) });

  const onSubmit = handleSubmit(async (values) => {
    await resetPassword.mutateAsync({ token, email, ...values });
    setDone(true);
  });

  return (
    <div className="relative mx-auto flex max-w-md flex-col items-center gap-6 overflow-hidden py-16">
      <div className="gradient-orb -left-20 top-0 h-56 w-56 bg-primary/25" />
      <FadeIn className="flex w-full flex-col items-center gap-6">
        <HelaIQMark variant="compact" />
        <Card className="glass w-full shadow-xl">
          <CardHeader className="text-center">
            <CardTitle className="text-2xl">{t('resetPasswordTitle')}</CardTitle>
            <CardDescription>{email}</CardDescription>
          </CardHeader>
          <CardContent>
            {done ? (
              <div className="flex flex-col items-center gap-3 text-center">
                <p className="text-sm text-muted-foreground">{t('passwordResetDone')}</p>
                <Link to="/login" className="font-medium text-primary underline-offset-4 hover:underline">
                  {t('signIn')}
                </Link>
              </div>
            ) : (
              <form className="flex flex-col gap-4" onSubmit={onSubmit}>
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="password">{t('newPassword')}</Label>
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
                {resetPassword.isError && <p className="text-sm text-destructive">{t('resetLinkInvalid')}</p>}
                <Button type="submit" className="w-full" disabled={resetPassword.isPending}>
                  {resetPassword.isPending ? t('sending') : t('resetPasswordAction')}
                </Button>
              </form>
            )}
          </CardContent>
        </Card>
      </FadeIn>
    </div>
  );
}
