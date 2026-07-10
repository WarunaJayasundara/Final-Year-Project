import type { ReactNode } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Brain,
  CalendarRange,
  Database,
  Gamepad2,
  LayoutDashboard,
  LineChart,
  LogOut,
  Menu,
  Shield,
  Sparkles,
  Trophy,
  User as UserIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { LanguageSwitcher } from './LanguageSwitcher';
import { ThemeToggle } from './ThemeToggle';
import { useCurrentUser, useLogout } from '@/features/auth/useAuth';

interface NavEntry {
  to: string;
  icon: ReactNode;
  label: string;
}

export function Navbar() {
  const { t } = useTranslation();
  const { data: user } = useCurrentUser();
  const logout = useLogout();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout.mutateAsync();
    navigate('/');
  };

  const initials = user?.name
    ?.split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  const studentNav: NavEntry[] = [
    { to: '/dashboard', icon: <LayoutDashboard className="h-4 w-4" />, label: t('nav.dashboard') },
    { to: '/test/daily', icon: <Sparkles className="h-4 w-4" />, label: t('nav.dailyPractice') },
    { to: '/test/practice', icon: <Brain className="h-4 w-4" />, label: t('nav.practice') },
    { to: '/study-plan', icon: <CalendarRange className="h-4 w-4" />, label: t('nav.studyPlan') },
    { to: '/games', icon: <Gamepad2 className="h-4 w-4" />, label: t('nav.games') },
  ];

  const adminNav: NavEntry[] = [
    { to: '/admin/dashboard', icon: <LayoutDashboard className="h-4 w-4" />, label: t('nav.dashboard') },
    { to: '/admin/questions', icon: <Brain className="h-4 w-4" />, label: t('nav.adminQuestions') },
    { to: '/admin/categories', icon: <Sparkles className="h-4 w-4" />, label: t('nav.adminCategories') },
    { to: '/admin/users', icon: <Shield className="h-4 w-4" />, label: t('nav.adminUsers') },
    { to: '/admin/psychometrics', icon: <LineChart className="h-4 w-4" />, label: t('nav.adminPsychometrics') },
    { to: '/admin/ai-questions', icon: <Sparkles className="h-4 w-4" />, label: t('nav.adminAiQuestions') },
    { to: '/admin/question-bank', icon: <Database className="h-4 w-4" />, label: t('nav.adminQuestionBank') },
  ];

  const activeNav = user?.role === 'user' ? studentNav : user?.role === 'admin' || user?.role === 'super_admin' ? adminNav : [];

  return (
    <header className="glass sticky top-0 z-40 border-b border-border/60 shadow-sm">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        <Link to={user ? '/dashboard' : '/'} className="flex items-center gap-2 font-semibold">
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary/70 text-primary-foreground shadow-md shadow-primary/30">
            <Brain className="h-4.5 w-4.5" />
          </span>
          <span className="text-lg tracking-tight">{t('appName')}</span>
        </Link>

        {activeNav.length > 0 && (
          <nav className="hidden items-center gap-1 md:flex">
            {activeNav.map((item) => (
              <NavItem key={item.to} {...item} />
            ))}
          </nav>
        )}

        <div className="flex items-center gap-2 sm:gap-3">
          <LanguageSwitcher />
          <ThemeToggle />

          {activeNav.length > 0 && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" className="md:hidden" aria-label="Open menu">
                  <Menu className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                {activeNav.map((item) => (
                  <DropdownMenuItem key={item.to} asChild>
                    <Link to={item.to}>
                      {item.icon} {item.label}
                    </Link>
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          )}

          {user ? (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button className="flex items-center gap-2 rounded-full outline-none ring-offset-2 focus-visible:ring-2 focus-visible:ring-ring">
                  <Avatar className="h-8 w-8 ring-2 ring-primary/20">
                    <AvatarImage src={user.avatar_url ?? undefined} alt={user.name} />
                    <AvatarFallback>{initials || <UserIcon className="h-4 w-4" />}</AvatarFallback>
                  </Avatar>
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <div className="px-2 py-1.5 text-sm">
                  <p className="font-medium">{user.name}</p>
                  <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                </div>
                <DropdownMenuSeparator />
                {user.role === 'user' && (
                  <>
                    <DropdownMenuItem asChild>
                      <Link to="/badges">
                        <Trophy className="h-4 w-4" /> {t('nav.badges')}
                      </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                      <Link to="/leaderboard">
                        <LineChart className="h-4 w-4" /> {t('nav.leaderboard')}
                      </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                      <Link to="/profile">
                        <UserIcon className="h-4 w-4" /> {t('nav.profile')}
                      </Link>
                    </DropdownMenuItem>
                  </>
                )}
                <DropdownMenuItem onClick={handleLogout}>
                  <LogOut className="h-4 w-4" /> {t('nav.logout')}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          ) : (
            <div className="flex items-center gap-2">
              <Button variant="ghost" asChild size="sm" className="hidden sm:inline-flex">
                <Link to="/admin/login">{t('nav.adminLogin')}</Link>
              </Button>
              <Button asChild size="sm">
                <Link to="/login">{t('nav.login')}</Link>
              </Button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}

function NavItem({ to, icon, label }: NavEntry) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        `flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
          isActive ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground'
        }`
      }
    >
      {icon}
      {label}
    </NavLink>
  );
}
