import logoUrl from './assets/logo.png'

export function money(n) {
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency: 'PKR',
    maximumFractionDigits: 2,
  }).format(Number(n || 0))
}

export function apiMsg(e, fallback = 'Something went wrong.') {
  const data = e?.response?.data
  if (data?.errors) return Object.values(data.errors).flat().join(', ')
  if (data && typeof data.message === 'string') return data.message
  return fallback
}

export function formatTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString(undefined, {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

export function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))
}

export function paymentLabel(m) {
  return m === 'cash' ? 'Cash' : m === 'card' ? 'Card' : m === 'mobile' ? 'Mobile Wallet' : m === 'credit' ? 'Credit' : m || '—'
}

export function receiptHtml(sale, { reprinted = false } = {}) {
  const n = (v) => Number(v || 0).toFixed(2)
  const w = (v) => Math.round(Number(v || 0)).toString()
  const items = (sale.items || []).map(
    (it) => `<tr>
      <td class="name" title="${esc(it.product?.name || '—')}">${esc(it.product?.name || '—')}</td>
      <td class="r">${it.quantity}</td>
      <td class="r">${n(it.unit_price)}</td>
      <td class="r">${n(it.total)}</td>
    </tr>`
  ).join('')
  const change = n(Number(sale.paid || 0) - Number(sale.total || 0))
  const due = n(Number(sale.due || 0))

  return `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
  <title>Receipt ${esc(sale.invoice_number)}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; width: 270px; margin: 0 auto; padding: 16px 8px; font-size: 12.5px; color: #111; }
    .logo-txt { text-align: center; line-height: 1.25; }
    .logo-line1 { font-weight: 900; font-size: 20px; letter-spacing: 4px; color: #0b3d5c; }
    .logo-line2 { font-weight: 700; font-size: 10.5px; letter-spacing: 3.5px; color: #111; margin-top: 2px; }
    .contact { text-align: center; font-weight: 700; font-size: 13px; margin-top: 2px; }
    .logo-wrap { text-align: center; margin-bottom: 4px; }
    .logo { max-width: 110px; max-height: 90px; }
    .muted { color: #444; font-size: 11.5px; }
    .info { font-weight: 600; font-size: 12.5px; color: #111; }
    .divider { border-top: 1px solid #666; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 3px 2px; white-space: nowrap; }
    th { text-align: left; font-size: 10.5px; text-transform: uppercase; border-bottom: 1px solid #444; }
    td { font-size: 12px; }
    .r { text-align: right; }
    .name { max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .totals { margin-top: 3px; }
    .totals div { display: flex; justify-content: space-between; padding: 2px 0; }
    .totals .line { border-bottom: 1px dashed #999; }
    .totals .big { font-weight: 700; font-size: 14px; border-top: 1px solid #444; margin-top: 4px; padding-top: 5px; }
    .footer { text-align: center; margin-top: 12px; font-size: 11px; }
    .rec-no { text-align: center; font-weight: 700; font-size: 12px; }
    .reprinted { text-align: center; font-weight: 900; font-size: 11.5px; letter-spacing: 2px; color: #b91c1c; border: 2px solid #b91c1c; padding: 4px 0; margin-top: 6px; }
  </style></head><body>
    <div class="logo-txt">
      <div class="logo-line1">MEHRIA</div>
      <div class="logo-line2">MEDICINE COMPANY</div>
    </div>
    ${reprinted ? '<div class="reprinted">* REPRINTED *</div>' : ''}
    <div class="divider"></div>
    <div class="info">Date: ${new Date(sale.created_at).toLocaleString()}</div>
    <div class="info">Receipt No: ${esc(sale.invoice_number)}</div>
    <div class="info">User: ${esc(sale.user?.name || '—')}</div>
    <div class="divider"></div>
    <table>
      <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Amt</th></tr></thead>
      <tbody>${items}</tbody>
    </table>
    <div class="divider"></div>
    <div class="totals">
      <div><span>Subtotal</span><span>${n(sale.subtotal)}</span></div>
      ${sale.discount ? `<div><span>Discount (-)</span><span>${n(sale.discount)}</span></div>` : ''}
      ${Number(sale.tax || 0) > 0 ? `<div><span>TAX</span><span>${n(sale.tax)}</span></div>` : ''}
      <div class="line"></div>
      <div class="big"><span>TOTAL</span><span>${w(sale.total)}</span></div>
      ${due > 0 ? `<div><span>Balance Due</span><span>${n(due)}</span></div>` : ''}
    </div>
  </body></html>`
}

let printFrame = null

export function printSaleReceipt(sale, opts = {}) {
  if (!printFrame) {
    printFrame = document.createElement('iframe')
    printFrame.style.position = 'fixed'
    printFrame.style.right = '0'
    printFrame.style.bottom = '0'
    printFrame.style.width = '0'
    printFrame.style.height = '0'
    printFrame.style.border = '0'
    document.body.appendChild(printFrame)
  }
  printFrame.onload = () => {
    try {
      printFrame.contentWindow.focus()
      printFrame.contentWindow.print()
    } catch (e) {
      alert('Print could not start. Allow popups or use the Print / Save PDF button.')
    }
  }
  printFrame.srcdoc = receiptHtml(sale, opts)
}