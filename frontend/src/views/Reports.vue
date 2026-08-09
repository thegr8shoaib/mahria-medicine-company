<template>
  <div>
    <div class="page-header">
      <h1>Reports & Analytics</h1>
      <div class="range-controls">
        <button
          v-for="r in ranges"
          :key="r.days"
          class="btn btn-sm"
          :class="range === r.days ? '' : 'btn-secondary'"
          @click="setRange(r.days)"
        >
          {{ r.label }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="card loading"><span class="spinner spinner-dark" /></div>

    <template v-else>
      <div v-if="exportsLoading" class="loading"><span class="spinner spinner-dark" /></div>

    <div v-else class="card exports-card">
      <h3 style="margin-bottom: 6px">Excel Exports</h3>
      <p class="exports-hint">Download live store data as Excel files.</p>
      <div class="export-btns">
        <button v-for="e in exportSheets" :key="e.key" class="btn" @click="exportXlsx(e.key, e.label)">
          {{ e.label }}
        </button>
      </div>
    </div>

    <div class="grid grid-4 stats">
        <div class="card stat">
          <div class="stat-label">Revenue</div>
          <div class="stat-value">{{ money(totals.revenue) }}</div>
          <div class="stat-sub">{{ totals.sales_count }} sales</div>
        </div>
        <div class="card stat">
          <div class="stat-label">Discounts Given</div>
          <div class="stat-value">{{ money(totals.discounts) }}</div>
          <div class="stat-sub">over period</div>
        </div>
        <div class="card stat">
          <div class="stat-label">Best Day</div>
          <div class="stat-value">{{ bestDay ? money(bestDay.revenue) : '—' }}</div>
          <div class="stat-sub">{{ bestDay?.date || 'no sales' }}</div>
        </div>
        <div class="card stat">
          <div class="stat-label">Avg / Sale</div>
          <div class="stat-value">{{ money(totals.sales_count ? totals.revenue / totals.sales_count : 0) }}</div>
          <div class="stat-sub">per invoice</div>
        </div>
      </div>

      <div class="grid grid-2" style="margin-top: 16px">
        <div class="card">
          <h3 style="margin-bottom: 16px">Revenue by Day</h3>
          <div class="chart">
            <div v-for="d in daily" :key="d.date" class="bar-col">
              <div class="bar-value">{{ compact(d.revenue) }}</div>
              <div class="bar" :style="{ height: barHeight(d.revenue) }" :title="`${d.date}: ${money(d.revenue)}`" />
              <div class="bar-label">{{ shortDate(d.date) }}</div>
            </div>
          </div>
        </div>

        <div class="card">
          <h3 style="margin-bottom: 16px">Top Selling Products (30 days)</h3>
          <div v-if="!top.length" class="empty">No sales data yet.</div>
          <div v-else class="top-list">
            <div v-for="(t, i) in top" :key="t.id" class="top-row">
              <span class="rank">{{ i + 1 }}</span>
              <div class="top-info">
                <div class="top-name">{{ t.name }}</div>
                <div class="top-sub">{{ t.sku }}</div>
              </div>
              <span class="badge badge-blue">{{ t.qty_sold }} sold</span>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top: 16px">
        <h3 style="margin-bottom: 12px">My Recent Sales</h3>
        <div v-if="myLoading" class="loading"><span class="spinner spinner-dark" /></div>
        <div v-else-if="!mySales.length" class="empty">No sales recorded by you yet.</div>
        <table v-else class="table">
          <thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Method</th><th style="text-align:right">Total</th></tr></thead>
          <tbody>
            <tr v-for="s in mySales" :key="s.id">
              <td class="mono">{{ s.invoice_number }}</td>
              <td>{{ s.customer?.name || 'Walk-in' }}</td>
              <td>{{ formatTime(s.created_at) }}</td>
              <td>{{ paymentLabel(s.payment_method) }}</td>
              <td style="text-align:right; font-weight:600">{{ money(s.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card" style="margin-top: 16px">
        <h3 style="margin-bottom: 12px">Sales by Cashier ({{ range }} days)</h3>
        <div v-if="!cashiers.length" class="empty">No sales in this period.</div>
        <table v-else class="table">
          <thead><tr><th>Cashier</th><th style="text-align:right">Sales</th><th style="text-align:right">Revenue</th><th style="text-align:right">Collected</th><th style="text-align:right">Credit (Due)</th></tr></thead>
          <tbody>
            <tr v-for="c in cashiers" :key="c.cashier">
              <td style="font-weight:600">{{ c.cashier }}</td>
              <td style="text-align:right">{{ c.sales }}</td>
              <td style="text-align:right">{{ money(c.revenue) }}</td>
              <td style="text-align:right">{{ money(c.collected) }}</td>
              <td style="text-align:right" :class="Number(c.credit) > 0 ? 'due-col' : ''">{{ money(c.credit) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card" style="margin-top: 16px">
        <h3 style="margin-bottom: 12px">Sales by Date</h3>
        <div class="date-query">
          <input v-model="queryDate" type="date" class="input" style="width: 180px" @change="loadDay" />
        </div>
        <div v-if="dayLoading" class="loading"><span class="spinner spinner-dark" /></div>
        <div v-else-if="!daySales?.sales?.length" class="empty">No sales on this date.</div>
        <table v-else class="table">
          <thead><tr><th>Invoice</th><th>Customer</th><th>Cashier</th><th>Method</th><th style="text-align:right">Total</th></tr></thead>
          <tbody>
            <tr v-for="s in daySales.sales" :key="s.id">
              <td class="mono">{{ s.invoice_number }}</td>
              <td>{{ s.customer?.name || 'Walk-in' }}</td>
              <td>{{ s.user?.name }}</td>
              <td>{{ s.payment_method }}</td>
              <td style="text-align:right; font-weight:600">{{ money(s.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import * as XLSX from 'xlsx'
import api from '../api/client'
import { useAuthStore } from '../stores/auth'
import { apiMsg, formatTime, money, paymentLabel } from '../utils'

const auth = useAuthStore()

const exportSheets = [
  { key: 'products', label: 'Products & Stock' },
  { key: 'batches', label: 'Batches & Expiry' },
  { key: 'suppliers', label: 'Suppliers' },
  { key: 'customers', label: 'Customers' },
  { key: 'sales', label: 'Sales' },
]
const exportsData = ref(null)
const exportsLoading = ref(false)

async function loadExports() {
  exportsLoading.value = true
  try {
    exportsData.value = (await api.get('/reports/exports')).data
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    exportsLoading.value = false
  }
}

function exportXlsx(key, label) {
  if (!exportsData.value) return
  const rows = exportsData.value[key] || []
  if (!rows.length) {
    alert('No data to export for ' + label + '.')
    return
  }
  const ws = XLSX.utils.json_to_sheet(rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, label.slice(0, 31))
  XLSX.writeFile(wb, `${label.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0, 10)}.xlsx`)
}

const ranges = [
  { days: 7, label: '7 Days' },
  { days: 30, label: '30 Days' },
  { days: 90, label: '90 Days' },
]
const range = ref(7)
const loading = ref(true)
const data = ref(null)
const top = ref([])

const queryDate = ref(new Date().toISOString().slice(0, 10))
const daySales = ref(null)
const dayLoading = ref(false)
const mySales = ref([])
const myLoading = ref(false)

const totals = computed(
  () => data.value?.totals || { revenue: 0, discounts: 0, sales_count: 0 }
)
const daily = computed(() => data.value?.daily || [])
const cashiers = computed(() => data.value?.cashiers || [])
const bestDay = computed(() =>
  daily.value.reduce((best, d) => (d.revenue > (best?.revenue || 0) ? d : best), null)
)

const maxRev = computed(() => Math.max(...daily.value.map((d) => d.revenue), 1))

function compact(n) {
  if (Math.abs(n) >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k'
  return String(Math.round(n))
}

function barHeight(rev) {
  return `${Math.max(2, (rev / maxRev.value) * 180)}px`
}
function shortDate(iso) {
  const d = new Date(iso + 'T00:00:00')
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

async function loadSummary() {
  loading.value = true
  try {
    const res = await api.get('/reports/summary', { params: { range: range.value } })
    data.value = res.data
    const topRes = await api.get('/reports/top-products', { params: { days: 30 } })
    top.value = topRes.data
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    loading.value = false
  }
}

async function loadDay() {
  dayLoading.value = true
  try {
    const res = await api.get('/reports/sales-by-date', { params: { date: queryDate.value } })
    daySales.value = res.data
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    dayLoading.value = false
  }
}

async function loadMySales() {
  myLoading.value = true
  try {
    const params = { per_page: 8 }
    if (auth.isAdmin) params.user_id = auth.user?.id
    const res = await api.get('/sales', { params })
    mySales.value = res.data.data
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    myLoading.value = false
  }
}

function setRange(r) {
  range.value = r
  loadSummary()
}

onMounted(() => {
  loadSummary()
  loadDay()
  loadExports()
  loadMySales()
})
</script>

<style scoped>
.range-controls { display: flex; gap: 6px; flex-wrap: wrap; }
.exports-card { padding: 18px; }
.exports-hint { font-size: 12.5px; color: var(--muted); margin-bottom: 12px; }
.export-btns { display: flex; gap: 8px; flex-wrap: wrap; }
.stats .stat { padding: 18px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.stat-value { font-size: 21px; font-weight: 700; margin: 4px 0 2px; }
.stat-sub { font-size: 12px; color: var(--muted); }

.chart { display: flex; align-items: flex-end; gap: 6px; height: 220px; overflow-x: auto; padding-top: 16px; }
.bar-col { flex: 1; min-width: 26px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; }
.bar {
  width: 100%; max-width: 34px;
  background: linear-gradient(180deg, #0e7490, #059669);
  border-radius: 6px 6px 2px 2px;
  min-height: 2px;
  transition: height 0.4s ease;
}
.bar-value { font-size: 10px; color: var(--muted); margin-bottom: 4px; white-space: nowrap; }
.bar-label { font-size: 10px; color: var(--muted); margin-top: 6px; white-space: nowrap; }

.top-list { display: flex; flex-direction: column; gap: 8px; }
.top-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; background: #f8fafc; border-radius: 9px; }
.rank {
  width: 26px; height: 26px;
  border-radius: 50%;
  background: var(--primary); color: #fff;
  display: grid; place-items: center;
  font-size: 12px; font-weight: 700;
  flex-shrink: 0;
}
.top-info { flex: 1; min-width: 0; }
.top-name { font-weight: 600; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top-sub { font-size: 11.5px; color: var(--muted); }
.muted { color: var(--muted); }
.mono { font-family: Consolas, monospace; font-size: 12px; }
.date-query { margin-bottom: 12px; }
</style>