<template>
  <div>
    <div class="page-header">
      <h1>Sales</h1>
      <div class="filters">
        <input v-model="search" class="input" placeholder="Search invoice #…" @keyup.enter="load(1)" />
        <input v-model="from" type="date" class="input" @change="load(1)" />
        <span class="filter-sep">to</span>
        <input v-model="to" type="date" class="input" @change="load(1)" />
        <select v-model="status" class="select" @change="load(1)">
          <option value="">All Status</option>
          <option value="completed">Completed</option>
          <option value="refunded">Refunded</option>
        </select>
        <button class="btn btn-secondary" @click="load(1)">Filter</button>
      </div>
    </div>

    <div v-if="loading" class="card loading"><span class="spinner spinner-dark" /></div>

    <template v-else>
      <div class="card">
        <div v-if="!sales.length" class="empty">No sales found for these filters.</div>
        <table v-else class="table">
          <thead>
            <tr>
              <th>Receipt #</th>
              <th>Date</th>
              <th>Cashier</th>
              <th>Customer</th>
              <th>Method</th>
              <th>Items</th>
              <th style="text-align:right">Total</th>
              <th style="text-align:right">Paid</th>
              <th style="text-align:right">Due</th>
              <th>Status</th>
              <th style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in sales" :key="s.id">
              <td class="mono">{{ s.invoice_number }}</td>
              <td>{{ formatTime(s.created_at) }}</td>
              <td>{{ s.user?.name }}</td>
              <td>{{ s.customer?.name || 'Walk-in' }}</td>
              <td>{{ paymentLabel(s.payment_method) }}</td>
              <td>{{ s.items_count }}</td>
              <td style="text-align:right; font-weight:600">{{ money(s.total) }}</td>
              <td style="text-align:right">{{ money(s.paid) }}</td>
              <td style="text-align:right" :class="Number(s.due) > 0 ? 'due-col' : ''">{{ money(s.due) }}</td>
              <td>
                <span class="badge" :class="s.status === 'refunded' ? 'badge-red' : 'badge-green'">
                  {{ s.status }}
                </span>
              </td>
              <td style="text-align:right">
                <div class="row-actions">
                  <button v-if="s.status === 'completed'" class="btn btn-sm" @click="printSale(s)">Reprint</button>
                  <button class="btn btn-sm btn-secondary" @click="openView(s)">View</button>
                  <button v-if="s.status === 'completed'" class="btn btn-sm btn-danger" @click="refundSale(s)">Refund</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="pager">
          <button class="btn btn-secondary btn-sm" :disabled="!pagination.prev_page_url" @click="load(pagination.current_page - 1)">Prev</button>
          <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="btn btn-secondary btn-sm" :disabled="!pagination.next_page_url" @click="load(pagination.current_page + 1)">Next</button>
        </div>
      </div>
    </template>

    <div v-if="viewSale" class="modal-overlay" @click.self="viewSale = null">
      <div class="modal receipt card">
        <div class="rec-head">
          <h3>Mehria Medicine Company</h3>
          <div class="muted">Receipt #: {{ viewSale.invoice_number }}</div>
          <div class="muted">{{ formatTime(viewSale.created_at) }} · {{ paymentLabel(viewSale.payment_method) }}</div>
          <div class="muted" v-if="viewSale.user">User: {{ viewSale.user.name }}</div>
          <div class="muted" v-if="viewSale.customer">Customer: {{ viewSale.customer.name }}</div>
        </div>
        <div class="rec-items">
          <div v-for="it in viewSale.items" :key="it.id" class="rec-row">
            <div class="rec-info">
              <div>{{ it.product?.name }}</div>
              <div class="muted">{{ it.quantity }} × {{ money(it.unit_price) }}</div>
            </div>
            <div>{{ money(it.total) }}</div>
          </div>
        </div>
        <div class="rec-totals">
          <div class="sum-row"><span>Subtotal</span><span>{{ money(viewSale.subtotal) }}</span></div>
          <div class="sum-row" v-if="viewSale.discount"><span>Discount</span><span>− {{ money(viewSale.discount) }}</span></div>
          <div class="sum-row total"><span>Total</span><span>{{ money(viewSale.total) }}</span></div>
          <div class="sum-row"><span>Paid</span><span>{{ money(viewSale.paid) }}</span></div>
          <div class="sum-row due-col" v-if="Number(viewSale.due) > 0"><span>Balance Due</span><span>{{ money(viewSale.due) }}</span></div>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="viewSale = null">Close</button>
          <button class="btn btn-sm" @click="printSale(viewSale)">Print</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '../api/client'
import { apiMsg, formatTime, money, paymentLabel, printSaleReceipt } from '../utils'

const sales = ref([])
const pagination = ref({})
const loading = ref(true)
const search = ref('')
const from = ref('')
const to = ref('')
const status = ref('')
const viewSale = ref(null)

async function load(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 15 }
    if (search.value) params.search = search.value
    if (from.value) params.from = from.value
    if (to.value) params.to = to.value
    if (status.value) params.status = status.value
    const res = await api.get('/sales', { params })
    sales.value = res.data.data
    pagination.value = {
      current_page: res.data.current_page,
      last_page: res.data.last_page,
      prev_page_url: res.data.prev_page_url,
      next_page_url: res.data.next_page_url,
    }
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    loading.value = false
  }
}

async function openView(s) {
  try {
    const res = await api.get(`/sales/${s.id}`)
    viewSale.value = res.data
  } catch (e) {
    alert(apiMsg(e))
  }
}

function printSale(s) {
  printSaleReceipt(s, { reprinted: true })
}

async function refundSale(s) {
  if (!confirm(`Refund ${s.invoice_number}? Stock will be restored.`)) return
  try {
    await api.post(`/sales/${s.id}/refund`)
    load(pagination.value.current_page)
  } catch (e) {
    alert(apiMsg(e))
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.filter-sep { color: var(--muted); font-size: 12px; }
.filters .input, .filters .select { width: auto; }
.mono { font-family: Consolas, monospace; font-size: 12px; }
.due-col { color: var(--danger); font-weight: 700; }
.row-actions { display: flex; gap: 6px; justify-content: flex-end; }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #b91c1c; }
.pager { display: flex; align-items: center; gap: 14px; justify-content: flex-end; margin-top: 14px; }
.page-info { font-size: 12.5px; color: var(--muted); }

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center;
  z-index: 50;
  padding: 20px;
}
.modal { width: 100%; max-width: 340px; display: flex; flex-direction: column; gap: 12px; }
.rec-head { text-align: center; border-bottom: 1px dashed var(--border); padding-bottom: 12px; margin-bottom: 12px; }
.rec-head h3 { font-size: 16px; }
.muted { color: var(--muted); font-size: 12px; }
.rec-items { display: flex; flex-direction: column; margin-bottom: 10px; }
.rec-row { display: flex; justify-content: space-between; gap: 10px; padding: 5px 0; }
.rec-info { flex: 1; min-width: 0; }
.rec-totals { border-top: 1px dashed var(--border); padding-top: 10px; display: flex; flex-direction: column; gap: 5px; }
.sum-row { display: flex; justify-content: space-between; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 6px; }
</style>