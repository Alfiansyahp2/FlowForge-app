import { useState, useCallback } from 'react';
import { scheduleApi, workflowApi } from '../services/api';
import type { Schedule, Workflow } from '../types';

export function useSchedules() {
  const [schedules, setSchedules] = useState<Schedule[]>([]);
  const [workflows, setWorkflows] = useState<Workflow[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const loadAll = useCallback(async () => {
    setIsLoading(true);
    try {
      const [schedRes, wfRes] = await Promise.all([
        scheduleApi.list(),
        workflowApi.list()
      ]);
      setSchedules(schedRes.data || []);
      setWorkflows(wfRes.data || []);
    } catch (err) {
      console.error('Failed to load data:', err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const triggerSchedule = async (id: string) => {
    try {
      await scheduleApi.trigger(id);
      return true;
    } catch (err) {
      return false;
    }
  };

  const toggleSchedule = async (id: string) => {
    try {
      await scheduleApi.toggle(id);
      await loadAll();
      return true;
    } catch (err) {
      return false;
    }
  };

  const deleteSchedule = async (id: string) => {
    try {
      await scheduleApi.delete(id);
      await loadAll();
      return true;
    } catch (err) {
      return false;
    }
  };

  return {
    schedules,
    workflows,
    isLoading,
    loadAll,
    triggerSchedule,
    toggleSchedule,
    deleteSchedule
  };
}
