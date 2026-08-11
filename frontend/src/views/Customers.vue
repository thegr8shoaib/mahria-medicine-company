<template>
  <div>
    <div class="page-header">
      <h1>Customers</h1>
      <button class="btn" @click="openForm()"><Plus /> Add Customer</button>
    </div>

    <div class="card" style="margin-bottom: 16px; padding: 14px 20px">
      <input v-model="search" class="input" style="max-width: 340px" placeholder="Search by name or phone…" @input="searchDebounced" />
    </div>

    <div v-if="loading" class="card loading"><span class="spinner spinner-dark" /></div>
    <div v-else-if="!customers.length" class="card empty">No customers found.</div>
    <div v-else class="card" style="padding: 0; overflow: auto">
      <table class="table">
        <thead>
          <tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Sales</th><th style="text-align:right">Balance</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="c in customers" :key="c.id">
            <td style="font-weight: 600">{{ c.name }}</td>
            <td>{{ c.phone || '—' }}</td>
            <td>{{ c.email || '—' }}</td>
            <td>{{ c.address || '—' }}</td>
            <td><span class="badge badge-blue">{{ c.sales_count }}</span></td>
            <td style="text-align:right; font-weight: 600">
              <span v-if="Number(c.credit) > 0" style="color: var(--danger)">{{ money(c.credit) }}</span>
              <span v-else-if="Number(c.credit) < 0" style="color: var(--green); white-space: nowrap">+ {{ money(Math.abs(c.credit)) }} adv.</span>
              <span v-else class="muted">—</span>
            </td>
            <td style="text-align:right; white-space: nowrap">
              <button class="btn btn-sm btn-secondary" style="color: var(--green)" @click="openAccount(c)"><Wallet /></button>
              <button class="btn btn-sm btn-secondary" @click="openForm(c)"><Pencil /></button>
              <button class="btn btn-sm btn-secondary" style="color: var(--danger)" @click="remove(c)"><Trash2 /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pagination" v-if="customers.length">
      <button class="btn btn-sm btn-secondary" :disabled="page <= 1" @click="load(page - 1)">Prev</button>
      <span class="badge badge-gray">Page {{ page }} of {{ lastPage }}</span>
      <button class="btn btn-sm btn-secondary" :disabled="page >= lastPage" @click="load(page + 1)">Next</button>
    </div>

    <div v-if="showForm" class="modal-overlay">
      <div class="modal card">
        <h3>{{ editing ? 'Edit Customer' : 'New Customer' }}</h3>
        <p v-if="error" class="alert-error">{{ error }}</p>
        <label class="label">Name *</label>
        <input v-model="form.name" class="input" required />
        <label class="label" style="margin-top: 10px">Phone</label>
        <input v-model="form.phone" class="input" />
        <label class="label" style="margin-top: 10px">Email</label>
        <input v-model="form.email" type="email" class="input" />
        <label class="label" style="margin-top: 10px">Address</label>
        <input v-model="form.address" class="input" />
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showForm = false">Cancel</button>
          <button class="btn" :disabled="saving" @click="submit">
            <span v-if="saving" class="spinner" /> Save
          </button>
        </div>
      </div>
    </div>

    <div v-if="showAccount" class="modal-overlay">
      <div class="modal card account-modal">
        <h3>{{ account.name }}</h3>
        <p v-if="account.phone" class="muted">{{ account.phone }}</p>
        <p v-if="payError" class="alert-error">{{ payError }}</p>

        <div class="balance-box" :class="{ advance: isAdvance }">
          <div class="balance-label">{{ isAdvance ? 'Advance Balance' : 'Current Balance' }}</div>
          <div class="balance-value" :class="isAdvance ? 'advance' : 'due'">
            {{ isAdvance ? '+ ' + money(Math.abs(accountBalance)) : money(accountBalance) }}
          </div>
          <div v-if="isAdvance" class="muted" style="font-size: 12px; margin-top: 4px">
            Customer has paid {{ money(Math.abs(accountBalance)) }} in advance; it will adjust against future bills.
          </div>
        </div>

        <div v-if="auth.isAdmin" style="margin-top: 16px">
          <label class="label">Receive Payment (Rs)</label>
          <input v-model.number="payAmount" type="number" min="0" step="0.01" class="input" placeholder="0" @input="payError = ''" />
          <label class="label" style="margin-top: 10px">Note</label>
          <input v-model="payNote" class="input" placeholder="e.g. paid cash" />
          <div v-if="remaining !== null" class="remaining-row">
            <span>Remaining after payment:</span>
            <strong :class="remaining > 0 ? 'due-text' : 'ok-text'">
              <template v-if="remaining > 0">{{ money(remaining) }}</template>
              <template v-else-if="remaining < 0">+ {{ money(Math.abs(remaining)) }} advance</template>
              <template v-else>settled</template>
            </strong>
          </div>
          <p v-if="isAdvance" class="muted" style="font-size: 12px; margin-top: 6px">
            Balance is in advance; recording a payment increases the advance.
          </p>
          <button class="btn btn-block" style="margin-top: 12px" :disabled="paying" @click="receivePayment">
            <span v-if="paying" class="spinner" /> Record Payment
          </button>
        </div>

        <h4 style="margin-top: 20px">Payment History</h4>
        <div v-if="payLoading" class="card loading"><span class="spinner spinner-dark" /></div>
        <div v-else-if="!paymentList.length" class="muted" style="padding: 8px 0">No payments yet.</div>
        <div v-else class="pay-list">
          <div v-for="p in paymentList" :key="p.id" class="pay-item">
            <div>
              <strong>{{ money(p.amount) }}</strong>
              <span v-if="p.note" class="muted"> — {{ p.note }}</span>
            </div>
            <div class="muted" style="font-size: 12px">
              {{ new Date(p.created_at).toLocaleDateString() }} · {{ p.user?.name }}
            </div>
          </div>
        </div>

        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showAccount = false">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Pencil, Plus, Trash2, Wallet } from 'lucide-vue-next'
import api from '../api/client'
import { useAuthStore } from '../stores/auth'
import { apiMsg, money } from '../utils'

const auth = useAuthStore()
const accountBalance = computed(() => Number(account.value.credit || 0))
const isAdvance = computed(() => accountBalance.value < 0)
const customers = ref([])
const loading = ref(true)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)
const error = ref('')
const form = ref({ name: '', phone: '', email: '', address: '' })

const showAccount = ref(false)
const account = ref({})
const payAmount = ref(0)
const payNote = ref('')
const payError = ref('')
const paying = ref(false)
const payLoading = ref(false)
const paymentList = ref([])
const remaining = ref(null)

let searchTimer = null
function searchDebounced() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 350)
}

async function load(p = 1) {
  loading.value = true
  try {
    const res = await api.get('/customers', {
      params: { page: p, per_page: 15, search: search.value.trim() || undefined },
    })
    customers.value = res.data.data
    page.value = res.data.current_page
    lastPage.value = res.data.last_page
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    loading.value = false
  }
}

function openForm(c = null) {
  editing.value = c
  form.value = c
    ? { name: c.name, phone: c.phone || '', email: c.email || '', address: c.address || '' }
    : { name: '', phone: '', email: '', address: '' }
  error.value = ''
  showForm.value = true
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) await api.put(`/customers/${editing.value.id}`, form.value)
    else await api.post('/customers', form.value)
    showForm.value = false
    load(page.value)
  } catch (e) {
    error.value = apiMsg(e)
  } finally {
    saving.value = false
  }
}

async function remove(c) {
  if (!confirm(`Delete customer "${c.name}"?`)) return
  try {
    await api.delete(`/customers/${c.id}`)
    load(page.value)
  } catch (e) {
    alert(apiMsg(e))
  }
}

async function openAccount(c) {
  account.value = { ...c }
  payAmount.value = 0
  payNote.value = ''
  payError.value = ''
  remaining.value = null
  showAccount.value = true
  payLoading.value = true
  paymentList.value = []
  try {
    const res = await api.get(`/customers/${c.id}/payments`)
    paymentList.value = res.data.payments.data || []
  } catch (e) {
    payError.value = apiMsg(e)
  } finally {
    payLoading.value = false
  }
}

async function receivePayment() {
  if (!payAmount.value || payAmount.value <= 0) {
    payError.value = 'Enter an amount greater than zero.'
    return
  }
  paying.value = true
  payError.value = ''
  try {
    const res = await api.post(`/customers/${account.value.id}/payments`, {
      amount: payAmount.value,
      note: payNote.value,
    })
    account.value.credit = res.data.credit
    paymentList.value.unshift(res.data.payment)
    remaining.value = res.data.credit
    payAmount.value = 0
    payNote.value = ''
    const row = customers.value.find((x) => x.id === account.value.id)
    if (row) row.credit = res.data.credit
  } catch (e) {
    payError.value = apiMsg(e)
  } finally {
    paying.value = false
  }
}

onMounted(() => load())
</script>

<style scoped>
.pagination { display: flex; align-items: center; gap: 10px; justify-content: center; margin-top: 18px; }
.modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center; z-index: 60; padding: 20px;
}
.modal { width: 100%; max-width: 420px; }
.account-modal { max-width: 460px; max-height: 85vh; overflow: auto; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.balance-box { margin-top: 14px; background: #f1f5f9; border-radius: 10px; padding: 14px 16px; text-align: center; }
.balance-box.advance { background: #ecfdf5; border: 1px solid #86efac; }
.balance-label { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.balance-value { font-size: 24px; font-weight: 700; margin-top: 2px; color: var(--green); }
.balance-value.due { color: var(--danger); }
.balance-value.advance { color: var(--green); }
.remaining-row { margin-top: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 14px; }
.due-text { color: var(--danger); font-weight: 700; }
.ok-text { color: var(--green); font-weight: 700; }
.btn-block { width: 100%; }
.pay-list { margin-top: 4px; display: flex; flex-direction: column; gap: 10px; }
.pay-item { border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
</style>