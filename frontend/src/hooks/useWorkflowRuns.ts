import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { runsApi } from '../services/api';
import type { WorkflowRun } from '../types';

export function useWorkflowRuns(workflowId?: string, isNew?: boolean) {
  const [runs, setRuns] = useState<WorkflowRun[]>([]);
  const [runsLoading, setRunsLoading] = useState(false);
  const [runDetails, setRunDetails] = useState<WorkflowRun | null>(null);
  const [runDetailsLoading, setRunDetailsLoading] = useState(false);
  const [selectedRunId, setSelectedRunId] = useState<string | null>(null);

  const loadRuns = useCallback(async () => {
    if (isNew || !workflowId) return;
    setRunsLoading(true);
    try {
      const res = await runsApi.list({ workflow_id: workflowId });
      setRuns(res.data ?? []);
    } catch (err) {
      console.error('Failed to load runs', err);
    } finally {
      setRunsLoading(false);
    }
  }, [workflowId, isNew]);

  const loadRunDetails = useCallback(async (runId: string) => {
    setRunDetailsLoading(true);
    try {
      const run = await runsApi.get(runId);
      setRunDetails(run);
      setSelectedRunId(runId);
    } catch (err) {
      console.error('Failed to load run details', err);
    } finally {
      setRunDetailsLoading(false);
    }
  }, []);

  // WebSockets Real-Time Monitoring
  useEffect(() => {
    if (isNew || !workflowId) return;

    let ws: WebSocket | null = null;
    let reconnectTimeout: number;

    const connectWebSocket = async () => {
      try {
        const authRaw = localStorage.getItem('auth-storage');
        let token = '';
        let tenantId = '';
        if (authRaw) {
          const auth = JSON.parse(authRaw);
          token = auth?.state?.token || '';
          tenantId = auth?.state?.user?.tenant_id || '';
        }

        const res = await axios.get(`${import.meta.env.VITE_API_URL || 'http://localhost:8000'}/api/config/reverb`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'X-Tenant-ID': tenantId,
          }
        });
        const { key, host, port } = res.data;

        const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws';
        ws = new WebSocket(`${protocol}://${host}:${port}/app/${key}?protocol=7&client=js&version=4.4.0`);

        ws.onopen = () => {
          console.log('WebSocket Connected to Reverb');
          ws?.send(JSON.stringify({
            event: 'pusher:subscribe',
            data: { channel: `workflows.${workflowId}` }
          }));
        };

        ws.onmessage = (event) => {
          try {
            const parsed = JSON.parse(event.data);
            if (parsed.event && parsed.data) {
              const eventData = JSON.parse(parsed.data);
              
              if (parsed.event === 'step.started' || parsed.event === 'step.completed' || parsed.event === 'step.failed') {
                setRunDetails(prev => {
                  if (!prev || prev.id !== eventData.workflow_run_id) return prev;
                  const stepRuns = [...(prev.step_runs ?? [])];
                  const idx = stepRuns.findIndex(s => s.id === eventData.id);
                  if (idx !== -1) {
                    stepRuns[idx] = { ...stepRuns[idx], ...eventData };
                  } else {
                    stepRuns.push(eventData);
                  }
                  return { ...prev, step_runs: stepRuns };
                });
                loadRuns();
              } else if (parsed.event === 'workflow.started' || parsed.event === 'workflow.completed' || parsed.event === 'workflow.failed') {
                setRunDetails(prev => {
                  if (!prev || prev.id !== eventData.id) return prev;
                  return { ...prev, ...eventData };
                });
                loadRuns();
              }
            }
          } catch (e) {
            console.error('Error handling WebSocket message:', e);
          }
        };

        ws.onclose = () => {
          console.log('WebSocket Connection Closed. Reconnecting...');
          reconnectTimeout = window.setTimeout(connectWebSocket, 3000);
        };

        ws.onerror = (err) => {
          console.error('WebSocket Error:', err);
        };
      } catch (err) {
        console.error('Failed to initialize WebSocket:', err);
        reconnectTimeout = window.setTimeout(connectWebSocket, 5000);
      }
    };

    connectWebSocket();

    return () => {
      if (ws) ws.close();
      clearTimeout(reconnectTimeout);
    };
  }, [workflowId, isNew, loadRuns]);

  return {
    runs,
    runsLoading,
    runDetails,
    runDetailsLoading,
    selectedRunId,
    loadRuns,
    loadRunDetails,
    setSelectedRunId,
    setRunDetails,
  };
}
