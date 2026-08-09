<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal card">
      <h3>Batches — {{ product?.name }}</h3>
      <div v-if="!batches.length" class="empty">No batches for this product.</div>
      <table v-else class="table">
        <thead><tr><th>Batch #</th><th>Expiry</th><th style="text-align:right">Qty</th><th></th></tr></thead>
        <tbody>
          <tr v-for="b in batches" :key="b.id">
            <td class="mono">{{ b.batch_number }}</td>
            <td>
              <span class="badge" :class="b.expiry_date < todayIso ? 'badge-red' : 'badge-gray'">
                {{ b.expiry_date }}
              </span>
            </td>
            <td style="text-align:right">{{ b.quantity }}</td>
            <td style="text-align:right">
              <button class="btn btn-sm btn-secondary" style="color: var(--danger)" @click="remove(b)">
                <Trash2 />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="modal-actions">
        <button class="btn btn-secondary" @click="$emit('close')">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { Trash2 } from 'lucide-vue-next'
import api from '../api/client'
import { apiMsg } from '../utils'

const props = defineProps({ product: { type: Object, required: true } })
const emit = defineEmits(['close', 'saved'])

const batches = ref([])
const todayIso = new Date().toISOString().slice(0, 10)

onMounted(async () => {
  try {
    const res = await api.get(`/products/${props.product.id}`)
    batches.value = res.data.batches
  } catch (e) {
    alert(apiMsg(e))
  }
})

async function remove(b) {
  if (!confirm(`Delete batch ${b.batch_number}?`)) return
  try {
    await api.delete(`/products/batches/${b.id}`)
    batches.value = batches.value.filter((x) => x.id !== b.id)
    emit('saved')
  } catch (e) {
    alert(apiMsg(e))
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
  display: grid; place-items: center; z-index: 60; padding: 20px;
}
.modal { width: 100%; max-width: 520px; }
.mono { font-family: Consolas, monospace; font-size: 12px; }
.modal-actions { display: flex; justify-content: flex-end; margin-top: 14px; }
</style>