export type UserRole = 'super_admin' | 'admin' | 'user';
export type Locale = 'en' | 'si';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  role: UserRole;
  locale: Locale;
  current_level_id: number | null;
  placement_completed_at: string | null;
}
