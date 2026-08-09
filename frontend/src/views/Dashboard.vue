<template>
  <div>
    <div class="page-header">
      <h1>Dashboard</h1>
      <span class="badge badge-gray">{{ todayLabel }}</span>
    </div>

    <div v-if="loading" class="card loading"><span class="spinner spinner-dark" /></div>

    <template v-else-if="data">
      <div class="grid grid-4 stats">
        <div class="card stat">
          <div class="stat-icon green"><TrendingUp /></div>
          <div class="stat-label">Today's Revenue</div>
          <div class="stat-value">{{ money(data.today.revenue) }}</div>
          <div class="stat-sub">{{ data.today.sales_count }} sale(s) today</div>
        </div>
        <div class="card stat">
          <div class="stat-icon blue"><Wallet /></div>
          <div class="stat-label">Today's Profit</div>
          <div class="stat-value">{{ money(data.today.profit) }}</div>
          <div class="stat-sub">Gross margin</div>
        </div>
        <div class="card stat">
          <div class="stat-icon purple"><Package /></div>
          <div class="stat-label">Inventory Value</div>
          <div class="stat-value">{{ money(monthPurchases) }}</div>
          <div class="stat-sub">Purchases this month</div>
        </div>
        <div class="card stat">
          <div class="stat-icon amber"><AlertTriangle /></div>
          <div class="stat-label">Alerts</div>
          <div class="stat-value">{{ data.inventory.low_stock + data.inventory.expiring }}</div>
          <div class="stat-sub">{{ data.inventory.low_stock }} low stock · {{ data.inventory.expiring }} expiring</div>
        </div>
      </div>

      <div class="grid grid-2" style="margin-top: 16px">
        <div class="card">
          <h3 style="margin-bottom: 12px">Low Stock Products</h3>
          <div v-if="!data.inventory.low_stock_products.length" class="empty">
            All products are sufficiently stocked.
          </div>
          <table v-else class="table">
            <thead><tr><th>Product</th><th>SKU</th><th style="text-align:right">Stock</th></tr></thead>
            <tbody>
              <tr v-for="p in data.inventory.low_stock_products" :key="p.id">
                <td>{{ p.name }}</td>
                <td class="muted">{{ p.sku }}</td>
                <td style="text-align:right"><span class="badge badge-red">{{ p.stock }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <h3 style="margin-bottom: 12px">Batch Expiry (next 90 days)</h3>
          <div v-if="!data.inventory.expiring_batches.length" class="empty">
            No batches expiring soon.
          </div>
          <table v-else class="table">
            <thead><tr><th>Batch</th><th>Product</th><th>Expiry</th><th style="text-align:right">Qty</th></tr></thead>
            <tbody>
              <tr v-for="b in data.inventory.expiring_batches" :key="b.id">
                <td>{{ b.batch_number }}</td>
                <td>{{ b.product }}</td>
                <td>
                  <span class="badge" :class="b.days_left <= 30 ? 'badge-red' : 'badge-amber'">
                    {{ b.expiry_date }} ({{ b.days_left }}d)
                  </span>
                </td>
                <td style="text-align:right">{{ b.quantity }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card" style="margin-top: 16px">
        <h3 style="margin-bottom: 12px">Recent Sales</h3>
        <div v-if="!data.recent_sales.length" class="empty">No sales yet. Head to POS to make the first sale.</div>
        <table v-else class="table">
          <thead><tr><th>Invoice</th><th>Customer</th><th>Items</th><th>Time</th><th style="text-align:right">Total</th></tr></thead>
          <tbody>
            <tr v-for="s in data.recent_sales" :key="s.id">
              <td class="mono">{{ s.invoice_number }}</td>
              <td>{{ s.customer?.name || 'Walk-in' }}</td>
              <td>{{ s.items?.length || 0 }}</td>
              <td class="muted">{{ formatTime(s.created_at) }}</td>
              <td style="text-align:right; font-weight:600">{{ money(s.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div v-else-if="error" class="alert-error">{{ error }}</div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { AlertTriangle, Package, TrendingUp, Wallet } from 'lucide-vue-next'
import api from '../api/client'
import { apiMsg, money, formatTime } from '../utils'

const data = ref(null)
const loading = ref(true)
const error = ref('')

const todayLabel = new Date().toLocaleDateString(undefined, {
  weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
})

const monthPurchases = computed(() => data.value?.month?.purchases ?? 0)

onMounted(async () => {
  try {
    const res = await api.get('/reports/dashboard')
    data.value = res.data
  } catch (e) {
    error.value = apiMsg(e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.stats .stat { padding: 18px; }
.stat-icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  display: grid; place-items: center;
  margin-bottom: 12px;
}
.stat-icon svg { width: 20px; height: 20px; }
.stat-icon.green { background: #dcfce7; color: #15803d; }
.stat-icon.blue { background: #dbeafe; color: #1d4ed8; }
.stat-icon.purple { background: #ede9fe; color: #6d28d9; }
.stat-icon.amber { background: #fef3c7; color: #b45309; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.stat-value { font-size: 22px; font-weight: 700; margin: 4px 0 2px; }
.stat-sub { font-size: 12px; color: var(--muted); }
.muted { color: var(--muted); }
.mono { font-family: Consolas, monospace; font-size: 12.5px; }
</style>