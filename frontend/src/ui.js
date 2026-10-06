export const STATUS_META = {
  normal: { label: '正常', cls: 'badge-normal' },
  quarantined: { label: '隔离中', cls: 'badge-quarantined' },
  frozen: { label: '复检未通过·冻结', cls: 'badge-frozen' },
  released: { label: '已解除隔离', cls: 'badge-released' },
  discarded: { label: '已废弃', cls: 'badge-discarded' },
};

export const EXPOSURE_META = {
  pending: { label: '待判定', cls: 'badge-pending' },
  exposed: { label: '判定受影响', cls: 'badge-quarantined' },
  cleared: { label: '确认不受影响', cls: 'badge-released' },
};

export const REPORT_STATUS_META = {
  open: { label: '待负责人判定', cls: 'badge-pending' },
  reviewed: { label: '已判定', cls: 'badge-released' },
  closed: { label: '已关闭', cls: 'badge-discarded' },
};

export function fmtDate(v) {
  if (!v) return '—';
  return String(v).replace('T', ' ').slice(0, 10);
}
export function fmtDateTime(v) {
  if (!v) return '—';
  return String(v).replace('T', ' ').slice(0, 16);
}

export function daysUntil(dateStr) {
  if (!dateStr) return null;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const d = new Date(dateStr + 'T00:00:00');
  return Math.round((d - today) / 86400000);
}
