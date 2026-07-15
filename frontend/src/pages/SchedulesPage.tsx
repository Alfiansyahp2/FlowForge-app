import { useState, useEffect } from 'react';
import { usePermissions } from '../hooks/usePermissions';
import { useModalStore } from '../components/ui/Modal';
import { scheduleApi, workflowApi } from '../services/api';
import type { Schedule, Workflow } from '../types';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card';
import {
  Plus, Trash2, RefreshCw, Play, CheckCircle, XCircle,
  Clock, X, Pencil,
} from 'lucide-react';
import { ScheduleFormModal } from '../components/schedules/ScheduleFormModal';
import { useSchedules } from '../hooks/useSchedules';



// ── Main Page ─────────────────────────────────────────────────────────────────
export default function SchedulesPage() {
  const { can } = usePermissions();
  const { confirm, denied, success } = useModalStore();

  const { schedules, workflows, isLoading, loadAll, toggleSchedule, triggerSchedule, deleteSchedule } = useSchedules();
  const [showForm, setShowForm]   = useState(false);
  const [editTarget, setEditTarget] = useState<Schedule | null>(null);

  useEffect(() => { loadAll(); }, [loadAll]);

  const handleToggle = (schedule: Schedule) => {
    if (!can('edit schedules')) { denied('edit schedules'); return; }
    confirm({
      title: schedule.is_active ? 'Pause schedule' : 'Resume schedule',
      message: `"${schedule.name}" will be ${schedule.is_active ? 'paused and will no longer trigger automatically' : 'resumed and will trigger on its cron schedule'}.`,
      type: schedule.is_active ? 'warning' : 'info',
      icon: schedule.is_active ? 'warning' : 'run',
      confirmLabel: schedule.is_active ? 'Pause' : 'Resume',
      onConfirm: async () => {
        const ok = await toggleSchedule(schedule.id);
        if (ok) success(`Schedule "${schedule.name}" ${schedule.is_active ? 'paused' : 'resumed'}.`);
      },
    });
  };

  const handleTrigger = (schedule: Schedule) => {
    if (!can('execute workflows')) { denied('execute workflows'); return; }
    confirm({
      title: 'Trigger now',
      message: `Manually run "${schedule.name}" now? This will execute the workflow immediately regardless of schedule.`,
      type: 'info',
      icon: 'run',
      confirmLabel: 'Run now',
      onConfirm: async () => {
        const ok = await triggerSchedule(schedule.id);
        if (ok) success(`Schedule "${schedule.name}" triggered successfully.`);
      },
    });
  };

  const handleDelete = (schedule: Schedule) => {
    if (!can('delete schedules')) { denied('delete schedules'); return; }
    confirm({
      title: 'Delete schedule',
      message: `"${schedule.name}" will be permanently deleted and will no longer trigger automatically.`,
      type: 'danger',
      icon: 'delete',
      confirmLabel: 'Delete',
      onConfirm: async () => {
        const ok = await deleteSchedule(schedule.id);
        if (ok) success(`Schedule "${schedule.name}" deleted.`);
      },
    });
  };

  const openCreate = () => {
    if (!can('create schedules')) { denied('create schedules'); return; }
    setEditTarget(null);
    setShowForm(true);
  };

  const openEdit = (schedule: Schedule) => {
    if (!can('edit schedules')) { denied('edit schedules'); return; }
    setEditTarget(schedule);
    setShowForm(true);
  };

  return (
    <div className="flex flex-col gap-5">
      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {[
          { label: 'Total',   value: schedules.length,                           color: 'text-gray-800' },
          { label: 'Active',  value: schedules.filter(s => s.is_active).length,  color: 'text-green-700' },
          { label: 'Paused',  value: schedules.filter(s => !s.is_active).length, color: 'text-amber-600' },
        ].map(s => (
          <Card key={s.label} className="shadow-none border border-gray-200">
            <CardContent className="p-4">
              <p className="text-xs text-gray-500">{s.label}</p>
              <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Table */}
      <Card className="shadow-none border border-gray-200 overflow-hidden">
        <CardHeader className="py-3 px-5 border-b border-gray-100 bg-gray-50/60 flex flex-row items-center justify-between">
          <CardTitle className="text-sm font-semibold text-gray-600 uppercase tracking-wider">
            Schedules ({schedules.length})
          </CardTitle>
          {can('create schedules') && (
            <Button size="sm" onClick={openCreate}>
              <Plus className="w-4 h-4 mr-1.5" />New Schedule
            </Button>
          )}
        </CardHeader>

        {isLoading ? (
          <CardContent className="flex items-center justify-center py-16 text-gray-400">
            <RefreshCw className="w-5 h-5 animate-spin mr-2" />Loading schedules...
          </CardContent>
        ) : schedules.length === 0 ? (
          <CardContent className="flex flex-col items-center justify-center py-16 text-gray-400 gap-3">
            <Clock className="w-10 h-10 opacity-30" />
            <p className="text-sm">No schedules yet</p>
            {can('create schedules') && (
              <Button size="sm" variant="outline" onClick={openCreate}>
                <Plus className="w-4 h-4 mr-1.5" />Create your first schedule
              </Button>
            )}
          </CardContent>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50/40">
                  <th className="text-left px-5 py-3 font-medium text-gray-500">Name</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-500">Cron</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-500">Workflow</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-500">Next Run</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-500">Status</th>
                  <th className="text-right px-5 py-3 font-medium text-gray-500">Actions</th>
                </tr>
              </thead>
              <tbody>
                {schedules.map((schedule, i) => (
                  <tr key={schedule.id}
                    className={`border-b border-gray-50 hover:bg-gray-50/60 transition-colors ${i === schedules.length - 1 ? 'border-0' : ''}`}>

                    <td className="px-5 py-3">
                      <p className="font-medium text-gray-800">{schedule.name}</p>
                      {schedule.description && <p className="text-xs text-gray-400">{schedule.description}</p>}
                      <p className="text-xs text-gray-400 mt-0.5">{schedule.timezone}</p>
                    </td>

                    <td className="px-4 py-3">
                      <code className="text-xs bg-gray-100 text-indigo-700 px-2 py-0.5 rounded font-mono">
                        {schedule.cron_expression}
                      </code>
                    </td>

                    <td className="px-4 py-3 text-xs text-gray-600">
                      {(schedule as any).workflow?.name ?? '—'}
                    </td>

                    <td className="px-4 py-3 text-xs text-gray-500">
                      {schedule.next_run_at
                        ? new Date(schedule.next_run_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' })
                        : '—'}
                    </td>

                    <td className="px-4 py-3">
                      {schedule.is_active ? (
                        <span className="flex items-center gap-1 text-xs text-green-700">
                          <CheckCircle className="w-3.5 h-3.5" /> Active
                        </span>
                      ) : (
                        <span className="flex items-center gap-1 text-xs text-amber-600">
                          <XCircle className="w-3.5 h-3.5" /> Paused
                        </span>
                      )}
                    </td>

                    <td className="px-5 py-3">
                      <div className="flex items-center justify-end gap-1">
                        {can('execute workflows') && (
                          <Button size="icon" variant="ghost" onClick={() => handleTrigger(schedule)}
                            title="Trigger now" className="h-7 w-7 text-gray-400 hover:text-green-600 hover:bg-green-50">
                            <Play className="w-3.5 h-3.5" />
                          </Button>
                        )}
                        {can('edit schedules') && (
                          <>
                            <Button size="icon" variant="ghost" onClick={() => openEdit(schedule)}
                              title="Edit" className="h-7 w-7 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50">
                              <Pencil className="w-3.5 h-3.5" />
                            </Button>
                            <Button size="icon" variant="ghost" onClick={() => handleToggle(schedule)}
                              title={schedule.is_active ? 'Pause' : 'Resume'}
                              className={`h-7 w-7 transition-colors ${schedule.is_active
                                ? 'text-green-600 hover:text-amber-600 hover:bg-amber-50'
                                : 'text-gray-300 hover:text-green-600 hover:bg-green-50'}`}>
                              {schedule.is_active ? <CheckCircle className="w-3.5 h-3.5" /> : <XCircle className="w-3.5 h-3.5" />}
                            </Button>
                          </>
                        )}
                        {can('delete schedules') && (
                          <Button size="icon" variant="ghost" onClick={() => handleDelete(schedule)}
                            title="Delete" className="h-7 w-7 text-gray-400 hover:text-red-600 hover:bg-red-50">
                            <Trash2 className="w-3.5 h-3.5" />
                          </Button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {showForm && (
        <ScheduleFormModal
          initial={editTarget}
          workflows={workflows}
          onSave={() => { setShowForm(false); setEditTarget(null); loadAll(); }}
          onClose={() => { setShowForm(false); setEditTarget(null); }}
        />
      )}
    </div>
  );
}
