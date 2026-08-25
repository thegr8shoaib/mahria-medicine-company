<template>
  <div>
    <div class="page-header">
      <h1>Purchases</h1>
      <button class="btn" @click="openPurchase()"><Plus /> New Purchase</button>
    </div>

    <div class="card" style="margin-bottom: 16px; padding: 14px 20px">
      <div class="toolbar">
        <input v-model="from" type="date" class="input" style="width: 160px" />
        <span style="color: var(--muted)">to</span>
        <input v-model="to" type="date" class="input" style="width: 160px" />
        <button class="btn btn-sm btn-secondary" @click="load()"><RefreshCw /></button>
      </div>
    </div>

    <div v-if="loading" class="card loading"><span class="spinner spinner-dark" /></div>
    <div v-else-if="!purchases.length" class="card empty">No purchases recorded. Click "New Purchase" to restock.</div>
    <div v-else class="card" style="padding: 0; overflow: auto">
      <table class="table">
        <thead>
          <tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Items</th><th style="text-align:right">Amount</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="p in purchases" :key="p.id">
            <td class="mono">{{ p.invoice_number }}</td>
            <td>{{ p.supplier?.name || '—' }}</td>
            <td>{{ p.purchase_date }}</td>
            <td>{{ p.items?.length }}</td>
            <td style="text-align:right; font-weight:600">{{ money(p.total_amount) }}</td>
            <td style="text-align:right; white-space: nowrap">
              <button class="btn btn-sm btn-secondary" @click="showDetails(p)"><Eye /></button>
              <button class="btn btn-sm btn-secondary" @click="editPurchase(p)"><Pencil /></button>
              <button class="btn btn-sm btn-secondary" style="color: var(--danger)" @click="remove(p)"><Trash2 /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pagination" v-if="purchases.length">
      <button class="btn btn-sm btn-secondary" :disabled="page <= 1" @click="load(page - 1)">Prev</button>
      <span class="badge badge-gray">Page {{ page }} of {{ lastPage }}</span>
      <button class="btn btn-sm btn-secondary" :disabled="page >= lastPage" @click="load(page + 1)">Next</button>
    </div>

    <div v-if="showForm" class="modal-overlay">
      <div class="modal card">
        <h3>{{ editing ? `Edit Purchase — ${editing.invoice_number}` : 'New Purchase' }}</h3>
        <p v-if="error" class="alert-error">{{ error }}</p>

        <div class="row">
          <div>
            <label class="label">Supplier *</label>
            <select v-model="form.supplier_id" class="select">
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Date</label>
            <input v-model="form.purchase_date" type="date" class="input" />
          </div>
        </div>

        <div class="supplier-actions">
          <span class="label">No supplier listed? </span>
          <button type="button" class="btn btn-sm btn-secondary" @click="addSupplier">+ New supplier</button>
        </div>

        <p v-if="selectedSupplier" class="muted" style="font-size: 13px">
          Showing products tagged to {{ selectedSupplier.name }}
          ({{ availableProducts.length }} of {{ products.length }}).
        </p>

        <div v-for="(item, i) in form.items" :key="i" class="line-item-wrap">
          <div class="line-item">
            <select v-model="item.product_id" class="select" required @change="onProductSelect(item)">
              <option :value="null" disabled>Select product</option>
              <option v-for="p in availableProducts" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
            </select>
            <input v-model="item.batch_number" class="input" placeholder="Batch #" required />
            <input v-model="item.expiry_date" type="date" class="input" required />
            <input v-model.number="item.quantity" type="number" min="1" class="input" :placeholder="qtyLabel(item)" required />
            <input
              v-model.number="item.items_per_pack"
              type="number" min="0" step="1"
              class="input"
              placeholder="Items/pack"
              :title="'Leave 0/blank for normal items — set > 1 when buying by the pack'"
            />
            <input v-model.number="item.unit_cost" type="number" min="0" step="0.01" class="input" :placeholder="costLabel(item)" required />
            <input v-model.number="item.sale_price" type="number" min="0" step="0.01" class="input" :placeholder="saleLabel(item)" />
            <button type="button" class="btn btn-sm btn-secondary" style="color: var(--danger)" @click="form.items.splice(i, 1)"><Trash2 /></button>
          </div>
          <div v-if="packInfo(item)" class="line-info">{{ packInfo(item) }}</div>
        </div>

        <button type="button" class="btn btn-sm btn-secondary" @click="addItem"><Plus /> Add item</button>

        <p class="muted" style="font-size: 12px">
          Buying by the pack? Enter Items/pack (e.g. 20) and the quantity &amp; cost/sale price are per pack — stock and prices are divided per item automatically.
        </p>

        <div class="modal-actions">
          <button class="btn btn-secondary" @click="closeForm">Cancel</button>
          <button class="btn" :disabled="saving" @click="submit">
            <span v-if="saving" class="spinner" /> {{ editing ? 'Update Purchase' : 'Record Purchase' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showNewSupplier" class="modal-overlay">
      <div class="modal modal-sm card">
        <h3>New Supplier</h3>
        <p v-if="supError" class="alert-error">{{ supError }}</p>
        <input v-model="supplierForm.name" class="input" placeholder="Supplier name *" />
        <input v-model="supplierForm.phone" class="input" placeholder="Phone" style="margin-top: 8px" />
        <input v-model="supplierForm.email" class="input" placeholder="Email" style="margin-top: 8px" />
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showNewSupplier = false">Cancel</button>
          <button class="btn" :disabled="supSaving" @click="saveSupplier">
            <span v-if="supSaving" class="spinner" /> Save
          </button>
        </div>
      </div>
    </div>

    <div v-if="detail" class="modal-overlay">
      <div class="modal card">
        <h3>Purchase {{ detail.invoice_number }}</h3>
        <p class="muted">
          {{ detail.supplier?.name || 'Unknown supplier' }} · {{ detail.purchase_date }} ·
          recorded by {{ detail.user?.name }}
        </p>
        <table class="table">
          <thead><tr><th>Product</th><th>Batch</th><th style="text-align:right">Qty</th><th style="text-align:right">Cost</th></tr></thead>
          <tbody>
            <tr v-for="it in detail.items" :key="it.id">
              <td>{{ it.product?.name }}</td>
              <td class="mono">{{ it.batch?.batch_number }}</td>
              <td style="text-align:right">{{ it.quantity }}</td>
              <td style="text-align:right">{{ money(it.total_cost) }}</td>
            </tr>
          </tbody>
        </table>
        <div style="display:flex; justify-content:flex-end; margin-top: 10px">
          <strong>Total: {{ money(detail.total_amount) }}</strong>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="detail = null">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Eye, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-vue-next'
import api from '../api/client'
import { useProductStore } from '../stores/products'
import { apiMsg, money } from '../utils'

const productsStore = useProductStore()
const products = ref([])
const suppliers = ref([])
const purchases = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const from = ref('')
const to = ref('')

const showForm = ref(false)
const saving = ref(false)
const error = ref('')
const editing = ref(null)
const form = ref({ supplier_id: null, purchase_date: todayIso(), notes: '', items: [] })

const showNewSupplier = ref(false)
const supSaving = ref(false)
const supError = ref('')
const supplierForm = ref({ name: '', phone: '', email: '' })

const detail = ref(null)

const selectedSupplier = computed(() =>
  suppliers.value.find((s) => s.id === form.value.supplier_id) || null
)

const availableProducts = computed(() => {
  const list = products.value
  if (!selectedSupplier.value) return list
  return list.filter((p) => p.company_model?.distributor_id === selectedSupplier.value.id)
})

function itemProduct(item) {
  return products.value.find((p) => p.id === item.product_id) || null
}

function lineIsPack(item) {
  return Number(item.items_per_pack) > 1
}

function qtyLabel(item) {
  return lineIsPack(item) ? 'Packs' : 'Qty'
}

function costLabel(item) {
  return lineIsPack(item) ? 'Trade / pack' : 'Unit cost'
}

function saleLabel(item) {
  return lineIsPack(item) ? 'Sale / pack' : 'Sale price'
}

function onProductSelect(item) {
  const p = itemProduct(item)
  if (p && !item.items_per_pack && Number(p.items_per_pack) > 1) {
    item.items_per_pack = Number(p.items_per_pack)
  }
}

function packInfo(item) {
  if (!lineIsPack(item)) return ''
  const perPack = Number(item.items_per_pack)
  const items = item.quantity ? Number(item.quantity) * perPack : '—'
  const perItemCost = item.unit_cost ? (Number(item.unit_cost) / perPack).toFixed(2) : '—'
  const perItemSale = item.sale_price ? (Number(item.sale_price) / perPack).toFixed(2) : '—'
  return `${perPack} items per pack → ${items} items in stock @ cost Rs ${perItemCost}/item · sale Rs ${perItemSale}/item`
}

watch(() => form.value.supplier_id, () => {
  form.value.items.forEach((it) => (it.product_id = null))
})

function todayIso() {
  return new Date().toISOString().slice(0, 10)
}

function addItem() {
  form.value.items.push({ product_id: null, batch_number: '', expiry_date: '', quantity: 1, unit_cost: 0, sale_price: 0, items_per_pack: 0 })
}

async function load(p = 1) {
  loading.value = true
  try {
    const params = { page: p, per_page: 15 }
    if (from.value) params.from = from.value
    if (to.value) params.to = to.value
    const res = await api.get('/purchases', { params })
    purchases.value = res.data.data
    page.value = res.data.current_page
    lastPage.value = res.data.last_page
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    loading.value = false
  }
}

async function loadSuppliers() {
  try {
    const res = await api.get('/suppliers')
    suppliers.value = res.data
  } catch (e) {}
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await api.put(`/purchases/${editing.value.id}`, form.value)
    } else {
      await api.post('/purchases', form.value)
    }
    closeForm()
    productsStore.invalidate()
    productsStore.ensureLoaded(true).catch(() => {})
    await load()
  } catch (e) {
    error.value = apiMsg(e, 'Could not save purchase.')
  } finally {
    saving.value = false
  }
}

function closeForm() {
  showForm.value = false
  editing.value = null
  form.value = { supplier_id: null, purchase_date: todayIso(), notes: '', items: [] }
}

function openPurchase() {
  editing.value = null
  if (!form.value.items.length) addItem()
  showForm.value = true
}

function toFormItem(it) {
  const prod = it.product
  const perPack = prod ? Number(prod.items_per_pack) || 0 : 0
  const base = {
    product_id: it.product_id,
    batch_number: it.batch?.batch_number || '',
    expiry_date: it.batch?.expiry_date ? String(it.batch.expiry_date).slice(0, 10) : '',
    unit_cost: it.unit_cost,
    sale_price: prod ? Number(prod.price) : 0,
    items_per_pack: perPack,
  }
  if (perPack > 1) {
    base.quantity = Math.max(1, Math.round(Number(it.quantity) / perPack))
    base.unit_cost = Number((Number(it.unit_cost) * perPack).toFixed(2))
    base.sale_price = Number((Number(base.sale_price) * perPack).toFixed(2))
  } else {
    base.quantity = it.quantity
  }
  return base
}

async function editPurchase(p) {
  try {
    const res = await api.get(`/purchases/${p.id}`)
    const detail = res.data
    for (const it of detail.items || []) {
      const prod = it.product
      if (prod && !products.value.some((x) => x.id === prod.id)) {
        products.value.push({ ...prod })
      }
    }
    form.value = {
      supplier_id: detail.supplier_id,
      purchase_date: detail.purchase_date ? String(detail.purchase_date).slice(0, 10) : todayIso(),
      notes: detail.notes || '',
      items: (detail.items || []).map(toFormItem),
    }
    editing.value = detail
    showForm.value = true
  } catch (e) {
    alert(apiMsg(e))
  }
}

async function saveSupplier() {
  supSaving.value = true
  supError.value = ''
  try {
    const res = await api.post('/suppliers', supplierForm.value)
    suppliers.value.push(res.data.supplier)
    if (!form.value.supplier_id) form.value.supplier_id = res.data.supplier.id
    showNewSupplier.value = false
    supplierForm.value = { name: '', phone: '', email: '' }
  } catch (e) {
    supError.value = apiMsg(e)
  } finally {
    supSaving.value = false
  }
}

async function showDetails(p) {
  try {
    const res = await api.get(`/purchases/${p.id}`)
    detail.value = res.data
  } catch (e) {
    alert(apiMsg(e))
  }
}

async function remove(p) {
  if (!confirm(`Delete purchase ${p.invoice_number}? Stock will be reverted.`)) return
  try {
    await api.delete(`/purchases/${p.id}`)
    productsStore.invalidate()
    productsStore.ensureLoaded(true).catch(() => {})
    load(page.value)
  } catch (e) {
    alert(apiMsg(e))
  }
}

onMounted(async () => {
  load()
  loadSuppliers()
  productsStore.ensureLoaded().then(() => {
    products.value = productsStore.list
  })
})
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 10px; }
.mono { font-family: Consolas, monospace; font-size: 12px; }
.pagination { display: flex; align-items: center; gap: 10px; justify-content: center; margin-top: 18px; }
.modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center; z-index: 60; padding: 20px;
}
.modal { width: 100%; max-width: 1040px; max-height: 88vh; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
.modal-sm { max-width: 380px; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.supplier-actions { display: flex; align-items: center; gap: 8px; margin: 2px 0 8px; font-size: 13px; }
.line-item-wrap { margin-bottom: 8px; }
.line-item {
  display: grid;
  grid-template-columns: 1.3fr 1fr 120px 64px 90px 90px 90px auto;
  gap: 8px;
  align-items: center;
}
.line-info { margin-top: 4px; font-size: 12px; color: var(--primary-dark, #1d4ed8); font-weight: 600; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; }
.muted { color: var(--muted); }
</style>