import type { ReactNode } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  CalendarRange,
  Database,
  FilePlus2,
  FolderTree,
  Gamepad2,
  LayoutDashboard,
  Library,
  LineChart,
  LogOut,
  Menu,
  Microscope,
  Shield,
  Target,
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
import { HelaIQMark } from '@/components/brand/HelaIQMark';
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

  // 5-item top nav (Dashboard / Learn / Practice / Games / Progress) - Daily
  // Practice and Mock Exam are no longer separate top-level items, they now
  // live as prominent quick-start cards inside the Practice page itself
  // (see PracticeTestPage.tsx) so the nav stays uncrowded.
  const studentNav: NavEntry[] = [
    { to: '/dashboard', icon: <LayoutDashboard className="h-4 w-4" />, label: t('nav.dashboard') },
    { to: '/study-notes', icon: <Library className="h-4 w-4" />, label: t('nav.learn') },
    { to: '/test/practice', icon: <Target className="h-4 w-4" />, label: t('nav.practice') },
    { to: '/games', icon: <Gamepad2 className="h-4 w-4" />, label: t('nav.games') },
    { to: '/study-plan', icon: <CalendarRange className="h-4 w-4" />, label: t('nav.progress') },
  ];

  const adminNav: NavEntry[] = [
    { to: '/admin/dashboard', icon: <LayoutDashboard className="h-4 w-4" />, label: t('nav.dashboard') },
    { to: '/admin/questions', icon: <Target className="h-4 w-4" />, label: t('nav.adminQuestions') },
    { to: '/admin/categories', icon: <FolderTree className="h-4 w-4" />, label: t('nav.adminCategories') },
    { to: '/admin/users', icon: <Shield className="h-4 w-4" />, label: t('nav.adminUsers') },
    { to: '/admin/psychometrics', icon: <LineChart className="h-4 w-4" />, label: t('nav.adminPsychometrics') },
    { to: '/admin/ai-questions', icon: <FilePlus2 className="h-4 w-4" />, label: t('nav.adminAiQuestions') },
    { to: '/admin/knowledge-library', icon: <Library className="h-4 w-4" />, label: t('nav.adminKnowledgeLibrary') },
    { to: '/admin/question-bank', icon: <Database className="h-4 w-4" />, label: t('nav.adminQuestionBank') },
    { to: '/admin/ml-research', icon: <Microscope className="h-4 w-4" />, label: t('nav.adminMlResearch') },
  ];

  const activeNav = user?.role === 'user' ? studentNav : user?.role === 'admin' || user?.role === 'super_admin' ? adminNav : [];
  const hasNav = activeNav.length > 0;

  return (
    <header className="sticky top-0 z-40 border-b border-border bg-background/95 backdrop-blur-sm">
      <div className="mx-auto grid h-16 max-w-6xl grid-cols-[auto_1fr_auto] items-center gap-3 px-4 sm:px-6">
        <Link to={user ? '/dashboard' : '/'} className="flex shrink-0 items-center font-semibold">
          <HelaIQMark variant="full" />
        </Link>

        <nav className="scrollbar-none hidden min-w-0 items-center justify-center gap-1 overflow-x-auto md:flex">
          {hasNav && activeNav.map((item) => <NavItem key={item.to} {...item} />)}
        </nav>

        <div className="hidden shrink-0 items-center gap-2 md:flex">
          <LanguageSwitcher />
          <ThemeToggle />

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
            <Button asChild size="sm">
              <Link to="/login">{t('nav.login')}</Link>
            </Button>
          )}
        </div>

        {/* Mobile: single hamburger menu containing everything - nav links,
            language, theme, profile/logout or login. Nothing is hidden,
            it's all one tap away instead of duplicated across two triggers. */}
        <div className="flex items-center justify-end gap-2 md:hidden">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon-sm" aria-label="Open menu">
                <Menu className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
              {user && (
                <>
                  <div className="flex items-center gap-2 px-2 py-1.5">
                    <Avatar className="h-8 w-8 ring-2 ring-primary/20">
                      <AvatarImage src={user.avatar_url ?? undefined} alt={user.name} />
                      <AvatarFallback>{initials || <UserIcon className="h-4 w-4" />}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0 text-sm">
                      <p className="truncate font-medium">{user.name}</p>
                      <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                    </div>
                  </div>
                  <DropdownMenuSeparator />
                </>
              )}

              {hasNav &&
                activeNav.map((item) => (
                  <DropdownMenuItem key={item.to} asChild>
                    <Link to={item.to}>
                      {item.icon} {item.label}
                    </Link>
                  </DropdownMenuItem>
                ))}

              {user?.role === 'user' && (
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

              {(hasNav || user) && <DropdownMenuSeparator />}

              <div className="flex items-center justify-between gap-2 px-2 py-1.5">
                <LanguageSwitcher />
                <ThemeToggle />
              </div>

              <DropdownMenuSeparator />

              {user ? (
                <DropdownMenuItem onClick={handleLogout}>
                  <LogOut className="h-4 w-4" /> {t('nav.logout')}
                </DropdownMenuItem>
              ) : (
                <DropdownMenuItem asChild>
                  <Link to="/login">{t('nav.login')}</Link>
                </DropdownMenuItem>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
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
        `flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
          isActive ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground'
        }`
      }
    >
      {icon}
      {label}
    </NavLink>
  );
}
