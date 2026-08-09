<template>
  <div class="layout">
    <div class="nav-overlay" :class="{ show: mobileOpen }" @click="mobileOpen = false" />
    <aside class="nav-sidebar" :class="{ open: mobileOpen }">
<nav class="nav-links">
      <router-link to="/pos" class="nav-link" @click="mobileOpen = false">
          <ShoppingCart class="icon" /> POS
        </router-link>
        <router-link v-if="auth.isAdmin" to="/" class="nav-link" @click="mobileOpen = false">
          <LayoutDashboard class="icon" /> Dashboard
        </router-link>
        <router-link v-if="auth.can('sales')" to="/sales" class="nav-link" @click="mobileOpen = false">
          <Receipt class="icon" /> Sales
        </router-link>
        <router-link v-if="auth.can('inventory')" to="/inventory" class="nav-link" @click="mobileOpen = false">
          <Boxes class="icon" /> Inventory
        </router-link>
        <router-link v-if="auth.can('purchases')" to="/purchases" class="nav-link" @click="mobileOpen = false">
          <Truck class="icon" /> Purchases
        </router-link>
        <router-link v-if="auth.can('customers')" to="/customers" class="nav-link" @click="mobileOpen = false">
          <Users class="icon" /> Customers
        </router-link>
        <router-link to="/reports" class="nav-link" @click="mobileOpen = false">
          <BarChart3 class="icon" /> Reports
        </router-link>
        <router-link v-if="auth.isAdmin" to="/users" class="nav-link" @click="mobileOpen = false">
          <UserCog class="icon" /> Users
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar">{{ initials }}</div>
          <div class="user-info">
            <div class="user-name">{{ auth.user?.name }}</div>
            <div class="user-role">{{ roleLabel }}</div>
          </div>
          <button class="logout-btn" @click="doLogout" title="Logout">
            <LogOut class="icon" />
          </button>
        </div>
      </div>
    </aside>

    <div class="main-area">
      <header class="topbar">
        <button class="icon-btn hamburger" @click="mobileOpen = true">
          <Menu class="icon" />
        </button>
        <div class="topbar-title">{{ pageTitle }}</div>
        <div class="topbar-spacer" />
        <button v-if="route.name === 'pos'" class="btn btn-secondary btn-sm" @click="openReprint">
          <Printer class="icon" /> Reprint Receipt
        </button>
      </header>

      <div v-if="reprintOpen" class="modal-overlay" @click.self="reprintOpen = false">
        <div class="modal card">
          <h3>Reprint Receipt</h3>
          <p class="muted" style="font-size:12.5px">Enter the receipt number, e.g. 080826-8</p>
          <input
            v-model="reprintNo"
            type="text"
            class="input"
            placeholder="Receipt No"
            @keyup.enter="doReprint"
          />
          <p v-if="reprintErr" class="alert-error">{{ reprintErr }}</p>
          <p v-if="reprintSale" class="muted" style="font-size:12.5px">
            Found: {{ reprintSale.invoice_number }} — {{ reprintSale.items.length }} item(s) — {{ money(reprintSale.total) }}
          </p>
          <div class="modal-actions">
            <button class="btn btn-secondary btn-sm" @click="reprintOpen = false">Cancel</button>
            <button class="btn btn-sm" :disabled="reprintLoading" @click="doReprint">
              <span v-if="reprintLoading" class="spinner" />
              <template v-else>Load &amp; Print</template>
            </button>
          </div>
        </div>
      </div>
      <main class="content">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  BarChart3, Boxes, LayoutDashboard, LogOut, Menu, Printer, Receipt,
  ShoppingCart, Truck, UserCog, Users,
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import api from '../api/client'
import { apiMsg, money, printSaleReceipt } from '../utils'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const mobileOpen = ref(false)
const reprintOpen = ref(false)
const reprintNo = ref('')
const reprintErr = ref('')
const reprintSale = ref(null)
const reprintLoading = ref(false)

const initials = computed(() =>
  (auth.user?.name || '')
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
)

const roleLabel = computed(() =>
  auth.user?.role === 'admin' ? 'Admin' : 'Cashier'
)

const titles = {
  pos: 'Point of Sale',
  sales: 'Sales History',
  dashboard: 'Dashboard',
  inventory: 'Inventory',
  purchases: 'Purchases',
  customers: 'Customers',
  reports: 'Reports & Analytics',
}

const pageTitle = computed(() => titles[route.name] || 'Mehria Medicine Company')

function openReprint() {
  reprintOpen.value = true
  reprintNo.value = ''
  reprintErr.value = ''
  reprintSale.value = null
}

async function doReprint() {
  const no = reprintNo.value.trim()
  if (!no) return
  reprintLoading.value = true
  reprintErr.value = ''
  try {
    const res = await api.get(`/sales/lookup/${encodeURIComponent(no)}`)
    reprintSale.value = res.data
    printSaleReceipt(res.data, { reprinted: true })
  } catch (e) {
    reprintErr.value = apiMsg(e, 'Receipt not found.')
  } finally {
    reprintLoading.value = false
  }
}

async function doLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.layout { display: flex; min-height: 100vh; }

.nav-sidebar {
  width: 250px;
  flex-shrink: 0;
  background: #0c1e2e;
  color: #cbd5e1;
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  height: 100vh;
  z-index: 40;
  transition: transform 0.25s ease;
}

.nav-links { flex: 1; padding: 14px 10px; display: flex; flex-direction: column; gap: 2px; overflow-y: auto; }
.nav-link {
  display: flex; align-items: center; gap: 11px;
  padding: 11px 14px;
  border-radius: 9px;
  font-weight: 600;
  font-size: 13.5px;
  color: #cbd5e1;
  transition: all 0.15s;
}
.nav-link .icon { width: 18px; height: 18px; }
.nav-link:hover { background: rgba(255, 255, 255, 0.07); color: #fff; }
.nav-link.router-link-exact-active {
  background: rgba(14, 116, 144, 0.35);
  color: #fff;
  box-shadow: inset 3px 0 0 var(--accent);
}

.sidebar-footer {
  padding: 14px 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}
.user-chip {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.user-avatar {
  width: 38px; height: 38px;
  border-radius: 10px;
  background: linear-gradient(135deg, #0e7490, #059669);
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.user-info { flex: 1; min-width: 0; line-height: 1.3; }
.user-name {
  font-weight: 700;
  font-size: 13.5px;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.user-role { font-size: 11.5px; font-weight: 600; color: #94a3b8; }
.logout-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: #fda4af;
  width: 34px; height: 34px;
  border-radius: 9px;
  display: grid; place-items: center;
  transition: all 0.15s;
  flex-shrink: 0;
}
.logout-btn:hover { background: rgba(244, 63, 94, 0.14); color: #fecdd3; }

.main-area { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.topbar {
  height: 60px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center;
  gap: 14px;
  padding: 0 22px;
  position: sticky;
  top: 0;
  z-index: 30;
}
.topbar-title { font-weight: 700; font-size: 15px; }
.topbar-spacer { flex: 1; }
.user-logout {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px 6px 6px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  cursor: pointer;
  transition: all 0.15s;
}
.user-logout:hover { border-color: var(--danger); box-shadow: var(--shadow); }
.user-avatar {
  width: 34px; height: 34px;
  border-radius: 8px;
  background: linear-gradient(135deg, #0e7490, #059669);
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  display: grid;
  place-items: center;
}
.user-text { text-align: left; line-height: 1.25; }
.logout-icon { width: 17px; height: 17px; color: var(--muted); margin-left: 2px; }
.user-logout:hover .logout-icon { color: var(--danger); }
.icon-btn {
  background: none; border: none; cursor: pointer;
  color: var(--muted); display: grid; place-items: center;
  padding: 7px; border-radius: 8px;
}
.icon-btn:hover { background: var(--bg); }
.hamburger { display: none; }

.content { padding: 22px; max-width: 1400px; width: 100%; margin: 0 auto; }

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center;
  z-index: 50;
  padding: 20px;
}
.modal { width: 100%; max-width: 340px; display: flex; flex-direction: column; gap: 12px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 6px; }
.modal-actions .btn { flex: 1; justify-content: center; }

.nav-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 35;
}

@media (max-width: 768px) {
  .hamburger { display: grid; }
  .nav-sidebar { position: fixed; transform: translateX(-100%); }
  .nav-sidebar.open { transform: translateX(0); }
  .nav-overlay.show { display: block; }
  .content { padding: 16px; }
}
</style>