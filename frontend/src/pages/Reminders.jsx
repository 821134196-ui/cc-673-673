import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { fmtDate } from '../ui.js';

const TYPE_LABEL = {
  recheck_due_today: '今日复检截止',
  recheck_overdue: '复检已逾期',
};

export default function Reminders() {
  const [data, setData] = useState({ reminders: [], generated_now: 0 });

  async function load() {
    setData(await api.get('/reminders'));
  }
  useEffect(() => { load(); }, []);

  async function markRead(id) {
    await api.patch(`/reminders/${id}/read`, {});
    load();
  }

  return (
    <div>
      <div className="page-head">
        <h2>复检提醒（本地模拟）</h2>
        <button className="btn btn-sm" onClick={load}>刷新并生成今日提醒</button>
      </div>
      <div className="muted small" style={{ marginBottom: 12 }}>
        提醒仅在本地生成与展示（reminders 表），不对接外部渠道；同样本同类型每天只生成一次。
        也可用命令模拟：<code>php artisan reminders:send --date=YYYY-MM-DD</code>
      </div>
      {data.generated_now > 0 && <div className="alert alert-ok">本次新生成 {data.generated_now} 条提醒</div>}

      <ul className="reminder-list">
        {data.reminders.map((r) => (
          <li key={r.id} className={`reminder-item ${r.read_at ? 'is-read' : ''} reminder-${r.type}`}>
            <div className="reminder-main">
              <div className="reminder-title">
                <span className={`badge ${r.type === 'recheck_overdue' ? 'badge-frozen' : 'badge-pending'}`}>
                  {TYPE_LABEL[r.type] || r.type}
                </span>
                <Link to={`/samples/${r.sample_id}`}><strong>{r.title}</strong></Link>
              </div>
              <div className="muted small">{r.body}</div>
              <div className="muted small">提醒日期：{fmtDate(r.remind_date)} · 渠道：本地模拟</div>
            </div>
            {!r.read_at && <button className="btn btn-sm" onClick={() => markRead(r.id)}>标为已读</button>}
          </li>
        ))}
        {data.reminders.length === 0 && <li className="muted">暂无提醒</li>}
      </ul>
    </div>
  );
}
