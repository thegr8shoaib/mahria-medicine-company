<template>
  <div class="modal-overlay">
    <div class="modal card">
      <h3>{{ product ? 'Edit Product' : 'New Product' }}</h3>
      <p v-if="error" class="alert-error">{{ error }}</p>
      <form @submit.prevent="submit">
        <div class="form-grid">
          <div>
            <label class="label">Name *</label>
            <input v-model="form.name" class="input" required />
          </div>
          <div>
            <label class="label">Company (manufacturer)</label>
            <input v-model="form.company" class="input" list="company-list" @change="syncCompany" />
            <datalist id="company-list">
              <option v-for="c in companies" :key="c.id" :value="c.name" />
            </datalist>
          </div>
          <div>
            <label class="label">Distributor (seller)</label>
            <select v-model="distributorId" class="select" @change="onDistChange">
              <option :value="null">— None —</option>
              <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <p v-if="rebindHint" class="rebind-hint">{{ rebindHint }}</p>
          </div>
          <div>
            <label class="label">Generic name</label>
            <input v-model="form.generic_name" class="input" />
          </div>
          <div>
            <label class="label">Therapeutic category</label>
            <input v-model="form.category" class="input" />
          </div>
          <div class="form-span">
            <label class="label">Product line / variants</label>
            <input v-model="form.variants" class="input" />
          </div>
          <div>
            <label class="label">SKU *</label>
            <input v-model="form.sku" class="input" required />
          </div>
          <div>
            <label class="label">Barcode</label>
            <input v-model="form.barcode" class="input" />
          </div>
          <div>
            <label class="label">{{ isPack ? 'Sale price per item (Rs) *' : 'Sale price (Rs) *' }}</label>
            <input v-model.number="form.price" type="number" min="0" step="0.01" class="input" required />
          </div>
          <div>
            <label class="label">{{ isPack ? 'Cost price per item (Rs)' : 'Cost price (Rs)' }}</label>
            <input v-model.number="form.cost_price" type="number" min="0" step="0.01" class="input" />
          </div>
          <div>
            <label class="label">Unit</label>
            <select v-model="form.unit" class="select">
              <option value="tablet">Tablet</option>
              <option value="capsule">Capsule</option>
              <option value="syrup">Syrup</option>
              <option value="injection">Injection</option>
              <option value="ointment">Ointment</option>
              <option value="drops">Drops</option>
              <option value="pack">Pack (items)</option>
            </select>
            <p v-if="isPack" class="pack-note">Items per pack is entered at purchase time.</p>
          </div>
          <div>
            <label class="label">Low stock alert</label>
            <input v-model.number="form.low_stock_alert" type="number" min="0" class="input" />
          </div>
        </div>
        <label class="check">
          <input type="checkbox" v-model="form.is_active" />
          Active (sellable in POS)
        </label>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
          <button type="submit" class="btn" :disabled="saving">
            <span v-if="saving" class="spinner" /> {{ product ? 'Save' : 'Create' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '../api/client'
import { apiMsg } from '../utils'

const props = defineProps({
  product: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')
const distributors = ref([])
const companies = ref([])
const distributorId = ref(null)

const boundCompanies = computed(() =>
  companies.value.filter((c) => !distributorId.value || c.distributor_id === distributorId.value)
)

const isPack = computed(() => form.unit === 'pack')

const rebindHint = computed(() => {
  const typed = form.company.trim()
  if (!typed || !distributorId.value) return ''
  const existing = companies.value.find((c) => c.name.toLowerCase() === typed.toLowerCase())
  if (!existing || existing.distributor_id === distributorId.value) return ''
  const target = distributors.value.find((d) => d.id === distributorId.value)
  return `"${existing.name}" (and its ${existing.products_count || 0} products) will move to ${target?.name || 'new distributor'} on save.`
})

const form = reactive({
  name: props.product?.name || '',
  company: props.product?.company || '',
  generic_name: props.product?.generic_name || '',
  category: props.product?.category || '',
  variants: props.product?.variants || '',
  sku: props.product?.sku || '',
  barcode: props.product?.barcode || '',
  price: props.product?.price || 0,
  cost_price: props.product?.cost_price || 0,
  unit: props.product?.unit || 'tablet',
  low_stock_alert: props.product?.low_stock_alert || 10,
  is_active: props.product ? Boolean(props.product.is_active) : true,
})

function syncCompany() {
  const match = boundCompanies.value.find((c) => c.name.toLowerCase() === form.company.trim().toLowerCase())
  if (match) distributorId.value = match.distributor_id
}

function onDistChange() {
  const match = boundCompanies.value.find((c) => c.name.toLowerCase() === form.company.trim().toLowerCase())
  if (match) form.company = match.name
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    let companyId = null
    const typed = form.company.trim()
    if (typed) {
      const existing = companies.value.find((c) => c.name.toLowerCase() === typed.toLowerCase())
      if (existing) {
        companyId = existing.id
        form.company = existing.name
        if (distributorId.value && existing.distributor_id !== distributorId.value) {
          await api.put(`/companies/${existing.id}`, { distributor_id: distributorId.value })
          existing.distributor_id = distributorId.value
        }
      } else if (distributorId.value) {
        const res = await api.post('/companies', {
          name: typed,
          distributor_id: distributorId.value,
        })
        companyId = res.data.company.id
        companies.value.push(res.data.company)
      }
    }
    const payload = { ...form, company_id: companyId }
    if (props.product) {
      await api.put(`/products/${props.product.id}`, payload)
    } else {
      await api.post('/products', payload)
    }
    emit('saved')
    emit('close')
  } catch (e) {
    error.value = apiMsg(e, 'Could not save product.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const [d, c] = await Promise.all([api.get('/suppliers'), api.get('/companies')])
    distributors.value = d.data
    companies.value = c.data
    if (props.product?.company) syncCompany()
  } catch (e) {
    /* optional metadata; form still works */
  }
})
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center;
  z-index: 60; padding: 20px;
}
.modal { width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; padding: 4px 2px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
.form-span { grid-column: 1 / -1; }
.rebind-hint { margin-top: 6px; font-size: 12px; color: var(--danger, #dc2626); font-weight: 600; line-height: 1.4; }
.pack-hint { margin-top: 2px; font-size: 12.5px; font-weight: 700; color: var(--primary-dark, #1d4ed8); }
.pack-note { margin-top: 2px; font-size: 12px; color: var(--muted); line-height: 1.4; }
.check { display: flex; align-items: center; gap: 8px; margin-top: 14px; font-size: 13px; cursor: pointer; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
</style>