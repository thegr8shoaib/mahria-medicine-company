import logoUrl from './assets/thermal-logo.png'
import qrImg from './assets/QR.png'
import api from './api/client'

export const RAAST_ID = 'PK52MSHQ0000089200047319'

export function raastBlock() {
  return `<div class="raast">
      <div class="raast-title">PAY VIA RAAST ONLINE PAYMENT</div>
      <img src="${qrImg}" alt="Raast QR" class="raast-qr" />
      <div class="raast-id">Raast ID: ${RAAST_ID}</div>
    </div>`
}

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

export function discountPct(sale) {
  const sub = Number(sale?.subtotal || 0)
  const disc = Number(sale?.discount || 0)
  if (sub <= 0 || !disc) return 0
  return Math.round((disc / sub) * 100)
}

export function receiptHtml(sale, { reprinted = false } = {}) {
  const n = (v) => Number(v || 0).toFixed(2)
  const w = (v) => Math.round(Number(v || 0)).toString()
  const items = (sale.items || []).map(
    (it, i) => `<tr>
      <td class="no">${i + 1}</td>
      <td class="name" title="${esc(it.product?.name || '—')}">${esc(it.product?.name || '—')}</td>
      <td class="r">${it.quantity}</td>
      <td class="r">${n(it.unit_price)}</td>
      <td class="r">${n(it.total)}</td>
    </tr>`
  ).join('')
  const change = n(Number(sale.paid || 0) - Number(sale.total || 0))
  const due = n(Number(sale.due || 0))

  const whatsappIcon = `<svg viewBox="0 0 24 24" fill="#111111" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>`

  return `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
  <title>Receipt ${esc(sale.invoice_number)}</title>
<style>
    @font-face {
      font-family: 'Noto Nastaliq Urdu';
      font-style: normal;
      font-weight: 400 700;
      font-display: swap;
      src: url('/fonts/noto-nastaliq-urdu.woff2') format('woff2');
    }
    @page { size: 80mm auto; margin: 0; }
    html, body { margin: 0 !important; }
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; width: 270px; margin: 0 auto; padding: 2px 8px; font-size: 11.5px; color: #111; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .logo-txt { text-align: center; line-height: 1.15; }
    .logo-thermal { display: block; margin: 0 auto 4px; width: 108px; }
    .logo-line1 { font-weight: 900; font-size: 18px; letter-spacing: 4px; color: #0b3d5c; }
    .logo-line2 { font-weight: 700; font-size: 9.5px; letter-spacing: 3px; color: #111; margin-top: 1px; }
    .contact { text-align: center; font-weight: 700; font-size: 12px; margin-top: 1px; }
    .logo-wrap { text-align: center; margin-bottom: 2px; }
    .logo { max-width: 110px; max-height: 90px; }
    .muted { color: #444; font-size: 10.5px; }
    .info { font-weight: 600; font-size: 9.5px; color: #111; white-space: nowrap; }
    .info-row { display: flex; justify-content: space-between; gap: 8px; font-weight: 600; font-size: 9.5px; color: #111; }
    .info-row span { white-space: nowrap; }
    .divider { border-top: 1px solid #666; margin: 3px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 1px 2px; white-space: nowrap; }
    th { text-align: left; font-size: 9.5px; text-transform: uppercase; border-bottom: 1px solid #444; }
    td { font-size: 11px; }
    .r { text-align: right; }
    .name { max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .no { width: 14px; color: #333; }
    .totals { margin-top: 2px; }
    .totals div { display: flex; justify-content: space-between; padding: 1px 0; }
    .totals .line { border-bottom: 1px dashed #999; }
    .totals .big { font-weight: 700; font-size: 13px; border-top: 1px solid #444; margin-top: 2px; padding-top: 3px; }
    .footer { text-align: center; margin-top: 8px; padding-top: 5px; border-top: 1px solid #444; }
    .footer .brand { font-weight: 900; font-size: 10.5px; letter-spacing: 1.5px; color: #0b3d5c; }
    .footer .contact { font-weight: 700; font-size: 10px; margin-top: 1px; letter-spacing: 0.5px; }
    .policy { text-align: center; margin-top: 4px; font-size: 9.5px; font-weight: 800; line-height: 1.25; }
    .raast { text-align: center; margin-top: 7px; }
    .raast-title { font-weight: 900; font-size: 10px; letter-spacing: 1px; margin-bottom: 2px; }
    .raast-qr { width: 96px; height: 96px; margin: 0 auto; }
    .raast-id { margin-top: 2px; font-family: inherit; font-size: 10.5px; font-weight: 600; letter-spacing: 0; white-space: nowrap; color: #111; }
    .policy-rtl { direction: rtl; unicode-bidi: embed; font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, Arial, sans-serif; font-weight: 700; }
    .rec-no { text-align: center; font-weight: 700; font-size: 11px; }
    .reprinted { text-align: center; font-weight: 900; font-size: 10.5px; letter-spacing: 2px; color: #b91c1c; border: 2px solid #b91c1c; padding: 2px 0; margin-top: 4px; }
    .shop-line { text-align: center; font-size: 10px; font-weight: 600; color: #111; margin-top: 1px; line-height: 1.25; }
    .shop-contact { display: flex; align-items: center; justify-content: center; gap: 4px; }
    .shop-contact svg { width: 12px; height: 12px; }
  </style></head><body>
    <div class="logo-txt">
      <img src="${logoUrl}" alt="Mehria Medicine Company" class="logo-thermal" />
      <div class="shop-line">BANGLA ROAD NEAR AGRICULTURE OFFICE, HAROONABAD</div>
      <div class="shop-line shop-contact"><span>CONTACT # 0345-2863883</span>${whatsappIcon}</div>
    </div>
    ${reprinted ? '<div class="reprinted">* REPRINTED *</div>' : ''}
    <div class="divider"></div>
    <div class="info-row"><span>Date: ${new Date(sale.created_at).toLocaleString()}</span><span>User: ${esc(sale.user?.name || '—')}</span></div>
    <div class="info-row"><span>NTN: 7483331-2</span><span>Receipt No: ${esc(sale.invoice_number)}</span></div>
    <div class="info">Licence No: 03-311-0032-101403M</div>
    <div class="divider"></div>
    <table>
      <thead><tr><th>#</th><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Amt</th></tr></thead>
      <tbody>${items}</tbody>
    </table>
    <div class="divider"></div>
    <div class="totals">
      <div><span>Subtotal</span><span>${n(sale.subtotal)}</span></div>
      ${sale.discount ? `<div><span>Discount (${discountPct(sale)}%)</span><span>${n(sale.discount)}</span></div>` : ''}
      ${Number(sale.tax || 0) > 0 ? `<div><span>TAX</span><span>${n(sale.tax)}</span></div>` : ''}
      <div class="line"></div>
      <div class="big"><span>TOTAL</span><span>${w(sale.total)}</span></div>
      ${due > 0 ? `<div><span>Balance Due</span><span>${n(due)}</span></div>` : ''}
    </div>
    <div class="policy policy-rtl">فریج والی اشیاء واپس نہیں ہوں گی۔</div>
    <div class="policy policy-rtl">دوائی بل کے ساتھ 7 دن کے اندر واپس یا تبدیل کی جا سکتی ہے۔</div>
    ${raastBlock()}
    <div class="footer">
      <div class="brand">POS Software by twobros.pk</div>
      <div class="contact">Contact 0301-5102370</div>
    </div>
  </body></html>`
}

let printFrame = null

export async function silentPrintSaleReceipt(sale) {
  try {
    await api.post('/print-receipt', { sale_id: sale.id })
    return true
  } catch (e) {
    return false
  }
}

export function printSaleReceipt(sale, opts = {}) {
  silentPrintSaleReceipt(sale).then((ok) => {
    if (ok) return
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
  })
}