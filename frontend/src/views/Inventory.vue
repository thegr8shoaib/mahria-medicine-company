<template>
  <div>
    <div class="page-header">
      <h1>Inventory</h1>
      <div class="header-actions">
        <button class="btn btn-secondary" :disabled="exporting" @click="exportExcel">
          <span v-if="exporting" class="spinner spinner-dark" />
          <template v-else><FileSpreadsheet class="icon" /> Export Excel</template>
        </button>
        <button class="btn btn-secondary" @click="showImport = true">
          <Upload class="icon" /> Import Excel
        </button>
        <button class="btn" @click="openProductModal()"><Plus /> Add Product</button>
      </div>
    </div>

    <div v-if="showImport" class="modal-overlay">
      <div class="modal card">
        <h3>Import Inventory (Excel / CSV)</h3>
        <p class="muted" style="font-size: 12.5px; line-height: 1.5">
          Rows match existing products by <b>SKU</b> (then by exact <b>Name</b>); unknown rows are created.
          Recognized columns: Name, SKU, Barcode, Category, Company, Generic Name, Variant, Price,
          Cost Price, Unit, Low Stock Alert, Batch No, Expiry Date, Batch Qty.
        </p>
        <p v-if="importErr" class="alert-error">{{ importErr }}</p>
        <p v-if="importMsg" class="alert-success">{{ importMsg }}</p>
        <input ref="importInput" type="file" accept=".xlsx,.csv" class="input import-file" @change="doImport" />
        <div class="modal-actions">
          <button class="btn btn-secondary" :disabled="importing" @click="showImport = false">Close</button>
        </div>
      </div>
    </div>

    <div class="card" style="padding: 14px 20px; margin-bottom: 16px">
      <div class="toolbar">
        <div style="position: relative; flex: 1; max-width: 340px">
          <input v-model="search" class="input" placeholder="Search products…" />
          <button v-if="search" class="search-clear" @click="search = ''"><X /></button>
        </div>
        <select v-model="distributorId" class="select filter-select" @change="onDistChange">
          <option :value="null">All Distributors</option>
          <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-model="companyId" class="select filter-select" @change="load(1)">
          <option :value="null">All Companies</option>
          <option v-for="c in boundCompanies" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <span class="badge badge-gray">{{ summary.total }} products</span>
        <span class="badge badge-blue">{{ summary.stock.toLocaleString() }} units in stock</span>
        <span class="badge badge-green">Stock value: {{ money(summary.value) }}</span>
      </div>
    </div>

    <div v-if="loading" class="card loading"><span class="spinner spinner-dark" /></div>
    <div v-else-if="!filtered.length" class="card empty">No products found.</div>
    <div v-else class="card" style="padding: 0; overflow: auto">
      <table class="table">
        <thead>
          <tr>
            <th>Product</th><th>SKU</th><th>Cost</th><th>Price</th><th>Stock</th>
            <th>Status</th><th>Batches</th><th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in filtered" :key="p.id">
            <td>
              <div style="font-weight: 600">{{ p.name }}</div>
              <div class="muted" style="font-size: 12px">{{ p.generic_name }}</div>
              <div class="muted" style="font-size: 11.5px" v-if="p.company">{{ p.company }}<template v-if="p.category"> · {{ p.category }}</template></div>
              <div class="muted" style="font-size: 11.5px" v-if="Number(p.items_per_pack) > 0">
                Sold per item · 1 pack = {{ p.items_per_pack }} items
              </div>
            </td>
            <td class="mono muted">{{ p.sku }}</td>
            <td>{{ money(p.cost_price) }}</td>
            <td style="font-weight: 600">{{ money(p.price) }}</td>
            <td>
              <span class="badge" :class="statusBadge(Number(p.stock), p)">{{ Number(p.stock) }}</span>
            </td>
            <td>
              <span class="badge" :class="p.is_active ? 'badge-green' : 'badge-gray'">
                {{ p.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td>
              <button class="btn btn-sm btn-secondary" @click="openBatches(p)">
                <CalendarClock /> {{ p.active_batches || 0 }}
              </button>
            </td>
            <td style="text-align: right; white-space: nowrap">
              <button class="btn btn-sm btn-secondary" @click="openProductModal(p)"><Pencil /></button>
              <button class="btn btn-sm btn-secondary" @click="openBatchModal(p)"><PackagePlus /></button>
              <button class="btn btn-sm btn-secondary" style="color: var(--danger)" @click="removeProduct(p)"><Trash2 /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pagination" v-if="products.length">
      <button class="btn btn-sm btn-secondary" :disabled="!page || page <= 1" @click="load(page - 1)">Prev</button>
      <span class="badge badge-gray">Page {{ page || 1 }} of {{ lastPage }}</span>
      <button class="btn btn-sm btn-secondary" :disabled="!page || page >= lastPage" @click="load(page + 1)">Next</button>
      <button class="btn btn-sm btn-secondary" @click="refresh"><RefreshCw /> Refresh</button>
    </div>

    <ProductForm
      v-if="showProduct"
      :product="editingProduct"
      @close="showProduct = false"
      @saved="onSaved"
    />
    <BatchModal
      v-if="showBatch"
      :product="batchProduct"
      @close="showBatch = false"
      @saved="onSaved"
    />
    <BatchesModal
      v-if="batchListProduct"
      :product="batchListProduct"
      @close="batchListProduct = null"
      @saved="onSaved"
    />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { CalendarClock, FileSpreadsheet, PackagePlus, Pencil, Plus, RefreshCw, Trash2, Upload, X } from 'lucide-vue-next'
import api from '../api/client'
import ProductForm from '../components/ProductForm.vue'
import BatchModal from '../components/BatchModal.vue'
import BatchesModal from '../components/BatchesModal.vue'
import { useProductStore } from '../stores/products'
import { apiMsg, money } from '../utils'

const productsStore = useProductStore()

const products = ref([])
const summary = ref({ total: 0, stock: 0, value: 0 })
const loading = ref(true)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const distributors = ref([])
const companies = ref([])
const distributorId = ref(null)
const companyId = ref(null)

const boundCompanies = computed(() =>
  companies.value.filter((c) => !distributorId.value || c.distributor_id === distributorId.value)
)

function onDistChange() {
  companyId.value = null
  load(1)
}

const showProduct = ref(false)
const editingProduct = ref(null)
const showBatch = ref(false)
const selectedProduct = ref(null)
const batchListProduct = ref(null)
const exporting = ref(false)
const showImport = ref(false)
const importing = ref(false)
const importErr = ref('')
const importMsg = ref('')
const importInput = ref(null)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return products.value
  return products.value.filter(
    (p) =>
      p.name.toLowerCase().includes(q) ||
      (p.sku || '').toLowerCase().includes(q) ||
      (p.barcode || '').toLowerCase().includes(q) ||
      (p.generic_name || '').toLowerCase().includes(q)
  )
})

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 350)
})
onBeforeUnmount(() => clearTimeout(searchTimer))

async function load(p = 1) {
  loading.value = true
  try {
    const res = await api.get('/products', {
      params: {
        page: p,
        per_page: 20,
        search: search.value.trim() || undefined,
        distributor_id: distributorId.value || undefined,
        company_id: companyId.value || undefined,
      },
    })
    products.value = res.data.data
    page.value = res.data.current_page
    lastPage.value = res.data.last_page
    summary.value = res.data.summary || { total: res.data.data.length, stock: 0, value: 0 }
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    loading.value = false
  }
}

function refresh() { load(page.value) }
function statusBadge(stock, p) {
  if (stock <= 0) return 'badge-red'
  return stock <= Number(p.low_stock_alert || 0) ? 'badge-amber' : 'badge-green'
}

function openProductModal(p = null) {
  editingProduct.value = p
  showProduct.value = true
}
function openBatchModal(p) {
  selectedProduct.value = p
  showBatch.value = true
}
function openBatches(p) {
  batchListProduct.value = p
}
function onSaved() { productsStore.invalidate(); refresh() }

async function removeProduct(p) {
  if (!confirm(`Delete "${p.name}"? This cannot be undone.`)) return
  try {
    await api.delete(`/products/${p.id}`)
    productsStore.invalidate()
    refresh()
  } catch (e) {
    alert(apiMsg(e))
  }
}

async function exportExcel() {
  exporting.value = true
  try {
    const res = await api.get('/products/export-excel', { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = 'inventory.xlsx'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    exporting.value = false
  }
}

async function doImport() {
  const f = importInput.value?.files?.[0]
  if (!f) return
  importing.value = true
  importErr.value = ''
  importMsg.value = ''
  const fd = new FormData()
  fd.append('file', f)
  try {
    const { data } = await api.post('/products/import-excel', fd)
    importMsg.value = data.message
    productsStore.invalidate()
    refresh()
  } catch (e) {
    importErr.value = apiMsg(e)
  } finally {
    importing.value = false
  }
}

onMounted(() => {
  const q = new URLSearchParams(location.hash.split('?')[1] || '')
  distributorId.value = q.get('distributor_id') ? Number(q.get('distributor_id')) : null
  companyId.value = q.get('company_id') ? Number(q.get('company_id')) : null
  load()
  api.get('/suppliers').then((r) => (distributors.value = r.data)).catch(() => {})
  api.get('/companies').then((r) => (companies.value = r.data)).catch(() => {})
})
</script>

<style scoped>
.header-actions { display: flex; gap: 8px; }
.header-actions .icon { width: 15px; height: 15px; }
.toolbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.filter-select { max-width: 260px; }
.search-clear {
  position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; color: var(--muted);
  display: grid; place-items: center; padding: 4px; border-radius: 6px;
}
.search-clear svg { width: 15px; height: 15px; }
.search-clear:hover { color: var(--danger); background: #fee2e2; }
.muted { color: var(--muted); }
.mono { font-family: Consolas, monospace; font-size: 12px; }
.pagination { display: flex; align-items: center; gap: 10px; justify-content: center; margin-top: 18px; }
.import-file { padding: 9px; }
.modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center; z-index: 60; padding: 20px;
}
.modal { width: 100%; max-width: 460px; display: flex; flex-direction: column; gap: 12px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 6px; }
</style>