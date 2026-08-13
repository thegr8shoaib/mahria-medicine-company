<template>
  <div>
    <div class="page-header">
      <h1>Distributors</h1>
      <div class="header-actions">
        <button class="btn" @click="openDistModal()"><Plus /> New Distributor</button>
      </div>
    </div>

    <div v-if="loading" class="card loading"><span class="spinner spinner-dark" /></div>
    <div v-else-if="!distributors.length" class="card empty">No distributors yet. Add your first distributor.</div>

    <div v-else class="dist-list">
      <div v-for="d in distributors" :key="d.id" class="card dist-card">
        <div class="dist-head" @click="toggle(d.id)">
          <div class="dist-info">
            <div class="dist-name">
              <Building2 class="icon" /> {{ d.name }}
              <span class="badge badge-gray">{{ d.companies_count }} companies</span>
              <span class="badge badge-blue">{{ d.company_products_count || 0 }} products</span>
            </div>
            <div class="muted" v-if="d.phone || d.email">{{ [d.phone, d.email].filter(Boolean).join(' · ') }}</div>
          </div>
          <div class="dist-actions">
            <button class="btn btn-sm btn-secondary" @click.stop="openDistModal(d)"><Pencil /></button>
            <button class="btn btn-sm btn-secondary" @click.stop="removeDist(d)"><Trash2 /></button>
            <ChevronDown class="icon chevron" :class="{ open: openIds.includes(d.id) }" />
          </div>
        </div>

        <div v-if="openIds.includes(d.id)" class="dist-body">
          <div class="dist-toolbar">
            <h4>Bound Companies ({{
              companies.filter((c) => c.distributor_id === d.id).length
            }})</h4>
            <button class="btn btn-sm" @click="openCompanyModal(d)"><Plus /> Bind Company</button>
          </div>
          <p v-if="!companies.filter((c) => c.distributor_id === d.id).length" class="muted" style="font-size: 13px">
            No companies bound yet. Bind a company (e.g. Abbott Laboratories) and its products will appear
            under this distributor in Inventory &amp; POS.
          </p>
          <table v-else class="table company-table">
            <thead>
              <tr><th>Company</th><th>Products</th><th style="text-align:right">Actions</th></tr>
            </thead>
            <tbody>
              <tr v-for="c in companies.filter((x) => x.distributor_id === d.id)" :key="c.id">
                <td style="font-weight: 600">{{ c.name }}</td>
                <td><span class="badge badge-gray">{{ c.products_count }}</span></td>
                <td style="text-align: right; white-space: nowrap">
                  <a href="#/inventory" class="btn btn-sm btn-secondary" @click.prevent="goInventory(d, c)">
                    <Boxes /> Products
                  </a>
                  <button class="btn btn-sm btn-secondary" @click="openCompanyModal(d, c)"><Pencil /></button>
                  <button class="btn btn-sm btn-secondary" style="color: var(--danger)" @click="removeCompany(c)"><Trash2 /></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="showDist" class="modal-overlay">
      <div class="modal card">
        <h3>{{ editingDist ? 'Edit Distributor' : 'New Distributor' }}</h3>
        <p class="muted" style="font-size: 12.5px; line-height: 1.5">
          Distributors (e.g. Data Medicine Company, Muller &amp; Phipps) supply you products from bound
          companies. You can attach companies to each distributor.
        </p>
        <p v-if="distErr" class="alert-error">{{ distErr }}</p>
        <input v-model="distForm.name" class="input" placeholder="Distributor name *" />
        <input v-model="distForm.phone" class="input" placeholder="Phone" />
        <input v-model="distForm.email" class="input" placeholder="Email" />
        <textarea v-model="distForm.address" class="input" rows="2" placeholder="Address" />
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showDist = false">Cancel</button>
          <button class="btn" :disabled="saving" @click="saveDist">
            <span v-if="saving" class="spinner" />
            <template v-else>{{ editingDist ? 'Save' : 'Add Distributor' }}</template>
          </button>
        </div>
      </div>
    </div>

    <div v-if="showCompany" class="modal-overlay">
      <div class="modal card">
        <h3>{{ editingCompany ? 'Edit Company' : 'Bind New Company' }}</h3>
        <p class="muted" style="font-size: 12.5px">
          Binding {{ editingCompany ? 'company' : 'company to distributor' }}:
          <b>{{ companyDist?.name }}</b>
        </p>
        <p v-if="compErr" class="alert-error">{{ compErr }}</p>
        <input v-model="companyForm.name" class="input" placeholder="Company name (e.g. Abbott Laboratories) *"
               list="company-suggestions" />
        <datalist id="company-suggestions">
          <option v-for="n in allCompanyNames" :key="n" :value="n" />
        </datalist>
        <div v-if="!editingCompany" class="modal-note">
          Tip: pick an existing company to re-bind it, or type a brand-new one.
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showCompany = false">Cancel</button>
          <button class="btn" :disabled="savingComp" @click="saveCompany">
            <span v-if="savingComp" class="spinner" />
            <template v-else>{{ editingCompany ? 'Save' : 'Bind Company' }}</template>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Boxes, Building2, ChevronDown, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import api from '../api/client'
import { apiMsg } from '../utils'

const router = useRouter()
const distributors = ref([])
const companies = ref([])
const loading = ref(true)

const showDist = ref(false)
const editingDist = ref(null)
const saving = ref(false)
const distErr = ref('')
const distForm = reactive({ name: '', phone: '', email: '', address: '' })

const showCompany = ref(false)
const editingCompany = ref(null)
const companyDist = ref(null)
const savingComp = ref(false)
const compErr = ref('')
const companyForm = reactive({ name: '' })

const openIds = ref([])

const allCompanyNames = computed(() => [...new Set(companies.value.map((c) => c.name))].sort())

function toggle(id) {
  openIds.value = openIds.value.includes(id)
    ? openIds.value.filter((x) => x !== id)
    : [...openIds.value, id]
}

async function load() {
  loading.value = true
  try {
    const [d, c] = await Promise.all([api.get('/suppliers'), api.get('/companies')])
    distributors.value = d.data
    companies.value = c.data
  } catch (e) {
    alert(apiMsg(e))
  } finally {
    loading.value = false
  }
}

function openDistModal(d = null) {
  editingDist.value = d
  distForm.name = d?.name || ''
  distForm.phone = d?.phone || ''
  distForm.email = d?.email || ''
  distForm.address = d?.address || ''
  distErr.value = ''
  showDist.value = true
}

async function saveDist() {
  if (!distForm.name.trim()) return
  saving.value = true
  distErr.value = ''
  try {
    if (editingDist.value) {
      await api.put(`/suppliers/${editingDist.value.id}`, distForm)
    } else {
      await api.post('/suppliers', distForm)
    }
    showDist.value = false
    await load()
  } catch (e) {
    distErr.value = apiMsg(e)
  } finally {
    saving.value = false
  }
}

async function removeDist(d) {
  if (!confirm(`Delete distributor "${d.name}"? Its companies (not products) will be removed too.`)) return
  try {
    await api.delete(`/suppliers/${d.id}`)
    await load()
  } catch (e) {
    alert(apiMsg(e))
  }
}

function openCompanyModal(dist, c = null) {
  editingCompany.value = c
  companyDist.value = dist
  companyForm.name = c?.name || ''
  compErr.value = ''
  showCompany.value = true
}

async function saveCompany() {
  if (!companyForm.name.trim()) return
  savingComp.value = true
  compErr.value = ''
  try {
    const payload = { name: companyForm.name.trim(), distributor_id: companyDist.value.id }
    if (editingCompany.value) {
      await api.put(`/companies/${editingCompany.value.id}`, payload)
    } else {
      await api.post('/companies', payload)
    }
    showCompany.value = false
    await load()
  } catch (e) {
    compErr.value = apiMsg(e)
  } finally {
    savingComp.value = false
  }
}

async function removeCompany(c) {
  if (!confirm(`Unbind company "${c.name}"? Its products stay in Inventory with company name only.`)) return
  try {
    await api.delete(`/companies/${c.id}`)
    await load()
  } catch (e) {
    alert(apiMsg(e))
  }
}

function goInventory(dist, comp) {
  router.push({ path: '/inventory', query: { distributor_id: dist.id, company_id: comp.id } })
}

onMounted(load)
</script>

<style scoped>
.header-actions { display: flex; gap: 8px; }
.dist-list { display: flex; flex-direction: column; gap: 12px; }
.dist-card { padding: 0; overflow: hidden; }
.dist-head {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 14px 18px; cursor: pointer;
}
.dist-head:hover { background: var(--surface-2); }
.dist-info { display: flex; flex-direction: column; gap: 2px; }
.dist-name { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; }
.dist-name .icon { width: 16px; height: 16px; color: var(--primary); }
.dist-actions { display: flex; align-items: center; gap: 6px; }
.chevron { transition: transform 0.2s; width: 18px; height: 18px; }
.chevron.open { transform: rotate(180deg); }
.dist-body { border-top: 1px solid var(--border); padding: 14px 18px; }
.dist-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.dist-toolbar h4 { font-size: 13px; }
.company-table { font-size: 13.5px; }
.company-table .icon { width: 14px; height: 14px; }
.modal { width: 100%; max-width: 440px; display: flex; flex-direction: column; gap: 10px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 6px; }
.modal-note { font-size: 12px; color: var(--muted); }
.muted { color: var(--muted); font-size: 12.5px; }
</style>