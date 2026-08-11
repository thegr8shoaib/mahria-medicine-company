import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../api/client'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/Login.vue'),
    meta: { public: true },
  },
  {
    path: '/',
    component: () => import('../layouts/MainLayout.vue'),
    children: [
      { path: '', name: 'dashboard', component: () => import('../views/Dashboard.vue'), meta: { admin: true } },
      { path: 'pos', name: 'pos', component: () => import('../views/POS.vue') },
      { path: 'sales', name: 'sales', component: () => import('../views/Sales.vue'), meta: { permission: 'sales' } },
      { path: 'inventory', name: 'inventory', component: () => import('../views/Inventory.vue'), meta: { permission: 'inventory' } },
      { path: 'purchases', name: 'purchases', component: () => import('../views/Purchases.vue'), meta: { permission: 'purchases' } },
      { path: 'users', name: 'users', component: () => import('../views/Users.vue'), meta: { admin: true } },
      { path: 'profile', name: 'profile', component: () => import('../views/Profile.vue') },
      { path: 'customers', name: 'customers', component: () => import('../views/Customers.vue'), meta: { permission: 'customers' } },
      { path: 'reports', name: 'reports', component: () => import('../views/Reports.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/pos' },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

let userRefreshed = false

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (to.meta.public && auth.isLoggedIn) return { name: 'pos' }
  if (!to.meta.public && !auth.isLoggedIn) return { name: 'login' }
  if (auth.isLoggedIn && !userRefreshed) {
    userRefreshed = true
    try {
      const { data } = await api.get('/me')
      auth.user = data
      localStorage.setItem('user', JSON.stringify(data))
    } catch (e) {
      /* token invalid; navigation continues and APIs will 401 */
    }
  }
  if (to.meta.admin && !auth.isAdmin) return { name: 'pos' }
  if (to.meta.permission && !auth.can(to.meta.permission)) return { name: 'pos' }
})

export default router