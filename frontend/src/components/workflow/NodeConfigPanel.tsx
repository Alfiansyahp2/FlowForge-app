import type { Node } from '@xyflow/react';
import { Input } from '../ui/Input';
import { Label } from '../ui/Label';

export interface NodeConfigPanelProps {
  node: Node;
  setNodes: (updater: (nds: Node[]) => Node[]) => void;
  setSelectedNode: (updater: (s: Node | null) => Node | null) => void;
}

export function NodeConfigPanel({ node, setNodes, setSelectedNode }: NodeConfigPanelProps) {
  const nodeType = node.data?.nodeType as string;
  const config   = (node.data?.config ?? {}) as Record<string, unknown>;

  const update = (key: string, value: unknown) => {
    setNodes(nds => nds.map(n =>
      n.id === node.id
        ? { ...n, data: { ...n.data, config: { ...(n.data?.config as object), [key]: value } } }
        : n
    ));
    setSelectedNode(s => s ? { ...s, data: { ...s.data, config: { ...(s.data?.config as object), [key]: value } } } : s);
  };

  const field = (label: string, key: string, type = 'text', placeholder = '') => (
    <div key={key} className="space-y-1">
      <Label className="text-xs text-gray-500">{label}</Label>
      <Input
        type={type}
        value={String(config[key] ?? '')}
        onChange={e => update(key, type === 'number' ? Number(e.target.value) : e.target.value)}
        placeholder={placeholder}
        className="text-xs h-7"
      />
    </div>
  );

  const selectField = (label: string, key: string, options: string[]) => (
    <div key={key} className="space-y-1">
      <Label className="text-xs text-gray-500">{label}</Label>
      <select
        value={String(config[key] ?? options[0])}
        onChange={e => update(key, e.target.value)}
        className="w-full h-7 rounded-md border border-input bg-background px-2 text-xs shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
      >
        {options.map(o => <option key={o} value={o}>{o}</option>)}
      </select>
    </div>
  );

  switch (nodeType) {
    case 'http':
      return (
        <div className="space-y-2">
          {selectField('Method', 'method', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])}
          {field('URL', 'url', 'text', 'https://api.example.com/endpoint')}
          {field('Timeout (s)', 'timeout', 'number', '30')}
        </div>
      );
    case 'delay':
      return <div className="space-y-2">{field('Seconds', 'seconds', 'number', '5')}</div>;
    case 'condition':
      return <div className="space-y-2">{field('Expression', 'expression', 'text', 'true')}</div>;
    case 'script':
      return (
        <div className="space-y-1">
          <Label className="text-xs text-gray-500">Code</Label>
          <textarea
            value={String(config['code'] ?? '')}
            onChange={e => update('code', e.target.value)}
            placeholder="return true;"
            rows={5}
            className="w-full text-xs rounded-md border border-input bg-background px-2 py-1.5 shadow-sm focus:outline-none focus:ring-1 focus:ring-ring font-mono resize-none"
          />
        </div>
      );
    case 'notification':
      return <div className="space-y-2">{field('Message', 'message', 'text', 'Step completed')}</div>;
    default:
      return null;
  }
}
