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
            <label class="label">Company</label>
            <input v-model="form.company" class="input" />
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
            <label class="label">Sale price (Rs) *</label>
            <input v-model.number="form.price" type="number" min="0" step="0.01" class="input" required />
          </div>
          <div>
            <label class="label">Cost price (Rs)</label>
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
              <option value="pack">Pack</option>
            </select>
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
import { reactive, ref } from 'vue'
import api from '../api/client'
import { apiMsg } from '../utils'

const props = defineProps({
  product: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')

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

async function submit() {
  saving.value = true
  error.value = ''
  try {
    if (props.product) {
      await api.put(`/products/${props.product.id}`, form)
    } else {
      await api.post('/products', form)
    }
    emit('saved')
    emit('close')
  } catch (e) {
    error.value = apiMsg(e, 'Could not save product.')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center;
  z-index: 60; padding: 20px;
}
.modal { width: 100%; max-width: 560px; display: flex; flex-direction: column; gap: 14px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
.form-span { grid-column: 1 / -1; }
.check { display: flex; align-items: center; gap: 8px; margin-top: 14px; font-size: 13px; cursor: pointer; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
</style>