import { FormEvent, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { categoriesApi } from '@/api/resources';

export function CategoriesPage() {
  const qc = useQueryClient();
  const [name, setName] = useState('');
  const [parentId, setParentId] = useState('');

  const { data: categories } = useQuery({ queryKey: ['categories'], queryFn: categoriesApi.list });

  const createMutation = useMutation({
    mutationFn: () => categoriesApi.create({ name, parentId: parentId || null }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['categories'] });
      setName('');
      setParentId('');
    },
  });
  const removeMutation = useMutation({
    mutationFn: (id: string) => categoriesApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['categories'] }),
  });

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (name.trim()) createMutation.mutate();
  }

  const roots = categories?.filter((c) => !c.parentId) ?? [];
  const childrenOf = (id: string) => categories?.filter((c) => c.parentId === id) ?? [];

  return (
    <div className="max-w-2xl space-y-6">
      <h1 className="text-2xl font-bold">Catégories</h1>

      <form onSubmit={onSubmit} className="flex flex-wrap items-end gap-2 rounded-xl border bg-white p-4">
        <label className="flex-1">
          <span className="mb-1 block text-sm font-medium text-slate-700">Nom</span>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Courses, Salaire…"
            className="w-full rounded border border-slate-300 px-3 py-2"
          />
        </label>
        <label>
          <span className="mb-1 block text-sm font-medium text-slate-700">Parent</span>
          <select
            value={parentId}
            onChange={(e) => setParentId(e.target.value)}
            className="rounded border border-slate-300 px-3 py-2"
          >
            <option value="">Aucun</option>
            {roots.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </label>
        <button className="rounded bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
          Ajouter
        </button>
      </form>

      <ul className="divide-y rounded-xl border bg-white">
        {roots.map((c) => (
          <li key={c.id} className="p-3">
            <div className="flex items-center justify-between">
              <span className="font-medium">{c.name}</span>
              <button
                onClick={() => removeMutation.mutate(c.id)}
                className="text-xs text-slate-400 hover:text-red-600"
              >
                Supprimer
              </button>
            </div>
            {childrenOf(c.id).length > 0 && (
              <ul className="mt-2 space-y-1 pl-4 text-sm text-slate-600">
                {childrenOf(c.id).map((child) => (
                  <li key={child.id} className="flex items-center justify-between">
                    <span>↳ {child.name}</span>
                    <button
                      onClick={() => removeMutation.mutate(child.id)}
                      className="text-xs text-slate-400 hover:text-red-600"
                    >
                      Supprimer
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </li>
        ))}
        {roots.length === 0 && <li className="p-6 text-center text-slate-400">Aucune catégorie.</li>}
      </ul>
    </div>
  );
}
