import { useState } from 'react';
import { scheduleApi } from '../../services/api';
import type { Schedule, Workflow } from '../../types';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { Label } from '../ui/Label';
import { RefreshCw, X } from 'lucide-react';

const CRON_PRESETS = [
  { label: 'Every minute',    value: '* * * * *' },
  { label: 'Every 5 minutes', value: '*/5 * * * *' },
  { label: 'Every hour',      value: '0 * * * *' },
  { label: 'Every day at midnight', value: '0 0 * * *' },
  { label: 'Every day at 9am',      value: '0 9 * * *' },
  { label: 'Every Monday 9am',      value: '0 9 * * 1' },
  { label: 'Every month (1st)',     value: '0 0 1 * *' },
];

export interface ScheduleFormProps {
  initial?: Schedule | null;
  workflows: Workflow[];
  onSave: () => void;
  onClose: () => void;
}

export function ScheduleFormModal({ initial, workflows, onSave, onClose }: ScheduleFormProps) {
  const [name, setCronName]         = useState(initial?.name ?? '');
  const [description, setDescription] = useState(initial?.description ?? '');
  const [workflowId, setWorkflowId] = useState(initial?.workflow_id ?? '');
  const [cron, setCron]             = useState(initial?.cron_expression ?? '');
  const [timezone, setTimezone]     = useState(initial?.timezone ?? 'UTC');
  const [saving, setSaving]         = useState(false);
  const [errors, setErrors]         = useState<Record<string, string>>({});
  const isEdit = !!initial;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    const errs: Record<string, string> = {};
    if (!name.trim())    errs.name = 'Name is required';
    if (!workflowId)     errs.workflow_id = 'Workflow is required';
    if (!cron.trim())    errs.cron_expression = 'Cron expression is required';
    if (Object.keys(errs).length) { setErrors(errs); return; }

    setSaving(true);
    try {
      if (isEdit) {
        await scheduleApi.update(initial!.id, { name, description, cron_expression: cron, timezone } as any);
      } else {
        const selectedWorkflow = workflows.find(w => w.id === workflowId);
        const workflowVersionId = selectedWorkflow?.current_version_id;

        await scheduleApi.create({
          name,
          description,
          workflow_id: workflowId,
          workflow_version_id: workflowVersionId,
          cron_expression: cron,
          timezone
        } as any);
      }
      onSave();
    } catch (err: unknown) {
      const e = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } };
      const api = e.response?.data?.errors ?? {};
      const mapped: Record<string, string> = {};
      for (const [f, msgs] of Object.entries(api)) mapped[f] = Array.isArray(msgs) ? msgs[0] : String(msgs);
      if (!Object.keys(mapped).length && e.response?.data?.message) mapped.general = e.response.data.message;
      setErrors(mapped);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg">
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h2 className="text-lg font-semibold">{isEdit ? 'Edit Schedule' : 'Create Schedule'}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600"><X className="w-5 h-5" /></button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="px-6 py-4 space-y-4">
            {errors.general && <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">{errors.general}</p>}

            <div className="space-y-1">
              <Label htmlFor="sc-name">Name</Label>
              <Input id="sc-name" value={name} onChange={e => setCronName(e.target.value)} placeholder="Daily Backup" disabled={saving} />
              {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
            </div>

            {!isEdit && (
              <div className="space-y-1">
                <Label htmlFor="sc-workflow">Workflow</Label>
                <select
                  id="sc-workflow"
                  value={workflowId}
                  onChange={e => setWorkflowId(e.target.value)}
                  disabled={saving}
                  className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                >
                  <option value="">Select a workflow...</option>
                  {workflows
                    .filter(w => w.current_version_id) // Only show workflows with versions
                    .map(w => (
                      <option key={w.id} value={w.id}>{w.name}</option>
                  ))}
                </select>
                {errors.workflow_id && <p className="text-xs text-red-600">{errors.workflow_id}</p>}
                {workflows.filter(w => w.current_version_id).length === 0 && (
                  <p className="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg p-2 mt-1">
                    ⚠️ No saved workflows found. Please save a workflow first to create a schedule.
                  </p>
                )}
              </div>
            )}

            <div className="space-y-1">
              <Label htmlFor="sc-cron">Cron Expression</Label>
              <Input id="sc-cron" value={cron} onChange={e => setCron(e.target.value)}
                placeholder="0 9 * * 1" disabled={saving} className="font-mono" />
              {errors.cron_expression && <p className="text-xs text-red-600">{errors.cron_expression}</p>}

              <div className="flex flex-wrap gap-1.5 mt-2">
                {CRON_PRESETS.map(p => (
                  <button
                    key={p.value}
                    type="button"
                    onClick={() => setCron(p.value)}
                    className={`text-xs px-2 py-1 rounded-md border transition-colors ${
                      cron === p.value
                        ? 'bg-indigo-100 text-indigo-700 border-indigo-300'
                        : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'
                    }`}
                  >
                    {p.label}
                  </button>
                ))}
              </div>
              {cron && (
                <p className="text-xs text-gray-400 mt-1 font-mono">
                  Pattern: <span className="text-indigo-600">{cron}</span>
                </p>
              )}
            </div>

            <div className="space-y-1">
              <Label htmlFor="sc-tz">Timezone</Label>
              <select
                id="sc-tz"
                value={timezone}
                onChange={e => setTimezone(e.target.value)}
                disabled={saving}
                className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
              >
                <option value="UTC">UTC</option>
                <option value="Asia/Jakarta">Asia/Jakarta (WIB)</option>
                <option value="Asia/Makassar">Asia/Makassar (WITA)</option>
                <option value="Asia/Jayapura">Asia/Jayapura (WIT)</option>
                <option value="America/New_York">America/New_York</option>
                <option value="America/Los_Angeles">America/Los_Angeles</option>
                <option value="Europe/London">Europe/London</option>
                <option value="Europe/Paris">Europe/Paris</option>
              </select>
            </div>

            <div className="space-y-1">
              <Label htmlFor="sc-desc">Description <span className="text-gray-400">(optional)</span></Label>
              <Input id="sc-desc" value={description} onChange={e => setDescription(e.target.value)}
                placeholder="What does this schedule do?" disabled={saving} />
            </div>
          </div>

          <div className="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
            <Button type="button" variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
            <Button type="submit" disabled={saving}>
              {saving ? <><RefreshCw className="w-4 h-4 animate-spin mr-2" />Saving...</> : isEdit ? 'Save Changes' : 'Create Schedule'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
