import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { useCurrentUser } from '@/features/auth/useAuth';
import { useAdminUsers, useCreateAdminUser, useDeleteUser, useUpdateUserRole } from '@/features/admin/useAdmin';
import type { AdminUser } from '@/features/admin/types';

export function AdminUsersPage() {
  const { t } = useTranslation('admin');
  const { data: me } = useCurrentUser();
  const [search, setSearch] = useState('');
  const { data: users, isLoading } = useAdminUsers(search);
  const updateRole = useUpdateUserRole();
  const deleteUser = useDeleteUser();
  const createAdmin = useCreateAdminUser();

  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', password: '', role: 'admin' as 'admin' | 'super_admin' });
  const [error, setError] = useState<string | null>(null);

  const isSuperAdmin = me?.role === 'super_admin';

  const handleCreate = async () => {
    setError(null);
    try {
      await createAdmin.mutateAsync(form);
      setOpen(false);
      setForm({ name: '', email: '', password: '', role: 'admin' });
    } catch {
      setError(t('users.createError'));
    }
  };

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{t('users.title')}</h1>
          <p className="text-muted-foreground">{t('users.subtitle')}</p>
        </div>
        {isSuperAdmin && (
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4" /> {t('users.new')}
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{t('users.createTitle')}</DialogTitle>
              </DialogHeader>
              <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-1.5">
                  <Label>{t('users.name')}</Label>
                  <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label>{t('users.email')}</Label>
                  <Input
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                  />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label>{t('users.password')}</Label>
                  <Input
                    type="password"
                    value={form.password}
                    onChange={(e) => setForm({ ...form, password: e.target.value })}
                  />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label>{t('users.role')}</Label>
                  <Select value={form.role} onValueChange={(v) => setForm({ ...form, role: v as 'admin' | 'super_admin' })}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="admin">{t('users.roles.admin')}</SelectItem>
                      <SelectItem value="super_admin">{t('users.roles.super_admin')}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}
              </div>
              <DialogFooter>
                <Button onClick={handleCreate} disabled={createAdmin.isPending}>
                  {t('users.createSubmit')}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        )}
      </div>

      <Input
        placeholder={t('users.search')}
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        className="max-w-sm"
      />

      {isLoading || !users ? (
        <FullPageSpinner />
      ) : (
        <div className="rounded-lg border border-border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('users.table.name')}</TableHead>
                <TableHead>{t('users.table.email')}</TableHead>
                <TableHead>{t('users.table.role')}</TableHead>
                <TableHead>{t('users.table.progress')}</TableHead>
                <TableHead>{t('users.table.joined')}</TableHead>
                {isSuperAdmin && <TableHead className="text-right">{t('users.table.actions')}</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {users.data.map((user) => (
                <TableRow key={user.id}>
                  <TableCell>{user.name}</TableCell>
                  <TableCell>{user.email}</TableCell>
                  <TableCell>
                    {isSuperAdmin && user.id !== me?.id ? (
                      <Select
                        value={user.role}
                        onValueChange={(role) => updateRole.mutate({ id: user.id, role: role as AdminUser['role'] })}
                      >
                        <SelectTrigger className="h-8 w-36">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="user">{t('users.roles.user')}</SelectItem>
                          <SelectItem value="admin">{t('users.roles.admin')}</SelectItem>
                          <SelectItem value="super_admin">{t('users.roles.super_admin')}</SelectItem>
                        </SelectContent>
                      </Select>
                    ) : (
                      <Badge variant="secondary">{t(`users.roles.${user.role}`)}</Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    {user.role === 'user' ? (
                      <div className="flex flex-col gap-1 text-xs">
                        <Badge variant={user.placement_completed_at ? 'secondary' : 'outline'}>
                          {user.placement_completed_at
                            ? (user.current_level?.name_en ?? t('users.table.placementDone'))
                            : t('users.table.placementPending')}
                        </Badge>
                        <span className="text-muted-foreground">
                          {t('users.table.dailySessions', { count: user.daily_sessions_completed_count })}
                        </span>
                      </div>
                    ) : (
                      <span className="text-muted-foreground">-</span>
                    )}
                  </TableCell>
                  <TableCell>{new Date(user.created_at).toLocaleDateString()}</TableCell>
                  {isSuperAdmin && (
                    <TableCell className="text-right">
                      {user.id !== me?.id && (
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          onClick={() => {
                            if (confirm(t('users.deleteConfirm', { name: user.name }))) deleteUser.mutate(user.id);
                          }}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      )}
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  );
}
