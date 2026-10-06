const STATUS_STYLE = {
  in_stock: { label: '在库', cls: 'ok' },
  quarantined: { label: '隔离中', cls: 'danger' },
  released: { label: '已放行', cls: 'ok' },
  frozen: { label: '冻结·复检未通过', cls: 'warn' },
  discarded: { label: '已废弃', cls: 'muted' },
}

export function StatusBadge({ status, label }) {
  const s = STATUS_STYLE[status] || { label: status, cls: 'muted' }
  return <span className={`badge ${s.cls}`}>{label || s.label}</span>
}

export function DecisionBadge({ decision }) {
  const map = {
    pending: { t: '待负责人判定', cls: 'warn' },
    affected: { t: '确认受影响·已隔离', cls: 'danger' },
    not_affected: { t: '确认未受影响', cls: 'ok' },
  }
  const s = map[decision] || { t: decision, cls: 'muted' }
  return <span className={`badge ${s.cls}`}>{s.t}</span>
}

export function fmt(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('zh-CN', { hour12: false })
}

export function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('zh-CN')
}
