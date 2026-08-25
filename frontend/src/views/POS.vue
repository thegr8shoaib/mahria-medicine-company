<template>
  <div class="pos-layout">
<section class="pos-products card">
      <div class="pos-search">
        <Search class="search-icon" />
        <input
          ref="searchInput"
          v-model="query"
          class="input"
          placeholder="Search by name, SKU or barcode… (type &amp; Enter)"
          autofocus
          @keyup.enter="addSelectedMatch"
        />
        <button v-if="query" class="icon-btn" @click="query = ''"><X /></button>
        <button class="icon-btn refresh-btn" title="Refresh stock" @click="refreshStock"><RefreshCw /></button>
      </div>

      <div class="pos-filters">
        <select v-model="distributorId" class="select filter-select" @change="companyId = null">
          <option :value="null">All Distributors</option>
          <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-model="companyId" class="select filter-select">
          <option :value="null">All Companies</option>
          <option v-for="c in boundCompanies" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <div v-if="products.loading" class="loading"><span class="spinner spinner-dark" /></div>

      <div class="product-grid" v-else-if="filtered.length">
        <button
          v-for="p in filtered"
          :key="p.id"
          class="product-tile"
          :disabled="!Number(p.stock)"
          @click="addToCart(p)"
        >
          <div class="tile-name">{{ p.name }}</div>
          <div class="tile-sub">{{ p.generic_name || p.sku }}</div>
          <div v-if="Number(p.items_per_pack) > 1" class="tile-pack">1 pack = {{ p.items_per_pack }} items</div>
          <div class="tile-bottom">
            <span class="tile-price">{{ money(p.price) }}<span class="tile-unit">/{{ saleUnit(p) }}</span></span>
            <span class="badge" :class="stockBadgeClass(p)">{{ Number(p.stock) || 0 }} {{ unitLabel(Number(p.stock), saleUnit(p)) }} left</span>
          </div>
        </button>
      </div>
      <div v-else class="empty">
        {{ query ? 'No products match your search.' : 'No products available. Stock them from Purchases first.' }}
      </div>
    </section>

    <div class="pos-cart card">
      <div class="cart-head">
        <h3>Current Bill</h3>
        <span class="badge badge-blue">{{ cart.length }} item(s)</span>
      </div>

      <div class="cart-selects">
        <div>
          <label class="label">Customer</label>
          <select v-model="customerId" class="select">
            <option :value="null">Walk-in Customer</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} <template v-if="c.phone">({{ c.phone }})</template></option>
          </select>
        </div>
        <div v-if="selectedCustomer">
          <label class="label">Balance</label>
          <div
            class="customer-balance"
            :class="customerBalance < 0 ? 'good' : 'due'"
            :style="customerBalance === 0 ? 'background: var(--primary-light); color: var(--green); border-color: #c7d2fe' : ''"
          >
            <Wallet v-if="customerBalance !== 0" size="15" />
            <template v-if="customerBalance > 0">{{ money(customerBalance) }} due</template>
            <template v-else-if="customerBalance < 0">Rs {{ Math.abs(customerBalance) }} advance</template>
            <template v-else>No dues</template>
          </div>
        </div>
      </div>

      <div class="cart-items">
        <p v-if="!cart.length" class="empty">
          Cart is empty. Click products to add them.
        </p>
        <div v-for="item in cart" :key="item.product_id" class="cart-row">
          <div class="cart-info">
            <div class="cart-name">{{ item.name }}</div>
            <div class="cart-price">{{ money(item.unit_price) }}/{{ item.unit }} × {{ item.quantity }}</div>
          </div>
          <div class="cart-qty">
            <button class="qty-btn" @click="changeQty(item, -1)">−</button>
            <input
              type="number" min="1"
              class="input input-qty"
              v-model.number="item.quantity"
              @change="clampQty(item)"
            />
            <button class="qty-btn" @click="changeQty(item, 1)">+</button>
          </div>
          <div class="cart-total">{{ money(item.unit_price * item.quantity) }}</div>
          <button class="icon-btn remove" @click="removeItem(item)"><Trash2 /></button>
        </div>
      </div>

      <div class="cart-summary">
        <div class="sum-row"><span>Subtotal</span><span>{{ money(subtotal) }}</span></div>
        <div class="sum-row">
          <span>Discount (%)</span>
          <div class="discount-input">
            <input type="number" min="0" max="100" v-model.number="discount" class="input" @change="discount = Math.min(100, Math.max(0, discount || 0))" />
            <span>%</span>
          </div>
        </div>
        <div class="sum-row total"><span>Total</span><span>{{ money(total) }}</span></div>
        <div v-if="selectedCustomer" class="sum-row received-row-total">
          <span>Amount Received</span>
          <input type="number" min="0" step="0.01" v-model.number="received" class="received-amount-input" placeholder="0" @input="received = Math.max(0, received || 0)" />
        </div>
        <div v-if="selectedCustomer && dueToBalance > 0" class="received-hint due"><Wallet size="13" /> {{ money(dueToBalance) }} to balance</div>
        <div v-else-if="selectedCustomer && advanceGiven > 0" class="received-hint ok"><Wallet size="13" /> {{ money(advanceGiven) }} advance</div>
        <div v-else-if="selectedCustomer && cart.length" class="received-hint ok">Balance fully paid</div>
      </div>

      <p v-if="error" class="alert-error">{{ error }}</p>

      <div class="checkout-row">
        <button class="btn btn-secondary checkout-btn" :disabled="!cart.length || paying" @click="chargeFast">
          <span v-if="paying" class="spinner" />
          <template v-else>Charge <kbd>F2</kbd></template>
        </button>
        <button class="btn btn-success checkout-btn" :disabled="!cart.length || paying" @click="chargeAndPrint">
          <span v-if="paying" class="spinner" />
          <template v-else>Charge &amp; Print <kbd>F3</kbd></template>
        </button>
      </div>
      <div class="shortcut-hint">
        <span><kbd>F1</kbd> Search</span>
        <span><kbd>F2</kbd> Charge</span>
        <span><kbd>F3</kbd> Charge &amp; Print</span>
        <span><kbd>Enter</kbd> Add product</span>
        <span><kbd>Esc</kbd> Close</span>
      </div>
    </div>

<div v-if="receipt" class="modal-overlay">
      <div class="receipt card">
          <div class="rec-head">
          <img :src="logoUrl" alt="logo" class="rec-logo" />
          <div class="muted">Receipt #: {{ receipt.invoice_number }}</div>
          <div class="muted rec-inline"><span>{{ receiptDate }}</span><span v-if="receipt.user">User: {{ receipt.user.name }}</span></div>
          <div class="muted rec-inline"><span>NTN: 7483331-2</span><span>Receipt No: {{ receipt.invoice_number }}</span></div>
          <div class="muted">Licence No: 03-311-0032-101403M</div>
          <div class="shop-line">BANGLA ROAD NEAR AGRICULTURE OFFICE, HAROONABAD</div>
          <div class="shop-line shop-contact">
            <span>CONTACT # 0345-2863883</span>
            <svg viewBox="0 0 24 24" fill="#25D366" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </div>
          <div class="muted" v-if="receipt.customer">Customer: {{ receipt.customer.name }}</div>
        </div>
        <div class="rec-items">
          <div v-for="(it, i) in receipt.items" :key="it.id" class="rec-row">
            <div class="rec-no-col">{{ i + 1 }}</div>
            <div class="rec-info">
              <div>{{ it.product?.name }}</div>
              <div class="muted">{{ it.quantity }} × {{ money(it.unit_price) }}</div>
            </div>
            <div>{{ money(it.total) }}</div>
          </div>
        </div>
        <div class="rec-totals">
          <div class="sum-row"><span>Subtotal</span><span>{{ money(receipt.subtotal) }}</span></div>
          <div class="sum-row" v-if="receipt.discount"><span>Discount ({{ discountPct(receipt) }}%)</span><span>− {{ money(receipt.discount) }}</span></div>
          <div class="sum-row" v-if="receipt.tax"><span>TAX</span><span>{{ money(receipt.tax) }}</span></div>
          <div class="sum-row total"><span>Total</span><span>{{ money(receipt.total) }}</span></div>
          <div class="sum-row"><span>Paid</span><span>{{ money(receipt.paid) }}</span></div>
          <div class="sum-row" :class="{ 'rec-due': Number(receipt.due || 0) > 0 }"><span>{{ Number(receipt.due || 0) > 0 ? 'Balance Due' : 'Change' }}</span><span>{{ money(Number(receipt.due || 0) > 0 ? receipt.due : Number(receipt.paid) - Number(receipt.total)) }}</span></div>
        </div>
        <div class="policy policy-rtl">فریج والی اشیاء واپس نہیں ہوں گی۔</div>
        <div class="policy policy-rtl">دوائی بل کے ساتھ 7 دن کے اندر واپس یا تبدیل کی جا سکتی ہے۔</div>
        <div class="raast" v-if="raastQr">
          <div class="raast-title">PAY VIA RAAST ONLINE PAYMENT</div>
          <img :src="raastQr" alt="Raast QR" class="raast-qr" />
          <div class="raast-id">Raast ID: {{ raastId }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RefreshCw, Trash2, Wallet, X } from 'lucide-vue-next'
import api from '../api/client'
import { useProductStore } from '../stores/products'
import { apiMsg, money, paymentLabel, printSaleReceipt, discountPct, RAAST_ID } from '../utils'
import logoUrl from '../assets/thermal-logo.png'
import qrImg from '../assets/QR.png'

const products = useProductStore()
const raastId = RAAST_ID
const raastQr = qrImg
const searchInput = ref(null)
const query = ref('')
const cart = ref([])
const customerId = ref(null)
const received = ref(0)
const discount = ref(0)
const paying = ref(false)
const error = ref('')
const customers = ref([])
const receipt = ref(null)
const distributors = ref([])
const companies = ref([])
const distributorId = ref(null)
const companyId = ref(null)

const boundCompanies = computed(() =>
  companies.value.filter((c) => !distributorId.value || c.distributor_id === distributorId.value)
)

const filtered = computed(() => {
  let list = products.list
  if (distributorId.value) {
    list = list.filter((p) => p.company_model?.distributor_id === distributorId.value)
  }
  if (companyId.value) {
    list = list.filter((p) => p.company_model?.id === companyId.value)
  }
  const term = query.value.trim()
  if (!term) return list.slice(0, 30)
  return products.byName(term, list).slice(0, 30)
})

const selectedCustomer = computed(() =>
  customers.value.find((c) => c.id === customerId.value) || null
)

const customerBalance = computed(() => Number(selectedCustomer.value?.credit || 0))

const dueToBalance = computed(() =>
  selectedCustomer.value ? Math.max(0, total.value - received.value) : 0
)

const advanceGiven = computed(() =>
  selectedCustomer.value ? Math.max(0, received.value - total.value) : 0
)

watch(customerId, (id) => {
  if (!id) received.value = 0
})

const subtotal = computed(() =>
  cart.value.reduce((sum, i) => sum + i.unit_price * i.quantity, 0)
)
const discountTotal = computed(() =>
  (subtotal.value * Math.min(100, Math.max(0, discount.value || 0))) / 100
)
const total = computed(() => Math.max(0, subtotal.value - discountTotal.value))

onMounted(async () => {
  products.ensureLoaded(true).catch(() => {})
  await loadCustomers()
  api.get('/suppliers').then((r) => (distributors.value = r.data)).catch(() => {})
  api.get('/companies').then((r) => (companies.value = r.data)).catch(() => {})
  window.addEventListener('keydown', onKey)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
})

function onKey(e) {
  if (e.key === 'F1') {
    e.preventDefault()
    searchInput.value?.focus()
  } else if (e.key === 'F2') {
    e.preventDefault()
    if (!paying.value && cart.value.length && !receipt.value) chargeFast()
  } else if (e.key === 'F3') {
    e.preventDefault()
    if (!paying.value && cart.value.length && !receipt.value) chargeAndPrint()
  } else if (e.key === 'Escape') {
    if (receipt.value) closeReceipt()
  }
}

function saleError(e) {
  const status = e?.response?.status || 'network'
  const detail = apiMsg(e, 'Something went wrong on the server.')
  return `Payment failed (HTTP ${status}). ${detail}`
}

async function chargeFast() {
  if (!cart.value.length || paying.value) return
  paying.value = true
  error.value = ''
  try {
    const res = await api.post('/sales', salePayload())
    if (!res.data?.sale) throw new Error('empty response')
    resetSale(res.data.sale, { silent: true })
    searchInput.value?.focus()
  } catch (e) {
    error.value = saleError(e)
  } finally {
    paying.value = false
  }
}

async function chargeAndPrint() {
  if (!cart.value.length || paying.value) return
  paying.value = true
  error.value = ''
  try {
    const res = await api.post('/sales', salePayload())
    if (!res.data?.sale) throw new Error('empty response')
    resetSale(res.data.sale)
    printReceipt()
  } catch (e) {
    error.value = saleError(e)
  } finally {
    paying.value = false
  }
}

function printReceipt() {
  if (!receipt.value) return
  printSaleReceipt(receipt.value)
  setTimeout(() => {
    closeReceipt()
    searchInput.value?.focus()
  }, 900)
}

function stockBadgeClass(p) {
  const stock = Number(p.stock)
  if (stock <= 0) return 'badge-red'
  return stock <= Number(p.low_stock_alert || 0) ? 'badge-amber' : 'badge-green'
}

function saleUnit(p) {
  return Number(p.items_per_pack) > 0 ? 'item' : p.unit || ''
}

function unitLabel(count, unit) {
  if (!unit) return ''
  return count === 1 ? unit : `${unit}s`
}

function addToCart(p) {
  const existing = cart.value.find((i) => i.product_id === p.id)
  if (existing) {
    existing.quantity = Math.min(existing.quantity + 1, Number(p.stock))
  } else {
    const price = Number(p.price)
    cart.value.push({
      product_id: p.id,
      name: p.name,
      unit_price: price,
      unit: saleUnit(p),
      quantity: 1,
      max: Number(p.stock),
    })
  }
  discount.value = Math.min(discount.value || 0, 100)
}

function refreshStock() {
  error.value = ''
  products.ensureLoaded(true).catch(() => {})
}

function addSelectedMatch() {
  if (!query.value.trim()) return
  const match = products.byName(query.value.trim())[0]
  if (match) addToCart(match)
}

function changeQty(item, delta) {
  item.quantity = Math.max(1, Math.min(item.quantity + delta, item.max))
  item.quantity = Math.min(item.quantity, item.max)
  if (item.quantity < 1) item.quantity = 1
}

function clampQty(item) {
  if (!item.quantity || item.quantity < 1) item.quantity = 1
  if (item.quantity > item.max) {
    item.quantity = item.max
    error.value = `Only ${item.max} ${item.unit || 'units'} in stock for ${item.name}.`
    setTimeout(() => (error.value = ''), 3500)
  }
}

function removeItem(item) {
  cart.value = cart.value.filter((i) => i.product_id !== item.product_id)
}

function salePayload() {
  const onAccount = Boolean(customerId.value)
  const paid = onAccount ? Math.max(0, Number(received.value) || 0) : total.value
  return {
    items: cart.value.map((i) => ({
      product_id: i.product_id,
      quantity: i.quantity,
      unit_price: i.unit_price,
    })),
    customer_id: customerId.value || null,
    discount: discount.value || 0,
    tax: 0,
    paid,
    payment_method: paid >= total.value ? 'cash' : 'credit',
  }
}

function resetSale(sale, { silent = false } = {}) {
  receipt.value = silent ? null : sale
  cart.value = []
  discount.value = 0
  received.value = 0
  products.invalidate()
  products.ensureLoaded(true)
}

async function loadCustomers() {
  try {
    const res = await api.get('/customers?per_page=200')
    customers.value = res.data.data
  } catch (e) {
    /* non-critical */
  }
}

const receiptDate = computed(() => (receipt.value ? new Date(receipt.value.created_at).toLocaleString() : ''))

function closeReceipt() {
  receipt.value = null
}
</script>

<style scoped>
.pos-layout {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 16px;
  align-items: start;
}
.pos-products { min-height: 560px; display: flex; flex-direction: column; }
.pos-search { position: relative; margin-bottom: 16px; }
.pos-search .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--muted); }
.pos-search .input { padding-left: 38px; padding-right: 38px; }
.pos-search .icon-btn { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); }
.pos-search .refresh-btn { right: 40px; }
.pos-search .refresh-btn svg { width: 16px; height: 16px; }
.pos-filters { display: flex; gap: 10px; margin-bottom: 14px; }
.pos-filters .filter-select { max-width: 240px; }

.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px; overflow-y: auto; max-height: 640px; padding: 2px; }
.product-tile {
  text-align: left;
  background: #f8fafc;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.15s;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.product-tile:hover:not(:disabled) { border-color: var(--primary); background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow); }
.product-tile:disabled { opacity: 0.45; cursor: not-allowed; }
.tile-name { font-weight: 600; font-size: 13px; }
.tile-sub { font-size: 11.5px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tile-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
.tile-price { font-weight: 700; color: var(--primary-dark); font-size: 13.5px; }
.tile-unit { font-weight: 500; font-size: 10.5px; color: var(--muted); }
.tile-pack { font-size: 10.5px; color: var(--primary-dark); font-weight: 700; }

.pos-cart { position: sticky; top: 76px; display: flex; flex-direction: column; gap: 14px; }
.cart-head { display: flex; justify-content: space-between; align-items: center; }
.cart-body { display: grid; grid-template-columns: 1fr auto; gap: 10px; }
.customer-balance {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--primary-light); color: var(--green);
  border: 1px solid var(--border); border-radius: 8px;
  padding: 6px 10px; font-weight: 700; font-size: 13.5px;
}
.customer-balance.due { background: #fef2f2; color: var(--danger); }
.customer-balance.good { background: #ecfdf5; color: var(--green); }
.received-row-total { font-weight: 700; font-size: 16px; margin-top: 4px; }
.received-amount-input {
  width: 130px;
  text-align: right;
  font-size: 16px;
  font-weight: 700;
  border: none;
  border-bottom: 2px solid var(--primary);
  border-radius: 0;
  padding: 2px 4px;
  background: transparent;
  color: inherit;
  outline: none;
}
.received-amount-input:focus { border-bottom-color: var(--primary-dark); }
.received-hint { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 600; justify-content: flex-end; margin-top: 2px; }
.received-hint.due { color: var(--danger); }
.received-hint.ok { color: var(--green); }
.cart-items { max-height: 300px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
.cart-row { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px dashed var(--border); }
.cart-info { flex: 1; min-width: 0; }
.cart-name { font-weight: 600; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cart-price { font-size: 11.5px; color: var(--muted); }
.cart-qty { display: flex; align-items: center; gap: 4px; }
.qty-btn {
  width: 26px; height: 26px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: #fff;
  cursor: pointer;
  font-weight: 700;
  color: var(--muted);
  display: grid; place-items: center;
}
.qty-btn:hover { background: var(--bg); color: var(--text); }
.cart-qty input { width: 44px; text-align: center; padding: 5px 2px; font-size: 12.5px; }
.cart-total { width: 76px; text-align: right; font-weight: 600; font-size: 13px; }
.remove { color: var(--muted); }
.remove:hover { color: var(--danger); background: #fee2e2; }

.cart-summary { display: flex; flex-direction: column; gap: 6px; border-top: 1px solid var(--border); padding-top: 12px; }
.sum-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
.cart-summary .sum-row:last-child { font-weight: 700; font-size: 16px; margin-top: 4px; }
.discount-input { display: flex; align-items: center; gap: 6px; }
.discount-input .input { width: 90px; text-align: right; padding: 5px 8px; }
.rec-due { color: var(--danger); font-weight: 700; }

.checkout-btn { width: 100%; padding: 13px; font-size: 15px; justify-content: center; }
.checkout-row { display: flex; gap: 10px; }
.checkout-row .checkout-btn { flex: 1; }
kbd {
  background: rgba(255, 255, 255, 0.28);
  border: 1px solid rgba(255, 255, 255, 0.4);
  border-radius: 4px;
  font-size: 10.5px;
  padding: 1px 5px;
  font-family: Consolas, monospace;
}
.btn-secondary kbd { background: var(--bg); border-color: var(--border); color: var(--muted); }
.shortcut-hint {
  display: flex;
  gap: 10px;
  justify-content: center;
  flex-wrap: wrap;
  font-size: 11px;
  color: var(--muted);
  margin-top: 6px;
}
.shortcut-hint kbd {
  background: var(--bg);
  border: 1px solid var(--border);
  border-bottom-width: 2px;
  border-radius: 4px;
  padding: 1px 5px;
  font-family: Consolas, monospace;
  font-size: 10.5px;
  color: var(--text);
}

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center;
  z-index: 50;
  padding: 20px;
}
.modal { width: 100%; max-width: 360px; display: flex; flex-direction: column; gap: 12px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 6px; }
.modal-actions .btn { flex: 1; justify-content: center; }

.receipt { width: 100%; max-width: 330px; font-size: 13px; }
.rec-head { text-align: center; border-bottom: 1px dashed var(--border); padding-bottom: 12px; margin-bottom: 12px; }
.rec-logo { width: 145px; margin-bottom: 6px; background: #fff; }
.rec-head h3 { font-size: 16px; }
.rec-inline { display: flex; justify-content: space-between; gap: 10px; font-size: 10.5px; }
.shop-line { font-size: 11px; font-weight: 600; line-height: 1.35; }
.shop-contact { display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
.shop-contact svg { width: 12px; height: 12px; }
.policy { text-align: center; margin-top: 8px; font-size: 11.5px; font-weight: 700; line-height: 1.5; }
.policy-rtl { direction: rtl; unicode-bidi: embed; font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, Arial, sans-serif; font-weight: 700; }
.raast { text-align: center; margin-top: 12px; }
.raast-title { font-weight: 900; font-size: 11px; letter-spacing: 1px; margin-bottom: 4px; }
.raast-qr { width: 110px; height: 110px; margin: 0 auto; background: #fff; padding: 4px; }
.raast-id { margin-top: 4px; font-family: Consolas, monospace; font-size: 10.5px; font-weight: 600; letter-spacing: 0; white-space: nowrap; color: #111; }
.muted { color: var(--muted); font-size: 12px; }
.rec-items { display: flex; flex-direction: column; margin-bottom: 10px; }
.rec-row { display: flex; justify-content: space-between; gap: 10px; padding: 5px 0; }
.rec-no-col { width: 14px; color: var(--muted); flex-shrink: 0; }
.rec-totals { border-top: 1px dashed var(--border); padding-top: 10px; display: flex; flex-direction: column; gap: 5px; }
.rec-totals .sum-row { font-size: 13.5px; }
.rec-totals .sum-row.total { font-weight: 700; font-size: 16px; }
.rec-head + .rec-totals .sum-row.total { border-top: none; }
.room-footer { display: flex; gap: 10px; justify-content: space-between; margin-top: 16px; }

@media (max-width: 1000px) {
  .pos-layout { grid-template-columns: 1fr; }
  .pos-cart { position: static; }
}
</style>