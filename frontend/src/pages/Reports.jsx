import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { REPORT_STATUS_META, fmtDateTime } from '../ui.js';

export default function Reports() {
  const [reports, setReports] = useState([]);
  const [incubators, setIncubators] = useState([]);
  const [samples, setSamples] = useState([]);
  const [form, setForm] = useState({
    incubator_id: '', suspect_sample_id: '',
    suspected_start_at: '', suspected_end_at: '', recheck_deadline: '', description: '',
  });
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState('');

  async function reload() {
    const [rs, ins, ss] = await Promise.all([
      api.get('/contamination-reports'), api.get('/incubators'), api.get('/samples'),
    ]);
    setReports(rs); setIncubators(ins); setSamples(ss);
  }
  useEffect(() => { reload(); }, []);

  function toS(v) { return v ? v.replace('T', ' ') + ':00' : v; }

  async function submit(e) {
    e.preventDefault();
    setErr(''); setMsg('');
    try {
      const d = await api.post('/contamination-reports', {
        incubator_id: Number(form.incubator_id),
        suspect_sample_id: Number(form.suspect_sample_id),
        suspected_start_at: toS(form.suspected_start_at),
        suspected_end_at: toS(form.suspected_end_at),
        recheck_deadline: form.recheck_deadline || undefined,
        description: form.description,
      });
      setMsg(`登记成功（${d.report_no}），可疑样本已立即隔离，请负责人进入详情筛选同箱培养皿`);
      setForm({ incubator_id: '', suspect_sample_id: '', suspected_start_at: '', suspected_end_at: '', recheck_deadline: '', description: '' });
      reload();
    } catch (ex) {
      const errs = ex.data?.errors || {};
      setErr(Object.values(errs).flat().join('；') || ex.message);
    }
  }

  return (
    <div>
      <div className="page-head"><h2>污染登记与同箱判定</h2></div>

      {msg && <div className="alert alert-ok">{msg}</div>}
      {err && <div className="alert alert-error">{err}</div>}

      <form className="form-card" onSubmit={submit}>
        <h3>实验员：登记疑似污染（先隔离，再追溯）</h3>
        <div className="form-grid2">
          <div>
            <label>培养箱位置 *</label>
            <select required value={form.incubator_id} onChange={(e) => setForm({ ...form, incubator_id: e.target.value })}>
              <option value="">请选择</option>
              {incubators.map((i) => <option key={i.id} value={i.id}>{i.code} · {i.name}</option>)}
            </select>
          </div>
          <div>
            <label>可疑培养皿 *（登记即隔离）</label>
            <select required value={form.suspect_sample_id} onChange={(e) => setForm({ ...form, suspect_sample_id: e.target.value })}>
              <option value="">请选择</option>
              {samples.filter((s) => s.status !== 'discarded').map((s) => <option key={s.id} value={s.id}>{s.code}（{s.batch_no}）</option>)}
            </select>
          </div>
          <div>
            <label>可疑时间段 · 起 *</label>
            <input type="datetime-local" required value={form.suspected_start_at} onChange={(e) => setForm({ ...form, suspected_start_at: e.target.value })} />
          </div>
          <div>
            <label>可疑时间段 · 止 *</label>
            <input type="datetime-local" required value={form.suspected_end_at} onChange={(e) => setForm({ ...form, suspected_end_at: e.target.value })} />
          </div>
          <div>
            <label>可疑样本复检截止日</label>
            <input type="date" value={form.recheck_deadline} onChange={(e) => setForm({ ...form, recheck_deadline: e.target.value })} />
          </div>
        </div>
        <label>污染现象描述</label>
        <textarea rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
        <button className="btn btn-primary">登记并立即隔离可疑样本</button>
      </form>

      <h3>污染登记单（{reports.length}）</h3>
      <table className="table">
        <thead>
          <tr><th>编号</th><th>培养箱</th><th>可疑样本</th><th>可疑时间段</th><th>状态</th><th>登记人</th><th></th></tr>
        </thead>
        <tbody>
          {reports.map((r) => {
            const meta = REPORT_STATUS_META[r.status] || { label: r.status, cls: '' };
            return (
              <tr key={r.id}>
                <td><strong>{r.report_no}</strong></td>
                <td>{r.incubator?.code}</td>
                <td>{r.suspect_sample?.code || '—'}</td>
                <td className="small">{fmtDateTime(r.suspected_start_at)} ~ {fmtDateTime(r.suspected_end_at)}</td>
                <td><span className={`badge ${meta.cls}`}>{meta.label}</span></td>
                <td className="small">{r.reporter?.name}</td>
                <td><Link className="btn btn-ghost btn-sm" to={`/reports/${r.id}`}>筛选/判定</Link></td>
              </tr>
            );
          })}
          {reports.length === 0 && <tr><td colSpan={7} className="muted center">暂无污染登记</td></tr>}
        </tbody>
      </table>
    </div>
  );
}
