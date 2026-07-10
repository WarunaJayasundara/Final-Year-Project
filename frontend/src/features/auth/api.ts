import { api } from '@/lib/api';
import type { AuthUser, Locale } from './types';

export async function fetchMe(): Promise<AuthUser | null> {
  const { data } = await api.get<{ user: AuthUser | null }>('/auth/me');
  return data.user;
}

export async function googleRedirectUrl(): Promise<string> {
  const { data } = await api.get<{ url: string }>('/auth/google/redirect');
  return data.url;
}

export async function adminLogin(email: string, password: string): Promise<AuthUser> {
  const { data } = await api.post<{ user: AuthUser }>('/admin/login', { email, password });
  return data.user;
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout');
}

export async function updateLocale(locale: Locale): Promise<AuthUser> {
  const { data } = await api.patch<{ user: AuthUser }>('/auth/locale', { locale });
  return data.user;
}
