// 极简 API 客户端：通过 X-Actor-Id 模拟当前操作人（实验员/负责人）
const ACTOR_KEY = 'lab_actor_id'

export function getActorId() {
  return localStorage.getItem(ACTOR_KEY) || ''
}
export function setActorId(id) {
  localStorage.setItem(ACTOR_KEY, String(id))
}

export async function api(path, options = {}) {
  const headers = { ...(options.headers || {}) }
  const actorId = getActorId()
  if (actorId) headers['X-Actor-Id'] = actorId

  let body = options.body
  if (body && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
    body = JSON.stringify(body)
  }

  const res = await fetch(`/api${path}`, { ...options, headers, body })
  const isJson = (res.headers.get('content-type') || '').includes('application/json')
  const data = isJson ? await res.json() : null

  if (!res.ok) {
    const message = data?.message || `请求失败 (${res.status})`
    const errors = data?.errors || null
    const err = new Error(message)
    err.status = res.status
    err.errors = errors
    throw err
  }
  return data

}

export function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
