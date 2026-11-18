import axios from 'axios'

/**
 * Shared axios instance. The SPA is served same-origin behind nginx
 * (/ -> Vite, /api and /sanctum -> Laravel), so Sanctum cookie auth needs
 * no base URL and no CORS setup, just the XSRF cookie, which axios then
 * echoes back automatically as X-XSRF-TOKEN on every mutating request.
 */
export const http = axios.create({
  headers: { Accept: 'application/json' },
})

let csrfBootstrap = null

export function ensureCsrf() {
  csrfBootstrap ??= http.get('/sanctum/csrf-cookie').catch((error) => {
    csrfBootstrap = null
    throw error
  })

  return csrfBootstrap
}

http.interceptors.request.use(async (config) => {
  const method = (config.method ?? 'get').toLowerCase()

  if (method !== 'get' && config.url !== '/sanctum/csrf-cookie') {
    await ensureCsrf()
  }

  return config
})

/**
 * Registered from main.js once the router and auth store exist: any 401
 * outside the auth screens means the session died, so reset and return
 * to login.
 */
export function installUnauthorizedHandler(onUnauthorized) {
  http.interceptors.response.use(
    (response) => response,
    (error) => {
      if (error.response?.status === 401) {
        onUnauthorized()
      }

      return Promise.reject(error)
    },
  )
}
