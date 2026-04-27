// Sidebar toggle
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('open');
}

// Confirm delete
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
      if (!confirm(this.dataset.confirm)) e.preventDefault();
    });
  });

  // Auto-dismiss alerts
  setTimeout(function() {
    document.querySelectorAll('.alert-auto').forEach(function(el) {
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 300);
    });
  }, 4000);
});

// Format number input with commas
function formatNumberInput(input) {
  var v = input.value.replace(/[^0-9.]/g, '');
  input.value = v;
}

// Wizard navigation
function showStep(n) {
  document.querySelectorAll('.wizard-section').forEach(function(el, i) {
    el.style.display = (i + 1 === n) ? 'block' : 'none';
  });
  document.querySelectorAll('.step-circle').forEach(function(el, i) {
    el.classList.remove('active', 'done');
    if (i + 1 === n) el.classList.add('active');
    else if (i + 1 < n) el.classList.add('done');
  });
  document.querySelectorAll('.step-label').forEach(function(el, i) {
    el.classList.toggle('active', i + 1 === n);
  });
  window.currentStep = n;
}

// Calculate totals in item table
function calcTotal() {
  var total = 0;
  document.querySelectorAll('.item-row').forEach(function(row) {
    var qty = parseFloat(row.querySelector('.qty')?.value) || 0;
    var price = parseFloat(row.querySelector('.price')?.value) || 0;
    var t = qty * price;
    total += t;
    var tf = row.querySelector('.total-field');
    if (tf) tf.value = t.toFixed(2);
    var td = row.querySelector('.total-display');
    if (td) td.textContent = t.toLocaleString('th-TH', {minimumFractionDigits:2});
  });
  var el = document.getElementById('grand-total');
  if (el) el.textContent = total.toLocaleString('th-TH', {minimumFractionDigits:2});
  var tf = document.getElementById('total-hidden');
  if (tf) tf.value = total.toFixed(2);
}
