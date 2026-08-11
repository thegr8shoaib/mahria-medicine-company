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
        </div>
        <span class="badge badge-gray">{{ filtered.length }} products</span>
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
import { computed, onMounted, ref } from 'vue'
import { CalendarClock, FileSpreadsheet, PackagePlus, Pencil, Plus, RefreshCw, Trash2, Upload } from 'lucide-vue-next'
import api from '../api/client'
import ProductForm from '../components/ProductForm.vue'
import BatchModal from '../components/BatchModal.vue'
import BatchesModal from '../components/BatchesModal.vue'
import { apiMsg, money } from '../utils'

const products = ref([])
const loading = ref(true)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

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
      (p.generic_name || '').toLowerCase().includes(q)
  )
})

async function load(p = 1) {
  loading.value = true
  try {
    const res = await api.get('/products', { params: { page: p, per_page: 20, search: search.value.trim() || undefined } })
    products.value = res.data.data
    page.value = res.data.current_page
    lastPage.value = res.data.last_page
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
function onSaved() { refresh() }

async function removeProduct(p) {
  if (!confirm(`Delete "${p.name}"? This cannot be undone.`)) return
  try {
    await api.delete(`/products/${p.id}`)
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
    refresh()
  } catch (e) {
    importErr.value = apiMsg(e)
  } finally {
    importing.value = false
  }
}

onMounted(() => load())
</script>

<style scoped>
.header-actions { display: flex; gap: 8px; }
.header-actions .icon { width: 15px; height: 15px; }
.toolbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
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