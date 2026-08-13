<template>
  <div>
    <div class="page-header">
      <h1>Data Backup</h1>
    </div>

    <div class="card backup-card">
      <div class="card-row">
        <div class="info">
          <h3>Full Backup</h3>
          <p class="muted">
            Creates a complete snapshot of the database plus settings (receipt logo, QR &amp; avatars) and stores it on
            this computer. Older backups are removed automatically — the latest 10 are kept.
          </p>
        </div>
        <button class="btn" :disabled="running" @click="createBackup">
          <span v-if="running" class="spinner" />
          <template v-else><Database /> Create Full Backup</template>
        </button>
      </div>
      <p v-if="lastMessage" class="alert-success">{{ lastMessage }}</p>
    </div>

    <div class="card backup-card">
      <div class="card-row">
        <div class="info">
          <h3>Excel Export</h3>
          <p class="muted">Export selected records as an Excel workbook (one sheet per section).</p>
        </div>
        <button class="btn btn-success" :disabled="exporting || !chosen.length" @click="exportExcel">
          <span v-if="exporting" class="spinner" />
          <template v-else><FileSpreadsheet /> Export Excel</template>
        </button>
      </div>
      <div class="export-options">
        <label v-for="s in sections" :key="s.key" class="check">
          <input type="checkbox" :value="s.key" v-model="chosen" />
          {{ s.label }}
        </label>
        <button class="btn btn-sm btn-secondary" @click="chosen = sections.map((s) => s.key)">All</button>
        <button class="btn btn-sm btn-secondary" @click="chosen = []">None</button>
      </div>
    </div>

    <div class="card backup-card">
      <div class="card-row">
        <div class="info">
          <h3>Restore / Upload Data</h3>
          <p class="muted">
            Upload a backup file (<code>.sqlite</code>) to restore the database to that state.
            The current data is automatically saved as a backup first, so nothing is lost.
            You will be signed out after a restore.
          </p>
        </div>
        <button class="btn btn-secondary" :disabled="restoring" @click="pickRestore">
          <span v-if="restoring" class="spinner" />
          <template v-else><Upload /> Choose File &amp; Restore</template>
        </button>
        <input ref="restoreInput" type="file" accept=".sqlite,.db,application/octet-stream" class="hidden" @change="onRestoreFile" />
      </div>
      <p v-if="restoreMsg" class="alert-success">{{ restoreMsg }}</p>
      <p v-if="restoreErr" class="alert-error">{{ restoreErr }}</p>
    </div>

    <div v-if="loading" class="card loading"><span class="spinner spinner-dark" /></div>

    <div v-else class="card" style="padding: 0; overflow: auto">
      <table class="table">
        <thead>
          <tr><th>Backup</th><th>Created</th><th style="text-align:right">Size</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="b in backups" :key="b.folder">
            <td class="mono">{{ b.folder }}</td>
            <td>{{ b.created_at }}</td>
            <td style="text-align:right">{{ formatSize(b.size) }}</td>
            <td style="text-align:right; white-space: nowrap">
              <button class="btn btn-sm btn-secondary" @click="download(b)"><Download /> Download</button>
              <button class="btn btn-sm btn-secondary" style="color: var(--danger)" @click="removeBackup(b)"><Trash2 /></button>
            </td>
          </tr>
          <tr v-if="!backups.length">
            <td colspan="4" class="empty">No backups yet. Click "Create Full Backup" to make one.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { Database, Download, FileSpreadsheet, Trash2, Upload } from 'lucide-vue-next'
import api from '../api/client'
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'
import { apiMsg } from '../utils'

const auth = useAuthStore()
const router = useRouter()

const sections = [
  { key: 'inventory', label: 'Inventory' },
  { key: 'sales', label: 'Sales' },
  { key: 'purchases', label: 'Purchases' },
  { key: 'distributors', label: 'Distributors' },
  { key: 'customers', label: 'Customers' },
]

const backups = ref([])
const loading = ref(true)
const running = ref(false)
const exporting = ref(false)
const chosen = ref(sections.map((s) => s.key))
const lastMessage = ref('')
const restoring = ref(false)
const restoreErr = ref('')
const restoreMsg = ref('')
const restoreInput = ref(null)

function pickRestore() {
  restoreErr.value = ''
  restoreMsg.value = ''
  restoreInput.value?.click()
}

async function onRestoreFile(e) {
  const f = e.target.files?.[0]
  e.target.value = ''
  if (!f) return
  if (!confirm('Restore this backup?\n\nThe CURRENT data will be saved as a backup first, then replaced. You will be signed out.')) return
  restoring.value = true
  restoreErr.value = ''
  restoreMsg.value = ''
  const fd = new FormData()
  fd.append('file', f)
  try {
    const res = await api.post('/backup/restore', fd)
    restoreMsg.value = res.data.message
    restoreInput.value && (restoreInput.value.value = '')
    setTimeout(() => router.push('/login'), 1500)
    auth.logout()
  } catch (err) {
    restoreErr.value = apiMsg(err)
  } finally {
    restoring.value = false
  }
}

function formatSize(bytes) {
  if (!bytes) return '—'
  const kb = bytes / 1024
  return kb >= 1024 ? `${(kb / 1024).toFixed(2)} MB` : `${kb.toFixed(0)} KB`
}

function saveBlob(data, filename) {
  const url = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 4000)
}

async function load() {
  loading.value = true
  try {
    const res = await api.get('/backup')
    backups.value = res.data
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    loading.value = false
  }
}

async function createBackup() {
  running.value = true
  lastMessage.value = ''
  try {
    const res = await api.post('/backup')
    lastMessage.value = `${res.data.message} (${formatSize(res.data.size)})`
    await load()
  } catch (e) {
    alert(apiMsg(e, 'Backup failed.'))
  } finally {
    running.value = false
  }
}

function download(b) {
  api
    .get(`/backup/${b.folder}/database.sqlite`, { responseType: 'blob' })
    .then((res) => saveBlob(res.data, `mahria-backup-${b.folder}.sqlite`))
    .catch((e) => alert(apiMsg(e)))
}

function removeBackup(b) {
  if (!confirm(`Delete backup ${b.folder}? This cannot be undone.`)) return
  api
    .post('/backup/delete', { folder: b.folder })
    .then(() => load())
    .catch((e) => alert(apiMsg(e)))
}

async function exportExcel() {
  exporting.value = true
  try {
    const res = await api.post('/backup/export-excel', { sections: chosen.value }, { responseType: 'blob' })
    const stamp = new Date().toISOString().slice(0, 10)
    saveBlob(res.data, `mahria-export-${stamp}.xlsx`)
  } catch (e) {
    const detail = e?.response?.data?.message || apiMsg(e)
    alert(detail)
  } finally {
    exporting.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.backup-card { display: flex; flex-direction: column; gap: 12px; }
.card-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.card-row .info h3 { margin-bottom: 4px; }
.card-row .btn { white-space: nowrap; }
.export-options { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.check { display: inline-flex; align-items: center; gap: 6px; font-size: 13.5px; cursor: pointer; }
.btn .icon { width: 15px; height: 15px; }
.hidden { display: none; }
.alert-success { background: #ecfdf5; color: var(--green, #16a34a); border: 1px solid #a7f3d0; border-radius: 8px; padding: 8px 12px; font-size: 13px; }
</style>