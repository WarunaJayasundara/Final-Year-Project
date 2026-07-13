import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import {
  adminLogin,
  fetchMe,
  forgotPassword,
  googleRedirectUrl,
  logout,
  register,
  resetPassword,
  studentLogin,
  updateLocale,
} from './api';
import type { Locale, RegisterPayload } from './types';

export const AUTH_QUERY_KEY = ['auth', 'me'];

export function useCurrentUser() {
  return useQuery({
    queryKey: AUTH_QUERY_KEY,
    queryFn: fetchMe,
    staleTime: 60_000,
  });
}

export function useGoogleLogin() {
  return useMutation({
    mutationFn: googleRedirectUrl,
    onSuccess: (url) => {
      window.location.href = url;
    },
  });
}

export function useAdminLogin() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ email, password }: { email: string; password: string }) => adminLogin(email, password),
    onSuccess: (user) => {
      queryClient.setQueryData(AUTH_QUERY_KEY, user);
    },
  });
}

export function useStudentLogin() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ identifier, password }: { identifier: string; password: string }) =>
      studentLogin(identifier, password),
    onSuccess: (user) => {
      queryClient.setQueryData(AUTH_QUERY_KEY, user);
    },
  });
}

export function useRegister() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: RegisterPayload) => register(payload),
    onSuccess: (user) => {
      queryClient.setQueryData(AUTH_QUERY_KEY, user);
    },
  });
}

export function useForgotPassword() {
  return useMutation({
    mutationFn: (email: string) => forgotPassword(email),
  });
}

export function useResetPassword() {
  return useMutation({
    mutationFn: (payload: { token: string; email: string; password: string; password_confirmation: string }) =>
      resetPassword(payload),
  });
}

export function useLogout() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: logout,
    onSuccess: () => {
      queryClient.setQueryData(AUTH_QUERY_KEY, null);
      queryClient.clear();
    },
  });
}

export function useUpdateLocale() {
  const queryClient = useQueryClient();
  const { i18n } = useTranslation();
  return useMutation({
    mutationFn: (locale: Locale) => updateLocale(locale),
    onSuccess: (user, locale) => {
      queryClient.setQueryData(AUTH_QUERY_KEY, user);
      i18n.changeLanguage(locale);
    },
  });
}
