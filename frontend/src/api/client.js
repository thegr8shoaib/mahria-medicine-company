import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      if (!location.hash.includes('/login')) window.location.hash = '#/login'
    }
    return Promise.reject(error)
  }
)

export function errMsg(error, fallback = 'Something went wrong.') {
  const data = error?.response?.data
  if (!data) return fallback
  if (typeof data.message === 'string') return data.message
  if (data.errors) return Object.values(data.errors).flat().join(', ')
  return fallback
}

export default api