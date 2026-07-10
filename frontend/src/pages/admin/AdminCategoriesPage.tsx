import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { useAdminCategories, useCreateCategory, useUpdateCategory } from '@/features/admin/useAdmin';
import type { AdminCategory } from '@/features/admin/types';

const EMPTY_FORM = { code: '', name_en: '', name_si: '', description_en: '', description_si: '' };

export function AdminCategoriesPage() {
  const { t } = useTranslation('admin');
  const { data: categories, isLoading } = useAdminCategories();
  const createCategory = useCreateCategory();
  const updateCategory = useUpdateCategory();

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<AdminCategory | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);

  const openCreate = () => {
    setEditing(null);
    setForm(EMPTY_FORM);
    setOpen(true);
  };

  const openEdit = (category: AdminCategory) => {
    setEditing(category);
    setForm({
      code: category.code,
      name_en: category.name_en,
      name_si: category.name_si,
      description_en: category.description_en ?? '',
      description_si: category.description_si ?? '',
    });
    setOpen(true);
  };

  const handleSave = async () => {
    setError(null);
    try {
      if (editing) {
        await updateCategory.mutateAsync({ id: editing.id, payload: form });
      } else {
        await createCategory.mutateAsync(form);
      }
      setOpen(false);
    } catch {
      setError(t('categories.saveError'));
    }
  };

  if (isLoading) {
    return <FullPageSpinner />;
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{t('categories.title')}</h1>
          <p className="text-muted-foreground">{t('categories.subtitle')}</p>
        </div>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild>
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> {t('categories.new')}
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{editing ? t('categories.edit') : t('categories.new')}</DialogTitle>
            </DialogHeader>
            <div className="flex flex-col gap-4">
              <div className="flex flex-col gap-1.5">
                <Label>{t('categories.code')}</Label>
                <Input value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="flex flex-col gap-1.5">
                  <Label>{t('categories.nameEn')}</Label>
                  <Input value={form.name_en} onChange={(e) => setForm({ ...form, name_en: e.target.value })} />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label>{t('categories.nameSi')}</Label>
                  <Input value={form.name_si} onChange={(e) => setForm({ ...form, name_si: e.target.value })} />
                </div>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="flex flex-col gap-1.5">
                  <Label>{t('categories.descriptionEn')}</Label>
                  <Textarea
                    value={form.description_en}
                    onChange={(e) => setForm({ ...form, description_en: e.target.value })}
                    rows={2}
                  />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label>{t('categories.descriptionSi')}</Label>
                  <Textarea
                    value={form.description_si}
                    onChange={(e) => setForm({ ...form, description_si: e.target.value })}
                    rows={2}
                  />
                </div>
              </div>
              {error && <p className="text-sm text-destructive">{error}</p>}
            </div>
            <DialogFooter>
              <Button onClick={handleSave} disabled={createCategory.isPending || updateCategory.isPending}>
                {t('categories.save')}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        {categories?.map((category) => (
          <Card key={category.id}>
            <CardContent className="flex items-start justify-between gap-4 p-5">
              <div>
                <p className="font-semibold">{category.name_en}</p>
                <p className="text-sm text-muted-foreground">{category.name_si}</p>
                <p className="mt-1 text-xs text-muted-foreground">{category.description_en}</p>
              </div>
              <Button size="icon-sm" variant="ghost" onClick={() => openEdit(category)}>
                <Pencil className="h-4 w-4" />
              </Button>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
