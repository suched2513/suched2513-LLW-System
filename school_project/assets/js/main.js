// assets/js/main.js
document.addEventListener('DOMContentLoaded', () => {
  // sidebar overlay close
  document.addEventListener('click', e => {
    if (document.body.classList.contains('sidebar-open') && !e.target.closest('.sidebar') && !e.target.closest('.sidebar-toggle')) {
      document.body.classList.remove('sidebar-open');
    }
  });

  // auto-dismiss alerts
  document.querySelectorAll('.alert-dismissible').forEach(el => {
    setTimeout(() => el.classList.add('fade'), 3500);
    setTimeout(() => el.remove(), 4000);
  });
});

// CSRF helper for fetch
async function postJSON(url, data) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify(data)
  });
  return res.json();
}

// Wizard controller
function initWizard(totalSteps) {
  let current = 1;
  function show(n) {
    document.querySelectorAll('.wizard-section').forEach((s, i) => s.classList.toggle('active', i + 1 === n));
    document.querySelectorAll('.wizard-step').forEach((s, i) => {
      s.classList.remove('active', 'done');
      if (i + 1 === n) s.classList.add('active');
      else if (i + 1 < n) s.classList.add('done');
    });
    current = n;
  }
  window.goStep = n => { if (n >= 1 && n <= totalSteps) show(n); };
  show(1);
}

// Dynamic item rows
function addItemRow(containerId) {
  const c = document.getElementById(containerId);
  const idx = c.querySelectorAll('.item-row').length;
  const row = document.createElement('div');
  row.className = 'item-row row g-2 align-items-center mb-2';
  row.innerHTML = `
    <div class="col-5"><input type="text" name="items[${idx}][name]" class="form-control form-control-sm" placeholder="รายการ" required></div>
    <div class="col-2"><input type="number" name="items[${idx}][qty]" class="form-control form-control-sm item-qty" placeholder="จำนวน" min="1" value="1" oninput="calcRow(this)"></div>
    <div class="col-1"><input type="text" name="items[${idx}][unit]" class="form-control form-control-sm" placeholder="หน่วย"></div>
    <div class="col-2"><input type="number" name="items[${idx}][price]" class="form-control form-control-sm item-price" placeholder="ราคา/หน่วย" min="0" value="0" oninput="calcRow(this)"></div>
    <div class="col-1"><input type="number" name="items[${idx}][total]" class="form-control form-control-sm item-total" placeholder="รวม" readonly></div>
    <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.item-row').remove();calcTotal()"><i class="bi bi-trash"></i></button></div>`;
  c.appendChild(row);
}

function calcRow(el) {
  const row = el.closest('.item-row');
  const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
  const price = parseFloat(row.querySelector('.item-price').value) || 0;
  row.querySelector('.item-total').value = (qty * price).toFixed(2);
  calcTotal();
}

function calcTotal() {
  let total = 0;
  document.querySelectorAll('.item-total').forEach(el => { total += parseFloat(el.value) || 0; });
  const el = document.getElementById('grand-total');
  if (el) el.textContent = total.toLocaleString('th-TH', { minimumFractionDigits: 2 });
}

function addMemberRow(containerId) {
  const c = document.getElementById(containerId);
  const idx = c.querySelectorAll('.member-row').length;
  const roles = ['ประธานกรรมการ','กรรมการ','กรรมการและเลขานุการ'];
  const defaultRole = roles[Math.min(idx, 2)];
  const row = document.createElement('div');
  row.className = 'member-row row g-2 align-items-center mb-2';
  row.innerHTML = `
    <div class="col-4"><input type="text" name="committee[${idx}][name]" class="form-control form-control-sm" placeholder="ชื่อ-สกุล"></div>
    <div class="col-3"><input type="text" name="committee[${idx}][position]" class="form-control form-control-sm" placeholder="ตำแหน่ง" value="ครู"></div>
    <div class="col-4"><select name="committee[${idx}][role]" class="form-select form-select-sm">${roles.map(r=>`<option ${r===defaultRole?'selected':''}>${r}</option>`).join('')}</select></div>
    <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.member-row').remove()"><i class="bi bi-trash"></i></button></div>`;
  c.appendChild(row);
}
