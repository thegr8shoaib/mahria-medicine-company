<template>
  <div class="modal-overlay">
    <div class="modal card">
      <h3>Add Batch — {{ product?.name }}</h3>
      <p v-if="error" class="alert-error">{{ error }}</p>
      <form @submit.prevent="submit">
        <div class="row">
          <div>
            <label class="label">Batch number *</label>
            <input v-model="form.batch_number" class="input" required />
          </div>
          <div>
            <label class="label">Quantity *</label>
            <input v-model.number="form.quantity" type="number" min="1" class="input" required />
          </div>
        </div>
        <label class="label" style="margin-top: 12px">Expiry date *</label>
        <input v-model="form.expiry_date" type="date" class="input" required />
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
          <button type="submit" class="btn" :disabled="saving">
            <span v-if="saving" class="spinner" /> Add Stock
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

const props = defineProps({ product: { type: Object, required: true } })
const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')
const form = reactive({ batch_number: '', quantity: 0, expiry_date: '' })

async function submit() {
  saving.value = true
  error.value = ''
  try {
    await api.post(`/products/${props.product.id}/batches`, form)
    emit('saved')
    emit('close')
  } catch (e) {
    error.value = apiMsg(e, 'Could not add batch.')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center; z-index: 60; padding: 20px;
}
.modal { width: 100%; max-width: 420px; display: flex; flex-direction: column; gap: 14px; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
</style>