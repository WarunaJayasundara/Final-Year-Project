import { api } from '@/lib/api';
import type { AuthUser, Locale, RegisterPayload } from './types';

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

export async function studentLogin(identifier: string, password: string): Promise<AuthUser> {
  const { data } = await api.post<{ user: AuthUser }>('/auth/login', { identifier, password });
  return data.user;
}

export async function register(payload: RegisterPayload): Promise<AuthUser> {
  const { data } = await api.post<{ user: AuthUser }>('/auth/register', payload);
  return data.user;
}

export async function forgotPassword(email: string): Promise<string> {
  const { data } = await api.post<{ message: string }>('/auth/forgot-password', { email });
  return data.message;
}

export async function resetPassword(payload: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<string> {
  const { data } = await api.post<{ message: string }>('/auth/reset-password', payload);
  return data.message;
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout');
}

export async function updateLocale(locale: Locale): Promise<AuthUser> {
  const { data } = await api.patch<{ user: AuthUser }>('/auth/locale', { locale });
  return data.user;
}
