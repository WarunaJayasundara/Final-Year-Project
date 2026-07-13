export type UserRole = 'super_admin' | 'admin' | 'user';
export type Locale = 'en' | 'si';

export interface AuthUser {
  id: number;
  name: string;
  username: string | null;
  email: string;
  avatar_url: string | null;
  auth_provider: 'google' | 'password';
  role: UserRole;
  locale: Locale;
  current_level_id: number | null;
  placement_completed_at: string | null;
}

export interface RegisterPayload {
  name: string;
  username: string;
  email: string;
  date_of_birth: string;
  password: string;
  password_confirmation: string;
}
