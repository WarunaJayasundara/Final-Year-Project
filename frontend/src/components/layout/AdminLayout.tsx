import type { ReactNode } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Database,
  FilePlus2,
  FolderTree,
  LayoutDashboard,
  Library,
  LineChart,
  LogOut,
  Menu,
  MessageSquareHeart,
  Microscope,
  Shield,
  Target,
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

interface AdminNavEntry {
  to: string;
  icon: ReactNode;
  label: string;
}

/**
 * Admin's own persistent-sidebar dashboard shell, separate from the public/
 * student MainLayout+Navbar - a 9-item admin nav genuinely doesn't fit a top
 * bar without either overflow-scrolling (the pre-HelaIQ-rebrand fix) or
 * feeling cramped, and dashboard-style apps conventionally use a left rail
 * for this many sections. Sidebar carries only branding + nav links (both
 * breakpoints); the top bar carries every contextual control - page title,
 * language, theme, admin profile/logout - present on desktop AND mobile so
 * nothing is hidden behind an extra tap on either. Below lg, the sidebar
 * itself collapses into a hamburger-triggered nav-links menu instead.
 */
export function AdminLayout() {
  const { t } = useTranslation('common');
  const { data: user } = useCurrentUser();
  const logout = useLogout();
  const navigate = useNavigate();
  const location = useLocation();

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

  const adminNav: AdminNavEntry[] = [
    { to: '/admin/dashboard', icon: <LayoutDashboard className="h-4 w-4" />, label: t('nav.dashboard') },
    { to: '/admin/questions', icon: <Target className="h-4 w-4" />, label: t('nav.adminQuestions') },
    { to: '/admin/categories', icon: <FolderTree className="h-4 w-4" />, label: t('nav.adminCategories') },
    { to: '/admin/users', icon: <Shield className="h-4 w-4" />, label: t('nav.adminUsers') },
    { to: '/admin/feedback', icon: <MessageSquareHeart className="h-4 w-4" />, label: t('nav.adminFeedback') },
    { to: '/admin/psychometrics', icon: <LineChart className="h-4 w-4" />, label: t('nav.adminPsychometrics') },
    { to: '/admin/ai-questions', icon: <FilePlus2 className="h-4 w-4" />, label: t('nav.adminAiQuestions') },
    { to: '/admin/knowledge-library', icon: <Library className="h-4 w-4" />, label: t('nav.adminKnowledgeLibrary') },
    { to: '/admin/question-bank', icon: <Database className="h-4 w-4" />, label: t('nav.adminQuestionBank') },
    { to: '/admin/ml-research', icon: <Microscope className="h-4 w-4" />, label: t('nav.adminMlResearch') },
  ];

  const activeItem = adminNav.find((item) => location.pathname.startsWith(item.to));
  const pageTitle = activeItem?.label ?? t('nav.admin');

  return (
    <div className="flex min-h-screen bg-background">
      {/* Desktop persistent sidebar: branding + nav only */}
      <aside className="hidden w-64 shrink-0 flex-col border-r border-border bg-card lg:flex">
        <Link to="/admin/dashboard" className="flex items-center gap-2 border-b border-border px-5 py-5">
          <HelaIQMark variant="compact" markClassName="h-7 w-7" />
          <div className="flex flex-col leading-tight">
            <span className="text-sm font-semibold">HelaIQ</span>
            <span className="text-xs text-muted-foreground">{t('nav.admin')}</span>
          </div>
        </Link>

        <nav className="scrollbar-none flex flex-1 flex-col gap-1 overflow-y-auto p-3">
          {adminNav.map((item) => (
            <SidebarItem key={item.to} {...item} />
          ))}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        {/* Top bar: every contextual control, on every breakpoint */}
        <header className="sticky top-0 z-40 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur-sm sm:px-6">
          <div className="flex items-center gap-2 lg:hidden">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label="Open menu">
                  <Menu className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" className="w-56">
                {adminNav.map((item) => (
                  <DropdownMenuItem key={item.to} asChild>
                    <Link to={item.to}>
                      {item.icon} {item.label}
                    </Link>
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
            <HelaIQMark variant="compact" markClassName="h-6 w-6" />
          </div>

          <h1 className="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{pageTitle}</h1>

          <div className="flex shrink-0 items-center gap-2">
            <LanguageSwitcher />
            <ThemeToggle />
            {user && (
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
                  <DropdownMenuItem onClick={handleLogout}>
                    <LogOut className="h-4 w-4" /> {t('nav.logout')}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            )}
          </div>
        </header>

        <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

function SidebarItem({ to, icon, label }: AdminNavEntry) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
          isActive ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground'
        }`
      }
    >
      {icon}
      {label}
    </NavLink>
  );
}
